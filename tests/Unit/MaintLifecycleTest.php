<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_maint_version() and the plugin lifecycle
 * contract functions in setup.php: plugin_maint_uninstall(),
 * plugin_maint_check_config(), and plugin_maint_upgrade().
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('parses the plugin INFO file into an info array', function () {
	$info = plugin_maint_version();

	expect($info)->toBeArray();
	expect($info)->toHaveKey('name');
	expect($info)->toHaveKey('version');
	expect($info['name'])->toBe('maint');
});

it('performs no work on uninstall without raising an error', function () {
	expect(plugin_maint_uninstall())->toBeNull();
});

it('reports the config as always valid', function () {
	expect(plugin_maint_check_config())->toBeTrue();
});

it('reports that no upgrade is pending', function () {
	expect(plugin_maint_upgrade())->toBeFalse();
});
