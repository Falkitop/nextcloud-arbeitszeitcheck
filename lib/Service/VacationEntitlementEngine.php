<?php

declare(strict_types=1);

/**
 * Layered vacation entitlement resolution engine.
 *
 * Resolves an annual vacation entitlement (in days, rounded to 2 decimals) for
 * a given `(userId, asOfDate)` by walking a deterministic precedence chain:
 *
 *     L3 individual policy (non-inherit) ▸ L2 team policy ▸ L1 model default
 *                                       ▸ L0 organisation default
 *                                       ▸ legacy fallback
 *
 * Each layer can be configured to deliver a number via three shared "modes":
 *
 *  - `manual_fixed`        : raw days from `manual_days`
 *  - `model_based_simple`  : `30 × (work_days_per_week / 5)` using the user's
 *                            currently active working-time model on `asOfDate`
 *  - `tariff_rule_based`   : delegated to {@see TariffRuleSetMapper} + modules
 *
 * Output contract (consumed by {@see VacationAllocationService},
 * {@see EntitlementSnapshotService} and the admin/employee surfaces):
 *
 *     [
 *       'days' => float,                  // rounded to 2dp; 0 ≤ days ≤ 366
 *       'source' => 'manual'|'manual_exception'|'simple_model'|'tariff'|'layered',
 *       'ruleSetId' => int|null,
 *       'matchedLayer' => 'L0'|'L1'|'L2'|'L3'|'legacy',
 *       'trace' => [                      // algorithm_version = 1
 *         'algorithm_version' => int,
 *         'as_of_date' => 'Y-m-d',
 *         'layers_evaluated' => list<array>,
 *         'winner' => array,
 *         'inputs_redacted' => bool,
 *         ...
 *       ],
 *     ]
 *
 * Numeric invariant (`REQ-ENT-07`): `days` is finite, clamped to `[0, 366]`,
 * rounded to 2 decimal places at the API boundary via
 * {@see self::roundDays()}.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\ModelVacationDefault;
use OCA\ArbeitszeitCheck\Db\ModelVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefault;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\TariffRuleModule;
use OCA\ArbeitszeitCheck\Db\TariffRuleModuleMapper;
use OCA\ArbeitszeitCheck\Db\TariffRuleSetMapper;
use OCA\ArbeitszeitCheck\Db\TeamMapper;
use OCA\ArbeitszeitCheck\Db\TeamMemberMapper;
use OCA\ArbeitszeitCheck\Db\TeamVacationPolicy;
use OCA\ArbeitszeitCheck\Db\TeamVacationPolicyMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignment;
use OCA\ArbeitszeitCheck\Db\UserVacationPolicyAssignmentMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCP\IConfig;

class VacationEntitlementEngine {
	/**
	 * Per-request memoisation key for team membership lookups. The engine is
	 * frequently called several times per request from
	 * {@see VacationAllocationService} (read-only stats, persisted
	 * computations, simulation). Calling out to TeamMemberMapper +
	 * TeamMapper::getParentMap on each call would N+1 against the DB; we
	 * cache for the lifetime of this *engine instance* (request scope) only.
	 *
	 * @var array<string, array{teamIds: list<int>, parentMap: array<int, int|null>}>
	 */
	private array $teamContextCache = [];

	public function __construct(
		private UserVacationPolicyAssignmentMapper $policyMapper,
		private TariffRuleSetMapper $ruleSetMapper,
		private TariffRuleModuleMapper $ruleModuleMapper,
		private UserWorkingTimeModelMapper $userWorkingTimeModelMapper,
		private WorkingTimeModelMapper $workingTimeModelMapper,
		private UserSettingsMapper $userSettingsMapper,
		private OrgVacationDefaultMapper $orgDefaultMapper,
		private ModelVacationDefaultMapper $modelDefaultMapper,
		private TeamVacationPolicyMapper $teamPolicyMapper,
		private TeamMapper $teamMapper,
		private TeamMemberMapper $teamMemberMapper,
		private IConfig $config,
	) {
	}

	/**
	 * Whether the layered resolution chain is active. Defaults to ON. Tenants
	 * can disable it by setting `layered_entitlements_enabled = 0` in app
	 * config; the engine then routes through the legacy "L3 only" path.
	 */
	public function isLayeredEnabled(): bool
	{
		return $this->config->getAppValue(
			'arbeitszeitcheck',
			Constants::CONFIG_LAYERED_ENTITLEMENTS_ENABLED,
			'1'
		) !== '0';
	}

	/**
	 * Resolve entitlement for `(userId, asOfDate)`. See class docblock for
	 * output contract.
	 *
	 * @return array{days: float, source: string, ruleSetId: int|null, matchedLayer: string, trace: array}
	 */
	public function computeForDate(string $userId, \DateTimeInterface $asOfDate): array {
		$asOfDateOnly = new \DateTimeImmutable($asOfDate->format('Y-m-d'));
		$policy = $this->policyMapper->findCurrentByUser($userId, $asOfDateOnly);

		$layered = $this->isLayeredEnabled();
		$layersEvaluated = [];

		// L3: explicit individual policy that does NOT defer to lower layers.
		if ($policy !== null && !$policy->isInherit()) {
			$resolved = $this->resolveFromPolicy($userId, $policy, $asOfDateOnly);
			$layersEvaluated[] = [
				'layer' => 'L3',
				'matched' => true,
				'policy_id' => $policy->getId(),
				'mode' => $policy->getVacationMode(),
				'days' => $resolved['days'],
			];
			return $this->finalise($resolved, 'L3', $asOfDateOnly, $layersEvaluated, false);
		}

		if ($policy !== null && $policy->isInherit()) {
			$layersEvaluated[] = [
				'layer' => 'L3',
				'matched' => false,
				'reason' => 'inherit',
				'policy_id' => $policy->getId(),
			];
		} else {
			$layersEvaluated[] = [
				'layer' => 'L3',
				'matched' => false,
				'reason' => 'no_policy',
			];
		}

		if (!$layered) {
			$legacy = $this->legacyFallback($userId, $asOfDateOnly);
			$layersEvaluated[] = [
				'layer' => 'legacy',
				'matched' => true,
				'reason' => 'layered_disabled',
				'days' => $legacy['days'],
			];
			return $this->finalise($legacy, 'legacy', $asOfDateOnly, $layersEvaluated, false);
		}

		// L2: team-attached policy. Tie-break by depth → priority → id.
		$teamResolution = $this->resolveTeamLayer($userId, $asOfDateOnly);
		if ($teamResolution !== null) {
			$layersEvaluated[] = [
				'layer' => 'L2',
				'matched' => true,
				'team_id' => $teamResolution['team_id'],
				'team_depth' => $teamResolution['team_depth'],
				'priority' => $teamResolution['priority'],
				'policy_id' => $teamResolution['policy_id'],
				'mode' => $teamResolution['resolved']['source'],
				'days' => $teamResolution['resolved']['days'],
				'candidates' => $teamResolution['candidates'],
			];
			return $this->finalise($teamResolution['resolved'], 'L2', $asOfDateOnly, $layersEvaluated, false, [
				'team_id' => $teamResolution['team_id'],
				'policy_id' => $teamResolution['policy_id'],
			]);
		}
		$layersEvaluated[] = ['layer' => 'L2', 'matched' => false, 'reason' => 'no_team_match'];

		// L1: model-attached default for user's active model on date.
		$modelResolution = $this->resolveModelLayer($userId, $asOfDateOnly);
		if ($modelResolution !== null) {
			$layersEvaluated[] = [
				'layer' => 'L1',
				'matched' => true,
				'working_time_model_id' => $modelResolution['working_time_model_id'],
				'default_id' => $modelResolution['default_id'],
				'mode' => $modelResolution['resolved']['source'],
				'days' => $modelResolution['resolved']['days'],
			];
			return $this->finalise($modelResolution['resolved'], 'L1', $asOfDateOnly, $layersEvaluated, false, [
				'working_time_model_id' => $modelResolution['working_time_model_id'],
				'default_id' => $modelResolution['default_id'],
			]);
		}
		$layersEvaluated[] = ['layer' => 'L1', 'matched' => false, 'reason' => 'no_model_default'];

		// L0: organisation default.
		$orgResolution = $this->resolveOrgLayer($userId, $asOfDateOnly);
		if ($orgResolution !== null) {
			$layersEvaluated[] = [
				'layer' => 'L0',
				'matched' => true,
				'default_id' => $orgResolution['default_id'],
				'mode' => $orgResolution['resolved']['source'],
				'days' => $orgResolution['resolved']['days'],
			];
			return $this->finalise($orgResolution['resolved'], 'L0', $asOfDateOnly, $layersEvaluated, false, [
				'default_id' => $orgResolution['default_id'],
			]);
		}
		$layersEvaluated[] = ['layer' => 'L0', 'matched' => false, 'reason' => 'no_org_default'];

		// Safe default: legacy fallback. Marked degraded in trace.
		$legacy = $this->legacyFallback($userId, $asOfDateOnly);
		$layersEvaluated[] = [
			'layer' => 'legacy',
			'matched' => true,
			'reason' => 'safe_default',
			'days' => $legacy['days'],
		];
		return $this->finalise($legacy, 'legacy', $asOfDateOnly, $layersEvaluated, true);
	}

	/**
	 * Backwards-compatible single-policy preview used by the admin
	 * "What if I apply this draft?" simulation. The caller pins an
	 * unsaved {@see UserVacationPolicyAssignment} and the engine returns
	 * the resolved entitlement *as if* this were the current L3 row.
	 *
	 * @return array{days: float, source: string, ruleSetId: int|null, matchedLayer: string, trace: array}
	 */
	public function computeForPolicy(string $userId, UserVacationPolicyAssignment $policy, \DateTimeInterface $asOfDate): array {
		$asOfDateOnly = new \DateTimeImmutable($asOfDate->format('Y-m-d'));
		if (!$policy->isInherit()) {
			$resolved = $this->resolveFromPolicy($userId, $policy, $asOfDateOnly);
			return $this->finalise($resolved, 'L3', $asOfDateOnly, [
				['layer' => 'L3', 'matched' => true, 'mode' => $policy->getVacationMode(), 'days' => $resolved['days'], 'simulated' => true],
			], false);
		}
		// Inherit-simulation: continue down the chain just like computeForDate would.
		$layersEvaluated = [
			['layer' => 'L3', 'matched' => false, 'reason' => 'inherit', 'simulated' => true],
		];
		$teamResolution = $this->resolveTeamLayer($userId, $asOfDateOnly);
		if ($teamResolution !== null) {
			$layersEvaluated[] = [
				'layer' => 'L2',
				'matched' => true,
				'team_id' => $teamResolution['team_id'],
				'team_depth' => $teamResolution['team_depth'],
				'priority' => $teamResolution['priority'],
				'policy_id' => $teamResolution['policy_id'],
				'mode' => $teamResolution['resolved']['source'],
				'days' => $teamResolution['resolved']['days'],
				'candidates' => $teamResolution['candidates'],
			];
			return $this->finalise($teamResolution['resolved'], 'L2', $asOfDateOnly, $layersEvaluated, false);
		}
		$modelResolution = $this->resolveModelLayer($userId, $asOfDateOnly);
		if ($modelResolution !== null) {
			$layersEvaluated[] = [
				'layer' => 'L1',
				'matched' => true,
				'working_time_model_id' => $modelResolution['working_time_model_id'],
				'days' => $modelResolution['resolved']['days'],
			];
			return $this->finalise($modelResolution['resolved'], 'L1', $asOfDateOnly, $layersEvaluated, false);
		}
		$orgResolution = $this->resolveOrgLayer($userId, $asOfDateOnly);
		if ($orgResolution !== null) {
			$layersEvaluated[] = [
				'layer' => 'L0',
				'matched' => true,
				'days' => $orgResolution['resolved']['days'],
			];
			return $this->finalise($orgResolution['resolved'], 'L0', $asOfDateOnly, $layersEvaluated, false);
		}
		$legacy = $this->legacyFallback($userId, $asOfDateOnly);
		$layersEvaluated[] = ['layer' => 'legacy', 'matched' => true, 'days' => $legacy['days']];
		return $this->finalise($legacy, 'legacy', $asOfDateOnly, $layersEvaluated, true);
	}

	/**
	 * Build a redacted copy of a trace for self-service / employee-facing
	 * surfaces. Internal rule IDs, audit metadata, and HR descriptions
	 * (which may name other teams) are stripped; the **numeric** result
	 * and the chosen layer label remain so the employee can answer
	 * "how was this computed?" without leaking colleagues' team policy
	 * names. Trace already produced by {@see self::computeForDate()} can
	 * be passed in directly.
	 *
	 * @param array<string, mixed> $trace
	 * @return array<string, mixed>
	 */
	public function redactTraceForUser(array $trace): array
	{
		$redacted = [
			'algorithm_version' => $trace['algorithm_version'] ?? Constants::ENTITLEMENT_ALGORITHM_VERSION,
			'as_of_date' => $trace['as_of_date'] ?? null,
			'matched_layer' => $trace['matched_layer'] ?? ($trace['winner']['layer'] ?? 'unknown'),
			'inputs_redacted' => true,
		];
		// Surface a short, sanitised reason per layer (no IDs, no other-cohort labels)
		$publicLayers = [];
		foreach (($trace['layers_evaluated'] ?? []) as $row) {
			$publicLayers[] = [
				'layer' => $row['layer'] ?? null,
				'matched' => (bool)($row['matched'] ?? false),
				'mode' => $row['mode'] ?? null,
			];
		}
		$redacted['layers_evaluated'] = $publicLayers;
		$redacted['result_days'] = $trace['result_days'] ?? null;
		return $redacted;
	}

	/**
	 * Resolve the numeric entitlement using a concrete L3 policy.
	 *
	 * @return array{days: float, source: string, ruleSetId: int|null, trace: array}
	 */
	private function resolveFromPolicy(string $userId, UserVacationPolicyAssignment $policy, \DateTimeImmutable $asOfDate): array
	{
		$mode = $policy->getVacationMode();
		if ($mode === Constants::VACATION_MODE_MANUAL_FIXED || $mode === Constants::VACATION_MODE_MANUAL_EXCEPTION) {
			$days = $this->roundDays((float)($policy->getManualDays() ?? 0.0));
			return [
				'days' => $days,
				'source' => $mode === Constants::VACATION_MODE_MANUAL_EXCEPTION ? 'manual_exception' : 'manual',
				'ruleSetId' => $policy->getTariffRuleSetId(),
				'trace' => [
					'mode' => $mode,
					'manual_days' => $days,
					'override_reason' => $policy->getOverrideReason(),
				],
			];
		}
		if ($mode === Constants::VACATION_MODE_MODEL_BASED_SIMPLE) {
			return $this->resolveSimpleModel($userId, $asOfDate, $mode);
		}
		if ($mode === Constants::VACATION_MODE_TARIFF_RULE_BASED) {
			return $this->resolveTariff($userId, $policy->getTariffRuleSetId(), $asOfDate, $mode);
		}
		// Defensive: unknown mode → fall back to legacy default. Logs but
		// does not throw so the user-facing surfaces stay alive.
		\OCP\Log\logger('arbeitszeitcheck')->error(
			'VacationEntitlementEngine: unknown vacation_mode ' . var_export($mode, true) . ' for user ' . $userId,
			['app' => 'arbeitszeitcheck']
		);
		return $this->legacyFallback($userId, $asOfDate);
	}

	/**
	 * @return array{days: float, source: string, ruleSetId: int|null, trace: array}
	 */
	private function resolveSimpleModel(string $userId, \DateTimeImmutable $asOfDate, string $mode): array
	{
		$referenceDays = 30.0;
		$referenceWeekDays = 5.0;
		$workDaysPerWeek = 5.0;
		$modelAssignment = $this->userWorkingTimeModelMapper->findByUserAndDate($userId, new \DateTime($asOfDate->format('Y-m-d')));
		if ($modelAssignment !== null) {
			try {
				$workingTimeModel = $this->workingTimeModelMapper->find($modelAssignment->getWorkingTimeModelId());
				$workDaysPerWeek = max(1.0, min(7.0, round((float)$workingTimeModel->getWorkDaysPerWeek(), 2)));
			} catch (\Throwable) {
				// Keep safe defaults if model cannot be resolved.
			}
		}
		$days = $this->roundDays($referenceDays * ($workDaysPerWeek / $referenceWeekDays));
		return [
			'days' => $days,
			'source' => 'simple_model',
			'ruleSetId' => null,
			'trace' => [
				'mode' => $mode,
				'formula' => 'reference_days * (work_days_per_week / reference_week_days)',
				'inputs' => [
					'reference_days' => $referenceDays,
					'work_days_per_week' => $workDaysPerWeek,
					'reference_week_days' => $referenceWeekDays,
				],
			],
		];
	}

	/**
	 * @return array{days: float, source: string, ruleSetId: int|null, trace: array}
	 */
	private function resolveTariff(string $userId, ?int $ruleSetId, \DateTimeImmutable $asOfDate, string $mode): array
	{
		if ($ruleSetId === null) {
			throw new \RuntimeException('Tariff mode requires an assigned rule set');
		}
		$ruleSet = $this->ruleSetMapper->find($ruleSetId);
		$modules = $this->ruleModuleMapper->findByRuleSetId($ruleSetId);
		$baseDays = 30.0;
		$referenceWeekDays = 5.0;
		$workDaysPerWeek = 5.0;
		$additional = 0.0;
		$deductions = 0.0;
		$rounding = 'commercial';
		$proRata = 'none';

		foreach ($modules as $module) {
			/** @var TariffRuleModule $module */
			$config = $module->getConfig();
			switch ($module->getModuleType()) {
				case 'base_formula':
					$baseDays = (float)($config['reference_days'] ?? $baseDays);
					$referenceWeekDays = (float)($config['reference_week_days'] ?? $referenceWeekDays);
					$workDaysPerWeek = (float)($config['work_days_per_week'] ?? $workDaysPerWeek);
					break;
				case 'additional_entitlements':
					$additional += (float)($config['days'] ?? 0.0);
					break;
				case 'deductions':
					$deductions += max(0.0, (float)($config['days'] ?? 0.0));
					break;
				case 'rounding_rule':
					$rounding = (string)($config['mode'] ?? $rounding);
					break;
				case 'pro_rata_rule':
					$proRata = (string)($config['mode'] ?? $proRata);
					break;
			}
		}

		if ($workDaysPerWeek <= 0.0) {
			$modelAssignment = $this->userWorkingTimeModelMapper->findByUserAndDate($userId, new \DateTime($asOfDate->format('Y-m-d')));
			if ($modelAssignment !== null) {
				try {
					$workingTimeModel = $this->workingTimeModelMapper->find($modelAssignment->getWorkingTimeModelId());
					$workDaysPerWeek = (float)$workingTimeModel->getWorkDaysPerWeek();
				} catch (\Throwable) {
					$workDaysPerWeek = 5.0;
				}
			}
		}

		$workDaysPerWeek = max(1.0, min(7.0, $workDaysPerWeek));
		$referenceWeekDays = max(1.0, min(7.0, $referenceWeekDays));
		$baseDays = max(0.0, min(366.0, $baseDays));

		$computed = $baseDays * ($workDaysPerWeek / max(1.0, $referenceWeekDays));
		$computed += $additional;
		$computed -= $deductions;
		$computed = max(0.0, min(366.0, $computed));
		$computed = $this->applyRounding($computed, $rounding);
		$computed = $this->applyProRata($computed, $proRata, $asOfDate);

		return [
			'days' => $this->roundDays($computed),
			'source' => 'tariff',
			'ruleSetId' => $ruleSet->getId(),
			'trace' => [
				'mode' => $mode,
				'rule_set' => [
					'id' => $ruleSet->getId(),
					'tariff_code' => $ruleSet->getTariffCode(),
					'version' => $ruleSet->getVersion(),
					'status' => $ruleSet->getStatus(),
				],
				'formula' => 'base + additional - deductions',
				'inputs' => [
					'base_reference_days' => $baseDays,
					'work_days_per_week' => $workDaysPerWeek,
					'reference_week_days' => $referenceWeekDays,
					'additional_days' => $additional,
					'deduction_days' => $deductions,
					'rounding' => $rounding,
					'pro_rata' => $proRata,
					'as_of_date' => $asOfDate->format('Y-m-d'),
				],
				'result_days' => $this->roundDays($computed),
			],
		];
	}

	/**
	 * Resolve via L2. Returns null when the user is in no team with an
	 * active policy on `asOfDate`.
	 *
	 * @return array{
	 *   team_id: int,
	 *   team_depth: int,
	 *   priority: int,
	 *   policy_id: int,
	 *   resolved: array{days: float, source: string, ruleSetId: int|null, trace: array},
	 *   candidates: list<array{team_id: int, team_depth: int, priority: int, policy_id: int}>
	 * }|null
	 */
	private function resolveTeamLayer(string $userId, \DateTimeImmutable $asOfDate): ?array
	{
		$ctx = $this->getTeamContext($userId);
		if ($ctx['teamIds'] === []) {
			return null;
		}
		$policies = $this->teamPolicyMapper->findActiveByTeamIds($ctx['teamIds'], $asOfDate);
		if ($policies === []) {
			return null;
		}
		// Tie-break: deepest team subtree first, then highest priority,
		// then smallest id (stable).
		$candidates = [];
		foreach ($policies as $p) {
			$teamId = (int)$p->getTeamId();
			$depth = $this->computeTeamDepth($teamId, $ctx['parentMap']);
			$candidates[] = [
				'policy' => $p,
				'team_id' => $teamId,
				'team_depth' => $depth,
				'priority' => (int)$p->getPriority(),
				'policy_id' => (int)$p->getId(),
			];
		}
		usort($candidates, function (array $a, array $b): int {
			$d = $b['team_depth'] <=> $a['team_depth'];
			if ($d !== 0) {
				return $d;
			}
			$pri = $b['priority'] <=> $a['priority'];
			if ($pri !== 0) {
				return $pri;
			}
			return $a['team_id'] <=> $b['team_id'];
		});
		$winner = $candidates[0];
		/** @var TeamVacationPolicy $winningPolicy */
		$winningPolicy = $winner['policy'];
		$resolved = $this->resolveFromLayerRow($userId, $winningPolicy->getVacationMode(), $winningPolicy->getManualDays(), $winningPolicy->getTariffRuleSetId(), $asOfDate);
		$candList = array_map(static function (array $c): array {
			return [
				'team_id' => $c['team_id'],
				'team_depth' => $c['team_depth'],
				'priority' => $c['priority'],
				'policy_id' => $c['policy_id'],
			];
		}, $candidates);
		return [
			'team_id' => $winner['team_id'],
			'team_depth' => $winner['team_depth'],
			'priority' => $winner['priority'],
			'policy_id' => $winner['policy_id'],
			'resolved' => $resolved,
			'candidates' => $candList,
		];
	}

	/**
	 * @return array{working_time_model_id: int, default_id: int, resolved: array{days: float, source: string, ruleSetId: int|null, trace: array}}|null
	 */
	private function resolveModelLayer(string $userId, \DateTimeImmutable $asOfDate): ?array
	{
		$assignment = $this->userWorkingTimeModelMapper->findByUserAndDate($userId, new \DateTime($asOfDate->format('Y-m-d')));
		if ($assignment === null) {
			return null;
		}
		$modelId = (int)$assignment->getWorkingTimeModelId();
		$default = $this->modelDefaultMapper->findActiveByModelAndDate($modelId, $asOfDate);
		if ($default === null) {
			return null;
		}
		$resolved = $this->resolveFromLayerRow($userId, $default->getVacationMode(), $default->getManualDays(), $default->getTariffRuleSetId(), $asOfDate);
		return [
			'working_time_model_id' => $modelId,
			'default_id' => (int)$default->getId(),
			'resolved' => $resolved,
		];
	}

	/**
	 * @return array{default_id: int, resolved: array{days: float, source: string, ruleSetId: int|null, trace: array}}|null
	 */
	private function resolveOrgLayer(string $userId, \DateTimeImmutable $asOfDate): ?array
	{
		$default = $this->orgDefaultMapper->findActiveByDate($asOfDate);
		if ($default === null) {
			return null;
		}
		$resolved = $this->resolveFromLayerRow($userId, $default->getVacationMode(), $default->getManualDays(), $default->getTariffRuleSetId(), $asOfDate);
		return [
			'default_id' => (int)$default->getId(),
			'resolved' => $resolved,
		];
	}

	/**
	 * Numeric resolution for one layer row (L0/L1/L2) given the row's mode +
	 * payload. Falls back to a clamped manual zero on internal failure rather
	 * than throwing so resolution down the chain still gets a chance to
	 * continue from the next layer.
	 *
	 * @return array{days: float, source: string, ruleSetId: int|null, trace: array}
	 */
	private function resolveFromLayerRow(string $userId, string $mode, ?float $manualDays, ?int $tariffRuleSetId, \DateTimeImmutable $asOfDate): array
	{
		if ($mode === Constants::VACATION_MODE_MANUAL_FIXED) {
			$days = $this->roundDays((float)($manualDays ?? 0.0));
			return [
				'days' => $days,
				'source' => 'manual',
				'ruleSetId' => null,
				'trace' => [
					'mode' => $mode,
					'manual_days' => $days,
				],
			];
		}
		if ($mode === Constants::VACATION_MODE_MODEL_BASED_SIMPLE) {
			return $this->resolveSimpleModel($userId, $asOfDate, $mode);
		}
		if ($mode === Constants::VACATION_MODE_TARIFF_RULE_BASED) {
			return $this->resolveTariff($userId, $tariffRuleSetId, $asOfDate, $mode);
		}
		// Unknown layer mode: clamp to zero with a degraded trace so the
		// admin surface can surface the misconfiguration without crashing.
		\OCP\Log\logger('arbeitszeitcheck')->error(
			'VacationEntitlementEngine: unknown layer vacation_mode ' . var_export($mode, true),
			['app' => 'arbeitszeitcheck']
		);
		return [
			'days' => 0.0,
			'source' => 'manual',
			'ruleSetId' => null,
			'trace' => [
				'mode' => $mode,
				'manual_days' => 0.0,
				'degraded' => 'unknown_mode',
			],
		];
	}

	/**
	 * Pre-layered-spec behaviour, kept as the deterministic "safe default"
	 * for tenants that have not yet configured any layer. Currently:
	 *  1. user's working-time model assignment `vacation_days_per_year`
	 *  2. user setting `vacation_days_per_year`
	 *  3. {@see Constants::DEFAULT_VACATION_DAYS_PER_YEAR}
	 *
	 * @return array{days: float, source: string, ruleSetId: int|null, trace: array}
	 */
	private function legacyFallback(string $userId, \DateTimeImmutable $asOfDate): array
	{
		$days = $this->resolveLegacyManualEntitlement($userId);
		return [
			'days' => $this->roundDays((float)$days),
			'source' => 'manual',
			'ruleSetId' => null,
			'trace' => [
				'mode' => 'legacy_default',
				'legacy_days' => (float)$days,
			],
		];
	}

	private function resolveLegacyManualEntitlement(string $userId): int {
		$currentModel = $this->userWorkingTimeModelMapper->findCurrentByUser($userId);
		if ($currentModel !== null && $currentModel->getVacationDaysPerYear() !== null) {
			return max(0, min(366, (int)$currentModel->getVacationDaysPerYear()));
		}
		return max(0, min(366, (int)$this->userSettingsMapper->getIntegerSetting(
			$userId,
			'vacation_days_per_year',
			Constants::DEFAULT_VACATION_DAYS_PER_YEAR
		)));
	}

	/**
	 * Build the final result shape, attach the trace v1 envelope.
	 *
	 * @param array{days: float, source: string, ruleSetId: int|null, trace: array} $resolved
	 * @param list<array> $layersEvaluated
	 * @return array{days: float, source: string, ruleSetId: int|null, matchedLayer: string, trace: array}
	 */
	private function finalise(
		array $resolved,
		string $matchedLayer,
		\DateTimeImmutable $asOfDate,
		array $layersEvaluated,
		bool $degraded,
		array $extraWinnerFields = []
	): array {
		$days = $this->roundDays((float)$resolved['days']);
		$winner = array_merge([
			'layer' => $matchedLayer,
			'mode' => $resolved['source'],
			'days' => $days,
			'rule_set_id' => $resolved['ruleSetId'],
		], $extraWinnerFields);

		$trace = [
			'algorithm_version' => Constants::ENTITLEMENT_ALGORITHM_VERSION,
			'as_of_date' => $asOfDate->format('Y-m-d'),
			'matched_layer' => $matchedLayer,
			'layers_evaluated' => $layersEvaluated,
			'winner' => $winner,
			'inputs_redacted' => false,
			'result_days' => $days,
			'inner' => $resolved['trace'],
		];
		if ($degraded) {
			$trace['degraded'] = true;
		}
		// Preserve legacy "source" / "ruleSetId" keys verbatim so existing
		// callers (allocation service, dashboards) keep working.
		return [
			'days' => $days,
			'source' => $matchedLayer === 'L3' ? $resolved['source'] : ($matchedLayer === 'legacy' ? $resolved['source'] : 'layered'),
			'ruleSetId' => $resolved['ruleSetId'],
			'matchedLayer' => $matchedLayer,
			'trace' => $trace,
		];
	}

	/**
	 * Team membership context for a user, memoised per engine instance.
	 *
	 * @return array{teamIds: list<int>, parentMap: array<int, int|null>}
	 */
	private function getTeamContext(string $userId): array
	{
		if (isset($this->teamContextCache[$userId])) {
			return $this->teamContextCache[$userId];
		}
		try {
			$memberships = $this->teamMemberMapper->findByUserId($userId);
		} catch (\Throwable) {
			$memberships = [];
		}
		$teamIds = [];
		foreach ($memberships as $m) {
			$tid = (int)$m->getTeamId();
			if (!in_array($tid, $teamIds, true)) {
				$teamIds[] = $tid;
			}
		}
		try {
			$parentMap = $this->teamMapper->getParentMap();
		} catch (\Throwable) {
			$parentMap = [];
		}
		return $this->teamContextCache[$userId] = [
			'teamIds' => $teamIds,
			'parentMap' => $parentMap,
		];
	}

	/**
	 * Single canonical rounding/clamping function for *all* entitlement
	 * outputs (REQ-ENT-07, GAP-01). Centralised so the rest of the codebase
	 * — VacationAllocationService, snapshot service, exports — can route
	 * through here and stay in sync. Half-up because that's what payroll
	 * tooling defaults to and matches the prior behaviour of
	 * `round($x, 2)` in PHP (PHP_ROUND_HALF_UP).
	 */
	public function roundDays(float $value): float
	{
		if (!is_finite($value)) {
			return 0.0;
		}
		$clamped = max(0.0, min(366.0, $value));
		return round($clamped, 2, PHP_ROUND_HALF_UP);
	}

	/**
	 * Depth of `$teamId` in the parent tree (root = 0). Returns `-1` for
	 * unknown / circular teams. Cycle guard caps the walk at 64 levels;
	 * the team form validator rejects writes that introduce cycles, so a
	 * cycle reaching this code is a corrupted-DB scenario we degrade
	 * gracefully on.
	 *
	 * Implemented inside the engine (not on `TeamMapper`) deliberately:
	 * keeping it here means unit tests that mock `TeamMapper` cannot
	 * accidentally erase the tie-breaker by stubbing `computeDepth()` to a
	 * default-int 0.
	 *
	 * @param array<int, int|null> $parentMap
	 */
	private function computeTeamDepth(int $teamId, array $parentMap): int
	{
		if (!array_key_exists($teamId, $parentMap)) {
			return -1;
		}
		$depth = 0;
		$current = $teamId;
		$seen = [$current => true];
		while (($parent = $parentMap[$current] ?? null) !== null) {
			if (isset($seen[$parent])) {
				return -1;
			}
			$depth++;
			if ($depth > 64) {
				return -1;
			}
			$seen[$parent] = true;
			$current = $parent;
		}
		return $depth;
	}

	private function applyRounding(float $value, string $mode): float {
		return match ($mode) {
			'floor' => floor($value),
			'ceil' => ceil($value),
			'half_day' => round($value * 2.0) / 2.0,
			default => round($value, 2, PHP_ROUND_HALF_UP),
		};
	}

	private function applyProRata(float $value, string $mode, \DateTimeInterface $asOfDate): float {
		if ($mode === 'none') {
			return $value;
		}
		$month = (int)$asOfDate->format('n');
		if ($mode === 'monthly') {
			return ($value / 12.0) * $month;
		}
		if ($mode === 'daily') {
			$dayOfYear = (int)$asOfDate->format('z') + 1;
			$yearDays = (int)$asOfDate->format('L') === 1 ? 366 : 365;
			return ($value / $yearDays) * $dayOfYear;
		}
		return $value;
	}
}
