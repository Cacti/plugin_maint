<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for maint_config_arrays() and maint_draw_navigation_text()
 * in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['menu'] = array();
});

it('registers the Maintenance Schedules menu entry under Management', function () {
	global $menu;

	maint_config_arrays();

	expect($menu)->toHaveKey('Management');
	expect($menu['Management'])->toBe(array(
		'plugins/maint/maint.php' => 'Maintenance Schedules',
	));
});

it('adds the maint breadcrumb entries without disturbing existing ones', function () {
	$nav = maint_draw_navigation_text(array('other.php:' => array('title' => 'Other')));

	expect($nav)->toHaveKey('other.php:');
	expect($nav)->toHaveKey('maint.php:');
	expect($nav)->toHaveKey('maint.php:edit');
	expect($nav)->toHaveKey('maint.php:actions');
});
