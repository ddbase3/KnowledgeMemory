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


namespace Base3\KnowledgeMemory\Test;

use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Closure;
use Base3\KnowledgeMemory\KnowledgeMemoryPlugin;
use Base3\KnowledgeMemory\Service\KnowledgeMemoryGameService;
use PHPUnit\Framework\TestCase;

final class KnowledgeMemoryPluginTest extends TestCase {

	public function testRegistersExistingSharedServiceLazilyWithNoOverwrite(): void {
		$agents = $this->createMock(IAgentConfigurationService::class);
		$execution = $this->createMock(IAgentExecutionService::class);
		$settings = $this->createMock(ISettingsStore::class);
		$container = $this->createMock(IContainer::class);
		$container->method('get')->willReturnCallback(static fn(string $type) => match ($type) {
			IAgentConfigurationService::class => $agents,
			IAgentExecutionService::class => $execution,
			ISettingsStore::class => $settings
		});
		$factory = null;
		$container->expects(self::once())->method('set')->willReturnCallback(
			static function(string $name, $definition, int $flags) use (&$factory, $container): IContainer {
				self::assertSame(KnowledgeMemoryGameService::class, $name);
				self::assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $flags);
				self::assertInstanceOf(Closure::class, $definition);
				$factory = $definition;
				return $container;
			}
		);
		self::assertSame('knowledgememoryplugin', KnowledgeMemoryPlugin::getName());
		$plugin = new KnowledgeMemoryPlugin($container);
		$plugin->init();
		self::assertInstanceOf(KnowledgeMemoryGameService::class, $factory($container));
	}
}
