<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Db\LicenseState;
use OCA\ArbeitszeitCheck\Db\LicenseStateMapper;
use OCA\ArbeitszeitCheck\License\Azc2Codec;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/**
 * AZC2 org license: verify, persist, envelope for bootstrap/config.
 */
class LicenseService
{
	private string $lastApplyError = '';

	public function __construct(
		private readonly LicenseStateMapper $licenseStateMapper,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getLastApplyErrorCode(): string
	{
		return $this->lastApplyError;
	}

	public function getLastApplyErrorMessage(): string
	{
		return match ($this->lastApplyError) {
			Azc2Codec::ERROR_INVALID_FORMAT => 'Ungültiges Lizenzformat.',
			Azc2Codec::ERROR_INVALID_SIGNATURE => 'Signatur ungültig.',
			Azc2Codec::ERROR_EXPIRED => 'Lizenz abgelaufen.',
			Azc2Codec::ERROR_NO_PRODUCTS => 'Kein Mobile- oder Terminal-Zugang enthalten.',
			Azc2Codec::ERROR_INVALID_PAYLOAD => 'Ungültige Lizenzdaten.',
			default => '',
		};
	}

	public function applyLicenseKey(string $wireKey): bool
	{
		$this->lastApplyError = '';

		$error = Azc2Codec::classifyApplyError($wireKey);
		if ($error !== '') {
			$this->lastApplyError = $error;
			$this->logger->warning('AZC2 license apply rejected', ['code' => $error]);
			return false;
		}

		$parsed = Azc2Codec::parseAndVerify($wireKey);
		if ($parsed === null) {
			$this->lastApplyError = Azc2Codec::ERROR_INVALID_FORMAT;
			return false;
		}

		$payload = $parsed['payload'];
		$now = $this->timeFactory->getDateTime();

		$state = new LicenseState();
		$state->setCustomerId((string)$payload['customerId']);
		$state->setValidUntil(new \DateTime((string)$payload['validUntil']));
		$state->setMobileSeats((int)$payload['mobileSeats']);
		$state->setTerminalDevices((int)$payload['terminalDevices']);
		$state->setBundle(!empty($payload['bundle']) ? 1 : 0);
		$state->setKeyAppliedAt($now);
		$state->setPayloadB64($parsed['payloadB64']);
		$state->setSignatureB64($parsed['signatureB64']);

		$this->licenseStateMapper->upsert($state);
		$this->logger->info('AZC2 license applied', [
			'customerId' => $state->getCustomerId(),
			'mobileSeats' => $state->getMobileSeats(),
			'terminalDevices' => $state->getTerminalDevices(),
			'validUntil' => $payload['validUntil'],
		]);

		return true;
	}

	/**
	 * @return array{format: string, payloadB64: string, signatureB64: string}|null
	 */
	public function buildEnvelope(): ?array
	{
		$state = $this->licenseStateMapper->findCurrent();
		if ($state === null) {
			return null;
		}
		if (!$this->isLicenseValid($state)) {
			return null;
		}

		$wire = Azc2Codec::FORMAT . '.'
			. $state->getPayloadB64() . '.'
			. $state->getSignatureB64();
		if (Azc2Codec::parseAndVerify($wire) === null) {
			$this->logger->warning('Stored AZC2 license failed cryptographic re-verification');
			return null;
		}

		return [
			'format' => Azc2Codec::FORMAT,
			'payloadB64' => $state->getPayloadB64(),
			'signatureB64' => $state->getSignatureB64(),
		];
	}

	public function isMobilePlanActive(): bool
	{
		$state = $this->licenseStateMapper->findCurrent();
		return $state !== null
			&& $this->isLicenseValid($state)
			&& $state->getMobileSeats() > 0;
	}

	public function isTerminalPlanActive(): bool
	{
		$state = $this->licenseStateMapper->findCurrent();
		return $state !== null
			&& $this->isLicenseValid($state)
			&& $state->getTerminalDevices() > 0;
	}

	public function getMobileSeatLimit(): int
	{
		$state = $this->licenseStateMapper->findCurrent();
		if ($state === null || !$this->isLicenseValid($state)) {
			return 0;
		}
		return max(0, $state->getMobileSeats());
	}

	public function getTerminalDeviceLimit(): int
	{
		$state = $this->licenseStateMapper->findCurrent();
		if ($state === null || !$this->isLicenseValid($state)) {
			return 0;
		}
		return max(0, $state->getTerminalDevices());
	}

	public function getValidUntil(): ?\DateTimeImmutable
	{
		$state = $this->licenseStateMapper->findCurrent();
		if ($state === null) {
			return null;
		}
		$dt = $state->getValidUntil();
		if ($dt === null) {
			return null;
		}
		return \DateTimeImmutable::createFromMutable($dt) ?: null;
	}

	public function getCustomerId(): ?string
	{
		$state = $this->licenseStateMapper->findCurrent();
		if ($state === null) {
			return null;
		}
		$id = $state->getCustomerId();
		return $id !== '' ? $id : null;
	}

	public function getLicenseSummary(): ?array
	{
		$state = $this->licenseStateMapper->findCurrent();
		if ($state === null) {
			return null;
		}
		$valid = $this->isLicenseValid($state);
		$validUntil = $state->getValidUntil();
		return [
			'customerId' => $state->getCustomerId(),
			'validUntil' => $validUntil?->format('Y-m-d'),
			'mobileSeats' => $state->getMobileSeats(),
			'terminalDevices' => $state->getTerminalDevices(),
			'bundle' => $state->getBundle() === 1,
			'active' => $valid,
			'keyAppliedAt' => $state->getKeyAppliedAt()?->format('c'),
		];
	}

	public function clearLicense(): void
	{
		$this->licenseStateMapper->deleteAll();
	}

	private function isLicenseValid(LicenseState $state): bool
	{
		$until = $state->getValidUntil();
		if ($until === null) {
			return false;
		}
		$today = $this->timeFactory->getDateTime()->setTime(0, 0, 0);
		$expiry = (clone $until)->setTime(0, 0, 0);
		return $expiry >= $today;
	}
}
