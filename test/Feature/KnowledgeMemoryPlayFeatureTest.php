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
use Base3\KnowledgeMemory\Feature\KnowledgeMemoryPlayFeature;
use PHPUnit\Framework\TestCase;

final class KnowledgeMemoryPlayFeatureTest extends TestCase {

	public function testFeatureIdentityTranslationAndDefaultAudience(): void {
		$translation = $this->createMock(ITranslation::class);
		$translation->expects(self::exactly(2))->method('translate')->willReturnCallback(
			static function(string $set, string $section, string $key): string {
				self::assertSame('Settings', $set);
				self::assertSame(KnowledgeMemoryPlayFeature::getName(), $section);
				return $key;
			}
		);
		$feature = new KnowledgeMemoryPlayFeature($translation);
		self::assertSame('knowledgememoryplayfeature', KnowledgeMemoryPlayFeature::getName());
		self::assertSame('label', $feature->getLabel());
		self::assertSame('description', $feature->getDescription());
		self::assertSame(['group' => 'administration'], $feature->getDefaultAudience());
		$permissions = $feature->getPermissions();
		self::assertCount(1, $permissions);
		self::assertSame('feature', $permissions[0]->scope);
		self::assertSame(KnowledgeMemoryPlayFeature::getName(), $permissions[0]->permission);
	}
}
