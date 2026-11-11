<?php declare(strict_types=1);

namespace KnowledgeMemory\Test\Display;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use Base3\Api\IAssetResolver;
use Base3\Authorization\Api\IFeature;
use Base3\Translation\Api\ITranslation;
use UiFoundation\Api\ISettingsTab;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use KnowledgeMemory\Display\KnowledgeMemorySettingsDisplay;
use KnowledgeMemory\Service\KnowledgeMemoryGameService;
use KnowledgeMemory\Service\KnowledgeMemoryPairParser;
use KnowledgeMemory\Settings\KnowledgeMemorySettingsTab;
use PHPUnit\Framework\TestCase;

final class KnowledgeMemorySettingsDisplayTest extends TestCase {

	public function testSettingsPermissionRetainsIdentityWithoutAdministrationTab(): void {
		$feature = new KnowledgeMemorySettingsTab($this->createMock(ITranslation::class));
		self::assertInstanceOf(IFeature::class, $feature);
		self::assertNotInstanceOf(ISettingsTab::class, $feature);
		self::assertSame('knowledgememorysettingstab', $feature::getName());
		self::assertSame(['group' => 'administration'], $feature->getDefaultAudience());
		self::assertCount(1, $feature->getPermissions());
		self::assertSame('knowledgememorysettingstab', $feature->getPermissions()[0]->permission);
	}

	public function testInGameConfigurationUsesTheExistingSettingsFeaturePermission(): void {
		foreach ([
			[AuthorizationDecision::allow(), true],
			[AuthorizationDecision::deny(), false],
			[AuthorizationDecision::indeterminate(), false]
		] as [$decision, $expected]) {
			$authorization = $this->createMock(IAuthorizationService::class);
			$authorization->method('getDefaultAuthority')->willReturn('host-instance');
			$authorization->expects(self::once())->method('decide')->willReturnCallback(
				static function(AuthorizationRequest $request) use ($decision): AuthorizationDecision {
					self::assertSame('host-instance', $request->authority);
					self::assertSame('feature', $request->permission->scope);
					self::assertSame(KnowledgeMemorySettingsTab::getName(), $request->permission->permission);
					return $decision;
				}
			);
			$settings = $this->createMock(ISettingsStore::class);
			$game = new KnowledgeMemoryGameService(
				$this->createMock(IAgentConfigurationService::class),
				$this->createMock(IAgentExecutionService::class),
				$settings,
				new KnowledgeMemoryPairParser()
			);
			$display = new KnowledgeMemorySettingsDisplay(
				$this->createMock(IMvcView::class),
				$this->createMock(IAssetResolver::class),
				$this->createMock(IRequest::class),
				$this->createMock(ILinkTargetService::class),
				$authorization,
				$this->createMock(IUsermanager::class),
				$settings,
				$game
			);
			self::assertSame($expected, $display->canConfigure());
		}
	}

	public function testSameOriginFormPostSavesSettingsWithoutAnApplicationToken(): void {
		[$result, $status] = $this->callSave(
			['REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => 'same-origin', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded; charset=UTF-8'],
			['agent_id' => 'existing-agent', 'pairs' => '8', 'difficulty' => 'normal'],
			AuthorizationDecision::allow(), true, true
		);
		self::assertSame(['ok' => true, 'message' => 'Saved'], $result);
		self::assertSame(200, $status);
	}

	public function testRequestsWithoutSameOriginProofOrFormContentTypeNeverWriteSettings(): void {
		$base = ['REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => 'same-origin', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded; charset=UTF-8'];
		foreach ([
			['HTTP_SEC_FETCH_SITE' => 'cross-site'],
			['HTTP_SEC_FETCH_SITE' => 'same-site'],
			['HTTP_SEC_FETCH_SITE' => 'none'],
			['HTTP_SEC_FETCH_SITE' => null],
			['CONTENT_TYPE' => 'application/json'],
			['CONTENT_TYPE' => 'multipart/form-data'],
			['CONTENT_TYPE' => null]
		] as $override) {
			$server = array_replace($base, $override);
			[$result, $status] = $this->callSave($server, [
				'agent_id' => 'existing-agent', 'pairs' => '8', 'difficulty' => 'normal'
			], AuthorizationDecision::allow(), true, false);
			self::assertSame(['ok' => false, 'error' => 'Invalid request'], $result);
			self::assertSame(403, $status);
		}
	}

	public function testGetCannotSaveSettings(): void {
		[$result, $status] = $this->callSave([
			'REQUEST_METHOD' => 'GET', 'HTTP_SEC_FETCH_SITE' => 'same-origin',
			'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
		], [], AuthorizationDecision::allow(), true, false);
		self::assertSame(['ok' => false, 'error' => 'Invalid request'], $result);
		self::assertSame(405, $status);
	}

	public function testPermissionAndAuthenticatedUserAreRequiredForEverySave(): void {
		$server = [
			'REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => 'same-origin',
			'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
		];
		foreach ([
			[AuthorizationDecision::deny(), true],
			[AuthorizationDecision::indeterminate(), true],
			[AuthorizationDecision::allow(), false]
		] as [$decision, $userPresent]) {
			[$result, $status] = $this->callSave($server, [
				'agent_id' => 'existing-agent', 'pairs' => '8', 'difficulty' => 'normal'
			], $decision, $userPresent, false);
			self::assertSame(['ok' => false, 'error' => 'Denied'], $result);
			self::assertSame(403, $status);
		}
	}

