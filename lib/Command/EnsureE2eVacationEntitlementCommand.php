<?php

declare(strict_types=1);

/**
 * Idempotent dev/E2E helper: ensure a user has manual_fixed vacation entitlement.
 *
 * Not for production cron — requires --force in non-interactive mode.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Command;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignment;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignmentMapper;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EnsureE2eVacationEntitlementCommand extends Command
{
	public function __construct(
		private IUserManager $userManager,
		private UserVacationPolicyAssignmentMapper $userVacationPolicyAssignmentMapper,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->setName('arbeitszeitcheck:ensure-e2e-vacation')
			->setDescription('Ensure manual_fixed vacation entitlement for E2E/dev users (idempotent).')
			->addArgument('user_id', InputArgument::REQUIRED, 'Nextcloud user id (UID)')
			->addOption('manual-days', null, InputOption::VALUE_REQUIRED, 'Annual vacation days (manual_fixed).', '30')
			->addOption('years', null, InputOption::VALUE_REQUIRED, 'Comma-separated calendar years to cover (default: current through +2).', '')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Required for non-interactive runs (CI/E2E).');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		if (!$input->getOption('force')) {
			if (!$input->isInteractive()) {
				$io->error('Non-interactive runs require --force.');
				return Command::FAILURE;
			}
			if (!$io->confirm('This writes vacation policy rows for development/E2E. Continue?', false)) {
				return Command::SUCCESS;
			}
		}

		$userId = trim((string)$input->getArgument('user_id'));
		if ($userId === '' || !$this->userManager->userExists($userId)) {
			$io->error('User does not exist: ' . $userId);
			return Command::FAILURE;
		}

		$manualDays = (float)str_replace(',', '.', (string)$input->getOption('manual-days'));
		if ($manualDays < 1 || $manualDays > 366) {
			$io->error('manual-days must be between 1 and 366.');
			return Command::FAILURE;
		}

		$years = $this->parseYears((string)$input->getOption('years'));
		foreach ($years as $year) {
			$this->ensureYearPolicy($userId, $year, $manualDays, $io);
		}

		$io->success(sprintf('Vacation entitlement ensured for %s (%s).', $userId, implode(', ', array_map('strval', $years))));

		return Command::SUCCESS;
	}

	/**
	 * @return list<int>
	 */
	private function parseYears(string $raw): array
	{
		if (trim($raw) !== '') {
			$years = [];
			foreach (explode(',', $raw) as $part) {
				$y = (int)trim($part);
				if ($y >= 2000 && $y <= 2100) {
					$years[] = $y;
				}
			}
			sort($years);
			return array_values(array_unique($years));
		}

		$current = (int)date('Y');
		return [$current, $current + 1, $current + 2];
	}

	private function ensureYearPolicy(string $userId, int $year, float $manualDays, SymfonyStyle $io): void
	{
		$effectiveFrom = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
		$current = $this->userVacationPolicyAssignmentMapper->findCurrentByUser($userId, $effectiveFrom);

		if (
			$current !== null
			&& $current->getEffectiveFrom() !== null
			&& $current->getEffectiveFrom()->format('Y-m-d') === $effectiveFrom->format('Y-m-d')
			&& !$current->isInherit()
			&& $current->getVacationMode() === Constants::VACATION_MODE_MANUAL_FIXED
			&& (float)($current->getManualDays() ?? 0) >= $manualDays
		) {
			$io->writeln(sprintf('  %d: already manual_fixed %.1f days', $year, (float)$current->getManualDays()));
			return;
		}

		if (
			$current !== null
			&& $current->getEffectiveFrom() !== null
			&& $current->getEffectiveFrom()->format('Y-m-d') === $effectiveFrom->format('Y-m-d')
		) {
			$current->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
			$current->setManualDays($manualDays);
			$current->setTariffRuleSetId(null);
			$current->setOverrideReason('E2E vacation seed');
			$current->setInheritLowerLayers(false);
			$current->setEffectiveTo(null);
			$current->setUpdatedAt(new \DateTime());
			$errors = $current->validate();
			if ($errors !== []) {
				$io->error(sprintf('%d: validation failed: %s', $year, json_encode($errors)));
				return;
			}
			$this->userVacationPolicyAssignmentMapper->update($current);
			$io->writeln(sprintf('  %d: updated policy to manual_fixed %.1f days', $year, $manualDays));
			return;
		}

		if ($current !== null && $current->getId() !== null) {
			$startOfNew = $effectiveFrom;
			$endOfCurrent = $startOfNew->modify('-1 day');
			$currentStart = $current->getEffectiveFrom();
			if ($currentStart !== null && $currentStart <= new \DateTime($endOfCurrent->format('Y-m-d'))) {
				$current->setEffectiveTo(new \DateTime($endOfCurrent->format('Y-m-d')));
				$current->setUpdatedAt(new \DateTime());
				$this->userVacationPolicyAssignmentMapper->update($current);
			}
		}

		$assignment = new UserVacationPolicyAssignment();
		$assignment->setUserId($userId);
		$assignment->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$assignment->setManualDays($manualDays);
		$assignment->setTariffRuleSetId(null);
		$assignment->setOverrideReason('E2E vacation seed');
		$assignment->setEffectiveFrom(new \DateTime($effectiveFrom->format('Y-m-d')));
		$assignment->setEffectiveTo(null);
		$assignment->setInheritLowerLayers(false);
		$assignment->setCreatedBy('occ:ensure-e2e-vacation');
		$assignment->setCreatedAt(new \DateTime());
		$assignment->setUpdatedAt(new \DateTime());
		$errors = $assignment->validate();
		if ($errors !== []) {
			$io->error(sprintf('%d: validation failed: %s', $year, json_encode($errors)));
			return;
		}
		$this->userVacationPolicyAssignmentMapper->insert($assignment);
		$io->writeln(sprintf('  %d: created manual_fixed %.1f days from %s', $year, $manualDays, $effectiveFrom->format('Y-m-d')));
	}

}
