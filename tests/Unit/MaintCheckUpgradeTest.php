<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Coverage for plugin_maint_check_upgrade()'s version-change branch: driven
 * past the page-gate with a stored plugin_config version that differs from the
 * packaged version, it refreshes the schema (maint_upgrade_tables()) and writes
 * the full plugin_config row.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/database.php';
});

beforeEach(function () {
	// Sandbox base_path (complete temp INFO + empty includes/database.php stub)
	// so every upgrade-path test runs plugin_maint_prune_files() against a
	// throwaway tree, never the real checkout. Tests needing a different INFO
	// override base_path themselves.
	$GLOBALS['__maint_base_restore'] = $GLOBALS['config']['base_path'];
	$base = sys_get_temp_dir() . '/maint-test-' . uniqid();
	mkdir($base . '/plugins/maint/includes', 0777, true);
	file_put_contents($base . '/plugins/maint/INFO', "[info]\nversion = 9.9.9\nname = maint\nlongname = Maint\nauthor = x\nhomepage = x\n");
	file_put_contents($base . '/plugins/maint/includes/database.php', "<?php\n");
	$GLOBALS['config']['base_path'] = $base;
});

afterEach(function () {
	if (isset($GLOBALS['__maint_base_restore'])) {
		$GLOBALS['config']['base_path'] = $GLOBALS['__maint_base_restore'];
	}
});

it('refreshes the schema and updates plugin_config when the stored version differs', function () {
	/*
	 * plugin_maint_check_upgrade() require_once's $config['library_path'] .
	 * '/database.php' and '/functions.php' after the page-gate. The bootstrap
	 * leaves library_path unset, so point it at a throwaway directory of empty
	 * files to let those requires resolve without redeclaring real Cacti helpers.
	 */
	$lib = sys_get_temp_dir() . '/maint_lib_' . uniqid('', true);
	mkdir($lib);
	file_put_contents($lib . '/database.php', "<?php\n");
	file_put_contents($lib . '/functions.php', "<?php\n");

	$original_library_path             = $GLOBALS['config']['library_path'] ?? null;
	$original_self                     = $_SERVER['PHP_SELF'] ?? null;
	$GLOBALS['config']['library_path'] = $lib;

	// Pass the page-gate: basename must be one of plugins.php / maint.php.
	$_SERVER['PHP_SELF'] = '/cacti/plugins/maint/maint.php';

	// db_table_exists defaults to false -> create branch; stale stored version.
	$GLOBALS['__test_table_exists'] = [];
	maint_test_queue('db_fetch_cell_prepared', '0.0-stale');
	$GLOBALS['__test_db_calls']         = [];
	$GLOBALS['__test_registered_hooks'] = [];

	try {
		plugin_maint_check_upgrade();
	} finally {
		if ($original_self === null) {
			unset($_SERVER['PHP_SELF']);
		} else {
			$_SERVER['PHP_SELF'] = $original_self;
		}

		if ($original_library_path === null) {
			unset($GLOBALS['config']['library_path']);
		} else {
			$GLOBALS['config']['library_path'] = $original_library_path;
		}

		@unlink($lib . '/database.php');
		@unlink($lib . '/functions.php');
		@rmdir($lib);
	}

	$created = array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'api_plugin_db_table_create';
	}), 'sql');

	$config_updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'plugin_config') !== false;
	});

	$repointed = array_filter($GLOBALS['__test_registered_hooks'], function ($hook) {
		return $hook['hook'] === 'is_device_in_maintenance' && $hook['file'] === 'includes/functions.php';
	});

	expect($created)->toContain('plugin_maint_schedules');
	expect($created)->toContain('plugin_maint_hosts');
	expect($repointed)->not->toBeEmpty();
	expect($config_updates)->not->toBeEmpty();
});

it('bails out without touching the schema when the packaged INFO is incomplete', function () {
	$lib = sys_get_temp_dir() . '/maint_lib_' . uniqid('', true);
	mkdir($lib);
	file_put_contents($lib . '/database.php', "<?php\n");
	file_put_contents($lib . '/functions.php', "<?php\n");

	$original_library_path             = $GLOBALS['config']['library_path'] ?? null;
	$original_self                     = $_SERVER['PHP_SELF'] ?? null;
	$GLOBALS['config']['library_path'] = $lib;
	$_SERVER['PHP_SELF']               = '/cacti/plugins/maint/maint.php';

	/*
	 * plugin_maint_version() parses $config['base_path'] . '/plugins/maint/INFO'.
	 * base_path must stay real (the require_once above reads the plugin's own
	 * includes/database.php from it), so temporarily replace the INFO on disk
	 * with one missing required keys to exercise the empty($info[...]) guard.
	 */
	$info_path   = $GLOBALS['config']['base_path'] . '/plugins/maint/INFO';
	$info_backup = file_get_contents($info_path);
	file_put_contents($info_path, "[info]\nname = maint\n");

	$GLOBALS['__test_db_calls'] = [];

	try {
		plugin_maint_check_upgrade();
	} finally {
		file_put_contents($info_path, $info_backup);

		if ($original_self === null) {
			unset($_SERVER['PHP_SELF']);
		} else {
			$_SERVER['PHP_SELF'] = $original_self;
		}

		if ($original_library_path === null) {
			unset($GLOBALS['config']['library_path']);
		} else {
			$GLOBALS['config']['library_path'] = $original_library_path;
		}

		@unlink($lib . '/database.php');
		@unlink($lib . '/functions.php');
		@rmdir($lib);
	}

	$config_updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'plugin_config') !== false;
	});

	expect($config_updates)->toBeEmpty();
});
