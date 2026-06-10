<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Middleware;

use OCA\ArbeitszeitCheck\Middleware\KioskLicenseMiddleware;
use OCA\ArbeitszeitCheck\Middleware\KioskTerminalLicenseRequiredException;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskSettingsService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class KioskLicenseMiddlewareTest extends TestCase
{
	public function testUnlicensedOrgGets402OnKioskAction(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/kiosk/action');
		$request->method('getHeader')->willReturnCallback(function (string $name): string {
			return match (strtolower($name)) {
				'x-kiosk-terminal-id' => 'term-1',
				'x-kiosk-token' => 'secret',
				default => '',
			};
		});

		$settings = $this->createMock(KioskSettingsService::class);
		$settings->method('isKioskEnabled')->willReturn(true);

		$license = $this->createMock(LicenseService::class);
		$license->method('isTerminalPlanActive')->willReturn(false);

		$terminals = $this->createMock(KioskTerminalService::class);
		$terminals->expects($this->never())->method('validateTerminalToken');

		$middleware = new KioskLicenseMiddleware(
			$request,
			$settings,
			$license,
			$terminals,
			$this->createMock(LoggerInterface::class),
		);

		$this->expectException(KioskTerminalLicenseRequiredException::class);
		$middleware->beforeController(new \stdClass(), 'action');
	}

	public function testPairPathSkipsTerminalLicenseCheck(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/kiosk/pair');
		$request->method('getHeader')->willReturn('');

		$settings = $this->createMock(KioskSettingsService::class);
		$settings->method('isKioskEnabled')->willReturn(true);

		$license = $this->createMock(LicenseService::class);
		$license->expects($this->never())->method('isTerminalPlanActive');

		$middleware = new KioskLicenseMiddleware(
			$request,
			$settings,
			$license,
			$this->createMock(KioskTerminalService::class),
			$this->createMock(LoggerInterface::class),
		);

		$middleware->beforeController(new \stdClass(), 'pair');
		$this->addToAssertionCount(1);
	}

	public function testAfterExceptionReturns402Json(): void
	{
		$middleware = new KioskLicenseMiddleware(
			$this->createMock(IRequest::class),
			$this->createMock(KioskSettingsService::class),
			$this->createMock(LicenseService::class),
			$this->createMock(KioskTerminalService::class),
			$this->createMock(LoggerInterface::class),
		);

		$response = $middleware->afterException(
			new \stdClass(),
			'action',
			new KioskTerminalLicenseRequiredException(),
		);
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_PAYMENT_REQUIRED, $response->getStatus());
		$data = $response->getData();
		$this->assertSame('TERMINAL_LICENSE_REQUIRED', $data['error']);
	}
}
