'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
	constructor(tag = 'div') {
		this.tagName = tag;
		this.children = [];
		this.listeners = {};
		this.dataset = {};
		this.attributes = {};
		this.style = {setProperty: (key, value) => {this.style[key] = value;}};
		this.classList = {add() {}, remove() {}, toggle() {}};
		this.textContent = '';
		this.hidden = false;
	}
	addEventListener(event, callback) {this.listeners[event] = callback;}
	append(...children) {
		for (const child of children) {
			child.parentElement = this;
			this.children.push(child);
		}
	}
	replaceChildren(...children) {
		for (const child of this.children) child.parentElement = null;
		this.children = [];
		this.append(...children);
	}
	remove() {
		if (!this.parentElement) return;
		this.parentElement.children = this.parentElement.children.filter(child => child !== this);
		this.parentElement = null;
	}
	setAttribute(name, value) {this.attributes[name] = value;}
	removeAttribute(name) {delete this.attributes[name];}
	getBoundingClientRect() {return {width: 750, top: 110};}
	querySelector(selector) {
		if (selector === '.km-card__content') {
			return this.children.flatMap(child => child.children.flatMap(inner => inner.children))
				.find(node => node.className === 'km-card__content') || new Element();
		}
		if (selector === '.km-card') return this.children[0] || null;
		return null;
	}
	focus() {this.focused = true;}
}

function fixture(respond, configuredPairs = 4, observeResize = false) {
	const selectors = [
		'[data-km-action="start"]', '[data-km-board]', '[data-km-empty]', '[data-km-empty-title]',
		'[data-km-feedback]', '[data-km-announcement]', '[data-km-result]', '[data-km-progress]',
		'[data-km-progress-fill]', '[data-km-percent]', '[data-km-stat="score"]',
		'[data-km-stat="matches"]', '[data-km-stat="time"]', '[data-km-score-pop]',
		'[data-km-combo]', '[data-km-layout-label]', '[data-km-action="fullscreen"]',
		'[data-km-seed-stage]', '[data-km-seed-heading]', '[data-km-seed-cloud]',
		'[data-km-seed-combinations]'
	];
	const nodes = new Map(selectors.map(selector => [selector, new Element()]));
	const root = new Element('section');
	root.id = 'test-root';
	root.querySelector = selector => nodes.get(selector) || null;
	const handlers = {};
	const createdSeedGroups = [];
	const scheduled = [];
	const resizeCallbacks = [];
	const doc = {
		readyState: 'loading',
		createElement: tag => {
			const element = new Element(tag);
			let className = '';
			Object.defineProperty(element, 'className', {
				get: () => className,
				set: value => {
					className = value;
					if (value === 'km-game__seed-group') createdSeedGroups.push(element);
				}
			});
			return element;
		},
		getElementById: id => id === 'test-root' ? root : null,
		addEventListener: (event, handler) => {handlers[event] = handler;},
		querySelectorAll: () => [],
		fullscreenElement: null
	};
	root.requestFullscreen = async () => {doc.fullscreenElement = root; handlers.fullscreenchange();};
	doc.exitFullscreen = async () => {doc.fullscreenElement = null; handlers.fullscreenchange();};
	const calls = [];
	const keywords = Array.from({length: 100}, (_, index) => 'Concept-' + index);
	let cardsSeen = 0;
	let active = 0;
	let maximumActive = 0;
	const context = {
		globalThis: null, document: doc, innerHeight: 870,
		Math, URLSearchParams, Date, JSON,
		clearTimeout() {}, clearInterval() {}, setInterval() {return 1;},
		setTimeout(callback) {scheduled.push(callback); return scheduled.length;}, addEventListener() {},
		fetch: async (url, options) => {
			const fields = options.body;
			const action = fields.get('action');
			const spotlight = nodes.get('[data-km-seed-combinations]').children;
			const progress = spotlight.find(node => node.className === 'km-game__seed-progress-track');
			const batch = spotlight.find(node => node.className === 'km-game__seed-batch');
			const activeSeeds = batch ? batch.children.map(group => group.children[1].children.map(node => node.textContent)) : [];
			calls.push({
				url, options, action,
				seed: [0, 1, 2].map(index => fields.get('seed_0_' + index)),
				progressAtRequest: progress ? progress.attributes['aria-valuenow'] : null,
				activeSeedsAtRequest: activeSeeds
			});
			if (action === 'keywords') return {ok: true, json: async () => ({ok: true, keywords, pairs_count: configuredPairs})};
			cardsSeen++;
			active++;
			maximumActive = Math.max(maximumActive, active);
			try {
				const result = await respond(cardsSeen, fields);
				if (result && result.error) {
					return {ok: false, status: result.status || 503, json: async () => ({ok: false, error: result.error})};
				}
				return {ok: true, json: async () => ({ok: true, pairs: result ? [result] : [], pairs_count: result ? 1 : 0})};
			} finally {
				active--;
			}
		}
	};
	if (observeResize) context.ResizeObserver = class {
		constructor(callback) {resizeCallbacks.push(callback);}
		observe() {}
	};
	context.globalThis = context;
	vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, '../../assets/knowledge-memory/game.js'), 'utf8'), context, {filename: path.resolve(__dirname, '../../assets/knowledge-memory/game.js')});
	const t = new Proxy({}, {get: (target, key) => String(key)});
	t.seed_progress = 'Pairs {done} / {total}';
	context.KnowledgeMemoryGame.mount('test-root', {texts: t, apiUrl: '/single-existing-endpoint'});
	return {nodes, calls, context, doc, createdSeedGroups, getMaximumActive: () => maximumActive,
		click: () => nodes.get('[data-km-action="start"]').listeners.click(),
		runNextTimeout: () => {const callback = scheduled.shift(); if (callback) callback();},
		triggerResize: () => {for (const callback of resizeCallbacks) callback();}};
}

