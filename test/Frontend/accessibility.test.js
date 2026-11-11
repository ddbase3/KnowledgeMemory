'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const template = fs.readFileSync(path.join(root, 'tpl/Display/KnowledgeMemoryGameDisplay.php'), 'utf8');
const client = fs.readFileSync(path.join(root, 'assets/knowledge-memory/game.js'), 'utf8');

test('each flipped card has a separate spoken status without changing the visual feedback', () => {
	assert.match(template, /data-km-announcement/);
	assert.match(template, /aria-live="polite"/);
	assert.match(client, /announcement\.textContent = \(card\.kind/);
	assert.match(client, /announceCard\(index\);/);
});

test('generated data is inserted as text and citations are labelled as unverified', () => {
	assert.match(client, /element\.querySelector\('\.km-card__content'\)\.textContent/);
	assert.match(client, /t\.source_note/);
	assert.doesNotMatch(client, /\.innerHTML\s*=/);
	for (const language of ['de', 'en']) {
		const translations = fs.readFileSync(path.join(root, 'lang/Display/' + language + '.ini'), 'utf8');
		assert.match(translations, /^source_note\s*=/m);
	}
});
