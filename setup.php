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

function plugin_maint_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/maint/INFO', true);
	return $info['info'];
}

function plugin_maint_install() {
	api_plugin_register_hook('maint', 'config_arrays', 'maint_config_arrays', 'setup.php');
	api_plugin_register_hook('maint', 'draw_navigation_text', 'maint_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('maint', 'is_device_in_maintenance', 'plugin_maint_check_cacti_host', 'functions.php');
	api_plugin_register_realm('maint', 'maint.php', 'Maintenance Schedules', 1);

	maint_setup_database();
}

function plugin_maint_uninstall() {
}

function plugin_maint_check_config() {
	return true;
}

function plugin_maint_upgrade() {
	return false;
}

function maint_config_arrays() {
	global $menu;
	$menu[__('Management')]['plugins/maint/maint.php'] = __('Maintenance Schedules', 'maint');
}

function maint_draw_navigation_text ($nav) {
	$nav['maint.php:'] = array('title' => __('Maintenance Schedules', 'maint'), 'mapping' => 'index.php:', 'url' => 'maint.php', 'level' => '1');
	$nav['maint.php:edit'] = array('title' => __('(edit)', 'maint'), 'mapping' => 'index.php:', 'url' => 'maint.php', 'level' => '2');
	$nav['maint.php:actions'] = array('title' => __('(actions)', 'maint'), 'mapping' => 'index.php:', 'url' => 'maint.php', 'level' => '2');
	return $nav;
}

function maint_setup_database() {
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'enabled', 'type' => 'varchar(3)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(128)', 'NULL' => true);
	$data['columns'][] = array('name' => 'mtype', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'stime', 'type' => 'int(22)', 'NULL' => false);
	$data['columns'][] = array('name' => 'etime', 'type' => 'int(22)', 'NULL' => false);
	$data['columns'][] = array('name' => 'minterval', 'type' => 'int(11)', 'NULL' => false);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'mtype', 'columns' => 'mtype');
	$data['keys'][] = array('name' => 'enabled', 'columns' => 'enabled');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Maintenance Schedules';
	api_plugin_db_table_create ('maint', 'plugin_maint_schedules', $data);

	$data = array();
	$data['columns'][] = array('name' => 'type', 'type' => 'int(6)', 'NULL' => false);
	$data['columns'][] = array('name' => 'host', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'schedule', 'type' => 'int(12)', 'NULL' => false);
	$data['primary'] = 'type`,`schedule`,`host';
	$data['keys'][] = array('name' => 'type', 'columns' => 'type');
	$data['keys'][] = array('name' => 'schedule', 'columns' => 'schedule');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Maintenance Schedules Hosts';
	api_plugin_db_table_create ('maint', 'plugin_maint_hosts', $data);
}
