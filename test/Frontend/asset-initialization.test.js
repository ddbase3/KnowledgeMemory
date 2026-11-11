'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const moduleRoot = path.resolve(__dirname, '../..');
const gameTemplate = fs.readFileSync(path.join(moduleRoot, 'tpl/Display/KnowledgeMemoryGameDisplay.php'), 'utf8');
const settingsTemplate = fs.readFileSync(path.join(moduleRoot, 'tpl/Display/KnowledgeMemorySettingsDisplay.php'), 'utf8');
const gameDisplay = fs.readFileSync(path.join(moduleRoot, 'src/Display/KnowledgeMemoryGameDisplay.php'), 'utf8');
const settingsDisplay = fs.readFileSync(path.join(moduleRoot, 'src/Display/KnowledgeMemorySettingsDisplay.php'), 'utf8');
const client = fs.readFileSync(path.join(moduleRoot, 'assets/knowledge-memory/game.js'), 'utf8');
const settingsFeature = fs.readFileSync(path.join(moduleRoot, 'src/Settings/KnowledgeMemorySettingsTab.php'), 'utf8');

test('both displays use resolved native assets and one external script, not an optional loader or inline JavaScript', () => {
	for (const template of [gameTemplate, settingsTemplate]) {
		assert.match(template, /<link rel="stylesheet" href="<\?php echo \$esc\(\$this->_\['css_url'\]\); \?>">/);
		assert.match(template, /<script src="<\?php echo \$esc\(\$this->_\['js_url'\]\); \?>"><\/script>/);
		assert.doesNotMatch(template, /<script>/);
		assert.doesNotMatch(template, /AssetLoader/);
	}
	assert.match(gameDisplay, /IAssetResolver/);
	assert.match(settingsDisplay, /IAssetResolver/);
	assert.match(settingsDisplay, /game\.js/);
	assert.match(gameTemplate, /data-km-game-config/);
	assert.match(settingsTemplate, /data-km-settings-config/);
});

test('game includes exactly the existing authorized settings display and keeps its save owner', () => {
	assert.match(gameDisplay, /getInstanceByInterfaceName\(IDisplay::class, KnowledgeMemorySettingsDisplay::getName\(\)\)/);
	assert.match(gameDisplay, /\$settingsDisplay->canConfigure\(\) \? \$settingsDisplay->getOutput\('html'\) : ''/);
	assert.match(settingsDisplay, /public function canConfigure\(\): bool/);
	assert.match(settingsDisplay, /Permission::for\('feature', KnowledgeMemorySettingsTab::getName\(\)\)/);
	assert.match(gameTemplate, /\$this->_\['settings_html'\]/);
	assert.match(gameTemplate, /<details class="km-game__configure">/);
	assert.doesNotMatch(client, /AssetLoader/);
});

test('the settings permission remains discoverable without an administration tab', () => {
	assert.match(settingsFeature, /implements IFeature/);
	assert.doesNotMatch(settingsFeature, /AbstractFeatureSettingsTab|ISettingsTab/);
	assert.match(settingsFeature, /return 'knowledgememorysettingstab'/);
	assert.match(settingsFeature, /'administration'/);
	assert.equal(fs.existsSync(path.join(moduleRoot, 'src/Settings/KnowledgeMemorySettingsRootTab.php')), false);
});

test('external game script initializes the settings form and submits through the existing endpoint once', async () => {
	let submits = [];
	let queryCount = 0;
	let handler = null;
	const message = {textContent: ''};
	const button = {disabled: false};
	const form = {
		elements: {
			agent_id: {value: 'known-agent'},
			pairs: {value: '8'},
			difficulty: {value: 'normal'}
		},
		querySelector: selector => selector === 'button[type="submit"]' ? button : null,
		addEventListener: (event, callback) => {
			assert.equal(event, 'submit');
			queryCount++;
			handler = callback;
		}
	};
	const root = {
		dataset: {kmSettingsConfig: JSON.stringify({apiUrl: '/knowledge/save', saveFailed: 'Save failed'})},
		querySelector: selector => selector === '[data-km-settings-form]' ? form : message
	};
	globalThis.document = {
		readyState: 'complete',
		querySelectorAll: selector => selector === '[data-km-settings-config]' ? [root] : []
	};
	globalThis.fetch = async (url, options) => {
		submits.push({url, options});
		return {json: async () => ({ok: true, message: 'Saved'})};
	};
	try {
		require('../../assets/knowledge-memory/game.js');
		assert.equal(root.dataset.kmSettingsMounted, '1');
		globalThis.KnowledgeMemoryGame.mountAll();
		assert.equal(queryCount, 1, 'No duplicate event handler after repeated script initialization');
		let prevented = false;
		await handler({preventDefault: () => {prevented = true;}});
		assert.equal(prevented, true);
		assert.equal(submits.length, 1);
		assert.equal(submits[0].url, '/knowledge/save');
		assert.equal(submits[0].options.method, 'POST');
		assert.equal(submits[0].options.credentials, 'same-origin');
		assert.equal(submits[0].options.headers['Content-Type'], 'application/x-www-form-urlencoded; charset=UTF-8');
		assert.equal(submits[0].options.headers.Accept, 'application/json');
		assert.deepEqual(Object.keys(submits[0].options.headers).sort(), ['Accept', 'Content-Type']);
		assert.ok(submits[0].options.body instanceof URLSearchParams);
		assert.deepEqual(Object.fromEntries(submits[0].options.body), {
			agent_id: 'known-agent',
			pairs: '8',
			difficulty: 'normal'
		});
		assert.equal(button.disabled, false);
		assert.equal(message.textContent, 'Saved');
	} finally {
		delete globalThis.document;
		delete globalThis.fetch;
	}
});


test('both AJAX actions keep the existing form transport without a duplicate application token', () => {
	assert.doesNotMatch(client, /X-Knowledge-Memory-Token|\b(?:options|config)\.token\b/);
	assert.match(client, /body: new URLSearchParams\(fields\)/);
	assert.equal((client.match(/application\/x-www-form-urlencoded; charset=UTF-8/g) || []).length, 2);
	assert.doesNotMatch(client, /body:\s*JSON\.stringify\(/);
	for (const template of [gameTemplate, settingsTemplate]) {
		assert.doesNotMatch(template, /\['token'\]/);
	}
	for (const display of [gameDisplay, settingsDisplay]) {
		assert.doesNotMatch(display, /IToken|knowledge_memory\.(?:play|settings)/);
		assert.match(display, /IUsermanager/);
	}
});


test('a failed settings request reports the configured failure and re-enables its button', async () => {
	let handler;
	const button = {disabled: false};
	const message = {textContent: ''};
	const form = {
		elements: {agent_id: {value: 'agent'}, pairs: {value: '4'}, difficulty: {value: 'normal'}},
		querySelector: () => button,
		addEventListener: (type, callback) => {assert.equal(type, 'submit'); handler = callback;}
	};
	const root = {
		dataset: {kmSettingsConfig: JSON.stringify({apiUrl: '/save', saveFailed: 'Save failed'})},
		querySelector: selector => selector === '[data-km-settings-form]' ? form : message
	};
	const previous = globalThis.fetch;
	globalThis.fetch = async () => {throw new Error('Network unavailable');};
	try {
		globalThis.KnowledgeMemoryGame.mountSettings(root);
		let prevented = false;
		await handler({preventDefault: () => {prevented = true;}});
		assert.equal(prevented, true);
		assert.equal(message.textContent, 'Save failed');
		assert.equal(button.disabled, false);
	} finally {
		if (previous === undefined) delete globalThis.fetch;
		else globalThis.fetch = previous;
	}
});
