<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('maint setup.php structure', function () {
	$source   = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
	$infoFile = parse_ini_file(realpath(__DIR__ . '/../../INFO'), true);

	if (!is_array($infoFile) || !isset($infoFile['info']) || !is_array($infoFile['info'])) {
		throw new RuntimeException('Unable to parse the INFO section');
	}
	$info = $infoFile['info'];

	it('defines plugin_maint_install function', function () use ($source) {
		expect($source)->toContain('function plugin_maint_install');
	});

	it('defines plugin_maint_version function', function () use ($source) {
		expect($source)->toContain('function plugin_maint_version');
	});

	it('defines plugin_maint_uninstall function', function () use ($source) {
		expect($source)->toContain('function plugin_maint_uninstall');
	});

	it('reads the plugin version from the INFO file', function () use ($source) {
		expect($source)->toContain("parse_ini_file(\$config['base_path'] . '/plugins/maint/INFO', true)");
		expect($source)->toContain("return \$info['info']");
	});

	it('declares a name in the INFO file', function () use ($info) {
		expect($info)->toHaveKey('name');
	});

	it('declares a version in the INFO file', function () use ($info) {
		expect($info)->toHaveKey('version');
	});

	it('registers hooks in install function', function () use ($source) {
		expect($source)->toContain('api_plugin_register_hook');
	});
});

