<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Service;

use OCA\ArbeitszeitCheck\Db\LicenseState;
use OCA\ArbeitszeitCheck\Db\LicenseStateMapper;
use OCA\ArbeitszeitCheck\License\Azc2Codec;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LicenseServiceTest extends TestCase
{
	private string $fixturePath;
	/** @var array<string, mixed> */
	private array $fixture;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixturePath = dirname(__DIR__, 2) . '/fixtures/license_azc2.json';
		$raw = file_get_contents($this->fixturePath);
		$this->fixture = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
	}

	public function testApplyValidMobileKey(): void
	{
		$mapper = $this->createMock(LicenseStateMapper::class);
		$mapper->method('findCurrent')->willReturn(null);
		$mapper->expects($this->once())
			->method('upsert')
			->with($this->callback(function (LicenseState $state): bool {
				return $state->getCustomerId() === 'test'
					&& $state->getMobileSeats() === 5
					&& $state->getTerminalDevices() === 0
					&& $state->getPayloadB64() === $this->fixture['payloadB64'];
			}))
			->willReturnArgument(0);

		$service = $this->makeService($mapper);
		$this->assertTrue($service->applyLicenseKey((string)$this->fixture['wireKey']));
		$this->assertSame('', $service->getLastApplyErrorCode());
	}

	public function testRejectTamperedSignature(): void
	{
		$mapper = $this->createMock(LicenseStateMapper::class);
		$mapper->expects($this->never())->method('upsert');

		$parts = explode('.', (string)$this->fixture['wireKey']);
		$parts[2] = str_repeat('A', strlen($parts[2] ?? ''));
		$tampered = implode('.', $parts);

		$service = $this->makeService($mapper);
		$this->assertFalse($service->applyLicenseKey($tampered));
		$this->assertSame(Azc2Codec::ERROR_INVALID_SIGNATURE, $service->getLastApplyErrorCode());
	}

	public function testRejectExpiredKey(): void
	{
		$mapper = $this->createMock(LicenseStateMapper::class);
		$mapper->expects($this->never())->method('upsert');

		$expiredWire = 'AZC2.eyJ2IjoyLCJjdXN0b21lcklkIjoidGVzdCIsImlzc3VlZEF0IjoiMjAyMC0wMS0wMSIsInZhbGlkVW50aWwiOiIyMDIwLTEyLTMxIiwibW9iaWxlU2VhdHMiOjUsInRlcm1pbmFsRGV2aWNlcyI6MH0.fNhzxPxhDLuXwcGEzivTcrEvoI6Q4hoEsOnWMF_Lb_3dzzCK818GeCiYJJ4-JQcBG9rSOC6G_GFyy3Z7TXElDg';

		$service = $this->makeService($mapper);
		$this->assertFalse($service->applyLicenseKey($expiredWire));
		$this->assertSame(Azc2Codec::ERROR_EXPIRED, $service->getLastApplyErrorCode());
	}

	public function testRejectBothSeatsZero(): void
	{
		$mapper = $this->createMock(LicenseStateMapper::class);
		$mapper->expects($this->never())->method('upsert');

		$service = $this->makeService($mapper);
		$this->assertFalse($service->applyLicenseKey('AZC2.notavalidpayload.notasig'));
		$this->assertNotSame('', $service->getLastApplyErrorCode());
	}

	public function testBuildEnvelopeFromStoredState(): void
	{
		$state = new LicenseState();
		$state->setId(1);
		$state->setCustomerId('test');
		$state->setValidUntil(new \DateTime('2027-12-31'));
		$state->setMobileSeats(5);
		$state->setTerminalDevices(0);
		$state->setBundle(0);
		$state->setKeyAppliedAt(new \DateTime());
		$state->setPayloadB64((string)$this->fixture['payloadB64']);
		$state->setSignatureB64((string)$this->fixture['signatureB64']);

		$mapper = $this->createMock(LicenseStateMapper::class);
		$mapper->method('findCurrent')->willReturn($state);

		$service = $this->makeService($mapper);
		$envelope = $service->buildEnvelope();
		$this->assertNotNull($envelope);
		$this->assertSame('AZC2', $envelope['format']);
		$this->assertSame($this->fixture['payloadB64'], $envelope['payloadB64']);
		$this->assertSame($this->fixture['signatureB64'], $envelope['signatureB64']);
	}

	public function testApplyTwiceIsIdempotent(): void
	{
		$stored = new LicenseState();
		$stored->setId(7);

		$mapper = $this->createMock(LicenseStateMapper::class);
		$mapper->method('findCurrent')->willReturnOnConsecutiveCalls(null, $stored);
		$mapper->expects($this->exactly(2))->method('upsert')->willReturnArgument(0);

		$service = $this->makeService($mapper);
		$wire = (string)$this->fixture['wireKey'];
		$this->assertTrue($service->applyLicenseKey($wire));
		$this->assertTrue($service->applyLicenseKey($wire));
	}

	public function testIsMobilePlanActiveRequiresValidNonExpiredLicense(): void
	{
		$state = new LicenseState();
		$state->setValidUntil(new \DateTime('2027-12-31'));
		$state->setMobileSeats(5);
		$state->setTerminalDevices(0);

		$mapper = $this->createMock(LicenseStateMapper::class);
		$mapper->method('findCurrent')->willReturn($state);

		$service = $this->makeService($mapper);
		$this->assertTrue($service->isMobilePlanActive());
		$this->assertFalse($service->isTerminalPlanActive());
		$this->assertSame(5, $service->getMobileSeatLimit());
	}

	public function testUpsertMergeClearsZeroSeatFields(): void
	{
		$existing = new LicenseState();
		$existing->setId(1);
		$existing->setMobileSeats(5);
		$existing->setTerminalDevices(1);
		$existing->resetUpdatedFields();

		$incoming = new LicenseState();
		$incoming->setCustomerId('term-only');
		$incoming->setValidUntil(new \DateTime('2027-12-31'));
		$incoming->setMobileSeats(0);
		$incoming->setTerminalDevices(3);
		$incoming->setBundle(0);
		$incoming->setKeyAppliedAt(new \DateTime('2026-06-10'));
		$incoming->setPayloadB64('payload');
		$incoming->setSignatureB64('sig');

		$existing->setCustomerId($incoming->getCustomerId());
		$existing->setValidUntil($incoming->getValidUntil());
		$existing->setMobileSeats($incoming->getMobileSeats());
		$existing->setTerminalDevices($incoming->getTerminalDevices());
		$existing->setBundle($incoming->getBundle());
		$existing->setKeyAppliedAt($incoming->getKeyAppliedAt());
		$existing->setPayloadB64($incoming->getPayloadB64());
		$existing->setSignatureB64($incoming->getSignatureB64());

		$this->assertSame(0, $existing->getMobileSeats());
		$this->assertSame(3, $existing->getTerminalDevices());
	}

	private function makeService(LicenseStateMapper $mapper): LicenseService
	{
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new \DateTime('2026-06-10'));

		return new LicenseService(
			$mapper,
			$time,
			$this->createMock(LoggerInterface::class),
		);
	}
}
