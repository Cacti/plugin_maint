<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for maint_device_action_array() and maint_setup_database()
 * in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
});

it('adds the maintenance actions to the device action dropdown', function () {
	$actions = maint_device_action_array(array('delete' => 'Delete'));

	expect($actions)->toHaveKey('delete');
	expect($actions)->toHaveKey('maint');
	expect($actions)->toHaveKey('maint_add_to_schedule');
});

it('creates the schedules and hosts tables', function () {
	maint_setup_database();

	$tables = array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'api_plugin_db_table_create';
	}), 'sql');

	expect($tables)->toBe(array('plugin_maint_schedules', 'plugin_maint_hosts'));
});
