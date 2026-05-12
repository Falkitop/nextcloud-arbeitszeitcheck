<?php

declare(strict_types=1);

/**
 * Admin-side CRUD for L0 / L1 / L2 vacation entitlement defaults.
 *
 * Responsibilities:
 *
 *  - Persist {@see OrgVacationDefault}, {@see ModelVacationDefault},
 *    {@see TeamVacationPolicy} via {@see DBAL} transactions.
 *  - Run pre-flight validation that goes beyond field validators
 *    (referential integrity for `working_time_model_id` and `team_id`,
 *    tariff rule-set activation status, organisation-default
 *    open-ended-row uniqueness).
 *  - Emit audit-log entries with before/after JSON payloads via
 *    {@see AuditLogMapper}, covering REQ-AUD-02.
 *  - Maintain an advisory app lock around writes so two admins editing
 *    the same layer concurrently produce a `409 Conflict` rather than a
 *    silent last-writer-wins (REQ-SEC-04 / EC-07).
 *
 * The service never returns HTTP responses itself; it returns DTO-style
 * arrays or throws domain exceptions that the controller maps to JSON
 * responses. This keeps the service reusable from CLI repair commands and
 * background jobs.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\ModelVacationDefault;
use OCA\ArbeitszeitCheck\Db\ModelVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefault;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\TariffRuleSetMapper;
use OCA\ArbeitszeitCheck\Db\TeamMapper;
use OCA\ArbeitszeitCheck\Db\TeamVacationPolicy;
use OCA\ArbeitszeitCheck\Db\TeamVacationPolicyMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;

class LayeredVacationDefaultsService
{
	use TTransactional;

	private const LOCK_NAMESPACE = 'arbeitszeitcheck/layered-vacation';

	public function __construct(
		private readonly OrgVacationDefaultMapper $orgMapper,
		private readonly ModelVacationDefaultMapper $modelMapper,
		private readonly TeamVacationPolicyMapper $teamPolicyMapper,
		private readonly TeamMapper $teamMapper,
		private readonly WorkingTimeModelMapper $workingTimeModelMapper,
		private readonly TariffRuleSetMapper $tariffRuleSetMapper,
		private readonly AuditLogMapper $auditLogMapper,
		private readonly IDBConnection $db,
		private readonly ILockingProvider $lockingProvider,
	) {
	}

	/* ============================================================ *
	 * L0 — Organisation default
	 * ============================================================ */

	/**
	 * Find the currently active L0 row (today). Returns null when nothing
	 * has been configured yet.
	 */
	public function getActiveOrgDefault(?\DateTimeInterface $asOfDate = null): ?OrgVacationDefault
	{
		return $this->orgMapper->findActiveByDate($asOfDate ?? new \DateTimeImmutable('today'));
	}

	/**
	 * @return OrgVacationDefault[]
	 */
	public function listOrgDefaults(): array
	{
		return $this->orgMapper->findAll();
	}

	/**
	 * Insert a new organisation default. Closes the currently open-ended row
	 * (`effective_to IS NULL`) atomically by setting its `effective_to` to
	 * the new row's `effective_from - 1 day`, so the resolution chain only
	 * ever sees one active L0 row per date.
	 *
	 * @param array{vacationMode: string, manualDays?: float|null, tariffRuleSetId?: int|null, description?: string|null, effectiveFrom: string, effectiveTo?: string|null} $payload
	 * @param string $performedBy
	 */
	public function upsertOrgDefault(array $payload, string $performedBy): OrgVacationDefault
	{
		$lockKey = self::LOCK_NAMESPACE . '/org';
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Org vacation default write lock');
		try {
			return $this->atomic(function () use ($payload, $performedBy): OrgVacationDefault {
				$entity = new OrgVacationDefault();
				$entity->setVacationMode((string)($payload['vacationMode'] ?? Constants::VACATION_MODE_MANUAL_FIXED));
				$entity->setManualDays(isset($payload['manualDays']) ? $this->parseDecimal($payload['manualDays']) : null);
				$entity->setTariffRuleSetId(isset($payload['tariffRuleSetId']) && $payload['tariffRuleSetId'] !== '' ? (int)$payload['tariffRuleSetId'] : null);
				$entity->setDescription(isset($payload['description']) ? trim((string)$payload['description']) : null);
				$entity->setEffectiveFrom(new \DateTime((string)$payload['effectiveFrom']));
				$entity->setEffectiveTo(!empty($payload['effectiveTo']) ? new \DateTime((string)$payload['effectiveTo']) : null);
				$entity->setVersion(1);
				$entity->setCreatedBy($performedBy);
				$entity->setCreatedAt(new \DateTime());
				$entity->setUpdatedAt(new \DateTime());

				$errors = $entity->validate();
				if ($errors !== []) {
					throw new LayeredVacationValidationException('Validation failed', $errors);
				}
				$this->assertTariffRuleSetUsable($entity->getTariffRuleSetId(), $entity->getEffectiveFrom());

				// Close currently open-ended overlapping row(s).
				$trimmed = $this->orgMapper->closeOverlappingOpenRows($entity->getEffectiveFrom());
				$saved = $this->orgMapper->insert($entity);

				$this->auditLogMapper->logAction(
					'system',
					'create',
					Constants::AUDIT_ENTITY_ORG_VACATION_DEFAULT,
					(int)$saved->getId(),
					$trimmed === [] ? null : ['trimmed_open_rows' => $trimmed],
					$saved->getSummary(),
					$performedBy
				);
				return $saved;
			}, $this->db);
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	public function deleteOrgDefault(int $id, string $performedBy): void
	{
		$lockKey = self::LOCK_NAMESPACE . '/org';
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Org vacation default delete lock');
		try {
			try {
				$existing = $this->orgMapper->find($id);
			} catch (DoesNotExistException) {
				throw new LayeredVacationNotFoundException('Organisation vacation default not found');
			}
			$before = $existing->getSummary();
			$this->orgMapper->delete($existing);
			$this->auditLogMapper->logAction(
				'system',
				'delete',
				Constants::AUDIT_ENTITY_ORG_VACATION_DEFAULT,
				$id,
				$before,
				null,
				$performedBy
			);
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/* ============================================================ *
	 * L1 — Working-time-model defaults
	 * ============================================================ */

	/**
	 * @return ModelVacationDefault[]
	 */
	public function listModelDefaults(?int $workingTimeModelId = null): array
	{
		if ($workingTimeModelId === null) {
			return $this->modelMapper->findAll();
		}
		return $this->modelMapper->findByModelId($workingTimeModelId);
	}

	/**
	 * @param array{workingTimeModelId: int, vacationMode: string, manualDays?: float|null, tariffRuleSetId?: int|null, description?: string|null, effectiveFrom: string, effectiveTo?: string|null} $payload
	 */
	public function upsertModelDefault(array $payload, string $performedBy): ModelVacationDefault
	{
		$workingTimeModelId = (int)($payload['workingTimeModelId'] ?? 0);
		if ($workingTimeModelId <= 0) {
			throw new LayeredVacationValidationException('Validation failed', ['workingTimeModelId' => 'Working time model is required']);
		}
		try {
			$this->workingTimeModelMapper->find($workingTimeModelId);
		} catch (DoesNotExistException) {
			throw new LayeredVacationNotFoundException('Working time model not found');
		}

		$lockKey = self::LOCK_NAMESPACE . '/model/' . $workingTimeModelId;
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Model vacation default write lock');
		try {
			return $this->atomic(function () use ($payload, $workingTimeModelId, $performedBy): ModelVacationDefault {
				$entity = new ModelVacationDefault();
				$entity->setWorkingTimeModelId($workingTimeModelId);
				$entity->setVacationMode((string)($payload['vacationMode'] ?? Constants::VACATION_MODE_MANUAL_FIXED));
				$entity->setManualDays(isset($payload['manualDays']) ? $this->parseDecimal($payload['manualDays']) : null);
				$entity->setTariffRuleSetId(isset($payload['tariffRuleSetId']) && $payload['tariffRuleSetId'] !== '' ? (int)$payload['tariffRuleSetId'] : null);
				$entity->setDescription(isset($payload['description']) ? trim((string)$payload['description']) : null);
				$entity->setEffectiveFrom(new \DateTime((string)$payload['effectiveFrom']));
				$entity->setEffectiveTo(!empty($payload['effectiveTo']) ? new \DateTime((string)$payload['effectiveTo']) : null);
				$entity->setVersion(1);
				$entity->setCreatedBy($performedBy);
				$entity->setCreatedAt(new \DateTime());
				$entity->setUpdatedAt(new \DateTime());

				$errors = $entity->validate();
				if ($errors !== []) {
					throw new LayeredVacationValidationException('Validation failed', $errors);
				}
				$this->assertTariffRuleSetUsable($entity->getTariffRuleSetId(), $entity->getEffectiveFrom());

				$saved = $this->modelMapper->insert($entity);
				$this->auditLogMapper->logAction(
					'system',
					'create',
					Constants::AUDIT_ENTITY_MODEL_VACATION_DEFAULT,
					(int)$saved->getId(),
					null,
					$saved->getSummary(),
					$performedBy
				);
				return $saved;
			}, $this->db);
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	public function deleteModelDefault(int $id, string $performedBy): void
	{
		try {
			$existing = $this->modelMapper->find($id);
		} catch (DoesNotExistException) {
			throw new LayeredVacationNotFoundException('Model vacation default not found');
		}
		$lockKey = self::LOCK_NAMESPACE . '/model/' . (int)$existing->getWorkingTimeModelId();
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Model vacation default delete lock');
		try {
			$before = $existing->getSummary();
			$this->modelMapper->delete($existing);
			$this->auditLogMapper->logAction(
				'system',
				'delete',
				Constants::AUDIT_ENTITY_MODEL_VACATION_DEFAULT,
				$id,
				$before,
				null,
				$performedBy
			);
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/* ============================================================ *
	 * L2 — Team policies
	 * ============================================================ */

	/**
	 * @return TeamVacationPolicy[]
	 */
	public function listTeamPolicies(?int $teamId = null): array
	{
		if ($teamId === null) {
			return $this->teamPolicyMapper->findAll();
		}
		return $this->teamPolicyMapper->findByTeamId($teamId);
	}

	/**
	 * @param array{teamId: int, vacationMode: string, manualDays?: float|null, tariffRuleSetId?: int|null, description?: string|null, priority?: int, effectiveFrom: string, effectiveTo?: string|null} $payload
	 */
	public function upsertTeamPolicy(array $payload, string $performedBy): TeamVacationPolicy
	{
		$teamId = (int)($payload['teamId'] ?? 0);
		if ($teamId <= 0) {
			throw new LayeredVacationValidationException('Validation failed', ['teamId' => 'Team is required']);
		}
		try {
			$this->teamMapper->find($teamId);
		} catch (DoesNotExistException) {
			throw new LayeredVacationNotFoundException('Team not found');
		}
		$lockKey = self::LOCK_NAMESPACE . '/team/' . $teamId;
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Team vacation policy write lock');
		try {
			return $this->atomic(function () use ($payload, $teamId, $performedBy): TeamVacationPolicy {
				$entity = new TeamVacationPolicy();
				$entity->setTeamId($teamId);
				$entity->setVacationMode((string)($payload['vacationMode'] ?? Constants::VACATION_MODE_MANUAL_FIXED));
				$entity->setManualDays(isset($payload['manualDays']) ? $this->parseDecimal($payload['manualDays']) : null);
				$entity->setTariffRuleSetId(isset($payload['tariffRuleSetId']) && $payload['tariffRuleSetId'] !== '' ? (int)$payload['tariffRuleSetId'] : null);
				$entity->setDescription(isset($payload['description']) ? trim((string)$payload['description']) : null);
				$entity->setPriority(isset($payload['priority']) ? (int)$payload['priority'] : 0);
				$entity->setEffectiveFrom(new \DateTime((string)$payload['effectiveFrom']));
				$entity->setEffectiveTo(!empty($payload['effectiveTo']) ? new \DateTime((string)$payload['effectiveTo']) : null);
				$entity->setVersion(1);
				$entity->setCreatedBy($performedBy);
				$entity->setCreatedAt(new \DateTime());
				$entity->setUpdatedAt(new \DateTime());

				$errors = $entity->validate();
				if ($errors !== []) {
					throw new LayeredVacationValidationException('Validation failed', $errors);
				}
				$this->assertTariffRuleSetUsable($entity->getTariffRuleSetId(), $entity->getEffectiveFrom());

				$saved = $this->teamPolicyMapper->insert($entity);
				$this->auditLogMapper->logAction(
					'system',
					'create',
					Constants::AUDIT_ENTITY_TEAM_VACATION_POLICY,
					(int)$saved->getId(),
					null,
					$saved->getSummary(),
					$performedBy
				);
				return $saved;
			}, $this->db);
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	public function deleteTeamPolicy(int $id, string $performedBy): void
	{
		try {
			$existing = $this->teamPolicyMapper->find($id);
		} catch (DoesNotExistException) {
			throw new LayeredVacationNotFoundException('Team vacation policy not found');
		}
		$lockKey = self::LOCK_NAMESPACE . '/team/' . (int)$existing->getTeamId();
		$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE, 'Team vacation policy delete lock');
		try {
			$before = $existing->getSummary();
			$this->teamPolicyMapper->delete($existing);
			$this->auditLogMapper->logAction(
				'system',
				'delete',
				Constants::AUDIT_ENTITY_TEAM_VACATION_POLICY,
				$id,
				$before,
				null,
				$performedBy
			);
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	/* ============================================================ *
	 * Helpers
	 * ============================================================ */

	/**
	 * Strict-but-tolerant decimal parser shared with the L3 admin flow:
	 * accepts `27.5`, `27,5`, `"27.50"`, rejects `NaN`, `inf`, negative
	 * specials. Returns null when value is empty string / null.
	 */
	private function parseDecimal(mixed $raw): ?float
	{
		if ($raw === null || $raw === '') {
			return null;
		}
		if (is_int($raw) || is_float($raw)) {
			$value = (float)$raw;
		} else {
			$value = (float)str_replace(',', '.', (string)$raw);
		}
		if (!is_finite($value)) {
			throw new LayeredVacationValidationException('Validation failed', ['manualDays' => 'Value must be a finite number']);
		}
		return $value;
	}

	private function assertTariffRuleSetUsable(?int $ruleSetId, ?\DateTime $effectiveFrom): void
	{
		if ($ruleSetId === null || $effectiveFrom === null) {
			return;
		}
		try {
			$ruleSet = $this->tariffRuleSetMapper->find($ruleSetId);
		} catch (DoesNotExistException) {
			throw new LayeredVacationValidationException('Validation failed', ['tariffRuleSetId' => 'Tariff rule set not found']);
		}
		if ($ruleSet->getStatus() !== Constants::TARIFF_RULE_SET_STATUS_ACTIVE) {
			throw new LayeredVacationValidationException('Validation failed', ['tariffRuleSetId' => 'Only active tariff rule sets can be referenced']);
		}
		if ($ruleSet->getValidFrom() > $effectiveFrom) {
			throw new LayeredVacationValidationException('Validation failed', ['tariffRuleSetId' => 'Tariff rule set starts after the layer effective date']);
		}
		$validTo = $ruleSet->getValidTo();
		if ($validTo !== null && $validTo < $effectiveFrom) {
			throw new LayeredVacationValidationException('Validation failed', ['tariffRuleSetId' => 'Tariff rule set is no longer valid for the selected layer effective date']);
		}
	}
}

/**
 * Domain validation error. Carries a field => message map identical in shape
 * to `Entity::validate()` output so the admin controller can pipe it back to
 * the form unchanged.
 */
class LayeredVacationValidationException extends \RuntimeException
{
	/** @var array<string, string> */
	public array $fieldErrors;

	public function __construct(string $message, array $fieldErrors)
	{
		parent::__construct($message);
		$this->fieldErrors = $fieldErrors;
	}
}

class LayeredVacationNotFoundException extends \RuntimeException
{
}
