<?php declare(strict_types=1);

namespace KnowledgeMemory\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use Base3\Usermanager\Api\IUsermanager;
use Base3\Usermanager\Permission;
use KnowledgeMemory\Service\KnowledgeMemoryGameService;
use KnowledgeMemory\Settings\KnowledgeMemorySettingsTab;
use Throwable;

final class KnowledgeMemorySettingsDisplay implements IDisplay {

	public function __construct(
		private readonly IMvcView $view,
		private readonly IAssetResolver $assets,
		private readonly IRequest $request,
		private readonly ILinkTargetService $links,
		private readonly IAuthorizationService $authorization,
		private readonly IUsermanager $users,
		private readonly ISettingsStore $settingsStore,
		private readonly KnowledgeMemoryGameService $game
	) {}

	public static function getName(): string {
		return 'knowledgememorysettingsdisplay';
	}

	public function setData($data): void {}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->view->setPath(dirname(__DIR__, 2));
		$this->view->loadBricks('Display');
		$texts = $this->view->getBricks('knowledge_memory');
		if ($texts === null) {
			throw new \LogicException('Knowledge Memory translations are missing.');
		}
		if (!$this->canConfigure()) {
			if ($out === 'json') {
				return $this->json(['ok' => false, 'error' => $texts['access_denied']], 403);
			}
			return '<p role="alert">' . htmlspecialchars($texts['access_denied'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
		}

		$user = $this->users->getUser();
		if ($user === null || trim((string)$user->id) === '') {
			if ($out === 'json') {
				return $this->json(['ok' => false, 'error' => $texts['access_denied']], 403);
			}
			return '<p role="alert">' . htmlspecialchars($texts['access_denied'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
		}

		if ($out === 'json') {
			if ($this->request->server('REQUEST_METHOD', '') !== 'POST') {
				return $this->json(['ok' => false, 'error' => $texts['invalid_request']], 405);
			}
			$contentType = $this->request->server('CONTENT_TYPE', '');
			$isFormPost = is_string($contentType)
				&& strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/x-www-form-urlencoded';
			if ($this->request->server('HTTP_SEC_FETCH_SITE', '') !== 'same-origin' || !$isFormPost) {
				return $this->json(['ok' => false, 'error' => $texts['invalid_request']], 403);
			}
			try {
				$agentId = $this->request->post('agent_id');
				$pairsValue = $this->request->post('pairs');
				$difficulty = $this->request->post('difficulty');
				if (!is_string($agentId) || !is_string($pairsValue) || !ctype_digit($pairsValue) || !is_string($difficulty)) {
					return $this->json(['ok' => false, 'error' => $texts['invalid_settings']], 422);
				}
				$pairs = (int)$pairsValue;
				$this->game->validateSettings($agentId, $pairs, $difficulty);
				$this->settingsStore->set(KnowledgeMemoryGameService::SETTINGS_GROUP, KnowledgeMemoryGameService::SETTINGS_NAME, [
					'agent_id' => $agentId,
					'pairs' => $pairs,
					'difficulty' => $difficulty
				]);
				$this->settingsStore->save();
				return $this->json(['ok' => true, 'message' => $texts['saved']]);
			} catch (\RuntimeException) {
				return $this->json(['ok' => false, 'error' => $texts['invalid_settings']], 422);
			} catch (Throwable) {
				return $this->json(['ok' => false, 'error' => $texts['save_failed']], 503);
			}
		}

		if ($out !== 'html') {
			return '';
		}
		$this->view->setTemplate('Display/KnowledgeMemorySettingsDisplay.php');
		$this->view->assign('texts', $texts);
		$this->view->assign('css_url', $this->assets->resolve('plugin/KnowledgeMemory/assets/knowledge-memory/game.css'));
		$this->view->assign('js_url', $this->assets->resolve('plugin/KnowledgeMemory/assets/knowledge-memory/game.js'));
		$this->view->assign('game_url', $this->links->getLink([
			'name' => KnowledgeMemoryGameDisplay::getName(),
			'out' => 'html'
		]));
		$this->view->assign('root_id', 'km-settings-' . bin2hex(random_bytes(8)));
		$this->view->assign('settings', $this->game->getSettings());
		$this->view->assign('agents', $this->game->getAvailableAgents());
		$this->view->assign('api_url', $this->links->getLink([
			'name' => self::getName(),
			'out' => 'json'
		]));
		return $this->view->loadTemplate();
	}

	public function canConfigure(): bool {
		try {
			return $this->authorization->decide(new AuthorizationRequest(
				$this->authorization->getDefaultAuthority(),
				Permission::for('feature', KnowledgeMemorySettingsTab::getName())
			))->isAllowed();
		} catch (Throwable) {
			return false;
		}
	}

	/** @param array<string,mixed> $body */
	private function json(array $body, int $status = 200): string {
		http_response_code($status);
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=UTF-8');
			header('Cache-Control: no-store');
		}
		return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
	}
}
