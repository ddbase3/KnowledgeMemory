<?php declare(strict_types=1);

namespace KnowledgeMemory\Test\Display;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use KnowledgeMemory\Display\KnowledgeMemoryGameDisplay;
use KnowledgeMemory\Display\KnowledgeMemorySettingsDisplay;
use KnowledgeMemory\Feature\KnowledgeMemoryPlayFeature;
use KnowledgeMemory\Service\KnowledgeMemoryGameService;
use KnowledgeMemory\Service\KnowledgeMemoryPairParser;
use KnowledgeMemory\Settings\KnowledgeMemorySettingsTab;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KnowledgeMemoryGameDisplayTest extends TestCase {

	public function testStableIdentityAndUnsupportedOutput(): void {
		self::assertSame('knowledgememorygamedisplay', KnowledgeMemoryGameDisplay::getName());
		$view = $this->createMock(IMvcView::class);
		$view->expects(self::never())->method('loadTemplate');
		$display = $this->display($view, AuthorizationDecision::allow(), true);
		$display->setData(['ignored' => true]);
		self::assertSame('', $display->getOutput('json'));
	}

	public function testMissingLanguageBricksAreNotSilentlyReplaced(): void {
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->willReturn(null);
		$this->expectException(LogicException::class);
		$this->display($view, AuthorizationDecision::allow(), true)->getOutput('html');
	}

	public function testAllNonAllowDecisionsDenyRendering(): void {
		foreach ([AuthorizationDecision::deny(), AuthorizationDecision::indeterminate()] as $decision) {
			$view = $this->view();
			$view->expects(self::never())->method('loadTemplate');
			$display = $this->display($view, $decision, true);
			self::assertSame('<p role="alert">Denied &amp; blocked</p>', $display->getOutput('html'));
		}
	}

	public function testMissingHostUserDoesNotRender(): void {
		$view = $this->view();
		$view->expects(self::never())->method('loadTemplate');
		self::assertSame('<p role="alert">Denied &amp; blocked</p>',
			$this->display($view, AuthorizationDecision::allow(), false)->getOutput('html'));
	}

	public function testAuthorizationFailureDeniesWithoutRendering(): void {
		$view = $this->view();
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$authorization->method('decide')->willThrowException(new RuntimeException('Authority unavailable'));
		$view->expects(self::never())->method('loadTemplate');
		self::assertSame('<p role="alert">Denied &amp; blocked</p>',
			$this->display($view, AuthorizationDecision::allow(), true, $authorization)->getOutput('html'));
	}

	public function testUnavailableSettingsDisplayFailsAtTheExistingClassMapBoundary(): void {
		$view = $this->view();
		$classMap = $this->createMock(IClassMap::class);
		$classMap->method('getInstanceByInterfaceName')->with(IDisplay::class, KnowledgeMemorySettingsDisplay::getName())
			->willReturn(null);
		$this->expectException(LogicException::class);
		$this->display($view, AuthorizationDecision::allow(), true, null, $classMap)->getOutput('html');
	}

	public function testAnAuthorizedPlayerWithoutAdminPermissionGetsTheSameGameWithoutSettings(): void {
		$view = $this->view();
		$assigned = [];
		$view->method('assign')->willReturnCallback(static function(string $key, $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->expects(self::once())->method('setTemplate')->with('Display/KnowledgeMemoryGameDisplay.php');
		$view->method('loadTemplate')->willReturn('<game-rendered>');
		$display = $this->display($view, AuthorizationDecision::allow(), true);
		self::assertSame('<game-rendered>', $display->getOutput('html'));
		self::assertSame('', $assigned['settings_html']);
		self::assertSame('/assets/plugin/KnowledgeMemory/assets/knowledge-memory/game.css', $assigned['css_url']);
		self::assertSame('/assets/plugin/KnowledgeMemory/assets/knowledge-memory/game.js', $assigned['js_url']);
		self::assertSame('/api', $assigned['api_url']);
		self::assertMatchesRegularExpression('/^km-[a-f0-9]{16}$/', $assigned['root_id']);
	}

	private function view(): IMvcView {
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->with('knowledge_memory')->willReturn(['access_denied' => 'Denied & blocked']);
		return $view;
	}

	private function display(IMvcView $view, AuthorizationDecision $playDecision, bool $hasUser,
		?IAuthorizationService $authorization = null, ?IClassMap $classMap = null): KnowledgeMemoryGameDisplay {
		$assets = $this->createMock(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $name): string => '/assets/' . $name);
		$links = $this->createMock(ILinkTargetService::class);
		$links->method('getLink')->willReturn('/api');
		if ($authorization === null) {
			$authorization = $this->createMock(IAuthorizationService::class);
			$authorization->method('getDefaultAuthority')->willReturn('host');
			$authorization->method('decide')->willReturnCallback(static function(AuthorizationRequest $request) use ($playDecision): AuthorizationDecision {
				self::assertSame('host', $request->authority);
				return $request->permission->permission === KnowledgeMemoryPlayFeature::getName()
					? $playDecision : AuthorizationDecision::deny();
			});
		}
		$users = $this->createMock(IUsermanager::class);
		$user = new User();
		$user->id = 91;
		$users->method('getUser')->willReturn($hasUser ? $user : null);
		if ($classMap === null) {
			$store = $this->createMock(ISettingsStore::class);
			$game = new KnowledgeMemoryGameService(
				$this->createMock(IAgentConfigurationService::class),
				$this->createMock(IAgentExecutionService::class),
				$store, new KnowledgeMemoryPairParser()
			);
			$settings = new KnowledgeMemorySettingsDisplay(
				$view, $assets, $this->createMock(IRequest::class), $links,
				$authorization, $users, $store, $game
			);
			$classMap = $this->createMock(IClassMap::class);
			$classMap->method('getInstanceByInterfaceName')->with(IDisplay::class, KnowledgeMemorySettingsDisplay::getName())
				->willReturn($settings);
		}
		return new KnowledgeMemoryGameDisplay($view, $assets, $links, $authorization, $users, $classMap);
	}
}
