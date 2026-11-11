<?php declare(strict_types=1);

namespace KnowledgeMemory\Test;

use AssistantFoundation\Api\IAgentConfigurationService;
use AssistantFoundation\Api\IAgentExecutionService;
use Base3\Api\IContainer;
use Base3\Settings\Api\ISettingsStore;
use Closure;
use KnowledgeMemory\KnowledgeMemoryPlugin;
use KnowledgeMemory\Service\KnowledgeMemoryGameService;
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
