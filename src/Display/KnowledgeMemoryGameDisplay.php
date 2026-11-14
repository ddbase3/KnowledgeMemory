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


namespace Base3\KnowledgeMemory\Display;

use Base3\CoreFoundation\Api\IAssetResolver;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IDisplay;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\KnowledgeMemory\Feature\KnowledgeMemoryPlayFeature;
use Base3\KnowledgeMemory\Output\KnowledgeMemoryGameOutput;
use Throwable;

final class KnowledgeMemoryGameDisplay implements IDisplay {

	public function __construct(
		private readonly IMvcView $view,
		private readonly IAssetResolver $assets,
		private readonly ILinkTargetService $links,
		private readonly IAuthorizationService $authorization,
		private readonly IUsermanager $users,
		private readonly IClassMap $classMap
	) {}

	public static function getName(): string {
		return 'knowledgememorygamedisplay';
	}

	public function setData($data): void {}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out !== 'html') {
			return '';
		}

		$this->view->setPath(dirname(__DIR__, 2));
		$this->view->loadBricks('Display');
		$texts = $this->view->getBricks('knowledge_memory');
		if ($texts === null) {
			throw new \LogicException('Knowledge Memory translations are missing.');
		}
		if (!$this->isAllowed()) {
			return '<p role="alert">' . htmlspecialchars($texts['access_denied'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
		}

		$user = $this->users->getUser();
		if ($user === null || trim((string)$user->id) === '') {
			return '<p role="alert">' . htmlspecialchars($texts['access_denied'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
		}

		$settingsDisplay = $this->classMap->getInstanceByInterfaceName(IDisplay::class, KnowledgeMemorySettingsDisplay::getName());
		if (!$settingsDisplay instanceof KnowledgeMemorySettingsDisplay) {
			throw new \LogicException('Knowledge Memory settings display is unavailable.');
		}
		$settingsHtml = $settingsDisplay->canConfigure() ? $settingsDisplay->getOutput('html') : '';

		$this->view->setTemplate('Display/KnowledgeMemoryGameDisplay.php');
		$this->view->assign('settings_html', $settingsHtml);
		$this->view->assign('texts', $texts);
		$this->view->assign('root_id', 'km-' . bin2hex(random_bytes(8)));
		$this->view->assign('css_url', $this->assets->resolve('plugin/KnowledgeMemory/assets/knowledge-memory/game.css'));
		$this->view->assign('js_url', $this->assets->resolve('plugin/KnowledgeMemory/assets/knowledge-memory/game.js'));
		$this->view->assign('api_url', $this->links->getLink([
			'name' => KnowledgeMemoryGameOutput::getName(),
			'out' => 'json'
		]));
		return $this->view->loadTemplate();
	}

	private function isAllowed(): bool {
		try {
			return $this->authorization->decide(new AuthorizationRequest(
				$this->authorization->getDefaultAuthority(),
				Permission::for('feature', KnowledgeMemoryPlayFeature::getName())
			))->isAllowed();
		} catch (Throwable) {
			return false;
		}
	}
}