function pair(question, answer) {
	return {question, answer, source_title: 'Document', source_excerpt: 'The document explains the topic.'};
}

test('three parallel card requests use the existing AJAX endpoint and progress counts accepted pairs', async () => {
	const game = fixture(index => pair('Question ' + index + '?', 'Answer-' + index));
	await game.click();
	assert.equal(game.calls.length, 5);
	assert.deepEqual(game.calls.map(call => call.action), ['keywords', 'cards', 'cards', 'cards', 'cards']);
	assert.ok(game.calls.every(call => call.url === '/single-existing-endpoint'));
	assert.equal(game.getMaximumActive(), 3);
	for (let index = 1; index <= 4; index++) {
		const request = game.calls[index];
		assert.equal(request.options.body.get('seed_1_0'), null);
		assert.equal(request.seed.filter(Boolean).length, 3);
		assert.equal(new Set(request.seed).size, 3);
		assert.ok(request.activeSeedsAtRequest.some(triple => triple.join('|') === request.seed.join('|')));
		assert.equal(request.progressAtRequest, index <= 3 ? '0' : '1');
	}
	assert.equal(game.calls[1].options.body.get('exclude_count'), '0');
	assert.equal(game.calls[2].options.body.get('exclude_count'), '0');
	assert.equal(game.calls[3].options.body.get('exclude_count'), '0');
	assert.equal(game.calls[4].options.body.get('exclude_count'), '1');
	assert.equal(game.nodes.get('[data-km-seed-cloud]').children.length, 100);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
	assert.equal(game.nodes.get('[data-km-seed-stage]').hidden, true);
	assert.equal(game.nodes.get('[data-km-action="start"]').disabled, false);
	await game.nodes.get('[data-km-action="fullscreen"]').listeners.click();
	assert.equal(game.doc.fullscreenElement, game.doc.getElementById('test-root'));
});

