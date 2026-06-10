<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

use OCA\ArbeitszeitCheck\Service\Kiosk\KioskSettingsService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Gates /api/kiosk/* — feature flag, terminal license, terminal token validation.
 * POST /api/kiosk/pair is exempt from token checks (pairing code only).
 */
class KioskLicenseMiddleware extends Middleware
{
	private const PAIR_PATH = '/api/kiosk/pair';

	public function __construct(
		private readonly IRequest $request,
		private readonly KioskSettingsService $kioskSettingsService,
		private readonly LicenseService $licenseService,
		private readonly KioskTerminalService $terminalService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function beforeController($controller, $methodName): void
	{
		if (!$this->isKioskApiRequest()) {
			return;
		}

		if (!$this->kioskSettingsService->isKioskEnabled()) {
			throw new KioskDisabledException();
		}

		$path = $this->normalizeApiPath((string)$this->request->getPathInfo());
		if ($path === self::PAIR_PATH) {
			return;
		}

		if (!$this->licenseService->isTerminalPlanActive()) {
			$this->logger->info('Kiosk license gate: no active terminal plan', ['path' => $path]);
			throw new KioskTerminalLicenseRequiredException();
		}

		$terminalId = (string)$this->request->getHeader('X-Kiosk-Terminal-Id');
		$token = (string)$this->request->getHeader('X-Kiosk-Token');
		if ($this->terminalService->validateTerminalToken($terminalId, $token) === null) {
			throw new KioskUnauthorizedException();
		}
	}

	public function afterException($controller, $methodName, \Exception $exception)
	{
		if ($exception instanceof KioskDisabledException) {
			return new JSONResponse([
				'success' => false,
				'error' => 'KIOSK_DISABLED',
				'message' => 'Kiosk mode is not enabled on this server.',
			], Http::STATUS_NOT_FOUND);
		}

		if ($exception instanceof KioskTerminalLicenseRequiredException) {
			return new JSONResponse([
				'success' => false,
				'error' => 'TERMINAL_LICENSE_REQUIRED',
				'message' => 'ArbeitszeitCheck Terminal is not licensed for this organisation.',
				'licensing' => [
					'purchaseUrl' => 'https://software-by-design.de/arbeitszeitcheck/terminal',
					'adminHint' => 'Ask your administrator to add a Terminal license key.',
				],
			], Http::STATUS_PAYMENT_REQUIRED);
		}

		if ($exception instanceof KioskUnauthorizedException) {
			return new JSONResponse([
				'success' => false,
				'error' => 'KIOSK_TERMINAL_UNAUTHORIZED',
				'message' => 'Terminal token invalid or revoked.',
			], Http::STATUS_UNAUTHORIZED);
		}

		throw $exception;
	}

	private function isKioskApiRequest(): bool
	{
		$path = $this->normalizeApiPath((string)$this->request->getPathInfo());
		return str_starts_with($path, '/api/kiosk');
	}

	private function normalizeApiPath(string $pathInfo): string
	{
		$path = $pathInfo;
		$prefix = '/apps/arbeitszeitcheck';
		if (str_starts_with($path, $prefix)) {
			$path = substr($path, strlen($prefix));
		}
		return $path === '' ? '/' : $path;
	}
}