	public function testInvalidPairCountDoesNotWriteSettings(): void {
		[$result, $status] = $this->callSave([
			'REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => 'same-origin',
			'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
		], ['agent_id' => 'existing-agent', 'pairs' => 'eight', 'difficulty' => 'normal'],
			AuthorizationDecision::allow(), true, false);
		self::assertSame(['ok' => false, 'error' => 'Invalid settings'], $result);
		self::assertSame(422, $status);
	}

	public function testAuthorizedHtmlSettingsRenderForEmbeddingInsideTheGameSidebar(): void {
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->willReturn(['access_denied' => 'Denied']);
		$view->expects(self::once())->method('setTemplate')->with('Display/KnowledgeMemorySettingsDisplay.php');
		$assigned = [];
		$view->method('assign')->willReturnCallback(static function(string $key, $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->method('loadTemplate')->willReturn('<settings-rendered>');
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host-instance');
		$authorization->method('decide')->willReturn(AuthorizationDecision::allow());
		$users = $this->createMock(IUsermanager::class);
		$user = new User();
		$user->id = 42;
		$users->method('getUser')->willReturn($user);
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['agent_id' => 'agent-one', 'pairs' => 12, 'difficulty' => 'hard']);
		$agents = $this->createMock(IAgentConfigurationService::class);
		$agents->method('getAgents')->with('agent')->willReturn([
			'agent-one' => ['enabled' => true, 'label' => 'Agent One']
		]);
		$game = new KnowledgeMemoryGameService($agents, $this->createMock(IAgentExecutionService::class), $store, new KnowledgeMemoryPairParser());
		$assets = $this->createMock(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $name): string => '/assets/' . $name);
		$links = $this->createMock(ILinkTargetService::class);
		$links->method('getLink')->willReturn('/existing-endpoint');
		$display = new KnowledgeMemorySettingsDisplay(
			$view, $assets, $this->createMock(IRequest::class), $links, $authorization, $users, $store, $game
		);
		self::assertSame('knowledgememorysettingsdisplay', KnowledgeMemorySettingsDisplay::getName());
		$display->setData(['unused' => true]);
		self::assertSame('<settings-rendered>', $display->getOutput('html'));
		self::assertSame(['agent_id' => 'agent-one', 'pairs' => 12, 'difficulty' => 'hard'], $assigned['settings']);
		self::assertSame(['agent-one' => 'Agent One'], $assigned['agents']);
		self::assertSame('/existing-endpoint', $assigned['api_url']);
		self::assertSame('/existing-endpoint', $assigned['game_url']);
		self::assertMatchesRegularExpression('/^km-settings-[a-f0-9]{16}$/', $assigned['root_id']);
	}

	/**
	 * @param array<string, mixed> $server
	 * @param array<string, mixed> $form
	 * @return array{array<string, mixed>, int}
	 */
	private function callSave(array $server, array $form, AuthorizationDecision $decision, bool $authenticated, bool $save): array {
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->with('knowledge_memory')->willReturn([
			'access_denied' => 'Denied', 'invalid_request' => 'Invalid request',
			'invalid_settings' => 'Invalid settings', 'save_failed' => 'Save failed', 'saved' => 'Saved'
		]);
		$request = $this->createMock(IRequest::class);
		$request->method('server')->willReturnCallback(static fn(string $key, $default = null) => $server[$key] ?? $default);
		$request->method('post')->willReturnCallback(static fn(string $key, $default = null) => $form[$key] ?? $default);
		$request->expects(self::never())->method('getJsonBody');
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host-instance');
		$authorization->method('decide')->willReturnCallback(static function(AuthorizationRequest $request) use ($decision): AuthorizationDecision {
			self::assertSame(KnowledgeMemorySettingsTab::getName(), $request->permission->permission);
			return $decision;
		});
		$users = $this->createMock(IUsermanager::class);
		$user = new User();
		$user->id = 42;
		$users->method('getUser')->willReturn($authenticated ? $user : null);
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects($save ? self::once() : self::never())->method('set')->with('knowledge_memory', 'game', [
			'agent_id' => 'existing-agent', 'pairs' => 8, 'difficulty' => 'normal'
		]);
		$settings->expects($save ? self::once() : self::never())->method('save');
		$agents = $this->createMock(IAgentConfigurationService::class);
		$agents->method('getAgent')->with('existing-agent', 'agent')->willReturn(['enabled' => true]);
		$game = new KnowledgeMemoryGameService(
			$agents, $this->createMock(IAgentExecutionService::class), $settings, new KnowledgeMemoryPairParser()
		);
		$display = new KnowledgeMemorySettingsDisplay(
			$view, $this->createMock(IAssetResolver::class), $request, $this->createMock(ILinkTargetService::class),
			$authorization, $users, $settings, $game
		);
		http_response_code(200);
		$data = json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		return [$data, http_response_code()];
	}
}
