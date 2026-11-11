'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const template = fs.readFileSync(path.join(root, 'tpl/Display/KnowledgeMemoryGameDisplay.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/knowledge-memory/game.css'), 'utf8');

function rule(source, identifier) {
	const start = source.indexOf(identifier);
	assert.notEqual(start, -1, 'Missing CSS rule: ' + identifier);
	const open = source.indexOf('{', start + identifier.length);
	assert.notEqual(open, -1, 'CSS rule has no body: ' + identifier);
	let level = 1;
	for (let i = open + 1; i < source.length; i++) {
		if (source[i] === '{') level++;
		if (source[i] === '}' && --level === 0) return source.slice(open + 1, i);
	}
	assert.fail('Unbalanced CSS rule: ' + identifier);
}

function insideSidebar(needle, count = 1) {
	const start = template.indexOf('<aside class="km-game__sidebar"');
	const end = template.indexOf('</aside>', start);
	assert.ok(start > -1 && end > start, 'One sidebar exists');
	const offset = template.indexOf(needle);
	assert.ok(offset > start && offset < end, needle + ' must live in the right column');
	assert.equal(template.split(needle).length - 1, count, 'Expected count for ' + needle);
}

test('the board is exclusively in the first column, and all other regions share the second column', () => {
	const playStart = template.indexOf('<div class="km-game__play">');
	const sidebar = template.indexOf('<aside class="km-game__sidebar"', playStart);
	const sidebarEnd = template.indexOf('</aside>', sidebar);
	const arena = template.indexOf('<section class="km-game__arena"', sidebarEnd);
	const arenaEnd = template.indexOf('</section>', arena);
	assert.ok(playStart > -1 && sidebar > playStart && sidebarEnd > sidebar && arena > sidebarEnd && arenaEnd > arena);
	assert.ok(template.indexOf('data-km-board', arena) < arenaEnd);
	for (const fragment of [
		'<header class="km-game__header">', '<h2>', 'data-km-action="start"',
		'data-km-action="fullscreen"', '<details class="km-game__configure">',
		'data-km-stat="score"', 'data-km-stat="matches"',
		'data-km-stat="time"', 'data-km-progress>', 'data-km-result'
	]) insideSidebar(fragment);
	const sidebarClose = template.indexOf('</aside>', sidebar);
	assert.ok(sidebarClose < arena);
	assert.equal(template.indexOf('data-km-board', sidebar), arena + template.slice(arena).indexOf('data-km-board'));
	assert.ok(template.indexOf('</div>\n\t</div>\n</section>', arenaEnd) > arenaEnd);
});

test('landscape uses two columns even below the old container breakpoint', () => {
	const media = rule(css, '@media (orientation: landscape)');
	assert.match(rule(media, '.km-game__play'), /grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(/);
	assert.match(rule(media, '.km-game__sidebar'), /order:\s*0\s*;/);
	assert.match(rule(css, '.km-game__play'), /grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(/);
	assert.match(css, /\.km-game__arena\s*\{\s*min-width:\s*0\s*;\s*order:\s*-1\s*;/);
});

test('portrait places the intact controls column above the board', () => {
	const media = rule(css, '@media (orientation: portrait)');
	assert.match(rule(media, '.km-game__play'), /grid-template-columns:\s*minmax\(0,\s*1fr\)\s*;/);
	assert.match(rule(media, '.km-game__sidebar'), /order:\s*-1\s*;/);
	assert.match(rule(media, '.km-game__arena'), /order:\s*0\s*;/);
	assert.match(rule(media, '.km-game__sidebar .km-game__stats'), /repeat\(3,\s*minmax\(0,\s*1fr\)\)/);
});

test('the right column contains the full configuration, progress and completion panel without overflow-prone grids', () => {
	assert.match(rule(css, '.km-game__sidebar'), /flex-direction:\s*column\s*;/);
	assert.match(rule(css, '.km-game__sidebar .km-game__header'), /flex-direction:\s*column\s*;/);
	assert.match(rule(css, '.km-game__sidebar .km-settings form'), /repeat\(2,\s*minmax\(0,\s*1fr\)\)/);
	assert.match(rule(css, '.km-game__sidebar .km-settings__field:first-child,\n.km-game__sidebar .km-settings form button,\n.km-game__sidebar .km-settings form \[data-km-settings-message\]'), /grid-column:\s*1\s*\/\s*-1\s*;/);
	assert.match(rule(css, '.km-game__sidebar .km-game__result'), /margin-top:\s*0\s*;/);
	assert.match(rule(css, '.km-game__sidebar .km-game__start'), /min-width:\s*0\s*;/);
});

test('layout changes retain game and accessibility hooks, and do not move the loading animation out of the board', () => {
	const needed = ['data-km-board', 'data-km-seed-stage', 'data-km-seed-cloud', 'data-km-seed-combinations',
		'data-km-feedback', 'data-km-announcement', 'data-km-empty>', 'data-km-result',
		'data-km-action="start"', 'data-km-action="fullscreen"'];
	for (const selector of needed) assert.equal(template.split(selector).length - 1, 1, selector);
	const boardEnd = template.indexOf('</section>', template.indexOf('<section class="km-game__arena"'));
	for (const marker of ['data-km-seed-stage', 'data-km-seed-cloud', 'data-km-board']) {
		assert.ok(template.indexOf(marker) < boardEnd, marker);
	}
	assert.match(template, /aria-live="polite"/);
	assert.match(css, /@media \(prefers-reduced-motion: reduce\)/);
});


test('malformed CSS is rejected by the layout contract parser', () => {
	assert.throws(() => rule('.km-game__x { color: red;', '.km-game__x'), /Unbalanced CSS rule/);
});
