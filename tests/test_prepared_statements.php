<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Regression checks for prepared DB helper migration in maint plugin      |
 |                                                                         |
 | Run: php tests/test_prepared_statements.php                             |
 +-------------------------------------------------------------------------+
 */

$pass = 0;
$fail = 0;

function assert_true($label, $value) {
	global $pass, $fail;

	if ($value) {
		echo "PASS  $label\n";
		$pass++;
	} else {
		echo "FAIL  $label\n";
		$fail++;
	}
}

$setup_contents = file_get_contents(__DIR__ . '/../setup.php');
$maint_contents = file_get_contents(__DIR__ . '/../maint.php');

assert_true(
	'setup.php uses prepared schedules query',
	preg_match('/db_fetch_assoc_prepared\s*\(\s*\'SELECT id,\s*name,\s*enabled,\s*mtype,\s*stime,\s*etime,\s*minterval/s', $setup_contents) === 1
);
assert_true(
	'setup.php has no raw db_fetch_assoc calls',
	preg_match('/\bdb_fetch_assoc\s*\(/', $setup_contents) === 0
);
assert_true(
	'maint.php uses prepared schedule list query',
	preg_match('/db_fetch_assoc_prepared\s*\(\s*\'SELECT \*\s+FROM plugin_maint_schedules/s', $maint_contents) === 1
);
assert_true(
	'maint.php uses prepared site list query',
	preg_match('/db_fetch_assoc_prepared\s*\(\s*\'SELECT id,\s*name\s+FROM sites/s', $maint_contents) === 1
);
assert_true(
	'maint.php uses prepared poller list query',
	preg_match('/db_fetch_assoc_prepared\s*\(\s*\'SELECT id,\s*name\s+FROM poller/s', $maint_contents) === 1
);
assert_true(
	'maint.php has no raw db_fetch_assoc calls',
	preg_match('/\bdb_fetch_assoc\s*\(/', $maint_contents) === 0
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
