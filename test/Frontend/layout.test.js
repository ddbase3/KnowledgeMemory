'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
require('../../assets/knowledge-memory/game.js');

const {computeLayout, selectLayout, seededRandom, chooseSeedTriples, LAYOUTS} = globalThis.KnowledgeMemoryGame;

function boxForAbsolute(layout, position) {
	const angle = Math.abs(position.angle) * Math.PI / 180;
	const halfWidth = (layout.cardWidth * Math.cos(angle) + layout.cardHeight * Math.sin(angle)) / 2;
	const halfHeight = (layout.cardHeight * Math.cos(angle) + layout.cardWidth * Math.sin(angle)) / 2;
	return {
		left: position.x + layout.cardWidth / 2 - halfWidth,
		right: position.x + layout.cardWidth / 2 + halfWidth,
		top: position.y + layout.cardHeight / 2 - halfHeight,
		bottom: position.y + layout.cardHeight / 2 + halfHeight
	};
}

test('all five layouts yield one position for every card on phones and desktops', () => {
	for (const mode of LAYOUTS) {
		for (const count of [8, 12, 16, 20, 24]) {
			for (const width of [240, 280, 320, 360, 420, 590, 760, 980, 1180]) {
				const layout = computeLayout(count, width, mode, 42);
				assert.equal(layout.positions.length, count);
				assert.ok(layout.cardHeight >= 144);
				assert.ok(layout.rows >= 1);
			}
		}
	}
});

test('classic, staggered and wave maintain unique grid locations', () => {
	for (const mode of ['classic', 'staggered', 'wave']) {
		for (const count of [8, 12, 24]) {
			for (const width of [260, 360, 590, 980]) {
				const layout = computeLayout(count, width, mode, 33);
				assert.equal(layout.absolute, false);
				const cells = new Set();
				for (const position of layout.positions) {
					assert.ok(position.col + position.span <= layout.columns);
					for (let col = position.col; col < position.col + position.span; col++) {
						const key = position.row + ':' + col;
						assert.ok(!cells.has(key), 'Grid collision ' + mode);
						cells.add(key);
					}
				}
			}
		}
	}
});

test('orbit is genuinely circular, bounded, and not a rectangular perimeter', () => {
	for (const width of [260, 340, 590, 900, 1200]) {
		for (const count of [8, 16, 24]) {
			const layout = computeLayout(count, width, 'orbit', 11);
			assert.equal(layout.absolute, true);
			assert.ok(layout.boardHeight <= 650);
			const outerCount = count > 16 ? Math.ceil(count * .66) : count;
			const radii = layout.positions.slice(0, outerCount).map(pos => {
				const centerX = pos.x + layout.cardWidth / 2;
				const centerY = pos.y + layout.cardHeight / 2;
				return Math.hypot(centerX - width / 2, centerY - layout.boardHeight / 2);
			});
			assert.ok(Math.max(...radii) - Math.min(...radii) < 2, 'Orbit must be circular');
			for (const pos of layout.positions) {
				const box = boxForAbsolute(layout, pos);
				assert.ok(box.left >= -1 && box.right <= width + 1, 'Card escapes horizontally');
				assert.ok(box.top >= -1 && box.bottom <= layout.boardHeight + 1, 'Card escapes vertically');
			}
		}
	}
});

test('orbit respects a constrained viewport height', () => {
	const layout = computeLayout(24, 900, 'orbit', 12, 340);
	assert.ok(layout.boardHeight <= 340);
	for (const pos of layout.positions) {
		const box = boxForAbsolute(layout, pos);
		assert.ok(box.top >= -1 && box.bottom <= layout.boardHeight + 1);
	}
});

test('24 card orbit intentionally overlaps rather than growing outside screen', () => {
	const layout = computeLayout(24, 760, 'orbit', 4);
	assert.ok(layout.boardHeight <= 650);
	let overlaps = 0;
	for (let i = 0; i < layout.positions.length; i++) {
		const a = boxForAbsolute(layout, layout.positions[i]);
		for (let j = i + 1; j < layout.positions.length; j++) {
			const b = boxForAbsolute(layout, layout.positions[j]);
			if (Math.min(a.right, b.right) > Math.max(a.left, b.left) + 8
				&& Math.min(a.bottom, b.bottom) > Math.max(a.top, b.top) + 8) overlaps++;
		}
	}
	assert.ok(overlaps > 0);
});

