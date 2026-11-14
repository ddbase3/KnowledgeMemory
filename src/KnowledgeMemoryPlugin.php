<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of KnowledgeMemory for BASE3 Framework.
 *
 * KnowledgeMemory provides document-grounded matching games.
 * It manages game settings, pairs and play sessions.
 * Agent execution uses existing BASE3 and AssistantFoundation services.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0-or-later
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 ***********************************************************************/


namespace Base3\KnowledgeMemory;

use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\KnowledgeMemory\Service\KnowledgeMemoryGameService;
use Base3\KnowledgeMemory\Service\KnowledgeMemoryPairParser;

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
