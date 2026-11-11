<?php declare(strict_types=1);

namespace KnowledgeMemory\Test\Output;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\User;
use KnowledgeMemory\Feature\KnowledgeMemoryPlayFeature;
use KnowledgeMemory\Output\KnowledgeMemoryGameOutput;
use KnowledgeMemory\Service\KnowledgeMemoryGameService;
use KnowledgeMemory\Service\KnowledgeMemoryPairParser;
use PHPUnit\Framework\TestCase;

final class KnowledgeMemoryGameOutputTest extends TestCase {

	/** @return array<string,string> */
	private function validCardsForm(): array {
		return [
			'action' => 'cards', 'exclude_count' => '0',
			'seed_0_0' => 'Physik', 'seed_0_1' => 'Atom', 'seed_0_2' => 'Strahlung'
		];
	}

	/** @return array<string,string> */
	private function server(): array {
		return [
			'REQUEST_METHOD' => 'POST', 'HTTP_SEC_FETCH_SITE' => 'same-origin',
			'CONTENT_TYPE' => 'application/x-www-form-urlencoded; charset=UTF-8'
		];
	}

	public function testKeywordDiscoveryUsesOneAuthorizedAgentCall(): void {
		[$result, $status] = $this->callApi($this->server(), ['action' => 'keywords'], AuthorizationDecision::allow(), true, true);
		self::assertTrue($result['ok']);
		self::assertCount(100, $result['keywords']);
		self::assertSame(4, $result['pairs_count']);
		self::assertSame(200, $status);
	}

	public function testCardGenerationUsesOneAuthorizedAgentCallForOneTriple(): void {
		[$result, $status] = $this->callApi($this->server(), $this->validCardsForm(), AuthorizationDecision::allow(), true, true);
		self::assertTrue($result['ok']);
		self::assertSame(1, $result['pairs_count']);
		self::assertCount(1, $result['pairs']);
		self::assertSame(200, $status);
	}

	public function testNonSameOriginAndNonFormRequestsNeverExecuteAnAgent(): void {
		foreach ([
			['HTTP_SEC_FETCH_SITE' => 'cross-site'],
			['HTTP_SEC_FETCH_SITE' => 'same-site'],
			['HTTP_SEC_FETCH_SITE' => 'none'],
			['HTTP_SEC_FETCH_SITE' => null],
			['CONTENT_TYPE' => 'application/json'],
			['CONTENT_TYPE' => 'multipart/form-data'],
			['CONTENT_TYPE' => null]
		] as $override) {
			[$result, $status] = $this->callApi(array_replace($this->server(), $override),
				['action' => 'keywords'], AuthorizationDecision::allow(), true, false);
			self::assertSame(['ok' => false, 'error' => 'Invalid'], $result);
			self::assertSame(403, $status);
		}
	}

	public function testGetAndUnknownActionCannotExecuteAnAgent(): void {
		[$result, $status] = $this->callApi(array_replace($this->server(), ['REQUEST_METHOD' => 'GET']),
			['action' => 'keywords'], AuthorizationDecision::allow(), true, false);
		self::assertFalse($result['ok']);
		self::assertSame(405, $status);
		[$result, $status] = $this->callApi($this->server(), ['action' => 'wrong'], AuthorizationDecision::allow(), true, false);
		self::assertFalse($result['ok']);
		self::assertSame(422, $status);
	}

	public function testPermissionOrIdentityDenialBlocksBothActions(): void {
		foreach ([AuthorizationDecision::deny(), AuthorizationDecision::indeterminate()] as $decision) {
			foreach (['keywords', 'cards'] as $action) {
				[$result, $status] = $this->callApi($this->server(), ['action' => $action], $decision, true, false);
				self::assertSame(['ok' => false, 'error' => 'Denied'], $result);
				self::assertSame(403, $status);
			}
		}
		[$result, $status] = $this->callApi($this->server(), ['action' => 'keywords'], AuthorizationDecision::allow(), false, false);
		self::assertSame(['ok' => false, 'error' => 'Invalid'], $result);
		self::assertSame(403, $status);
	}

	public function testMissingSeedFieldsNeverInvokeAgent(): void {
		[$result, $status] = $this->callApi($this->server(), ['action' => 'cards', 'exclude_count' => '0'],
			AuthorizationDecision::allow(), true, false);
		self::assertSame(['ok' => false, 'error' => 'Invalid'], $result);
		self::assertSame(422, $status);
	}

	/**
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $form
	 * @return array{0:array<string,mixed>,1:int}
	 */
	private function callApi(array $server, array $form, AuthorizationDecision $decision, bool $authenticated, bool $execute): array {
		$request = $this->createMock(IRequest::class);
		$request->method('server')->willReturnCallback(static fn(string $key, $default = null) => $server[$key] ?? $default);
		$request->method('post')->willReturnCallback(static fn(string $key, $default = null) => $form[$key] ?? $default);
		$request->expects(self::never())->method('getJsonBody');
		$view = $this->createMock(IMvcView::class);
		$view->method('getBricks')->willReturn([
			'access_denied' => 'Denied', 'invalid_request' => 'Invalid',
			'invalid_settings' => 'Bad settings', 'generation_failed' => 'Failed cards',
			'keywords_failed' => 'Failed keywords'
		]);
		$authorization = $this->createMock(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host-instance');
		$authorization->method('decide')->willReturnCallback(static function(AuthorizationRequest $request) use ($decision): AuthorizationDecision {
			self::assertSame(KnowledgeMemoryPlayFeature::getName(), $request->permission->permission);
			return $decision;
		});
		$users = $this->createMock(IUsermanager::class);
		$user = new User();
		$user->id = 73;
		$users->method('getUser')->willReturn($authenticated ? $user : null);
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn(['agent_id' => 'existing-agent', 'pairs' => 4, 'difficulty' => 'normal']);
		$agents = $this->createMock(IAgentConfigurationService::class);
		$agents->method('getAgent')->with('existing-agent', 'agent')->willReturn(['enabled' => true]);
		$execution = $this->createMock(IAgentExecutionService::class);
		$execution->expects($execute ? self::once() : self::never())->method('execute')->willReturnCallback(
			static function(AgentExecutionRequest $request): AgentExecutionResult {
				$prompt = $request->getInputs()['prompt'];
				if (str_contains($prompt, 'Aim for at least 100 terms.')) {
					$content = json_encode(array_map(static fn(int $n): string => 'Term' . $n, range(1, 100)), JSON_THROW_ON_ERROR);
				} else {
					$pairs = [[
						'seed_index' => 1,
						'question' => 'Which property has number 1?',
						'answer' => 'Term1',
						'source_title' => 'Document 1',
						'source_excerpt' => 'The cited document explicitly contains the term Term1 here.'
					]];
					$content = json_encode(['pairs' => $pairs], JSON_THROW_ON_ERROR);
				}
				return new AgentExecutionResult(['assistant' => ['message' => ['content' => $content]]]);
			}
		);
		$game = new KnowledgeMemoryGameService($agents, $execution, $settings, new KnowledgeMemoryPairParser());
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::never())->method('warning');
		$output = new KnowledgeMemoryGameOutput($request, $view, $authorization, $users, $game, $logger);
		http_response_code(200);
		$result = json_decode($output->getOutput('json'), true, 32, JSON_THROW_ON_ERROR);
		return [$result, http_response_code()];
	}
}
