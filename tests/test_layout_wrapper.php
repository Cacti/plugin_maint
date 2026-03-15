<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | Regression checks for shared layout wrapper routing in maint.php        |
 |                                                                         |
 | Run: php tests/test_layout_wrapper.php                                  |
 +-------------------------------------------------------------------------+
 */

$pass = 0;
$fail = 0;
$events = array();

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

function top_header() {
	global $events;
	$events[] = 'top_header';
}

function bottom_footer() {
	global $events;
	$events[] = 'bottom_footer';
}

require_once __DIR__ . '/../ui_helpers.php';

$events = array();
$callback_runs = 0;

maint_render_with_layout(function () use (&$events, &$callback_runs) {
	$events[] = 'content';
	$callback_runs++;
});

assert_true('layout callback executes once', $callback_runs === 1);
assert_true('layout call order is top->content->bottom', $events === array('top_header', 'content', 'bottom_footer'));

$source = file_get_contents(__DIR__ . '/../maint.php');

assert_true(
	'edit action uses shared layout helper',
	strpos($source, "maint_render_with_layout('schedule_edit');") !== false
);
assert_true(
	'default action uses shared layout helper',
	strpos($source, "maint_render_with_layout('schedules');") !== false
);

echo "\n";
echo "Results: $pass passed, $fail failed\n";

exit($fail > 0 ? 1 : 0);
