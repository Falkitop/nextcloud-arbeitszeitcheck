<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Util;

use OCA\ArbeitszeitCheck\Db\Absence;
use OCA\ArbeitszeitCheck\Db\AbsenceMapper;
use OCA\ArbeitszeitCheck\Service\HolidayService;
use OCA\ArbeitszeitCheck\Util\AbsenceWorkingDaysResolver;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbsenceWorkingDaysResolverTest extends TestCase
{
	/** @var AbsenceMapper|MockObject */
	private $absenceMapper;

	/** @var HolidayService|MockObject */
	private $holidayService;

	private AbsenceWorkingDaysResolver $resolver;

	protected function setUp(): void
	{
		parent::setUp();

		$this->absenceMapper = $this->createMock(AbsenceMapper::class);
		$this->holidayService = $this->createMock(HolidayService::class);
		$this->resolver = new AbsenceWorkingDaysResolver($this->absenceMapper, $this->holidayService);
	}

	public function testUsesPositiveDaysFromParameters(): void
	{
		$this->absenceMapper->expects($this->never())->method('find');

		$days = $this->resolver->resolveFromNotificationParameters([
			'days' => 4.0,
			'absence_id' => 12,
		]);

		$this->assertSame(4.0, $days);
	}

	public function testLoadsDaysFromAbsenceWhenParameterIsZero(): void
	{
		$absence = new Absence();
		$absence->setId(55);
		$absence->setUserId('user1');
		$absence->setDays(2.0);
		$absence->setStartDate(new \DateTime('2026-04-29'));
		$absence->setEndDate(new \DateTime('2026-04-30'));

		$this->absenceMapper->expects($this->once())
			->method('find')
			->with(55)
			->willReturn($absence);
		$this->holidayService->expects($this->never())->method('computeWorkingDaysForUser');

		$days = $this->resolver->resolveFromNotificationParameters([
			'absence_id' => 55,
			'type' => 'vacation',
			'days' => 0,
		]);

		$this->assertSame(2.0, $days);
	}

	public function testComputesDaysWhenAbsenceRecordHasNoStoredDays(): void
	{
		$start = new \DateTime('2026-06-02');
		$end = new \DateTime('2026-06-06');
		$absence = new Absence();
		$absence->setId(42);
		$absence->setUserId('user1');
		$absence->setStartDate($start);
		$absence->setEndDate($end);

		$this->absenceMapper->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($absence);
		$this->holidayService->expects($this->once())
			->method('computeWorkingDaysForUser')
			->with('user1', $start, $end)
			->willReturn(3.0);

		$days = $this->resolver->resolveFromNotificationParameters([
			'absence_id' => 42,
			'type' => 'vacation',
		]);

		$this->assertSame(3.0, $days);
	}

	public function testReturnsZeroWhenAbsenceDoesNotExist(): void
	{
		$this->absenceMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException('not found'));

		$days = $this->resolver->resolveFromNotificationParameters([
			'absence_id' => 999,
		]);

		$this->assertSame(0.0, $days);
	}
}
