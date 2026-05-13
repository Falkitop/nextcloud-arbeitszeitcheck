<?php

declare(strict_types=1);

/**
 * Unit tests for {@see LayeredVacationDefaultsService}.
 *
 * Scope:
 *  - Validation errors are surfaced as {@see LayeredVacationValidationException}
 *    with a translatable field-error map.
 *  - Audit-log entries are emitted on create and delete (REQ-AUD-02).
 *  - L1 / L2 reject references to non-existent working-time-models / teams
 *    with the right exception (404-style).
 *  - L0 closes the currently open-ended row so the resolution chain only
 *    ever sees one active organisation default per date.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\ModelVacationDefault;
use OCA\ArbeitszeitCheck\Db\ModelVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefault;
use OCA\ArbeitszeitCheck\Db\OrgVacationDefaultMapper;
use OCA\ArbeitszeitCheck\Db\TariffRuleSetMapper;
use OCA\ArbeitszeitCheck\Db\Team;
use OCA\ArbeitszeitCheck\Db\TeamMapper;
use OCA\ArbeitszeitCheck\Db\TeamMemberMapper;
use OCA\ArbeitszeitCheck\Db\TeamVacationPolicy;
use OCA\ArbeitszeitCheck\Db\TeamVacationPolicyMapper;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModel;
use OCA\ArbeitszeitCheck\Db\UserWorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModel;
use OCA\ArbeitszeitCheck\Db\WorkingTimeModelMapper;
use OCA\ArbeitszeitCheck\Service\LayeredVacationDefaultsService;
use OCA\ArbeitszeitCheck\Service\LayeredVacationNotFoundException;
use OCA\ArbeitszeitCheck\Service\LayeredVacationValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

class LayeredVacationDefaultsServiceTest extends TestCase
{
	private OrgVacationDefaultMapper $orgMapper;
	private ModelVacationDefaultMapper $modelMapper;
	private TeamVacationPolicyMapper $teamPolicyMapper;
	private TeamMapper $teamMapper;
	private WorkingTimeModelMapper $workingTimeModelMapper;
	private TariffRuleSetMapper $tariffRuleSetMapper;
	private AuditLogMapper $auditLogMapper;
	private IDBConnection $db;
	private ILockingProvider $lockingProvider;
	private LayeredVacationDefaultsService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->orgMapper = $this->createMock(OrgVacationDefaultMapper::class);
		$this->modelMapper = $this->createMock(ModelVacationDefaultMapper::class);
		$this->teamPolicyMapper = $this->createMock(TeamVacationPolicyMapper::class);
		$this->teamMapper = $this->createMock(TeamMapper::class);
		$this->workingTimeModelMapper = $this->createMock(WorkingTimeModelMapper::class);
		$this->tariffRuleSetMapper = $this->createMock(TariffRuleSetMapper::class);
		$this->auditLogMapper = $this->createMock(AuditLogMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->lockingProvider = $this->createMock(ILockingProvider::class);

		// TTransactional uses begin/commit/rollBack — stub them as no-ops.
		$this->db->method('beginTransaction');
		$this->db->method('commit');
		$this->db->method('rollBack');

		$this->service = new LayeredVacationDefaultsService(
			$this->orgMapper,
			$this->modelMapper,
			$this->teamPolicyMapper,
			$this->teamMapper,
			$this->workingTimeModelMapper,
			$this->tariffRuleSetMapper,
			$this->auditLogMapper,
			$this->db,
			$this->lockingProvider,
		);
	}

	public function testUpsertOrgRejectsManualModeWithoutDays(): void
	{
		$this->expectException(LayeredVacationValidationException::class);
		$this->service->upsertOrgDefault([
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'effectiveFrom' => '2026-01-01',
		], 'admin');
	}

	public function testUpsertOrgPersistsAndAudits(): void
	{
		$this->orgMapper->method('findOverlappingRanges')->willReturn([]);
		$this->orgMapper->method('closeOverlappingOpenRows')->willReturn([]);
		$saved = new OrgVacationDefault();
		$saved->setId(7);
		$saved->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$saved->setManualDays(28.0);
		$saved->setEffectiveFrom(new \DateTime('2026-01-01'));
		$this->orgMapper->expects(self::once())
			->method('insert')
			->willReturn($saved);
		$this->auditLogMapper->expects(self::once())
			->method('logAction')
			->with('system', 'create', Constants::AUDIT_ENTITY_ORG_VACATION_DEFAULT, 7);

		$result = $this->service->upsertOrgDefault([
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'manualDays' => 28.0,
			'effectiveFrom' => '2026-01-01',
		], 'admin');

		self::assertSame(7, $result->getId());
	}

	/**
	 * REQ-DAT-03 — a new L0 row whose validity range overlaps an existing
	 * *closed* range must be rejected up-front. Open-ended overlaps remain
	 * the only flavour that gets auto-closed.
	 */
	public function testUpsertOrgRejectsOverlapWithExistingClosedRange(): void
	{
		$this->orgMapper->method('findOverlappingRanges')->willReturn([
			[
				'id' => 1,
				'effective_from' => '2025-01-01',
				'effective_to' => '2026-12-31',
			],
		]);
		$this->orgMapper->expects(self::never())->method('insert');

		$this->expectException(LayeredVacationValidationException::class);
		$this->service->upsertOrgDefault([
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'manualDays' => 30.0,
			'effectiveFrom' => '2026-06-01',
			'effectiveTo' => '2026-09-30',
		], 'admin');
	}

	/**
	 * The existing open-ended row is *not* a blocking overlap — the service
	 * auto-trims it via `closeOverlappingOpenRows`, see REQ-DAT-03 fallback
	 * branch.
	 */
	public function testUpsertOrgAcceptsOverlapWithOpenEndedRow(): void
	{
		$this->orgMapper->method('findOverlappingRanges')->willReturn([
			[
				'id' => 1,
				'effective_from' => '2024-01-01',
				'effective_to' => null,
			],
		]);
		$this->orgMapper->method('closeOverlappingOpenRows')->willReturn([1]);
		$saved = new OrgVacationDefault();
		$saved->setId(99);
		$saved->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$saved->setManualDays(30.0);
		$saved->setEffectiveFrom(new \DateTime('2026-06-01'));
		$this->orgMapper->expects(self::once())->method('insert')->willReturn($saved);

		$result = $this->service->upsertOrgDefault([
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'manualDays' => 30.0,
			'effectiveFrom' => '2026-06-01',
		], 'admin');

		self::assertSame(99, $result->getId());
	}

	public function testUpsertOrgNormalizesModelBasedStripsIrrelevantFields(): void
	{
		$this->orgMapper->method('findOverlappingRanges')->willReturn([]);
		$this->orgMapper->method('closeOverlappingOpenRows')->willReturn([]);
		$captured = null;
		$this->orgMapper->expects(self::once())->method('insert')
			->willReturnCallback(function ($entity) use (&$captured) {
				$captured = $entity;
				$saved = new OrgVacationDefault();
				$saved->setId(1);
				$saved->setVacationMode($entity->getVacationMode());
				return $saved;
			});
		$this->auditLogMapper->expects(self::once())->method('logAction');

		$this->service->upsertOrgDefault([
			'vacationMode' => Constants::VACATION_MODE_MODEL_BASED_SIMPLE,
			'manualDays' => 30.0,
			'tariffRuleSetId' => 5,
			'effectiveFrom' => '2026-01-01',
		], 'admin');

		self::assertInstanceOf(OrgVacationDefault::class, $captured);
		self::assertSame(Constants::VACATION_MODE_MODEL_BASED_SIMPLE, $captured->getVacationMode());
		self::assertNull($captured->getManualDays());
		self::assertNull($captured->getTariffRuleSetId());
	}

	public function testOrgVacationDefaultValidateCrossFieldConstraints(): void
	{
		$manualWithTariff = new OrgVacationDefault();
		$manualWithTariff->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$manualWithTariff->setManualDays(25.0);
		$manualWithTariff->setTariffRuleSetId(3);
		$manualWithTariff->setEffectiveFrom(new \DateTime('2026-01-01'));
		self::assertArrayHasKey('tariffRuleSetId', $manualWithTariff->validate());

		$modelWithDays = new OrgVacationDefault();
		$modelWithDays->setVacationMode(Constants::VACATION_MODE_MODEL_BASED_SIMPLE);
		$modelWithDays->setManualDays(20.0);
		$modelWithDays->setEffectiveFrom(new \DateTime('2026-01-01'));
		self::assertArrayHasKey('manualDays', $modelWithDays->validate());
	}

	public function testUpsertModelRejectsUnknownModel(): void
	{
		$this->workingTimeModelMapper->method('find')->willThrowException(new DoesNotExistException('nope'));
		$this->expectException(LayeredVacationNotFoundException::class);
		$this->service->upsertModelDefault([
			'workingTimeModelId' => 123,
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'manualDays' => 24.0,
			'effectiveFrom' => '2026-01-01',
		], 'admin');
	}

	public function testUpsertModelPersistsAndAudits(): void
	{
		$model = new WorkingTimeModel();
		$model->setId(42);
		$this->workingTimeModelMapper->method('find')->with(42)->willReturn($model);
		$saved = new ModelVacationDefault();
		$saved->setId(11);
		$saved->setWorkingTimeModelId(42);
		$saved->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$saved->setManualDays(24.0);
		$saved->setEffectiveFrom(new \DateTime('2026-01-01'));
		$this->modelMapper->expects(self::once())->method('insert')->willReturn($saved);
		$this->auditLogMapper->expects(self::once())
			->method('logAction')
			->with('system', 'create', Constants::AUDIT_ENTITY_MODEL_VACATION_DEFAULT, 11);

		$result = $this->service->upsertModelDefault([
			'workingTimeModelId' => 42,
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'manualDays' => 24.0,
			'effectiveFrom' => '2026-01-01',
		], 'admin');

		self::assertSame(11, $result->getId());
	}

	public function testUpsertTeamRejectsUnknownTeam(): void
	{
		$this->teamMapper->method('find')->willThrowException(new DoesNotExistException('nope'));
		$this->expectException(LayeredVacationNotFoundException::class);
		$this->service->upsertTeamPolicy([
			'teamId' => 99,
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'manualDays' => 27.0,
			'effectiveFrom' => '2026-01-01',
		], 'admin');
	}

	public function testUpsertTeamPersistsAndAudits(): void
	{
		$team = new Team();
		$team->setId(5);
		$this->teamMapper->method('find')->with(5)->willReturn($team);
		$saved = new TeamVacationPolicy();
		$saved->setId(17);
		$saved->setTeamId(5);
		$saved->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$saved->setManualDays(27.0);
		$saved->setPriority(10);
		$saved->setEffectiveFrom(new \DateTime('2026-01-01'));
		$this->teamPolicyMapper->expects(self::once())->method('insert')->willReturn($saved);
		$this->auditLogMapper->expects(self::once())
			->method('logAction')
			->with('system', 'create', Constants::AUDIT_ENTITY_TEAM_VACATION_POLICY, 17);

		$result = $this->service->upsertTeamPolicy([
			'teamId' => 5,
			'vacationMode' => Constants::VACATION_MODE_MANUAL_FIXED,
			'manualDays' => 27.0,
			'priority' => 10,
			'effectiveFrom' => '2026-01-01',
		], 'admin');

		self::assertSame(17, $result->getId());
	}

	public function testDeleteOrgEmitsAudit(): void
	{
		$existing = new OrgVacationDefault();
		$existing->setId(3);
		$existing->setVacationMode(Constants::VACATION_MODE_MANUAL_FIXED);
		$existing->setManualDays(25.0);
		$existing->setEffectiveFrom(new \DateTime('2026-01-01'));
		$this->orgMapper->method('find')->with(3)->willReturn($existing);
		$this->orgMapper->expects(self::once())->method('delete')->with($existing);
		$this->auditLogMapper->expects(self::once())
			->method('logAction')
			->with('system', 'delete', Constants::AUDIT_ENTITY_ORG_VACATION_DEFAULT, 3);

		$this->service->deleteOrgDefault(3, 'admin');
	}

	public function testDeleteOrgWhenMissingThrowsNotFound(): void
	{
		$this->orgMapper->method('find')->willThrowException(new DoesNotExistException('nope'));
		$this->expectException(LayeredVacationNotFoundException::class);
		$this->service->deleteOrgDefault(99, 'admin');
	}

	/* ============================================================ *
	 * Impact preview (REQ-UX-03)
	 * ============================================================ */

	/**
	 * Build a service with the optional impact-preview dependencies wired
	 * (mirrors the production registration in Application.php). Tests for
	 * non-impact methods continue to use the parent setUp without these
	 * dependencies, so the optional-arg contract stays exercised both ways.
	 */
	private function makeServiceWithImpactDeps(
		TeamMemberMapper $teamMember,
		UserWorkingTimeModelMapper $userModel,
	): LayeredVacationDefaultsService {
		return new LayeredVacationDefaultsService(
			$this->orgMapper,
			$this->modelMapper,
			$this->teamPolicyMapper,
			$this->teamMapper,
			$this->workingTimeModelMapper,
			$this->tariffRuleSetMapper,
			$this->auditLogMapper,
			$this->db,
			$this->lockingProvider,
			$teamMember,
			$userModel,
		);
	}

	public function testPreviewImpactRejectsInvalidScope(): void
	{
		$this->expectException(LayeredVacationValidationException::class);
		$this->service->previewImpact('garbage');
	}

	public function testPreviewImpactModelRequiresTargetId(): void
	{
		$this->expectException(LayeredVacationValidationException::class);
		$this->service->previewImpact('model');
	}

	public function testPreviewImpactTeamRequiresTargetId(): void
	{
		$this->expectException(LayeredVacationValidationException::class);
		$this->service->previewImpact('team');
	}

	public function testPreviewImpactOrgReturnsZeroWithoutDependencies(): void
	{
		// Without optional deps (the legacy 9-arg constructor path) the
		// preview must still return a clean payload — never throw — so
		// the UI degrades gracefully on partially-mocked installs.
		$result = $this->service->previewImpact('org');
		$this->assertSame('org', $result['scope']);
		$this->assertSame(0, $result['affected_user_count']);
		$this->assertFalse($result['exact']);
	}

	public function testPreviewImpactModelDelegatesToUserWorkingTimeModelMapper(): void
	{
		$teamMember = $this->createMock(TeamMemberMapper::class);
		$userModel = $this->createMock(UserWorkingTimeModelMapper::class);
		$assignments = [new UserWorkingTimeModel(), new UserWorkingTimeModel(), new UserWorkingTimeModel()];
		$userModel->expects(self::once())
			->method('findByWorkingTimeModel')
			->with(42, true)
			->willReturn($assignments);

		$service = $this->makeServiceWithImpactDeps($teamMember, $userModel);
		$result = $service->previewImpact('model', 42);
		$this->assertSame('model', $result['scope']);
		$this->assertSame(42, $result['target_id']);
		$this->assertSame(3, $result['affected_user_count']);
	}

	public function testPreviewImpactTeamAggregatesSubtreeMembers(): void
	{
		// Tree: 10 → 11 → 12. Asking for team #10 must collect members
		// from {10, 11, 12} and de-duplicate user IDs.
		$teamMember = $this->createMock(TeamMemberMapper::class);
		$userModel = $this->createMock(UserWorkingTimeModelMapper::class);
		$this->teamMapper->method('getParentMap')->willReturn([10 => null, 11 => 10, 12 => 11]);
		$teamMember->expects(self::once())
			->method('getMemberUserIdsByTeamIds')
			->willReturnCallback(function (array $teamIds) {
				sort($teamIds);
				$this->assertSame([10, 11, 12], $teamIds);
				return ['alice', 'bob', 'alice', 'carol']; // dupes on purpose
			});

		$service = $this->makeServiceWithImpactDeps($teamMember, $userModel);
		$result = $service->previewImpact('team', 10);
		$this->assertSame('team', $result['scope']);
		$this->assertSame(10, $result['target_id']);
		$this->assertSame(3, $result['affected_user_count']);
	}
}
