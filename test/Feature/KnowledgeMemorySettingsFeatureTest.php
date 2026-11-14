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


namespace Base3\KnowledgeMemory\Test\Feature;

use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\KnowledgeMemory\Settings\KnowledgeMemorySettingsTab;
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
