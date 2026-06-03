<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\UserSettingsMapper;
use OCA\ArbeitszeitCheck\Exception\BusinessRuleException;
use OCA\ArbeitszeitCheck\Exception\TimeCaptureForbiddenException;
use OCA\ArbeitszeitCheck\Service\TimeCaptureMethodService;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TimeCaptureMethodServiceTest extends TestCase
{
	private UserSettingsMapper&MockObject $userSettingsMapper;
	private AuditLogMapper&MockObject $auditLogMapper;
	private IL10N&MockObject $l10n;
	private TimeCaptureMethodService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->userSettingsMapper = $this->createMock(UserSettingsMapper::class);
		$this->auditLogMapper = $this->createMock(AuditLogMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->service = new TimeCaptureMethodService(
			$this->userSettingsMapper,
			$this->auditLogMapper,
			$this->l10n,
		);
	}

	public function testDefaultsBothEnabledWhenSettingsMissing(): void
	{
		$this->userSettingsMapper->method('getBooleanSetting')->willReturnCallback(
			static function (string $userId, string $key, bool $default): bool {
				self::assertSame('alice', $userId);
				self::assertContains($key, [
					Constants::SETTING_CLOCK_STAMPING_ENABLED,
					Constants::SETTING_MANUAL_TIME_ENTRY_ENABLED,
				]);
				return $default;
			}
		);

		self::assertSame([
			'clockStampingEnabled' => true,
			'manualTimeEntryEnabled' => true,
		], $this->service->getSettings('alice'));
	}

	public function testSetSettingsDeletesRowsWhenEnabled(): void
	{
		$this->userSettingsMapper->expects(self::exactly(2))
			->method('deleteSetting')
			->withConsecutive(
				['alice', Constants::SETTING_CLOCK_STAMPING_ENABLED],
				['alice', Constants::SETTING_MANUAL_TIME_ENTRY_ENABLED],
			);
		$this->userSettingsMapper->method('getBooleanSetting')->willReturn(true);
		$this->auditLogMapper->expects(self::never())->method('logAction');

		$this->service->setSettings('alice', [
			'clockStampingEnabled' => true,
			'manualTimeEntryEnabled' => true,
		], 'admin');
	}

	public function testSetSettingsPersistsDisabledFlags(): void
	{
		$readIndex = 0;
		$this->userSettingsMapper->method('getBooleanSetting')->willReturnCallback(
			static function (string $userId, string $key, bool $default) use (&$readIndex): bool {
				$readIndex++;
				if ($key === Constants::SETTING_CLOCK_STAMPING_ENABLED && $readIndex >= 4) {
					return false;
				}

				return true;
			}
		);
		$this->userSettingsMapper->expects(self::once())
			->method('setSetting')
			->with('alice', Constants::SETTING_CLOCK_STAMPING_ENABLED, '0');
		$this->auditLogMapper->expects(self::once())->method('logAction');

		$result = $this->service->setSettings('alice', [
			'clockStampingEnabled' => false,
		], 'admin');

		self::assertFalse($result['clockStampingEnabled']);
		self::assertTrue($result['manualTimeEntryEnabled']);
	}

	public function testCannotDisableBothMethods(): void
	{
		$this->expectException(BusinessRuleException::class);
		$this->service->setSettings('alice', [
			'clockStampingEnabled' => false,
			'manualTimeEntryEnabled' => false,
		], 'admin');
	}

	public function testAssertClockStampingAllowedThrowsWhenDisabled(): void
	{
		$this->userSettingsMapper->method('getBooleanSetting')->willReturnMap([
			['alice', Constants::SETTING_CLOCK_STAMPING_ENABLED, true, false],
		]);

		$this->expectException(TimeCaptureForbiddenException::class);
		$this->service->assertClockStampingAllowed('alice');
	}
}
