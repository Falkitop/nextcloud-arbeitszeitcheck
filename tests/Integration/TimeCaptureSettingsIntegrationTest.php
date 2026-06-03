<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Integration;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;
use OCA\ArbeitszeitCheck\Service\TimeCaptureMethodService;
use Test\TestCase;

/**
 * Persists per-user time capture settings through the real service and database.
 */
class TimeCaptureSettingsIntegrationTest extends TestCase
{
	private const TEST_USER = '__arbeitszeitcheck_time_capture_int__';

	private UserSettingsMapper $userSettingsMapper;

	private TimeCaptureMethodService $timeCaptureMethodService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->userSettingsMapper = \OC::$server->get(UserSettingsMapper::class);
		$this->timeCaptureMethodService = \OC::$server->get(TimeCaptureMethodService::class);
		$this->clearTestUserSettings();
	}

	protected function tearDown(): void
	{
		$this->clearTestUserSettings();
		parent::tearDown();
	}

	private function clearTestUserSettings(): void
	{
		$this->userSettingsMapper->deleteSetting(self::TEST_USER, Constants::SETTING_CLOCK_STAMPING_ENABLED);
		$this->userSettingsMapper->deleteSetting(self::TEST_USER, Constants::SETTING_MANUAL_TIME_ENTRY_ENABLED);
	}

	public function testDefaultsBothEnabledWhenNoRowsExist(): void
	{
		$settings = $this->timeCaptureMethodService->getSettings(self::TEST_USER);

		$this->assertTrue($settings['clockStampingEnabled']);
		$this->assertTrue($settings['manualTimeEntryEnabled']);
	}

	public function testDisableManualTimeEntryPersistsAndBlocksAssertion(): void
	{
		$updated = $this->timeCaptureMethodService->setSettings(
			self::TEST_USER,
			['manualTimeEntryEnabled' => false],
			'integration_test',
		);

		$this->assertTrue($updated['clockStampingEnabled']);
		$this->assertFalse($updated['manualTimeEntryEnabled']);
		$this->assertFalse($this->timeCaptureMethodService->isManualTimeEntryEnabled(self::TEST_USER));

		$this->expectException(BusinessRuleException::class);
		$this->timeCaptureMethodService->assertManualTimeEntryAllowed(self::TEST_USER);
	}

	public function testDisableClockStampingPersistsAndBlocksAssertion(): void
	{
		$updated = $this->timeCaptureMethodService->setSettings(
			self::TEST_USER,
			['clockStampingEnabled' => false],
			'integration_test',
		);

		$this->assertFalse($updated['clockStampingEnabled']);
		$this->assertTrue($updated['manualTimeEntryEnabled']);
		$this->assertFalse($this->timeCaptureMethodService->isClockStampingEnabled(self::TEST_USER));

		$this->expectException(BusinessRuleException::class);
		$this->timeCaptureMethodService->assertClockStampingAllowed(self::TEST_USER);
	}

	public function testCannotDisableBothMethods(): void
	{
		$this->expectException(BusinessRuleException::class);
		$this->timeCaptureMethodService->setSettings(
			self::TEST_USER,
			[
				'clockStampingEnabled' => false,
				'manualTimeEntryEnabled' => false,
			],
			'integration_test',
		);
	}

	public function testReEnableMethodRemovesPersistedDisableRow(): void
	{
		$this->timeCaptureMethodService->setSettings(
			self::TEST_USER,
			['clockStampingEnabled' => false],
			'integration_test',
		);
		$this->assertFalse($this->timeCaptureMethodService->isClockStampingEnabled(self::TEST_USER));

		$this->timeCaptureMethodService->setSettings(
			self::TEST_USER,
			['clockStampingEnabled' => true],
			'integration_test',
		);

		$this->assertTrue($this->timeCaptureMethodService->isClockStampingEnabled(self::TEST_USER));
		$this->assertNull(
			$this->userSettingsMapper->getSetting(self::TEST_USER, Constants::SETTING_CLOCK_STAMPING_ENABLED),
		);
	}
}
