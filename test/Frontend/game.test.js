'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
require('../../assets/knowledge-memory/game.js');
const {GameState, summarize} = globalThis.KnowledgeMemoryGame;

function sampleGame() {
	return new GameState([
		{question: 'Q1', answer: 'A1'},
		{question: 'Q2', answer: 'A2'}
	]);
}

test('matching cards are retained and scored', () => {
	const game = sampleGame();
	const first = game.cards.findIndex(card => card.pair === 0 && card.kind === 'question');
	const second = game.cards.findIndex(card => card.pair === 0 && card.kind === 'answer');
	assert.equal(game.flip(first), 'first');
	assert.equal(game.flip(second), 'match');
	assert.equal(game.score, 100);
	assert.equal(game.matches, 1);
	assert.equal(game.cards[first].matched, true);
	assert.equal(game.cards[second].matched, true);
});

test('wrong pairs lock input, subtract points, and turn back', () => {
	const game = sampleGame();
	const first = game.cards.findIndex(card => card.pair === 0);
	const wrong = game.cards.findIndex(card => card.pair === 1);
	assert.equal(game.flip(first), 'first');
	assert.equal(game.flip(wrong), 'miss');
	assert.equal(game.flip(game.cards.findIndex(card => !card.exposed)), 'ignored');
	assert.equal(game.score, 0);
	game.hideMismatch();
	assert.equal(game.cards[first].exposed, false);
	assert.equal(game.cards[wrong].exposed, false);
	assert.equal(game.locked, false);
});

test('game finishes only when all pairs are matched', () => {
	const game = sampleGame();
	for (const pair of [0, 1]) {
		const question = game.cards.findIndex(card => card.pair === pair && card.kind === 'question');
		const answer = game.cards.findIndex(card => card.pair === pair && card.kind === 'answer');
		game.flip(question);
		const response = game.flip(answer);
		assert.equal(response, pair === 1 ? 'complete' : 'match');
	}
	assert.equal(game.completed, true);
	assert.equal(game.score, 210);
	assert.equal(game.flip(0), 'ignored');
});


test('combo awards are bounded, and a mismatch breaks the combo', () => {
	const pairs = Array.from({length: 8}, (_, i) => ({question: 'Q' + i, answer: 'A' + i}));
	const game = new GameState(pairs);
	for (let pair = 0; pair < 6; pair++) {
		const question = game.cards.findIndex(card => card.pair === pair && card.kind === 'question');
		const answer = game.cards.findIndex(card => card.pair === pair && card.kind === 'answer');
		assert.equal(game.flip(question), 'first');
		assert.equal(game.flip(answer), 'match');
	}
	assert.equal(game.score, 100 + 110 + 120 + 130 + 140 + 140);
	const first = game.cards.findIndex(card => card.pair === 6 && card.kind === 'question');
	const other = game.cards.findIndex(card => card.pair === 7 && card.kind === 'answer');
	assert.equal(game.flip(first), 'first');
	assert.equal(game.flip(other), 'miss');
	assert.equal(game.streak, 0);
	assert.equal(game.score, 720);
	game.hideMismatch();
	const answer = game.cards.findIndex(card => card.pair === 6 && card.kind === 'answer');
	game.flip(first);
	assert.equal(game.flip(answer), 'match');
	assert.equal(game.score, 820);
});

test('score summary counts actual attempts and does not add new game state', () => {
	const game = sampleGame();
	const q0 = game.cards.findIndex(card => card.pair === 0 && card.kind === 'question');
	const q1 = game.cards.findIndex(card => card.pair === 1 && card.kind === 'question');
	const a0 = game.cards.findIndex(card => card.pair === 0 && card.kind === 'answer');
	const a1 = game.cards.findIndex(card => card.pair === 1 && card.kind === 'answer');
	game.flip(q0);
	assert.equal(game.flip(q1), 'miss');
	game.hideMismatch();
	game.flip(q0);
	assert.equal(game.flip(a0), 'match');
	game.flip(q1);
	assert.equal(game.flip(a1), 'complete');
	assert.deepEqual(summarize(game), {
		accuracy: 67,
		stars: 2,
		attempts: 3,
		misses: 1,
		score: 210,
		matches: 2,
		total: 2
	});
	assert.throws(() => summarize({}), TypeError);
});

test('finished games reject further input and valid card state stays immutable', () => {
	const game = sampleGame();
	assert.equal(game.flip(-1), 'ignored');
	assert.equal(game.flip(999), 'ignored');
	for (const pair of [0, 1]) {
		const question = game.cards.findIndex(card => card.pair === pair && card.kind === 'question');
		const answer = game.cards.findIndex(card => card.pair === pair && card.kind === 'answer');
		assert.equal(game.flip(question), 'first');
		assert.equal(game.flip(question), 'ignored');
		game.flip(answer);
	}
	const scoreBefore = game.score;
	assert.equal(game.flip(0), 'ignored');
	assert.equal(game.score, scoreBefore);
});
