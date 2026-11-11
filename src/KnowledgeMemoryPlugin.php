<?php declare(strict_types=1);

namespace KnowledgeMemory;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Settings\Api\ISettingsStore;
use KnowledgeMemory\Service\KnowledgeMemoryGameService;
use KnowledgeMemory\Service\KnowledgeMemoryPairParser;

final class KnowledgeMemoryPlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string {
		return 'knowledgememoryplugin';
	}

	public function init(): void {
		$this->container->set(
			KnowledgeMemoryGameService::class,
			fn(IContainer $c) => new KnowledgeMemoryGameService(
				$c->get(IAgentConfigurationService::class),
				$c->get(IAgentExecutionService::class),
				$c->get(ISettingsStore::class),
				new KnowledgeMemoryPairParser()
			),
			IContainer::SHARED | IContainer::NOOVERWRITE
		);
	}
}
