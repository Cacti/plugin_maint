<?php

declare(strict_types=1);

/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Check if a Cacti host is in maintenance
 *
 * This is called via the 'is_device_in_maintenance' hook.
 *
 * @param int $host Host ID to check
 *
 * @return bool True if host is in active maintenance, false otherwise
 */
function plugin_maint_check_cacti_host(int $host): bool {
	return plugin_maint_check_host(1, $host);
}

/**
 * Check if a WebSeer URL is in maintenance
 *
 * @param int $host WebSeer URL ID to check
 *
 * @return bool True if URL is in active maintenance, false otherwise
 */
function plugin_maint_check_webseer_url(int $host): bool {
	return plugin_maint_check_host(2, $host);
}

/**
 * Check if a Servcheck test is in maintenance
 *
 * @param int $host Servcheck test ID to check
 *
 * @return bool True if test is in active maintenance, false otherwise
 */
function plugin_maint_check_servcheck_test(int $host): bool {
	return plugin_maint_check_host(3, $host);
}

/**
 * Check if a host is in maintenance based on type
 *
 * @param int $type Host type (1=Cacti host, 2=WebSeer URL, 3=Servcheck test)
 * @param int $host Host/URL/Test ID to check
 *
 * @return bool True if host is in active maintenance schedule, false otherwise
 */
function plugin_maint_check_host(int $type, int $host): bool {
	$schedules = db_fetch_assoc_prepared('SELECT *
		FROM plugin_maint_hosts
		WHERE TYPE = ?
		AND (host = ? OR host = 0)',
		[$type, $host],
	);

	if (!empty($schedules)) {
		foreach ($schedules as $s) {
			if (plugin_maint_check_schedule($s['schedule'])) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Check if a maintenance schedule is currently active
 *
 * Handles both one-time and recurring schedules.
 * For recurring schedules that have passed, automatically calculates
 * and updates the next occurrence.
 *
 * @param int $schedule Schedule ID to check
 *
 * @return bool True if schedule is active now, false otherwise
 */
function plugin_maint_check_schedule(int $schedule): bool {
	$sc = db_fetch_row_prepared('SELECT *
		FROM plugin_maint_schedules
		WHERE enabled = \'on\' AND id = ?',
		[$schedule],
	);

	if (!empty($sc)) {
		$t = time();

		switch ($sc['mtype']) {
			case 1:
				if ($t > $sc['stime'] && $t < $sc['etime']) {
					return true;
				}

				break;
			case 2: // Recurring
				// past, calculate next
				if ($sc['etime'] < $t) {
					// convert start and end to local so that hour stays same for add days across daylight saving time change
					$starttimelocal = (new DateTime('@' . strval($sc['stime'])))->setTimezone(new DateTimeZone(date_default_timezone_get()));
					$endtimelocal   = (new DateTime('@' . strval($sc['etime'])))->setTimezone(new DateTimeZone(date_default_timezone_get()));
					$nowtime        = new DateTime();
					// add interval days
					$addday = new DateInterval('P' . strval($sc['minterval'] / 86400) . 'D');

					while ($endtimelocal < $nowtime) {
						$starttimelocal = $starttimelocal->add($addday);
						$endtimelocal   = $endtimelocal->add($addday);
					}

					$sc['stime'] = $starttimelocal->getTimestamp();
					$sc['etime'] = $endtimelocal->getTimestamp();
					// save next interval so not need to recalculate
					db_execute_prepared('UPDATE plugin_maint_schedules
						SET stime = ?, etime = ?
						WHERE id = ?',
						[$sc['stime'], $sc['etime'], $schedule],
					);
					// format yyyy-mm-dd hh:mm
					cacti_log('INFO: Maintenance schedule "' . $sc['name'] . '" Next start ' . $starttimelocal->format('Y-m-d H:i')
							   . ' End ' . $endtimelocal->format('Y-m-d H:i'), false, 'MAINT');
				}

				if ($t > $sc['stime'] && $t < $sc['etime']) {
					return true;
				}

				break;
		}
	}

	return false;
}