test('a new round starts empty and can reuse all answers from earlier rounds', async () => {
	const game = fixture(index => pair('Question ' + ((index - 1) % 4 + 1) + '?', 'Answer-' + ((index - 1) % 4 + 1)));
	await game.click();
	await game.click();
	assert.equal(game.calls.length, 10);
	assert.equal(game.createdSeedGroups.length, 8);
	assert.deepEqual(game.calls.map(call => call.action).filter(action => action === 'keywords'), ['keywords', 'keywords']);
	assert.equal(game.calls[6].options.body.get('exclude_count'), '0');
	assert.equal(game.calls[7].options.body.get('exclude_count'), '0');
	assert.equal(game.calls[8].options.body.get('exclude_count'), '0');
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
	assert.equal(game.nodes.get('[data-km-feedback]').textContent, 'choose_first');
});

test('a duplicated answer is rejected only within the current round and triggers another request', async () => {
	const game = fixture(index => index === 3 ? pair('Question 3?', 'Answer-2')
		: pair('Question ' + index + '?', 'Answer-' + index));
	await game.click();
	assert.equal(game.calls.length, 6);
	assert.equal(game.getMaximumActive(), 3);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
	assert.equal(game.nodes.get('[data-km-feedback]').textContent, 'choose_first');
});

test('a duplicated question is rejected even if its answer is new', async () => {
	const game = fixture(index => index === 2 ? pair('Question 1?', 'Fresh-answer')
		: pair('Question ' + index + '?', 'Answer-' + index));
	await game.click();
	assert.equal(game.calls.length, 6);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
});

test('a failed single card request does not destroy usable results from other requests', async () => {
	const game = fixture(index => index === 2 ? {error: 'Temporary generation failure', status: 503}
		: pair('Question ' + index + '?', 'Answer-' + index));
	await game.click();
	assert.equal(game.calls.length, 6);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
});

test('an unusable empty answer only causes a new attempt', async () => {
	const game = fixture(index => index === 2 ? pair('Question?', '   ')
		: pair('Question ' + index + '?', 'Answer-' + index));
	await game.click();
	assert.equal(game.calls.length, 6);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
});

test('authorization errors stop more requests but await already running calls', async () => {
	const game = fixture(index => index === 1 ? {error: 'Denied', status: 403}
		: pair('Question ' + index + '?', 'Answer-' + index));
	await game.click();
	assert.equal(game.calls.length, 4);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 0);
	assert.equal(game.nodes.get('[data-km-feedback]').textContent, 'Denied');
});

test('never starts more than three concurrent requests when the agent responds slowly', async () => {
	const pending = new Map();
	const game = fixture(index => new Promise(resolve => pending.set(index, resolve)));
	const run = game.click();
	for (let i = 0; i < 15 && pending.size < 3; i++) await new Promise(resolve => setImmediate(resolve));
	assert.equal(pending.size, 3);
	assert.equal(game.calls.length, 4);
	assert.equal(game.getMaximumActive(), 3);
	pending.get(2)(pair('Question 2?', 'Answer-2'));
	for (let i = 0; i < 15 && pending.size < 4; i++) await new Promise(resolve => setImmediate(resolve));
	assert.equal(game.calls.length, 5);
	assert.equal(game.calls[4].progressAtRequest, '1');
	pending.get(1)(pair('Question 1?', 'Answer-1'));
	pending.get(3)(pair('Question 3?', 'Answer-3'));
	pending.get(4)(pair('Question 4?', 'Answer-4'));
	await run;
	assert.equal(game.getMaximumActive(), 3);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
});

test('repeated duplicates stop at the existing bounded attempt count without fake cards', async () => {
	const game = fixture(() => pair('Repeated question?', 'Repeated-answer'));
	await game.click();
	assert.equal(game.calls.length, 1 + 4 * 6);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 0);
	assert.equal(game.nodes.get('[data-km-empty]').hidden, false);
	assert.equal(game.nodes.get('[data-km-feedback]').textContent, 'seed_exhausted');
});

