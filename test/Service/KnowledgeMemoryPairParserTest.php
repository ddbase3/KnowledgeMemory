<?php declare(strict_types=1);

namespace KnowledgeMemory\Test\Service;

use KnowledgeMemory\Service\KnowledgeMemoryPairParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KnowledgeMemoryPairParserTest extends TestCase {

	/** @return array<string,mixed> */
	private function pair(string $question, string $answer, string $excerpt): array {
		return [
			'seed_index' => 1,
			'question' => $question,
			'answer' => $answer,
			'source_title' => 'Dokument',
			'source_excerpt' => $excerpt
		];
	}

	public function testAcceptsSingleStructuredPair(): void {
		$payload = json_encode(['pairs' => [
			$this->pair('Welchen Farbton hat der Himmel?', 'blau', 'Der Himmel erscheint am Tag blau.')
		]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		$result = (new KnowledgeMemoryPairParser())->parse($payload, 1);
		self::assertCount(1, $result);
		self::assertSame('blau', $result[0]['answer']);
		self::assertArrayNotHasKey('seed_index', $result[0]);
	}

	public function testDoesNotRejectAnOtherwiseValidPairBecauseOfASharedWord(): void {
		$payload = json_encode(['pairs' => [
			$this->pair('Welche Leistung gibt P=UI an?', 'Elektrische Leistung', 'Die Leistung ist P=UI.')
		]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		$result = (new KnowledgeMemoryPairParser())->parse($payload, 1);
		self::assertSame('Elektrische Leistung', $result[0]['answer']);
	}

	public function testAcceptsFencedJsonResponse(): void {
		$payload = json_encode(['pairs' => [
			$this->pair('Wie heisst die Eigenschaft?', 'Radioaktivitaet', 'Instabile Kerne sind radioaktiv.')
		]], JSON_THROW_ON_ERROR);
		self::assertCount(1, (new KnowledgeMemoryPairParser())->parse("```json\n" . $payload . "\n```", 1));
	}

	public function testUsesTheFirstPopulatedPairEvenWhenTheAgentReturnsExtras(): void {
		$result = (new KnowledgeMemoryPairParser())->parse(json_encode(['pairs' => [
			['seed_index' => 1, 'question' => '', 'answer' => ''],
			$this->pair('Welche Einheit beschreibt Energie?', 'Elektronenvolt', 'Die Einheit ist Elektronenvolt.'),
			$this->pair('Welche Kraft?', 'Lorentzkraft', 'Die Lorentzkraft wirkt im Magnetfeld.')
		]], JSON_THROW_ON_ERROR), 1);
		self::assertCount(1, $result);
		self::assertSame('Elektronenvolt', $result[0]['answer']);
	}

	public function testDoesNotRejectUsableCardsWithoutSourceTextOrAnIndex(): void {
		$result = (new KnowledgeMemoryPairParser())->parse(json_encode(['pairs' => [
			['question' => 'Wie heißt der Wert?', 'answer' => 'R = ρl/A']
		]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 1);
		self::assertSame('R = ρl/A', $result[0]['answer']);
		self::assertSame('', $result[0]['source_title']);
		self::assertSame('', $result[0]['source_excerpt']);
	}

	public function testRejectsMissingQuestionOrAnswer(): void {
		$this->expectException(RuntimeException::class);
		(new KnowledgeMemoryPairParser())->parse(json_encode(['pairs' => [
			$this->pair('', 'Answer', 'Some text')
		]], JSON_THROW_ON_ERROR), 1);
	}

	public function testRejectsMalformedJson(): void {
		$this->expectException(RuntimeException::class);
		(new KnowledgeMemoryPairParser())->parse('Not a JSON object', 1);
	}

	public function testRemovesBidirectionalFormattingControlWithoutDroppingCard(): void {
		$result = (new KnowledgeMemoryPairParser())->parse(json_encode(['pairs' => [
			$this->pair("Welche Stadt?\u{202E}", 'Berlin', 'Die Quelle nennt Berlin.')
		]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 1);
		self::assertSame('Welche Stadt?', $result[0]['question']);
	}

	public function testAcceptsFlatKeywordListWithoutGrouping(): void {
		$terms = ['Atom', 'Kern', 'Strahlung', 'Nuklid', 'Atom', 'Ion'];
		$result = (new KnowledgeMemoryPairParser())->parseKeywords(json_encode($terms, JSON_THROW_ON_ERROR));
		self::assertSame(['Atom', 'Kern', 'Strahlung', 'Nuklid', 'Ion'], $result);
	}

	public function testRejectsGroupedKeywordObjects(): void {
		$this->expectException(RuntimeException::class);
		(new KnowledgeMemoryPairParser())->parseKeywords('{"groups":[["Atom","Kern","Ion"]]}');
	}

	public function testRejectsKeywordsWhenFewerThanThreeTermsAreAvailable(): void {
		$this->expectException(RuntimeException::class);
		(new KnowledgeMemoryPairParser())->parseKeywords('["Atom","Ion"]');
	}
}
