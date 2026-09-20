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

describe('plugin_maint_check_host type dispatch', function () {
	it('queries plugin_maint_hosts with type 1 for a Cacti host', function () {
		maint_test_queue('db_fetch_assoc_prepared', []);

		expect(plugin_maint_check_cacti_host(5))->toBeFalse();

		$call = $GLOBALS['__test_db_calls'][0];
		expect($call['fn'])->toBe('db_fetch_assoc_prepared');
		expect($call['params'])->toBe([1, 5]);
	});

	it('queries plugin_maint_hosts with type 2 for a WebSeer URL', function () {
		maint_test_queue('db_fetch_assoc_prepared', []);

		expect(plugin_maint_check_webseer_url(7))->toBeFalse();

		$call = $GLOBALS['__test_db_calls'][0];
		expect($call['params'])->toBe([2, 7]);
	});

	it('queries plugin_maint_hosts with type 3 for a Servcheck test', function () {
		maint_test_queue('db_fetch_assoc_prepared', []);

		expect(plugin_maint_check_servcheck_test(9))->toBeFalse();

		$call = $GLOBALS['__test_db_calls'][0];
		expect($call['params'])->toBe([3, 9]);
	});

	it('returns false when no schedules are associated with the host', function () {
		maint_test_queue('db_fetch_assoc_prepared', []);

		expect(plugin_maint_check_host(1, 5))->toBeFalse();
	});

	it('returns true as soon as one associated schedule is currently active', function () {
		$now = time();

		maint_test_queue('db_fetch_assoc_prepared', [
			['schedule' => '42'],
		]);
		maint_test_queue('db_fetch_row_prepared', [
			'mtype' => 1,
			'stime' => $now - 100,
			'etime' => $now + 100,
		]);

		expect(plugin_maint_check_host(1, 5))->toBeTrue();
	});

	it('returns false when the only associated schedule is not currently active', function () {
		$now = time();

		maint_test_queue('db_fetch_assoc_prepared', [
			['schedule' => '42'],
		]);
		maint_test_queue('db_fetch_row_prepared', [
			'mtype' => 1,
			'stime' => $now + 100,
			'etime' => $now + 200,
		]);

		expect(plugin_maint_check_host(1, 5))->toBeFalse();
	});
});