test('duplicate comparison normalizes punctuation and case within the current round', () => {
	const {isDuplicatePair} = fixture(() => pair('Question?', 'Answer')).context.KnowledgeMemoryGame;
	const accepted = [pair('Was ist die U-Sache?', 'Ohmsches Gesetz')];
	assert.equal(isDuplicatePair(pair('Was ist die U Sache?', 'Other'), accepted), true);
	assert.equal(isDuplicatePair(pair('Other?', 'OHMSCHES  GESETZ!'), accepted), true);
	assert.equal(isDuplicatePair(pair('Other?', 'Elektrische Leistung'), accepted), false);
	assert.equal(isDuplicatePair(pair('Other?', 'Ohmsches Gesetz'), []), false);
});


test('twelve successful requests produce twelve pairs without wasting any parallel result', async () => {
	const game = fixture(index => pair('Question ' + index + '?', 'Answer-' + index), 12);
	await game.click();
	const cardRequests = game.calls.filter(call => call.action === 'cards');
	assert.equal(game.calls.length, 13);
	assert.equal(cardRequests.length, 12);
	assert.equal(game.getMaximumActive(), 3);
	assert.equal(game.createdSeedGroups.length, 12, 'Each actual request animates its own triple exactly once');
	const seedSignatures = cardRequests.map(call => [...call.seed].sort().join('|'));
	assert.equal(new Set(seedSignatures).size, 12, 'No triple gets redrawn or selected twice');
	assert.equal(game.nodes.get('[data-km-board]').children.length, 24);
	assert.equal(game.nodes.get('[data-km-seed-stage]').hidden, true);
	assert.equal(game.nodes.get('[data-km-feedback]').textContent, 'choose_first');
});

test('parallel requests retain their seed animation until their own answer resolves', async () => {
	const pending = new Map();
	const game = fixture(index => new Promise(resolve => pending.set(index, resolve)), 4);
	const run = game.click();
	for (let i = 0; i < 15 && pending.size < 3; i++) await new Promise(resolve => setImmediate(resolve));
	const groupsParent = game.nodes.get('[data-km-seed-combinations]').children
		.find(node => node.className === 'km-game__seed-batch');
	assert.equal(groupsParent.children.length, 3);
	assert.equal(game.createdSeedGroups.length, 3);
	const [first, second, third] = groupsParent.children;
	assert.equal(first.children[0].textContent, '#1');
	assert.equal(second.children[0].textContent, '#2');
	assert.equal(third.children[0].textContent, '#3');
	pending.get(1)(pair('Question 1?', 'Answer-1'));
	for (let i = 0; i < 15 && pending.size < 4; i++) await new Promise(resolve => setImmediate(resolve));
	assert.equal(game.createdSeedGroups.length, 4);
	assert.equal(groupsParent.children.length, 3);
	assert.strictEqual(groupsParent.children[0], second);
	assert.strictEqual(groupsParent.children[1], third);
	assert.equal(groupsParent.children[2].children[0].textContent, '#4');
	pending.get(2)(pair('Question 2?', 'Answer-2'));
	pending.get(3)(pair('Question 3?', 'Answer-3'));
	pending.get(4)(pair('Question 4?', 'Answer-4'));
	await run;
	assert.equal(game.calls.filter(call => call.action === 'cards').length, 4);
	assert.equal(game.createdSeedGroups.length, 4);
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
});


