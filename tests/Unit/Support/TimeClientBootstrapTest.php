<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Support;

use OCA\ArbeitszeitCheck\Service\TimeZoneService;
use OCA\ArbeitszeitCheck\Support\TimeClientBootstrap;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TimeClientBootstrapTest extends TestCase {
	protected function tearDown(): void {
		$ref = new \ReflectionClass(TimeClientBootstrap::class);
		foreach (['configRegistered', 'scriptsRegistered'] as $property) {
			$prop = $ref->getProperty($property);
			$prop->setAccessible(true);
			$prop->setValue(null, false);
		}
		parent::tearDown();
	}

	private function createBootstrap(IInitialState $initialState): TimeClientBootstrap {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn ($app, $key, $default) => match ($key) {
			'app_timezone' => 'Europe/Berlin',
			default => $default,
		});
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$timeZoneService = new TimeZoneService($config, $dateTimeZone, $userSession, new NullLogger());

		return new TimeClientBootstrap($timeZoneService, $dateTimeZone, $initialState);
	}

	public function testRegisterConfigEmitsTimeInitialState(): void {
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects($this->once())
			->method('provideInitialState')
			->with(
				'time',
				$this->callback(static function (array $payload): bool {
					return isset($payload['tz']['storage'], $payload['tz']['display'], $payload['serverNow'])
						&& $payload['tz']['storage'] === 'Europe/Berlin'
						&& $payload['tz']['display'] === 'Europe/Berlin'
						&& is_string($payload['serverNow'])
						&& $payload['serverNow'] !== '';
				})
			);

		$bootstrap = $this->createBootstrap($initialState);
		$bootstrap->registerConfig();
	}

	public function testRegisterConfigIsIdempotentPerRequest(): void {
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects($this->once())->method('provideInitialState');

		$bootstrap = $this->createBootstrap($initialState);
		$bootstrap->registerConfig();
		$bootstrap->registerConfig();
	}

	public function testStorageAndDisplayTimeZonesMatchService(): void {
		$initialState = $this->createMock(IInitialState::class);
		$bootstrap = $this->createBootstrap($initialState);

		$this->assertSame('Europe/Berlin', $bootstrap->storageTimeZone()->getName());
		$this->assertSame('Europe/Berlin', $bootstrap->userDisplayTimeZone()->getName());
		$this->assertNotSame('', $bootstrap->serverNowIso());
	}
}
