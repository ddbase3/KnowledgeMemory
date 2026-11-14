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


namespace Base3\KnowledgeMemory\Test\Fixture;

final class TemplateViewFixture {

	public array $_ = [];

	public function render(string $file): string {
		ob_start();
		include $file;
		return (string)ob_get_clean();
	}
}
