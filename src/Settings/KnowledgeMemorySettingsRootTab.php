<?php declare(strict_types=1);

namespace KnowledgeMemory\Settings;

use UiFoundation\Settings\AbstractSettingsTab;

final class KnowledgeMemorySettingsRootTab extends AbstractSettingsTab {

	public function getOrder(): int {
		return 90;
	}
}
