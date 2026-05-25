<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Middleware;

use OCA\ArbeitszeitCheck\Controller\AdminController;
use OCA\ArbeitszeitCheck\Exception\NotAppAdminException;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

final class AppAdminMiddleware extends Middleware
{
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly PermissionService $permissionService,
		private readonly IL10N $l10n,
		private readonly IRequest $request,
	) {
	}

	public function beforeController($controller, $methodName): void
	{
		if (!$controller instanceof AdminController) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null || !$this->permissionService->isAdmin($user->getUID())) {
			throw new NotAppAdminException($this->l10n->t('Access denied. You are not an ArbeitszeitCheck app administrator.'));
		}
	}

	public function afterException($controller, $methodName, \Exception $exception): Response
	{
		if (!$exception instanceof NotAppAdminException) {
			throw $exception;
		}

		// Return JSON for API/AJAX consumers; HTML for browser page loads.
		// This avoids serving an HTML 403 page where JS code expects JSON.
		// All request introspection is wrapped defensively because the request
		// object may not be fully populated (e.g. unit/integration test runner).
		$path = '';
		$method = 'GET';
		$accept = '';
		$contentType = '';
		$xRequestedWith = '';
		try {
			$path = (string)($this->request->getPathInfo() ?? '');
		} catch (\Throwable) {
			// Path info not available in this request context — treat as page request.
		}
		try {
			$method = (string)$this->request->getMethod();
		} catch (\Throwable) {
			$method = 'GET';
		}
		try {
			$accept = strtolower((string)$this->request->getHeader('Accept'));
			$contentType = strtolower((string)$this->request->getHeader('Content-Type'));
			$xRequestedWith = strtolower((string)$this->request->getHeader('X-Requested-With'));
		} catch (\Throwable) {
			// Headers not available — treat as page request.
		}

		$isApi = str_contains($path, '/api/')
			|| str_contains($path, '/ocs/')
			|| ($method !== '' && $method !== 'GET');
		$wantsJson = str_contains($accept, 'application/json')
			|| str_contains($contentType, 'application/json')
			|| $xRequestedWith === 'xmlhttprequest';

		if ($isApi || $wantsJson) {
			return new JSONResponse([
				'ok' => false,
				'error' => ['code' => 'admin_required'],
				'message' => $exception->getMessage(),
			], Http::STATUS_FORBIDDEN);
		}

		$response = new TemplateResponse('core', '403', ['message' => $exception->getMessage()], 'guest');
		$response->setStatus(Http::STATUS_FORBIDDEN);
		return $response;
	}
}
