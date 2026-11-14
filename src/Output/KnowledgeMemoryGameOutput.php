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


namespace Base3\KnowledgeMemory\Output;

use Base3\CoreFoundation\Api\IOutput;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Usermanager\Api\IUsermanager;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\KnowledgeMemory\Feature\KnowledgeMemoryPlayFeature;
use Base3\KnowledgeMemory\Service\KnowledgeMemoryGameService;
use Throwable;

/** Existing single game AJAX endpoint: discover keywords, then create cards. */
final class KnowledgeMemoryGameOutput implements IOutput {

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAuthorizationService $authorization,
		private readonly IUsermanager $users,
		private readonly KnowledgeMemoryGameService $game,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'knowledgememorygameoutput';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out !== 'json') {
			return '';
		}
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=UTF-8');
			header('Cache-Control: no-store');
		}

		$this->view->setPath(dirname(__DIR__, 2));
		$this->view->loadBricks('Display');
		$texts = $this->view->getBricks('knowledge_memory');
		if ($texts === null) {
			throw new \LogicException('Knowledge Memory translations are missing.');
		}

		if ($this->request->server('REQUEST_METHOD', '') !== 'POST') {
			return $this->error($texts['invalid_request'], 405);
		}
		if (!$this->isAllowed()) {
			return $this->error($texts['access_denied'], 403);
		}
		$user = $this->users->getUser();
		$contentType = $this->request->server('CONTENT_TYPE', '');
		$isFormPost = is_string($contentType)
			&& strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/x-www-form-urlencoded';
		if ($user === null || trim((string)$user->id) === ''
			|| $this->request->server('HTTP_SEC_FETCH_SITE', '') !== 'same-origin' || !$isFormPost) {
			return $this->error($texts['invalid_request'], 403);
		}

		$action = $this->request->post('action');
		if ($action !== 'keywords' && $action !== 'cards') {
			return $this->error($texts['invalid_request'], 422);
		}
		try {
			if ($action === 'keywords') {
				return $this->success($this->game->discoverKeywords());
			}

			$triple = [];
			for ($term = 0; $term < 3; $term++) {
				$value = $this->request->post('seed_0_' . $term);
				if (!is_string($value)) {
					return $this->error($texts['invalid_request'], 422);
				}
				$triple[] = $value;
			}
			$excludeCount = $this->request->post('exclude_count');
			if (!is_string($excludeCount) || !ctype_digit($excludeCount) || (int)$excludeCount > 36) {
				return $this->error($texts['invalid_request'], 422);
			}
			$excluded = [];
			for ($index = 0; $index < (int)$excludeCount; $index++) {
				$value = $this->request->post('exclude_' . $index);
				if (!is_string($value)) {
					return $this->error($texts['invalid_request'], 422);
				}
				$excluded[] = $value;
			}
			return $this->success($this->game->generate([$triple], $excluded));
		} catch (Throwable $exception) {
			$this->logger->warning('Knowledge Memory agent generation failed.', [
				'stage' => $action,
				'error_type' => get_class($exception)
			]);
			return $this->error($action === 'keywords' ? $texts['keywords_failed'] : $texts['generation_failed'], 503);
		}
	}

	/** @param array<string,mixed> $data */
	private function success(array $data): string {
		return json_encode(['ok' => true] + $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
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

	private function error(string $message, int $status): string {
		http_response_code($status);
		return json_encode(['ok' => false, 'error' => $message], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
	}
}
