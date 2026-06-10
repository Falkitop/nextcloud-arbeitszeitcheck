<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCA\ArbeitszeitCheck\Service\CSPService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskCredentialService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskEnrollmentService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskException;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskSettingsService;
use OCA\ArbeitszeitCheck\Service\Kiosk\KioskTerminalService;
use OCA\ArbeitszeitCheck\Service\LocaleFormatService;
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

class KioskAdminController extends Controller
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
		private readonly KioskTerminalService $terminalService,
		private readonly KioskCredentialService $credentialService,
		private readonly KioskEnrollmentService $enrollmentService,
		private readonly KioskSettingsService $settingsService,
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

	/** @return array{showSubstitutionLink: bool, showManagerLink: bool, showReportsLink: bool, showAdminNav: bool} */
	private function buildAdminNavFlags(): array
	{
		return [
			'showSubstitutionLink' => false,
			'showManagerLink' => true,
			'showReportsLink' => true,
			'showAdminNav' => true,
		];
	}

	/** @return array<string, mixed> */
	private function buildAdminShellParams(string $pageId, string $title, string $help): array
	{
		return $this->buildShellParams($pageId, $title, $help, $this->buildAdminNavFlags(), $this->l10n->t('Administration'));
	}

	#[NoCSRFRequired]
	public function index(): TemplateResponse
	{
		$this->registerFrontEndAssets('admin-kiosk', 'admin-kiosk', [], ['common/admin-user-picker']);

		$terminals = [];
		foreach ($this->terminalService->listTerminals() as $terminal) {
			$terminals[] = [
				'terminalId' => $terminal->getTerminalId(),
				'label' => $terminal->getLabel(),
				'status' => $terminal->getStatus(),
				'lastSeenAt' => $terminal->getLastSeenAt()?->format('c'),
				'createdAt' => $terminal->getCreatedAt()->format('c'),
			];
		}

		$response = new TemplateResponse('arbeitszeitcheck', 'admin-kiosk', array_merge(
			$this->buildAdminShellParams(
				'admin-kiosk',
				$this->l10n->t('Kiosk terminals'),
				$this->l10n->t('Manage foyer tablets, employee badges, and PIN credentials'),
			),
			[
				'kioskEnabled' => $this->settingsService->isKioskEnabled(),
				'terminals' => $terminals,
				'terminalDevicesUsed' => $this->terminalDeviceService->getActiveCount(),
				'terminalDevicesLimit' => $this->terminalDeviceService->getDeviceLimit(),
				'apiBase' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.listCredentials'),
				'apiTerminals' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.createTerminal'),
				'apiCredentials' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.listCredentials'),
				'apiRfid' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.assignRfid'),
				'apiPinGenerate' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.generatePin'),
				'apiEnrollmentStart' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.startEnrollment'),
				'apiEnrollmentStatus' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.enrollmentStatus'),
				'apiEnrollmentCancel' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.cancelEnrollment'),
				'apiKioskEnabled' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.setKioskEnabled'),
				'apiSearchUsers' => $this->urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.searchUsers'),
				'apiTerminalRevoke' => $this->urlGenerator->linkToRoute(
					'arbeitszeitcheck.kiosk_admin.revokeTerminal',
					['terminalId' => '__ID__'],
				),
				'apiUserAllowed' => $this->urlGenerator->linkToRoute(
					'arbeitszeitcheck.kiosk_admin.setUserAllowed',
					['userId' => '__ID__'],
				),
				'requesttoken' => Util::callRegister(),
				'urlGenerator' => $this->urlGenerator,
				'i18n' => [
					'kioskEnabled' => $this->l10n->t('Kiosk enabled'),
					'kioskDisabled' => $this->l10n->t('Kiosk disabled'),
					'labelRequired' => $this->l10n->t('Enter a terminal label'),
					'terminalCreated' => $this->l10n->t('Terminal created — save the pairing code'),
					'terminalRevoked' => $this->l10n->t('Terminal revoked'),
					'confirmRevoke' => $this->l10n->t('Revoke this terminal? The tablet will need to be paired again.'),
					'revoke' => $this->l10n->t('Revoke'),
					'selectEmployeeTerminal' => $this->l10n->t('Select employee and terminal'),
					'selectEmployee' => $this->l10n->t('Select an employee first'),
					'enrollmentWaiting' => $this->l10n->t('Waiting for badge scan…'),
					'enrollmentDone' => $this->l10n->t('Badge assigned successfully'),
					'enrollmentExpired' => $this->l10n->t('Enrollment expired'),
					'credentialRemoved' => $this->l10n->t('Credential removed'),
					'delete' => $this->l10n->t('Delete'),
					'kioskAllowedOn' => $this->l10n->t('Kiosk access enabled'),
					'kioskAllowedOff' => $this->l10n->t('Kiosk access disabled'),
					'kioskAllowedLabel' => $this->l10n->t('Allow kiosk access'),
					'pinTitle' => $this->l10n->t('PIN generated'),
					'pinHint' => $this->l10n->t('PIN is shown only once. Share it securely with the employee.'),
					'close' => $this->l10n->t('Close'),
					'requestFailed' => $this->l10n->t('Request failed'),
					'yes' => $this->l10n->t('Yes'),
					'no' => $this->l10n->t('No'),
					'statusActive' => $this->l10n->t('Active'),
					'statusPending' => $this->l10n->t('Pending pairing'),
					'statusRevoked' => $this->l10n->t('Revoked'),
				],
			],
		));

		return $this->configureCSP($response, 'admin');
	}

	#[NoCSRFRequired]
	public function setKioskEnabled(): JSONResponse
	{
		$data = $this->readJsonBody();
		$enabled = !empty($data['enabled']);
		$this->settingsService->setKioskEnabled($enabled);
		return new JSONResponse(['success' => true, 'enabled' => $enabled]);
	}

	#[NoCSRFRequired]
	public function createTerminal(): JSONResponse
	{
		$data = $this->readJsonBody();
		$label = trim((string)($data['label'] ?? ''));
		if ($label === '') {
			return new JSONResponse(['success' => false, 'error' => 'label_required'], Http::STATUS_BAD_REQUEST);
		}
		$actor = $this->userSession->getUser()?->getUID() ?? '';
		try {
			$result = $this->terminalService->createPendingTerminal($label, $actor);
			return new JSONResponse([
				'success' => true,
				'data' => [
					'terminalId' => $result['terminal']->getTerminalId(),
					'label' => $result['terminal']->getLabel(),
					'pairingCode' => $result['pairingCode'],
					'pairingExpiresAt' => $result['pairingExpiresAt'],
				],
			], Http::STATUS_CREATED);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	#[NoCSRFRequired]
	public function revokeTerminal(string $terminalId): JSONResponse
	{
		$this->terminalService->revoke($terminalId);
		return new JSONResponse(['success' => true]);
	}

	#[NoCSRFRequired]
	public function listCredentials(): JSONResponse
	{
		$userId = trim((string)$this->request->getParam('userId', ''));
		$credentials = [];
		foreach ($this->credentialService->listCredentials($userId !== '' ? $userId : null) as $cred) {
			$user = $this->userManager->get($cred->getUserId());
			$credentials[] = [
				'id' => $cred->getId(),
				'userId' => $cred->getUserId(),
				'displayName' => $user !== null ? $user->getDisplayName() : $cred->getUserId(),
				'type' => $cred->getType(),
				'label' => $cred->getLabel(),
				'kioskAllowed' => $this->settingsService->isUserKioskAllowed($cred->getUserId()),
				'lockedUntil' => $cred->getLockedUntil()?->format('c'),
				'hasPin' => $cred->getType() === 'pin',
				'hasRfid' => $cred->getType() === 'rfid',
			];
		}
		return new JSONResponse(['success' => true, 'data' => ['credentials' => $credentials]]);
	}

	#[NoCSRFRequired]
	public function assignRfid(): JSONResponse
	{
		$data = $this->readJsonBody();
		$userId = trim((string)($data['userId'] ?? ''));
		$rfidUid = trim((string)($data['rfidUid'] ?? ''));
		$label = isset($data['label']) ? trim((string)$data['label']) : null;
		$actor = $this->userSession->getUser()?->getUID() ?? '';
		try {
			$result = $this->credentialService->assignRfid($userId, $rfidUid, $actor, $label !== '' ? $label : null);
			return new JSONResponse(['success' => true, 'data' => $result], Http::STATUS_CREATED);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	#[NoCSRFRequired]
	public function generatePin(): JSONResponse
	{
		$data = $this->readJsonBody();
		$userId = trim((string)($data['userId'] ?? ''));
		$actor = $this->userSession->getUser()?->getUID() ?? '';
		try {
			$result = $this->credentialService->generatePin($userId, $actor);
			return new JSONResponse([
				'success' => true,
				'data' => [
					'pin' => $result['pin'],
					'message' => $this->l10n->t('PIN is shown only once'),
				],
			], Http::STATUS_CREATED);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	#[NoCSRFRequired]
	public function deleteCredential(int $id): JSONResponse
	{
		$actor = $this->userSession->getUser()?->getUID() ?? '';
		try {
			$this->credentialService->revoke($id, $actor);
			return new JSONResponse(['success' => true]);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	#[NoCSRFRequired]
	public function setUserAllowed(string $userId): JSONResponse
	{
		$data = $this->readJsonBody();
		$allowed = !empty($data['kioskAllowed']);
		$this->settingsService->setUserKioskAllowed($userId, $allowed);
		return new JSONResponse(['success' => true, 'userId' => $userId, 'kioskAllowed' => $allowed]);
	}

	#[NoCSRFRequired]
	public function importCredentials(): JSONResponse
	{
		$csv = (string)($this->request->getParam('csv') ?? '');
		if ($csv === '' && isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
			$csv = (string)file_get_contents($_FILES['file']['tmp_name']);
		}
		$actor = $this->userSession->getUser()?->getUID() ?? '';
		$result = $this->credentialService->importCsv($csv, $actor);
		return new JSONResponse(['success' => true, 'data' => $result]);
	}

	#[NoCSRFRequired]
	public function startEnrollment(): JSONResponse
	{
		$data = $this->readJsonBody();
		$userId = trim((string)($data['userId'] ?? ''));
		$terminalId = trim((string)($data['terminalId'] ?? ''));
		$actor = $this->userSession->getUser()?->getUID() ?? '';
		try {
			$result = $this->enrollmentService->start($userId, $terminalId, $actor);
			return new JSONResponse(['success' => true, 'data' => $result], Http::STATUS_CREATED);
		} catch (KioskException $e) {
			return $this->kioskError($e);
		}
	}

	#[NoCSRFRequired]
	public function enrollmentStatus(): JSONResponse
	{
		$terminalId = trim((string)$this->request->getParam('terminalId', ''));
		return new JSONResponse(['success' => true, 'data' => $this->enrollmentService->getStatus($terminalId)]);
	}

	#[NoCSRFRequired]
	public function cancelEnrollment(): JSONResponse
	{
		$data = $this->readJsonBody();
		$terminalId = trim((string)($data['terminalId'] ?? ''));
		$actor = $this->userSession->getUser()?->getUID() ?? '';
		$this->enrollmentService->cancel($terminalId, $actor);
		return new JSONResponse(['success' => true]);
	}

	#[NoCSRFRequired]
	public function searchUsers(): JSONResponse
	{
		$query = trim((string)$this->request->getParam('q', ''));
		$result = UserDirectorySearch::searchByIdOrName($this->userManager, $query, 25);
		$users = [];
		foreach ($result['users'] as $user) {
			$users[] = ['userId' => $user->getUID(), 'displayName' => $user->getDisplayName()];
		}
		return new JSONResponse(['success' => true, 'users' => $users]);
	}

	/** @return array<string, mixed> */
	private function readJsonBody(): array
	{
		$body = file_get_contents('php://input');
		$data = is_string($body) ? json_decode($body, true) : null;
		return is_array($data) ? $data : $this->request->getParams();
	}

	private function kioskError(KioskException $e): JSONResponse
	{
		$status = match ($e->getErrorCode()) {
			'KIOSK_RFID_ALREADY_ASSIGNED' => Http::STATUS_CONFLICT,
			'KIOSK_USER_NOT_ALLOWED', 'TERMINAL_DEVICE_LIMIT_REACHED', 'TERMINAL_LICENSE_REQUIRED' => Http::STATUS_FORBIDDEN,
			default => Http::STATUS_BAD_REQUEST,
		};
		return new JSONResponse(['success' => false, 'error' => $e->getErrorCode()], $status);
	}
}
