<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\Middleware;

use OCA\ArbeitszeitCheck\Exception\AppAccessDeniedException;
use OCA\ArbeitszeitCheck\Middleware\AppAccessMiddleware;
use OCA\ArbeitszeitCheck\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AppAccessMiddlewareTest extends TestCase
{
	private AppAccessMiddleware $middleware;
	private PermissionService $permissionService;
	private IUserSession $userSession;

	protected function setUp(): void
	{
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/dashboard');
		$request->method('getMethod')->willReturn('GET');
		$request->method('getHeader')->willReturn('');

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToDefaultPageUrl')->willReturn('/');

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l10n);

		$this->middleware = new AppAccessMiddleware(
			$this->userSession,
			$this->permissionService,
			$request,
			$urlGenerator,
			$l10nFactory,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function fakeController(): object
	{
		return new \OCA\ArbeitszeitCheck\Controller\FakeControllerForMiddlewareTest();
	}

	public function testAllowsWhenUserCanUseApp(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($user);
		$this->permissionService->method('isUserAllowedByAccessGroups')->with('alice')->willReturn(true);

		$this->middleware->beforeController($this->fakeController(), 'dashboard');
		$this->addToAssertionCount(1);
	}

	public function testThrowsWhenUserDenied(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$this->userSession->method('getUser')->willReturn($user);
		$this->permissionService->method('isUserAllowedByAccessGroups')->with('bob')->willReturn(false);

		$this->expectException(AppAccessDeniedException::class);
		$this->middleware->beforeController($this->fakeController(), 'dashboard');
	}

	public function testAfterExceptionReturnsForbiddenTemplateForPage(): void
	{
		$exception = new AppAccessDeniedException('restriction');
		$response = $this->middleware->afterException(null, 'dashboard', $exception);
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testAfterExceptionReturnsJsonForApiPath(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/arbeitszeitcheck/api/clock/in');
		$request->method('getMethod')->willReturn('POST');
		$request->method('getHeader')->willReturn('');

		$middleware = new AppAccessMiddleware(
			$this->userSession,
			$this->permissionService,
			$request,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IFactory::class),
			$this->createMock(LoggerInterface::class),
		);

		$response = $middleware->afterException(null, 'clockIn', new AppAccessDeniedException('restriction'));
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}
}
