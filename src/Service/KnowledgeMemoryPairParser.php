<?php declare(strict_types=1);

namespace KnowledgeMemory\Service;

use JsonException;
use RuntimeException;

/** Validates model output without asserting independent verification of citations. */
final class KnowledgeMemoryPairParser {

	/**
	 * @param string $content Raw terminal assistant content.
	 * @return list<string> Distinct keywords from the agent's JSON list.
	 * @throws RuntimeException If no usable three-term seed can be assembled.
	 */
	public function parseKeywords(string $content): array {
		$payload = $this->decode($content, 24000);
		if (!is_array($payload) || !array_is_list($payload)) {
			throw new RuntimeException('Agent returned an invalid keyword list.');
		}
		$keywords = [];
		$seen = [];
		foreach ($payload as $term) {
			if (!is_string($term) || !$this->isValidKeyword($term)) {
				continue;
			}
			$term = trim($term);
			$key = strtolower($term);
			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$keywords[] = $term;
			}
		}
		if (count($keywords) < 3) {
			throw new RuntimeException('The agent returned fewer than three usable keywords.');
		}
		return $keywords;
	}

	/** True only for short, human-readable topic terms. Not a prompt or free-text field. */
	public function isValidKeyword(string $term): bool {
		$trimmed = trim($term);
		return strlen($term) < 256 && $this->characters($trimmed) >= 2
			&& $this->characters($trimmed) <= 48
			&& preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\p{Zs}.+&()\/\-]*$/uD', $trimmed) === 1
			&& count(preg_split('/\s+/u', $trimmed) ?: []) <= 3;
	}

	/**
	 * Read one populated Q&A pair from the single-seed agent response.
	 * Duplicate checking belongs to the browser round loop, after each response.
	 * @param string $content Raw terminal assistant content.
	 * @param int $expectedPairs Must be 1 for a single seed triple.
	 * @return list<array{question:string,answer:string,source_title:string,source_excerpt:string}>
	 * @throws RuntimeException If the response has no populated question and answer.
	 */
	public function parse(string $content, int $expectedPairs): array {
		if ($expectedPairs !== 1) {
			throw new RuntimeException('One keyword combination is required per request.');
		}
		$payload = $this->decode($content, 128000);
		if (!is_array($payload) || !isset($payload['pairs']) || !is_array($payload['pairs'])
			|| !array_is_list($payload['pairs'])) {
			throw new RuntimeException('Agent returned no structured pairs.');
		}
		foreach ($payload['pairs'] as $item) {
			if (!is_array($item) || !is_string($item['question'] ?? null)
				|| !is_string($item['answer'] ?? null)) {
				continue;
			}
			$question = trim($item['question']);
			$answer = trim($item['answer']);
			if ($question === '' || $answer === '') continue;
			$clean = static fn(string $value): string => trim(preg_replace(
				'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u',
				'', $value
			) ?? $value);
			$question = $clean($question);
			$answer = $clean($answer);
			if ($question === '' || $answer === '') continue;
			return [[
				'question' => $question,
				'answer' => $answer,
				'source_title' => is_string($item['source_title'] ?? null) ? $clean($item['source_title']) : '',
				'source_excerpt' => is_string($item['source_excerpt'] ?? null) ? $clean($item['source_excerpt']) : ''
			]];
		}
		throw new RuntimeException('Agent returned no populated question and answer.');
	}

	/** @return mixed */
	private function decode(string $content, int $maxBytes): mixed {
		if (strlen($content) > $maxBytes) {
			throw new RuntimeException('Agent response exceeds the permitted size.');
		}
		$content = trim($content);
		if (str_starts_with($content, '```')) {
			$content = preg_replace('/^```(?:json)?\s*\n(.*?)\n```\s*$/si', '$1', $content) ?? $content;
		}
		try {
			return json_decode($content, true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new RuntimeException('Agent response is not valid JSON.', 0, $exception);
		}
	}

	private function characters(string $value): int {
		$count = preg_match_all('/./us', $value);
		if ($count === false) {
			throw new RuntimeException('Invalid Unicode card text.');
		}
		return $count;
	}

}
