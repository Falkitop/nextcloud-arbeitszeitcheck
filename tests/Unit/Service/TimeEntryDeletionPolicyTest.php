<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\TimeEntry;
use OCA\ArbeitszeitCheck\Exception\MonthFinalizedException;
use OCA\ArbeitszeitCheck\Service\MonthClosureGuard;
use OCA\ArbeitszeitCheck\Service\TimeEntryDeletionPolicy;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class TimeEntryDeletionPolicyTest extends TestCase
{
	public function testAllowsStampedEntryInsideEditWindow(): void
	{
		$entry = new TimeEntry();
		$entry->setIsManualEntry(false);
		$entry->setStatus(TimeEntry::STATUS_COMPLETED);
		$entry->setStartTime(new \DateTime('-2 days 09:00:00'));
		$entry->setEndTime(new \DateTime('-2 days 17:00:00'));

		$result = $this->makePolicy()->evaluate($entry);

		$this->assertTrue($result['canDelete']);
		$this->assertNotEmpty($result['warnings']);
		$this->assertStringContainsString('Edit', $result['warnings'][0]);
	}

	public function testBlocksStampedEntryOutsideEditWindow(): void
	{
		$entry = new TimeEntry();
		$entry->setIsManualEntry(false);
		$entry->setStatus(TimeEntry::STATUS_COMPLETED);
		$entry->setStartTime(new \DateTime('2024-01-01 09:00:00'));
		$entry->setEndTime(new \DateTime('2024-01-01 17:00:00'));

		$result = $this->makePolicy()->evaluate($entry);

		$this->assertFalse($result['canDelete']);
		$this->assertSame('edit_window_expired', $result['blockCode']);
	}

	public function testBlocksWhenMonthFinalized(): void
	{
		$entry = new TimeEntry();
		$entry->setIsManualEntry(true);
		$entry->setStatus(TimeEntry::STATUS_COMPLETED);
		$entry->setStartTime(new \DateTime('-1 day'));

		$guard = $this->createMock(MonthClosureGuard::class);
		$guard->method('assertTimeEntryMutable')->willThrowException(new MonthFinalizedException('finalized'));

		$result = $this->makePolicy($guard)->evaluate($entry);

		$this->assertFalse($result['canDelete']);
		$this->assertSame('month_finalized', $result['blockCode']);
	}

	public function testBlocksPendingCorrectionWhenApprovalRequired(): void
	{
		$entry = new TimeEntry();
		$entry->setIsManualEntry(false);
		$entry->setStatus(TimeEntry::STATUS_PENDING_APPROVAL);
		$entry->setStartTime(new \DateTime('-1 day'));

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static fn ($app, $key, $default) => match ($key) {
			Constants::CONFIG_TIME_ENTRY_CHANGES_REQUIRE_APPROVAL => '1',
			default => $default,
		});

		$result = $this->makePolicy(null, $config)->evaluate($entry);

		$this->assertFalse($result['canDelete']);
		$this->assertSame('cancel_correction_first', $result['blockCode']);
	}

	private function makePolicy(?MonthClosureGuard $guard = null, ?IConfig $config = null): TimeEntryDeletionPolicy
	{
		$guard ??= $this->createConfiguredMock(MonthClosureGuard::class, []);
		$config ??= $this->createConfiguredMock(IConfig::class, [
			'getAppValue' => static fn ($app, $key, $default) => $default,
		]);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn ($s, $p = []) => $p ? (string)vsprintf($s, $p) : $s);

		return new TimeEntryDeletionPolicy($config, $guard, $l10n);
	}
}
