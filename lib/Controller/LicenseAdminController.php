<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Service\CSPService;
use OCA\ArbeitszeitCheck\Service\LicenseEnforcementService;
use OCA\ArbeitszeitCheck\Service\LicenseService;
use OCA\ArbeitszeitCheck\Service\LocaleFormatService;
use OCA\ArbeitszeitCheck\Service\MobileSeatService;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCA\ArbeitszeitCheck\Service\TerminalDeviceService;
use OCA\ArbeitszeitCheck\Support\UserDirectorySearch;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

/**
 * Admin UI and API for org license (AZC2) and mobile seat assignment.
 */
class LicenseAdminController extends Controller
{
	use CSPTrait;
	use PageShellTrait;

	protected PermissionService $permissionService;
	protected IUserSession $userSession;
	protected IURLGenerator $urlGenerator;
	protected IL10N $l10n;
	protected LocaleFormatService $localeFormat;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly LicenseService $licenseService,
		private readonly LicenseEnforcementService $licenseEnforcementService,
		private readonly MobileSeatService $mobileSeatService,
		private readonly TerminalDeviceService $terminalDeviceService,
		private readonly IUserManager $userManager,
		PermissionService $permissionService,
		IUserSession $userSession,
		CSPService $cspService,
		IURLGenerator $urlGenerator,
		LocaleFormatService $localeFormat,
		IL10N $l10n,
	) {
		parent::__construct($appName, $request);
		$this->permissionService = $permissionService;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->localeFormat = $localeFormat;
		$this->l10n = $l10n;
		$this->setCspService($cspService);
	}

	/**
	 * @return array{showSubstitutionLink: bool, showManagerLink: bool, showReportsLink: bool, showAdminNav: bool}
	 */
	private function buildAdminNavFlags(): array
	{
		return [
			'showSubstitutionLink' => false,
			'showManagerLink' => true,
			'showReportsLink' => true,
			'showAdminNav' => true,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildAdminShellParams(string $pageId, string $title, string $help): array
	{
		return $this->buildShellParams($pageId, $title, $help, $this->buildAdminNavFlags(), $this->l10n->t('Administration'));
	}

	#[NoCSRFRequired]
	public function index(): TemplateResponse
	{
		$this->registerFrontEndAssets('admin-license', 'admin-license', [], ['common/admin-user-picker']);

		$summary = $this->licenseService->getLicenseSummary();
		$mobileLimit = $this->licenseService->getMobileSeatLimit();
		$terminalLimit = $this->licenseService->getTerminalDeviceLimit();

		$response = new TemplateResponse('arbeitszeitcheck', 'admin-license', array_merge(
			$this->buildAdminShellParams(
				'admin-license',
				$this->l10n->t('License'),
				$this->l10n->t('Manage your organisation license for Mobile and Terminal apps'),
			),
			[
				'license' => $summary,
				'mobileSeatsUsed' => $this->mobileSeatService->getAssignedCount(),
				'mobileSeatsLimit' => $mobileLimit,
				'terminalDevicesUsed' => $this->terminalDeviceService->getActiveCount(),
				'terminalDevicesLimit' => $terminalLimit,
				'mobileSeats' => $this->mobileSeatService->listSeats(),
				'showMobileSeats' => $mobileLimit > 0,
				'showTerminal' => $terminalLimit > 0,
				'purchaseUrl' => 'https://software-by-design.de/arbeitszeitcheck/preise',
				'apiLicenseUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.license_admin.applyLicense'),
				'apiClearLicenseUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.license_admin.clearLicense'),
				'apiSeatsUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.license_admin.assignSeat'),
				'apiRemoveSeatUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.license_admin.removeSeat'),
				'apiSearchUsersUrl' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.license_admin.searchUsers'),
				'requesttoken' => Util::callRegister(),
				'i18n' => [
					'saveSuccess' => $this->l10n->t('License saved successfully.'),
					'saveFailed' => $this->l10n->t('Could not save license.'),
					'emptyKey' => $this->l10n->t('Please paste a license key.'),
					'networkError' => $this->l10n->t('Network error. Please try again.'),
					'seatAssigned' => $this->l10n->t('Seat assigned.'),
					'seatRemoved' => $this->l10n->t('Seat removed.'),
					'assignFailed' => $this->l10n->t('Could not assign seat.'),
					'clearConfirm' => $this->l10n->t('Remove the organisation license and revoke all mobile seats and kiosk terminals? This cannot be undone.'),
					'clearSuccess' => $this->l10n->t('License removed.'),
					'clearFailed' => $this->l10n->t('Could not remove license.'),
					'clearLicense' => $this->l10n->t('Remove license'),
					'activeLabel' => $this->l10n->t('Active'),
					'inactiveLabel' => $this->l10n->t('Expired or invalid'),
				],
				'urlGenerator' => $this->urlGenerator,
			],
		));

		return $this->configureCSP($response, 'admin');
	}

	#[NoCSRFRequired]
	public function applyLicense(): JSONResponse
	{
		$body = file_get_contents('php://input');
		$data = is_string($body) ? json_decode($body, true) : null;
		if (!is_array($data)) {
			$data = $this->request->getParams();
		}
		$key = trim((string)($data['licenseKey'] ?? ''));

		if ($key === '') {
			return new JSONResponse([
				'ok' => false,
				'error' => 'empty_key',
				'message' => $this->l10n->t('Please paste a license key.'),
			], Http::STATUS_BAD_REQUEST);
		}

		if ($this->licenseService->applyLicenseKey($key)) {
			$enforced = $this->licenseEnforcementService->enforceCurrentLimits();
			$summary = $this->licenseService->getLicenseSummary();
			return new JSONResponse([
				'ok' => true,
				'license' => $summary,
				'mobileSeatsUsed' => $this->mobileSeatService->getAssignedCount(),
				'mobileSeatsLimit' => $this->licenseService->getMobileSeatLimit(),
				'terminalDevicesUsed' => $this->terminalDeviceService->getActiveCount(),
				'terminalDevicesLimit' => $this->licenseService->getTerminalDeviceLimit(),
				'enforced' => $enforced,
			]);
		}

		return new JSONResponse([
			'ok' => false,
			'error' => $this->licenseService->getLastApplyErrorCode(),
			'message' => $this->licenseService->getLastApplyErrorMessage(),
		], Http::STATUS_UNPROCESSABLE_ENTITY);
	}

	#[NoCSRFRequired]
	public function clearLicense(): JSONResponse
	{
		$cleared = $this->licenseEnforcementService->clearAllCommercialState();
		return new JSONResponse([
			'ok' => true,
			'cleared' => $cleared,
		]);
	}

	#[NoCSRFRequired]
	public function assignSeat(): JSONResponse
	{
		$body = file_get_contents('php://input');
		$data = is_string($body) ? json_decode($body, true) : null;
		if (!is_array($data)) {
			$data = $this->request->getParams();
		}
		$userId = trim((string)($data['userId'] ?? ''));
		$actor = $this->userSession->getUser()?->getUID() ?? '';

		$result = $this->mobileSeatService->assignSeat($userId, $actor);
		if (!$result['ok']) {
			$message = match ($result['error'] ?? '') {
				'seat_limit_reached' => $this->l10n->t('All mobile seats are assigned. Remove a user or upgrade your license.'),
				'no_mobile_plan' => $this->l10n->t('No mobile plan in the current license.'),
				'user_not_found' => $this->l10n->t('User not found.'),
				default => $this->l10n->t('Could not assign seat.'),
			};
			return new JSONResponse(['ok' => false, 'error' => $result['error'] ?? 'unknown', 'message' => $message], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new JSONResponse([
			'ok' => true,
			'seats' => $this->mobileSeatService->listSeats(),
			'mobileSeatsUsed' => $this->mobileSeatService->getAssignedCount(),
			'mobileSeatsLimit' => $this->licenseService->getMobileSeatLimit(),
		]);
	}

	#[NoCSRFRequired]
	public function removeSeat(): JSONResponse
	{
		$body = file_get_contents('php://input');
		$data = is_string($body) ? json_decode($body, true) : null;
		if (!is_array($data)) {
			$data = $this->request->getParams();
		}
		$userId = trim((string)($data['userId'] ?? ''));
		$this->mobileSeatService->removeSeat($userId);

		return new JSONResponse([
			'ok' => true,
			'seats' => $this->mobileSeatService->listSeats(),
			'mobileSeatsUsed' => $this->mobileSeatService->getAssignedCount(),
			'mobileSeatsLimit' => $this->licenseService->getMobileSeatLimit(),
		]);
	}

	#[NoCSRFRequired]
	public function searchUsers(): JSONResponse
	{
		$query = trim((string)$this->request->getParam('q', ''));
		$limit = min(25, max(1, (int)$this->request->getParam('limit', 15)));
		$result = UserDirectorySearch::searchByIdOrName($this->userManager, $query, $limit);
		$assigned = array_column($this->mobileSeatService->listSeats(), 'userId');
		$users = [];
		foreach ($result['users'] as $user) {
			$uid = $user->getUID();
			$users[] = [
				'id' => $uid,
				'displayName' => $user->getDisplayName(),
				'hasSeat' => in_array($uid, $assigned, true),
			];
		}

		return new JSONResponse([
			'ok' => true,
			'users' => $users,
			'truncated' => $result['truncated'],
		]);
	}
}
