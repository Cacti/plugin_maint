<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

uses(TestCase::class);

beforeEach(function () {
	$this->loadPluginSource('functions.php');
});

describe('plugin_maint_check_schedule one-time schedules', function () {
	it('returns false when the schedule id does not exist', function () {
		maint_test_queue('db_fetch_row_prepared', []);

		expect(plugin_maint_check_schedule(1))->toBeFalse();
	});

	it('returns true while inside the one-time window', function () {
		$now = time();

		maint_test_queue('db_fetch_row_prepared', [
			'mtype' => 1,
			'stime' => $now - 100,
			'etime' => $now + 100,
		]);

		expect(plugin_maint_check_schedule(1))->toBeTrue();
	});

	it('returns false before the one-time window starts', function () {
		$now = time();

		maint_test_queue('db_fetch_row_prepared', [
			'mtype' => 1,
			'stime' => $now + 100,
			'etime' => $now + 200,
		]);

		expect(plugin_maint_check_schedule(1))->toBeFalse();
	});

	it('returns false after the one-time window ends, without touching the database', function () {
		$now = time();

		maint_test_queue('db_fetch_row_prepared', [
			'mtype' => 1,
			'stime' => $now - 200,
			'etime' => $now - 100,
		]);

		expect(plugin_maint_check_schedule(1))->toBeFalse();

		$updates = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared');
		expect($updates)->toBeEmpty();
	});
});

describe('plugin_maint_check_schedule recurring schedules', function () {
	it('returns true while inside a not-yet-elapsed recurring window, without advancing it', function () {
		$now = time();

		maint_test_queue('db_fetch_row_prepared', [
			'name'      => 'nightly',
			'mtype'     => 2,
			'stime'     => $now - 100,
			'etime'     => $now + 100,
			'minterval' => 86400,
		]);

		expect(plugin_maint_check_schedule(3))->toBeTrue();

		$updates = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared');
		expect($updates)->toBeEmpty();
	});

	it('advances an elapsed recurring window by whole intervals and reports active if it now covers now', function () {
		$now = time();

		maint_test_queue('db_fetch_row_prepared', [
			'name'      => 'nightly',
			'mtype'     => 2,
			'stime'     => $now - 90000,
			'etime'     => $now - 3600,
			'minterval' => 86400,
		]);

		expect(plugin_maint_check_schedule(3))->toBeTrue();

		$updates = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
		expect($updates)->toHaveCount(1);
		expect($updates[0]['params'][2])->toBe(3);
		expect($updates[0]['params'][0])->toBeGreaterThan($now - 90000);
		expect($GLOBALS['__test_log'])->toHaveCount(1);
		expect($GLOBALS['__test_log'][0])->toContain('Next start');
	});

	it('advances an elapsed recurring window and reports inactive if the next occurrence is still in the future', function () {
		$now = time();

		maint_test_queue('db_fetch_row_prepared', [
			'name'      => 'nightly',
			'mtype'     => 2,
			'stime'     => $now - 40,
			'etime'     => $now - 30,
			'minterval' => 86400,
		]);

		expect(plugin_maint_check_schedule(3))->toBeFalse();

		$updates = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
		expect($updates)->toHaveCount(1);
	});

	it('refuses to advance and logs a warning when minterval is zero (FIND-004 guard)', function () {
		$now = time();

		maint_test_queue('db_fetch_row_prepared', [
			'name'      => 'broken',
			'mtype'     => 2,
			'stime'     => $now - 200,
			'etime'     => $now - 100,
			'minterval' => 0,
		]);

		expect(plugin_maint_check_schedule(4))->toBeFalse();

		$updates = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared');
		expect($updates)->toBeEmpty();
		expect($GLOBALS['__test_log'])->toHaveCount(1);
		expect($GLOBALS['__test_log'][0])->toContain('WARNING');
		expect($GLOBALS['__test_log'][0])->toContain('invalid recurring interval');
	});

	it('refuses to advance and logs a warning when minterval is negative', function () {
		$now = time();

		maint_test_queue('db_fetch_row_prepared', [
			'name'      => 'broken',
			'mtype'     => 2,
			'stime'     => $now - 200,
			'etime'     => $now - 100,
			'minterval' => -86400,
		]);

		expect(plugin_maint_check_schedule(4))->toBeFalse();

		$updates = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared');
		expect($updates)->toBeEmpty();
		expect($GLOBALS['__test_log'][0])->toContain('WARNING');
	});
});
