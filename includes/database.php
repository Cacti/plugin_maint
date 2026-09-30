<?php
/*
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * The plugin_maint_schedules table definition (one row per maintenance
 * schedule), shared by the create path (api_plugin_db_table_create()) and
 * the upgrade path (db_update_table()) so both stay in sync from a single
 * definition.
 *
 * @return array<string, mixed> The table definition array.
 */
function maint_schedules_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'enabled', 'type' => 'varchar(3)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(128)', 'NULL' => true];
	$data['columns'][] = ['name' => 'mtype', 'type' => 'int(11)', 'NULL' => false];
	$data['columns'][] = ['name' => 'stime', 'type' => 'int(22)', 'NULL' => false];
	$data['columns'][] = ['name' => 'etime', 'type' => 'int(22)', 'NULL' => false];
	$data['columns'][] = ['name' => 'minterval', 'type' => 'int(11)', 'NULL' => false];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'mtype', 'columns' => ['mtype']];
	$data['keys'][]    = ['name' => 'enabled', 'columns' => ['enabled']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Maintenance Schedules';

	return $data;
}

/**
 * The plugin_maint_hosts table definition (maps devices/tests to a
 * maintenance schedule), shared by the create path
 * (api_plugin_db_table_create()) and the upgrade path (db_update_table()).
 *
 * @return array<string, mixed> The table definition array.
 */
function maint_hosts_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'type', 'type' => 'int(6)', 'NULL' => false];
	$data['columns'][] = ['name' => 'host', 'type' => 'int(12)', 'NULL' => false];
	$data['columns'][] = ['name' => 'schedule', 'type' => 'int(12)', 'NULL' => false];
	$data['primary']   = ['type', 'schedule', 'host'];
	$data['keys'][]    = ['name' => 'type', 'columns' => ['type']];
	$data['keys'][]    = ['name' => 'schedule', 'columns' => ['schedule']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Maintenance Schedules Hosts';

	return $data;
}

/**
 * Creates this plugin's database tables (plugin_maint_schedules and
 * plugin_maint_hosts) through Cacti's tracked plugin table API. Called
 * from plugin_maint_install() during installation, and re-run (safely, as
 * a no-op for already-applied changes) from maint_upgrade_tables() during
 * upgrades.
 *
 * @return void
 */
function maint_setup_database(): void {
	api_plugin_db_table_create('maint', 'plugin_maint_schedules', maint_schedules_table_data());
	api_plugin_db_table_create('maint', 'plugin_maint_hosts', maint_hosts_table_data());
}

/**
 * Refreshes this plugin's tables to their current definition on upgrade:
 * db_update_table() diffs the live schema against each definition and
 * issues the exact ALTER when the table already exists, otherwise the
 * table is created outright. Called from plugin_maint_check_upgrade() when
 * the stored version changes.
 *
 * @return void
 */
function maint_upgrade_tables(): void {
	foreach ([
		'plugin_maint_schedules' => maint_schedules_table_data(),
		'plugin_maint_hosts'     => maint_hosts_table_data(),
	] as $table => $data) {
		if (db_table_exists($table)) {
			db_update_table($table, $data);
		} else {
			api_plugin_db_table_create('maint', $table, $data);
		}
	}
}
