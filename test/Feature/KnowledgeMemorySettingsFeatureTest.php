<?php declare(strict_types=1);

namespace KnowledgeMemory\Test\Feature;

use Base3\Translation\Api\ITranslation;
use KnowledgeMemory\Settings\KnowledgeMemorySettingsTab;
use PHPUnit\Framework\TestCase;

final class KnowledgeMemorySettingsFeatureTest extends TestCase {

	public function testPreservedSettingsFeatureHasNoSecondAdminTabAndKeepsItsPermission(): void {
		$translation = $this->createMock(ITranslation::class);
		$translation->expects(self::exactly(2))->method('translate')->willReturnCallback(
			static function(string $set, string $section, string $key): string {
				self::assertSame('Settings', $set);
				self::assertSame(KnowledgeMemorySettingsTab::getName(), $section);
				return $key;
			}
		);
		$feature = new KnowledgeMemorySettingsTab($translation);
		self::assertSame('knowledgememorysettingstab', KnowledgeMemorySettingsTab::getName());
		self::assertSame('label', $feature->getLabel());
		self::assertSame('description', $feature->getDescription());
		self::assertSame(['group' => 'administration'], $feature->getDefaultAudience());
		$permissions = $feature->getPermissions();
		self::assertCount(1, $permissions);
		self::assertSame('feature', $permissions[0]->scope);
		self::assertSame(KnowledgeMemorySettingsTab::getName(), $permissions[0]->permission);
	}
}
