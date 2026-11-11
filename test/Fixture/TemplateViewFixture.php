<?php declare(strict_types=1);

namespace KnowledgeMemory\Test\Fixture;

final class TemplateViewFixture {

	public array $_ = [];

	public function render(string $file): string {
		ob_start();
		include $file;
		return (string)ob_get_clean();
	}
}
