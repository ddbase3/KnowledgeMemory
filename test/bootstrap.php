<?php declare(strict_types=1);

// Standalone PHPUnit bootstrap for the directly adjacent BASE3 modules.
spl_autoload_register(static function(string $class): void {
	$root = dirname(__DIR__, 2);
	$parts = explode('\\', $class);
	$map = [
		'Base3' => 'Base3Framework',
		'AssistantFoundation' => 'AssistantFoundation',
		'UiFoundation' => 'UiFoundation',
		'KnowledgeMemory' => 'KnowledgeMemory'
	];
	$module = $map[$parts[0]] ?? null;
	if ($module === null) return;
	array_shift($parts);
	$path = $root . '/' . $module . '/src/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});
