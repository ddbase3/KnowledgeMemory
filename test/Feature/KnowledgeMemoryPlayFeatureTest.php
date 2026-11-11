<?php declare(strict_types=1);

namespace KnowledgeMemory\Test\Feature;

use Base3\Translation\Api\ITranslation;
use KnowledgeMemory\Feature\KnowledgeMemoryPlayFeature;
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