test('a complete round still renders final results, score, time and document review', async () => {
	const game = fixture(index => pair('Question ' + index + '?', 'Answer-' + index));
	await game.click();
	const board = game.nodes.get('[data-km-board]');
	const feedback = game.nodes.get('[data-km-feedback]');
	let completed = false;
	let mismatches = 0;
	let matches = 0;
	for (let round = 0; round < 4 && !completed; round++) {
		const cards = board.children;
		const first = cards.findIndex(card => card.attributes['aria-disabled'] !== 'true');
		assert.ok(first >= 0);
		for (let second = 0; second < cards.length; second++) {
			if (second === first || cards[second].attributes['aria-disabled'] === 'true') continue;
			cards[first].listeners.click();
			assert.equal(feedback.textContent, 'choose_second');
			cards[second].listeners.click();
			if (feedback.textContent === 'wrong_pair') {
				mismatches++;
				assert.equal(cards[first].attributes['aria-disabled'], 'true');
				game.runNextTimeout();
				assert.equal(feedback.textContent, 'try_again');
				continue;
			}
			matches++;
			assert.ok(['correct_pair', 'completed'].includes(feedback.textContent));
			if (feedback.textContent === 'completed') completed = true;
			break;
		}
	}
	assert.equal(matches, 4);
	assert.ok(mismatches >= 1);
	assert.equal(game.nodes.get('[data-km-stat="matches"]').textContent, '4/4');
	assert.equal(game.nodes.get('[data-km-progress]').attributes['aria-valuenow'], '100');
	const result = game.nodes.get('[data-km-result]');
	assert.equal(result.hidden, false);
	assert.equal(result.focused, true);
	assert.equal(result.children.length, 7);
	assert.equal(result.children[1].textContent, 'completed');
	const review = result.children.find(node => node.className === 'km-game__review');
	assert.ok(review);
	assert.equal(review.children[2].children.length, 4);
	assert.equal(game.nodes.get('[data-km-action="start"]').textContent, 'play_again');
	assert.equal(game.nodes.get('[data-km-announcement]').textContent.length > 0, true);
});

test('fullscreen can be exited without rebuilding cards and reports failures', async () => {
	const game = fixture(index => pair('Question ' + index + '?', 'Answer-' + index));
	await game.click();
	const fullscreen = game.nodes.get('[data-km-action="fullscreen"]');
	await fullscreen.listeners.click();
	assert.equal(fullscreen.attributes['aria-pressed'], 'true');
	await fullscreen.listeners.click();
	assert.equal(fullscreen.attributes['aria-pressed'], 'false');
	assert.equal(game.nodes.get('[data-km-board]').children.length, 8);
	game.doc.getElementById('test-root').requestFullscreen = async () => {throw new Error('unavailable');};
	await fullscreen.listeners.click();
	assert.equal(game.nodes.get('[data-km-feedback]').textContent, 'fullscreen_unavailable');
});


test('invalid keyword payload is reported without sending a card request', async () => {
	const game = fixture(index => pair('Question ' + index, 'Answer-' + index));
	game.context.fetch = async () => ({ok: true, json: async () => ({ok: true, keywords: null, pairs_count: 4})});
	await game.click();
	assert.equal(game.nodes.get('[data-km-feedback]').textContent, 'keywords_failed');
	assert.equal(game.nodes.get('[data-km-board]').children.length, 0);
	assert.equal(game.nodes.get('[data-km-action="start"]').disabled, false);
});

test('ResizeObserver preserves the established game after a real board-width change', async () => {
	const game = fixture(index => pair('Question ' + index + '?', 'Answer-' + index), 4, true);
	await game.click();
	const board = game.nodes.get('[data-km-board]');
	assert.equal(board.style['--km-cols'] !== undefined, true);
	board.getBoundingClientRect = () => ({width: 850, top: 110});
	game.triggerResize();
	assert.equal(board.style['--km-cols'] !== undefined, true);
	assert.equal(board.children.length, 8);
	assert.equal(game.calls.length, 5);
});

test('mountAll does not mount an already initialized game twice', async () => {
	const game = fixture(index => pair('Question ' + index + '?', 'Answer-' + index));
	const root = game.doc.getElementById('test-root');
	root.dataset.kmGameConfig = JSON.stringify({apiUrl: '/single-existing-endpoint', texts: {}});
	game.doc.querySelectorAll = selector => selector === '[data-km-game-config]' ? [root] : [];
	game.context.KnowledgeMemoryGame.mountAll();
	await game.click();
	assert.equal(game.calls.length, 5);
});
