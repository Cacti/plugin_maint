<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Verify setup.php defines required plugin hooks and info function.

describe('maint setup.php structure', function () {
	$setupPath = realpath(__DIR__ . '/../../setup.php');
	expect($setupPath)->not->toBeFalse();

	$source = file_get_contents($setupPath);
	expect($source)->not->toBeFalse();

	$infoPath = realpath(__DIR__ . '/../../INFO');
	expect($infoPath)->not->toBeFalse();

	$info = parse_ini_file($infoPath, true);
	expect($info)->not->toBeFalse();

	it('defines plugin_maint_install function', function () use ($source) {
		expect($source)->toContain('function plugin_maint_install');
	});

	it('defines plugin_maint_version function', function () use ($source) {
		expect($source)->toContain('function plugin_maint_version');
	});

	it('defines plugin_maint_uninstall function', function () use ($source) {
		expect($source)->toContain('function plugin_maint_uninstall');
	});

	it('declares a name in INFO', function () use ($info) {
		expect($info['info'])->toHaveKey('name');
	});

	it('declares a version in INFO', function () use ($info) {
		expect($info['info'])->toHaveKey('version');
	});
});
