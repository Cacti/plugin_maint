<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010-2017 The Cacti Group                                 |
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

function plugin_maint_check_cacti_host($host) {
	return plugin_maint_check_host(1, $host);
}

function plugin_maint_check_webseer_url($host) {
	return plugin_maint_check_host(2, $host);
}

function plugin_maint_check_host ($type, $host) {
	$schedules = db_fetch_assoc_prepared('SELECT *
		FROM plugin_maint_hosts
		WHERE TYPE = ?
		AND (host = ? OR host = 0)',
		array($type, $host));

	if (!empty($schedules)) {
		foreach ($schedules as $s) {
			if (plugin_maint_check_schedule($s['schedule'])) {
				return true;
			}
		}
	}
	return false;
}

function plugin_maint_check_schedule($schedule) {
	$sc = db_fetch_row_prepared('SELECT *
		FROM plugin_maint_schedules
		WHERE enabled = \'on\' AND id = ?',
		array($schedule));

	if (!empty($sc)) {
		$t = time();
		switch ($sc['mtype']) {
			case 1:
				if ($t > $sc['stime'] && $t < $sc['etime']) 
					return true;
				break;
			case 2:
				while ($sc['etime'] < $t) {
					$sc['etime'] += $sc['minterval'];
					$sc['stime'] += $sc['minterval'];
				}
				if ($t > $sc['stime'] && $t < $sc['etime']) 
					return true;
				break;
		}
	}
	return false;
}
