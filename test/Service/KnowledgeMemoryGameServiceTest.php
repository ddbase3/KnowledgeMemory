<?php declare(strict_types=1);

namespace KnowledgeMemory\Test\Service;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;
use Base3\Settings\Api\ISettingsStore;
use KnowledgeMemory\Service\KnowledgeMemoryGameService;
use KnowledgeMemory\Service\KnowledgeMemoryPairParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KnowledgeMemoryGameServiceTest extends TestCase {

	private function service(IAgentExecutionService $execution): KnowledgeMemoryGameService {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->with('knowledge_memory', 'game')->willReturn([
			'agent_id' => 'course-knowledge', 'pairs' => 4, 'difficulty' => 'normal'
		]);
		$settings->expects(self::never())->method('set');
		$agents = $this->createMock(IAgentConfigurationService::class);
		$agents->method('getAgent')->with('course-knowledge', 'agent')->willReturn([
			'id' => 'course-knowledge', 'label' => 'Course Knowledge',
			'enabled' => true, 'agent_runtime' => 'missionbay', 'system_prompt' => 'Stored instructions must be cleared'
		]);
		return new KnowledgeMemoryGameService($agents, $execution, $settings, new KnowledgeMemoryPairParser());
	}

	public function testDiscoversFlatDocumentKeywordsWithEmptySystemPrompt(): void {
		$execution = $this->createMock(IAgentExecutionService::class);
		$execution->expects(self::once())->method('execute')->willReturnCallback(
			static function(AgentExecutionRequest $request): AgentExecutionResult {
				self::assertSame('course-knowledge', $request->getContext()['agent_id']);
				self::assertSame('agent', $request->getContext()['agent_config_group']);
				self::assertSame('missionbay', $request->getAgentConfiguration()['agent_runtime']);
				self::assertSame('', $request->getAgentConfiguration()['system_prompt']);
				self::assertSame('', $request->getInputs()['system']);
				self::assertStringContainsString('Aim for at least 100 terms.', $request->getInputs()['prompt']);
				return new AgentExecutionResult([
					'assistant' => ['message' => ['content' => json_encode(array_map(
						static fn(int $term): string => 'Begriff-' . $term, range(1, 100)
					), JSON_THROW_ON_ERROR)]]
				]);
			}
		);
		$result = $this->service($execution)->discoverKeywords();
		self::assertCount(100, $result['keywords']);
		self::assertSame(4, $result['pairs_count']);
	}

	public function testGeneratesOnePairForExactlyOneTripleInOneAgentCall(): void {
		$execution = $this->createMock(IAgentExecutionService::class);
		$execution->expects(self::once())->method('execute')->willReturnCallback(
			static function(AgentExecutionRequest $request): AgentExecutionResult {
				self::assertSame('course-knowledge', $request->getContext()['agent_id']);
				self::assertSame('agent', $request->getContext()['agent_config_group']);
				self::assertSame('', $request->getInputs()['system']);
				self::assertSame('', $request->getAgentConfiguration()['system_prompt']);
				self::assertStringContainsString('1: Physik / Atom / Strahlung', $request->getInputs()['prompt']);
				self::assertStringNotContainsString('2: ', $request->getInputs()['prompt']);
				self::assertStringContainsString('AlteAntwort', $request->getInputs()['prompt']);
				self::assertStringContainsString('thematic guidance, not strict requirements', $request->getInputs()['prompt']);
				self::assertStringContainsString('You MUST return a populated question-and-answer pair for every triple.', $request->getInputs()['prompt']);
				self::assertStringNotContainsString('report inability', $request->getInputs()['prompt']);
				return new AgentExecutionResult([
					'assistant' => ['message' => ['content' => json_encode(['pairs' => [[
						'seed_index' => 1,
						'question' => 'Welche Eigenschaft besitzen instabile Kerne?',
						'answer' => 'Radioaktivitaet',
						'source_title' => 'Dokument',
						'source_excerpt' => 'Instabile Kerne zeigen die Eigenschaft Radioaktivitaet.'
					]]], JSON_THROW_ON_ERROR)]]
				]);
			}
		);
		$result = $this->service($execution)->generate([['Physik', 'Atom', 'Strahlung']], ['AlteAntwort']);
		self::assertCount(1, $result['pairs']);
		self::assertSame('Radioaktivitaet', $result['pairs'][0]['answer']);
		self::assertSame(1, $result['pairs_count']);
		self::assertArrayNotHasKey('seed_index', $result['pairs'][0]);
	}

	public function testExcludedAnswersAreLimitedToTheCurrentRoundAndHarmlessLongValuesDoNotBreakGeneration(): void {
		$execution = $this->createMock(IAgentExecutionService::class);
		$execution->expects(self::once())->method('execute')->willReturnCallback(
			static function(AgentExecutionRequest $request): AgentExecutionResult {
				self::assertSame('', $request->getInputs()['system']);
				self::assertStringContainsString('AktuelleAntwort', $request->getInputs()['prompt']);
				self::assertStringNotContainsString(str_repeat('X', 241), $request->getInputs()['prompt']);
				return new AgentExecutionResult(['assistant' => ['message' => ['content' => json_encode([
					'pairs' => [['question' => 'Welche Eigenschaft?', 'answer' => 'Radioaktivitaet']]
				], JSON_THROW_ON_ERROR)]]]);
			}
		);
		$result = $this->service($execution)->generate([['Physik', 'Atom', 'Strahlung']], ['AktuelleAntwort', str_repeat('X', 241)]);
		self::assertSame('Radioaktivitaet', $result['pairs'][0]['answer']);
	}

	public function testRejectsMultipleTriplesBeforeCallingTheAgent(): void {
		$execution = $this->createMock(IAgentExecutionService::class);
		$execution->expects(self::never())->method('execute');
		$this->expectException(RuntimeException::class);
		$this->service($execution)->generate([
			['Physik', 'Atom', 'Strahlung'], ['Wasser', 'Licht', 'Wellen']
		]);
	}

	public function testRejectsRepeatedTermsWithinOneTriple(): void {
		$execution = $this->createMock(IAgentExecutionService::class);
		$execution->expects(self::never())->method('execute');
		$this->expectException(RuntimeException::class);
		$this->service($execution)->generate([['Physik', 'Physik', 'Strahlung']]);
	}

	public function testRejectsMissingConfiguredAgent(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn([]);
		$agents = $this->createMock(IAgentConfigurationService::class);
		$execution = $this->createMock(IAgentExecutionService::class);
		$execution->expects(self::never())->method('execute');
		$service = new KnowledgeMemoryGameService($agents, $execution, $settings, new KnowledgeMemoryPairParser());
		$this->expectException(RuntimeException::class);
		$service->discoverKeywords();
	}
}
