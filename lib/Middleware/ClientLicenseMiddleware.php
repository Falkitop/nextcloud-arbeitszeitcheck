<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

use OCA\ArbeitszeitCheck\AppInfo\Application;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\MobileSeatService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/**
 * Returns HTTP 402 for API clock/break writes via app password (Basic auth).
 * Web UI session requests are not gated — OSS browser stamping stays free.
 */
class ClientLicenseMiddleware extends Middleware
{
	/** @var list<string> */
	private const GATED_PATHS = [
		'/api/clock/in',
		'/api/clock/out',
		'/api/break/start',
		'/api/break/end',
		'/api/dashboard-widget/clock/in',
		'/api/dashboard-widget/clock/out',
		'/api/dashboard-widget/break/start',
		'/api/dashboard-widget/break/end',
	];

	public function __construct(
		private readonly IRequest $request,
		private readonly IUserSession $userSession,
		private readonly LicenseService $licenseService,
		private readonly MobileSeatService $mobileSeatService,
		private readonly IFactory $l10nFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	public function beforeController($controller, $methodName): void
	{
		if (strtoupper($this->request->getMethod()) !== 'POST') {
			return;
		}

		if (!$this->usesBasicAppPassword()) {
			return;
		}

		$path = $this->normalizeApiPath((string)$this->request->getPathInfo());
		if (!in_array($path, self::GATED_PATHS, true)) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		$userId = $user->getUID();

		if (!$this->licenseService->isMobilePlanActive()) {
			$this->logger->info('Mobile license gate: no active mobile plan', ['userId' => $userId, 'path' => $path]);
			throw new ClientLicenseRequiredException('no_plan');
		}

		if (!$this->mobileSeatService->isUserAllowed($userId)) {
			$this->logger->info('Mobile license gate: user has no seat', ['userId' => $userId, 'path' => $path]);
			throw new ClientLicenseRequiredException('no_seat');
		}
	}

	public function afterException($controller, $methodName, \Exception $exception)
	{
		if (!$exception instanceof ClientLicenseRequiredException) {
			throw $exception;
		}

		$l = $this->l10nFactory->get(Application::APP_ID);
		$message = $exception->getReason() === 'no_plan'
			? $l->t('ArbeitszeitCheck Mobile is not licensed for this organisation.')
			: $l->t('ArbeitszeitCheck Mobile is not licensed for this user.');
		$adminHint = $l->t('Ask your administrator to assign a mobile seat or add an organisation license.');

		return new JSONResponse([
			'success' => false,
			'error' => $message,
			'message' => $message,
			'code' => 'LICENSE_REQUIRED',
			'licensing' => [
				'purchaseUrl' => 'https://software-by-design.de/arbeitszeitcheck/mobile',
				'adminHint' => $adminHint,
			],
		], Http::STATUS_PAYMENT_REQUIRED);
	}

	/**
	 * Mobile and other API clients authenticate with a dedicated app password (Basic).
	 * Browser web UI uses session cookies without Basic — those requests are not gated.
	 */
	private function usesBasicAppPassword(): bool
	{
		$auth = (string)$this->request->getHeader('Authorization');
		return str_starts_with(strtolower($auth), 'basic ');
	}

	private function normalizeApiPath(string $pathInfo): string
	{
		$path = $pathInfo;
		$prefix = '/apps/arbeitszeitcheck';
		if (str_starts_with($path, $prefix)) {
			$path = substr($path, strlen($prefix));
		}
		if ($path === '') {
			return '/';
		}
		return $path;
	}
}
