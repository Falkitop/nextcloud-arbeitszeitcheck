<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\OvertimePayoutMapper;
use OCP\IURLGenerator;
use OCP\IUserManager;

/**
 * Read-only audit views for recorded overtime payouts (Auszahlung).
 */
class OvertimePayoutAuditService
{
	public function __construct(
		private readonly OvertimePayoutMapper $payoutMapper,
		private readonly OvertimePayoutService $payoutService,
		private readonly OvertimeBankService $bankService,
		private readonly MonthClosureService $monthClosureService,
		private readonly AuditLogMapper $auditLogMapper,
		private readonly IUserManager $userManager,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * @return array{
	 *   items: list<array<string, mixed>>,
	 *   total: int,
	 *   summary: array{total_records: int, total_hours: float, years: list<int>}
	 * }
	 */
	public function listAuditEntries(?int $year, ?int $month, ?string $userId, int $limit = 100, int $offset = 0): array
	{
		$total = $this->payoutMapper->countFiltered($year, $month, $userId);
		$entities = $this->payoutMapper->findFiltered($year, $month, $userId, $limit, $offset);

		$items = [];
		$totalHours = 0.0;
		foreach ($entities as $entity) {
			$row = $this->enrichPayoutRow($entity);
			$items[] = $row;
			$totalHours += (float)($row['hours_paid'] ?? 0);
		}

		return [
			'items' => $items,
			'total' => $total,
			'summary' => [
				'total_records' => $total,
				'total_hours' => round($totalHours, 2),
			],
		];
	}

	/**
	 * Finalized months where payout-eligible hours existed but no payout was recorded (audit gaps).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function findComplianceGaps(int $year, ?int $month, int $limit = 100): array
	{
		if (!$this->bankService->isEnabled()) {
			return [];
		}

		$overview = $this->payoutService->listMonthOverview($year, $month ?? 1);
		if ($month !== null) {
			$overview['items'] = array_filter($overview['items'], static fn (array $i): bool => ($i['status'] ?? '') === 'pending');
		} else {
			// Scan each month of year when no month filter — bounded
			$gaps = [];
			for ($m = 12; $m >= 1; $m--) {
				$mo = $this->payoutService->listMonthOverview($year, $m);
				foreach ($mo['items'] as $item) {
					if (($item['status'] ?? '') !== 'pending') {
						continue;
					}
					$uid = (string)($item['user_id'] ?? '');
					if (!$this->monthClosureService->isMonthFinalized($uid, $year, $m)) {
						continue;
					}
					$item['calendar_year'] = $year;
					$item['calendar_month'] = $m;
					$item['gap_type'] = 'finalized_without_payout';
					$gaps[] = $item;
				}
			}

			return array_slice($gaps, 0, $limit);
		}

		$gaps = [];
		foreach ($overview['items'] as $item) {
			if (($item['status'] ?? '') !== 'pending') {
				continue;
			}
			$uid = (string)($item['user_id'] ?? '');
			if (!$this->monthClosureService->isMonthFinalized($uid, $year, $month)) {
				continue;
			}
			$item['calendar_year'] = $year;
			$item['calendar_month'] = $month;
			$item['gap_type'] = 'finalized_without_payout';
			$gaps[] = $item;
		}

		return $gaps;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function enrichPayoutRow(\OCA\ArbeitszeitCheck\Db\OvertimePayout $entity): array
	{
		$row = $this->payoutService->entityToArray($entity);
		$userId = $entity->getUserId();
		$year = $entity->getCalendarYear();
		$month = $entity->getCalendarMonth();

		$user = $this->userManager->get($userId);
		$row['display_name'] = $user !== null ? $user->getDisplayName() : $userId;
		$row['period'] = sprintf('%04d-%02d', $year, $month);
		$row['month_finalized'] = $this->monthClosureService->isMonthFinalized($userId, $year, $month);

		$payoutId = $entity->getId();
		$auditBase = $this->urlGenerator->linkToRoute('arbeitszeitcheck.admin.auditLog');
		$row['audit_log_url'] = $auditBase . '?' . http_build_query([
			'action' => 'overtime_payout_processed',
			'user_id' => $userId,
		]);
		$row['pdf_url'] = $row['month_finalized']
			? $this->urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.adminMonthClosurePdf')
				. '?' . http_build_query(['userId' => $userId, 'year' => $year, 'month' => $month])
			: null;

		$logs = $this->auditLogMapper->findByEntity('overtime_payout', $payoutId, 5);
		$row['audit_log_entries'] = count($logs);

		return $row;
	}
}
