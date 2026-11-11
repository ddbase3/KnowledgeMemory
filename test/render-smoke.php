<?php declare(strict_types=1);

require __DIR__ . '/Fixture/TemplateViewFixture.php';

use KnowledgeMemory\Test\Fixture\TemplateViewFixture;

$root = dirname(__DIR__);
$texts = parse_ini_file($root . '/lang/Display/en.ini', true, INI_SCANNER_RAW)['knowledge_memory'];
$view = new TemplateViewFixture();
$view->_ = [
	'root_id' => 'test-settings',
	'texts' => $texts,
	'settings' => ['agent_id' => 'agent-1', 'pairs' => 8, 'difficulty' => 'normal'],
	'agents' => ['agent-1' => 'Existing Agent'],
	'api_url' => '/settings/save?a=1&b=2',
	'game_url' => '/game',
	'css_url' => '/assets/game.css?t=abc&x=1',
	'js_url' => '/assets/game.js?t=abc&x=1'
];
$settingsHtml = $view->render($root . '/tpl/Display/KnowledgeMemorySettingsDisplay.php');
$view->_ = [
	'root_id' => 'test-game',
	'texts' => $texts,
	'settings_html' => $settingsHtml,
	'api_url' => '/round',
	'css_url' => '/assets/game.css?t=abc&x=1',
	'js_url' => '/assets/game.js?t=abc&x=1'
];
$gameHtml = $view->render($root . '/tpl/Display/KnowledgeMemoryGameDisplay.php');
foreach ([
	'game config' => 'data-km-game-config=',
	'settings config' => 'data-km-settings-config=',
	'game start button' => 'data-km-action="start"',
	'agent selection' => '<option value="agent-1" selected>',
	'escaped CSS URL' => 'href="/assets/game.css?t=abc&amp;x=1"',
	'escaped JS URL' => 'src="/assets/game.js?t=abc&amp;x=1"',
	'settings accordion' => '<details class="km-game__configure">'
] as $label => $needle) {
	if (!str_contains($gameHtml, $needle)) {
		fwrite(STDERR, 'Missing expected ' . $label . PHP_EOL);
		exit(1);
	}
}
if (str_contains($gameHtml, 'AssetLoader') || str_contains($gameHtml, '<script>')) {
	fwrite(STDERR, 'Unexpected inline script or loader.' . PHP_EOL);
	exit(1);
}
if (str_contains($gameHtml, 'X-Knowledge-Memory-Token') || str_contains($gameHtml, 'setting-token') || str_contains($gameHtml, 'play-token')) {
	fwrite(STDERR, 'Legacy request token unexpectedly rendered.' . PHP_EOL);
	exit(1);
}
if (substr_count($gameHtml, 'data-km-settings-config=') !== 1) {
	fwrite(STDERR, 'Settings must be rendered once.' . PHP_EOL);
	exit(1);
}

$sidebarStart = strpos($gameHtml, '<aside class="km-game__sidebar"');
$sidebarEnd = strpos($gameHtml, '</aside>', (int)$sidebarStart);
$arenaStart = strpos($gameHtml, '<section class="km-game__arena"');
$arenaEnd = strpos($gameHtml, '</section>', (int)$arenaStart);
if ($sidebarStart === false || $sidebarEnd === false || $arenaStart === false || $arenaEnd === false
	|| $sidebarStart >= $arenaStart || $sidebarEnd >= $arenaStart) {
	fwrite(STDERR, "The controls must precede the board in reading order.\n");
	exit(1);
}
$sidebarHtml = substr($gameHtml, $sidebarStart, $sidebarEnd - $sidebarStart);
$boardHtml = substr($gameHtml, $arenaStart, $arenaEnd - $arenaStart);
foreach ([
	'heading' => '<h2>',
	'start button' => 'data-km-action="start"',
	'fullscreen' => 'data-km-action="fullscreen"',
	'authorized configuration' => '<details class="km-game__configure">',
	'agent field' => 'name="agent_id"',
	'points' => 'data-km-stat="score"',
	'matches' => 'data-km-stat="matches"',
	'time' => 'data-km-stat="time"',
	'progress' => 'data-km-progress',
	'finish result' => 'data-km-result'
] as $label => $needle) {
	if (!str_contains($sidebarHtml, $needle) || str_contains($boardHtml, $needle)) {
		fwrite(STDERR, 'Wrong layout location: ' . $label . PHP_EOL);
		exit(1);
	}
}
foreach (['data-km-board', 'data-km-seed-stage', 'data-km-seed-cloud'] as $marker) {
	if (!str_contains($boardHtml, $marker) || str_contains($sidebarHtml, $marker)) {
		fwrite(STDERR, 'Board element misplaced: ' . $marker . PHP_EOL);
		exit(1);
	}
}
$view->_['settings_html'] = '';
$withoutConfiguration = $view->render($root . '/tpl/Display/KnowledgeMemoryGameDisplay.php');
if (str_contains($withoutConfiguration, '<details class="km-game__configure">')
	|| !str_contains($withoutConfiguration, '<aside class="km-game__sidebar"')
	|| !str_contains($withoutConfiguration, 'data-km-result')) {
	fwrite(STDERR, "The non-admin game must retain the result and controls without exposing configuration.\n");
	exit(1);
}

fwrite(STDOUT, "OK: game and settings template render with native resolved assets.\n");
