<?php declare(strict_types=1);

namespace KnowledgeMemory\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use KnowledgeMemory\Feature\KnowledgeMemoryPlayFeature;
use KnowledgeMemory\Output\KnowledgeMemoryGameOutput;
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