test('scattered cards rotate up to 15 degrees and safely overlap', () => {
	for (const width of [260, 360, 760, 1180]) {
		const layout = computeLayout(24, width, 'scattered', 123);
		assert.equal(layout.absolute, true);
		assert.ok(layout.positions.some(pos => Math.abs(pos.angle) > 10));
		for (const pos of layout.positions) {
			assert.ok(Math.abs(pos.angle) <= 15);
			const bounds = boxForAbsolute(layout, pos);
			assert.ok(bounds.left >= -1 && bounds.right <= width + 1, 'Scattered card horizontally clipped');
			assert.ok(bounds.top >= -1 && bounds.bottom <= layout.boardHeight + 1, 'Scattered card vertically clipped');
		}
	}
	assert.deepEqual(computeLayout(24, 700, 'scattered', 42), computeLayout(24, 700, 'scattered', 42));
	assert.notDeepEqual(computeLayout(24, 700, 'scattered', 42), computeLayout(24, 700, 'scattered', 43));
});

test('wave and staggered patterns remain recognizable', () => {
	const stagger = computeLayout(20, 900, 'staggered', 1);
	assert.equal(stagger.positions.find(pos => pos.row === 1).col, 1);
	const wave = computeLayout(20, 790, 'wave', 12);
	assert.ok(wave.positions.some(pos => pos.y < 0));
	assert.ok(wave.positions.some(pos => pos.y > 0));
});

test('seed selection assigns distinct triples to each card pair', () => {
	const terms = Array.from({length: 24}, (_, index) => 'Term ' + index);
	const triples = chooseSeedTriples(terms, 12, new Set(), seededRandom(71));
	assert.equal(triples.length, 12);
	const signatures = triples.map(terms => {
		assert.equal(terms.length, 3);
		assert.equal(new Set(terms).size, 3);
		return [...terms].sort().join('|');
	});
	assert.equal(new Set(signatures).size, 12);
	const next = chooseSeedTriples(terms, 12, new Set(signatures), seededRandom(71));
	assert.equal(next.length, 12);
	assert.ok(next.every(terms => !signatures.includes([...terms].sort().join('|'))));
});

test('layout selection never repeats the previous layout', () => {
	for (const previous of LAYOUTS) {
		for (const value of [0, .1, .25, .5, .75, .99999]) {
			assert.notEqual(selectLayout(previous, () => value), previous);
		}
	}
	assert.equal(seededRandom(987)(), seededRandom(987)());
});

test('invalid layouts and insufficient seed pools fail explicitly', () => {
	assert.throws(() => computeLayout(0, 500, 'classic'), TypeError);
	assert.throws(() => computeLayout(12, 0, 'orbit'), TypeError);
	assert.throws(() => computeLayout(12, 500, 'unknown'), TypeError);
	assert.throws(() => chooseSeedTriples(['one', 'two', 'three'], 12), TypeError);
});

test('every user-visible game and accessibility key has German and English translations', () => {
	const fs = require('node:fs');
	const path = require('node:path');
	const root = path.resolve(__dirname, '../../');
	const keys = new Set();
	for (const source of [
		fs.readFileSync(path.join(root, 'tpl/Display/KnowledgeMemoryGameDisplay.php'), 'utf8'),
		fs.readFileSync(path.join(root, 'assets/knowledge-memory/game.js'), 'utf8')
	]) {
		for (const match of source.matchAll(/\$t\['([^']+)'\]|\bt\.([a-z][a-z0-9_]*)/g)) keys.add(match[1] || match[2]);
	}
	for (const name of LAYOUTS) keys.add('layout_' + name);
	for (const language of ['de', 'en']) {
		const ini = fs.readFileSync(path.join(root, 'lang/Display', language + '.ini'), 'utf8');
		const translated = new Set([...ini.matchAll(/^([a-z][a-z0-9_]*)\s*=/gm)].map(match => match[1]));
		for (const key of keys) assert.ok(translated.has(key), 'Missing ' + language + ' key: ' + key);
	}
});


test('invalid seed input types and counts are rejected at the source', () => {
	for (const [keywords, count] of [
		[null, 1], [42, 1], [['a', 'b', 'c'], 0], [['a', 'b', 'c'], 13],
		[['a', 'b'], 1], [['a', 'a', 'a'], 1]
	]) {
		assert.throws(() => chooseSeedTriples(keywords, count), TypeError);
	}
});
