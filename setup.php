<?php

/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
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

function plugin_maint_version()
{
    global $config;

    $info = parse_ini_file($config['base_path'] . '/plugins/maint/INFO', true);

    return $info['info'];
}

function plugin_maint_install()
{
    api_plugin_register_hook('maint', 'config_arrays', 'maint_config_arrays', 'setup.php');
    api_plugin_register_hook('maint', 'draw_navigation_text', 'maint_draw_navigation_text', 'setup.php');
    api_plugin_register_hook('maint', 'device_edit_top_links', 'maint_device_edit_top_links', 'setup.php');
    api_plugin_register_hook('maint', 'is_device_in_maintenance', 'plugin_maint_check_cacti_host', 'functions.php');
    api_plugin_register_hook('maint', 'device_action_array', 'maint_device_action_array', 'setup.php');
    api_plugin_register_hook('maint', 'device_action_prepare', 'maint_device_action_prepare', 'setup.php');
    api_plugin_register_hook('maint', 'device_action_execute', 'maint_device_action_execute', 'setup.php');
    api_plugin_register_realm('maint', 'maint.php', 'Maintenance Schedules', 1);

    maint_setup_database();
}

function plugin_maint_uninstall() {}

function plugin_maint_check_config()
{
    return true;
}

function plugin_maint_upgrade()
{
    return false;
}

function maint_config_arrays()
{
    global $menu;

    $menu[__('Management')]['plugins/maint/maint.php'] = __('Maintenance Schedules', 'maint');

    if (function_exists('auth_augment_roles')) {
        auth_augment_roles(__('System Administration'), ['maint.php']);
    }
}

function maint_draw_navigation_text($nav)
{
    $nav['maint.php:'] = ['title' => __('Maintenance Schedules', 'maint'), 'mapping' => 'index.php:', 'url' => 'maint.php', 'level' => '1'];
    $nav['maint.php:edit'] = ['title' => __('(edit)', 'maint'), 'mapping' => 'index.php:', 'url' => 'maint.php', 'level' => '2'];
    $nav['maint.php:actions'] = ['title' => __('(actions)', 'maint'), 'mapping' => 'index.php:', 'url' => 'maint.php', 'level' => '2'];

    return $nav;
}

// Centralized action labels to avoid duplication
if (!defined('MAINT_LABEL_ENABLE_NOW')) {
    define('MAINT_LABEL_ENABLE_NOW', __('Enable maintenance for this device (now+1h)', 'maint'));
}
if (!defined('MAINT_LABEL_ADD_TO_SCHEDULE')) {
    define('MAINT_LABEL_ADD_TO_SCHEDULE', __('Add device(s) to existing maintenance schedule', 'maint'));
}

function maint_device_edit_top_links()
{
    global $config;

    // Get current device id from edit context
    $host_id = get_filter_request_var('id');
    if (empty($host_id)) {
        return;
    }

    $action_url = $config['url_path'] . 'host.php';
    $uid = 'maint_' . (int) $host_id . '_' . mt_rand(1000, 9999);

    // Hidden form: Enable maintenance (now+1h) using same flow as device list dropdown
    print "<form id='{$uid}_f1' method='post' action='" . html_escape($action_url) . "' style='display:none'>";
    print "<input type='hidden' name='action' value='actions'>";
    print "<input type='hidden' name='drp_action' value='maint'>";
    // Simulate selection of this device as if checked in list
    print "<input type='hidden' name='chk_" . (int) $host_id . "' value='on'>";
    print "</form>";

    // Hidden form: Add device to existing schedule
    print "<form id='{$uid}_f2' method='post' action='" . html_escape($action_url) . "' style='display:none'>";
    print "<input type='hidden' name='action' value='actions'>";
    print "<input type='hidden' name='drp_action' value='maint_add_to_schedule'>";
    print "<input type='hidden' name='chk_" . (int) $host_id . "' value='on'>";
    print "</form>";

    // Links that submit the forms above, reusing the exact same confirmation UI
    print "<br><span class='linkMarker'>*</span><a class='hyperLink' href='#' onclick=\"document.getElementById('{$uid}_f1').submit(); return false;\">" . MAINT_LABEL_ENABLE_NOW . "</a><br>";
    print "<span class='linkMarker'>*</span><a class='hyperLink' href='#' onclick=\"document.getElementById('{$uid}_f2').submit(); return false;\">" . MAINT_LABEL_ADD_TO_SCHEDULE . "</a>";
}

function maint_device_action_array($actions)
{
    $actions['maint'] = MAINT_LABEL_ENABLE_NOW;
    $actions['maint_add_to_schedule'] = MAINT_LABEL_ADD_TO_SCHEDULE;

    return $actions;
}

function maint_device_action_prepare($save)
{
    // Render confirmation details for the maint action
    if (isset($save['drp_action']) && $save['drp_action'] == 'maint') {
        $now = time();
        $one_hour_later = $now + 3600;

        // Human readable time window
        $time_window = date(date_time_format(), $now) . ' - ' . date(date_time_format(), $one_hour_later);

        // Build device list
        $host_list = '';
        if (!empty($save['host_array']) && is_array($save['host_array'])) {
            foreach ($save['host_array'] as $host_id) {
                $row = db_fetch_row_prepared('SELECT description FROM host WHERE id = ?', [(int) $host_id]);

                if (!empty($row)) {
                    $host_list .= '<li>' . html_escape($row['description']) . '</li>';
                }
            }
        }

        // Output a styled summary box with the window and devices
        $tz = function_exists('date_default_timezone_get') ? date_default_timezone_get() : '';
        $duration_min = round(($one_hour_later - $now) / 60);

        $summary  = "<div style='border:1px solid #e0e0e0;border-left:4px solid #4caf50;background:#f9fffa;padding:8px 12px;margin:6px 0;'>";
        $summary .=     "<div style='display:flex;'>";
        $summary .=         "<b> " . __('Maintenance Window', 'maint') . "</b><span style='color:#666'>(now + " . intval($duration_min) . " min): </span>";
        $summary .=         "<span style='font-family:monospace;margin-left:2em;'>" . html_escape($time_window) . "</span>";
        $summary .=     "</div>";
        if (!empty($tz)) {
            $summary .= "<div style='margin-top:6px;color:#666;font-size:11px;'>" . __('Timezone', 'maint') . ": " . html_escape($tz) . " • " . __('Duration', 'maint') . ": " . intval($duration_min) . " " . __('min', 'maint') . "</div>";
        }
        $summary .= "</div>";

        // Maintenance window full-width box
        print "<tr><td colspan='2' class='textArea'>" . $summary . "</td></tr>";

        // Aligned Schedule Name full-width row
        $label_name = __('Schedule name', 'maint');
        $ph_name    = __esc('e.g. Emergency patching', 'maint');

        print "<tr><td colspan='2' class='textArea'>"
            . "<div style='border:1px solid #efefef;background:#fafafa;padding:8px 12px;'>"
            . "<div style='display:flex;align-items:center;gap:12px;'>"
            . "<div style='min-width:180px;font-weight:bold;'>" . html_escape($label_name) . ":</div>"
            . "<input type='text' name='maint_schedule_name' value='' size='50' placeholder='" . $ph_name . "'>"
            . "</div>"
            . "</div>"
            . "</td></tr>";

        // Aligned Schedule Type full-width row
        $one_time_label  = __('One Time', 'maint');
        $recurring_label = __('Recurring', 'maint');

        $every_day  = __('Every Day', 'maint');
        $every_week = __('Every Week', 'maint');
        $label_type = __('Schedule Type', 'maint');

        $row  = "<div style='border:1px solid #efefef;background:#fafafa;padding:8px 12px;'>";
        $row .= "<div style='display:flex;align-items:center;gap:12px;'>";
        $row .= "<div style='min-width:180px;font-weight:bold;'>" . html_escape($label_type) . ":</div>";

        $row .= "<select name='maint_mtype' id='maint_mtype' style='min-width:220px' onchange=\"document.getElementById('maint_interval_wrap').style.display=(this.value==='2')?'':'none'\">"
            . "<option value='1' selected>" . html_escape($one_time_label) . "</option>"
            . "<option value='2'>" . html_escape($recurring_label) . "</option>"
            . "</select>";

        $row .=     "<span id='maint_interval_wrap' style='margin-left:12px; display:none;'>"
            . "<select name='maint_minterval' id='maint_minterval'>"
            . "<option value='86400'>" . html_escape($every_day) . "</option>"
            . "<option value='604800'>" . html_escape($every_week) . "</option>"
            . "</select>"
            . "</span>";

        $row .=   "</div>";
        $row .= "</div>";

        print "<tr><td colspan='2' class='textArea'>" . $row . "</td></tr>";

        // Initialize visibility on load
        print "<tr style='display:none'><td colspan='2'><script>(function(){try{var e=document.getElementById('maint_mtype'),w=document.getElementById('maint_interval_wrap');if(e&&w){w.style.display=(e.value==='2')?'':'none';}}catch(ex){}})();</script></td></tr>";

        $devices  = "<div style='border:1px solid #efefef;background:#fafafa;padding:8px 12px;'>";
        $devices .=     "<b>" . __('Devices', 'maint') . " (" . count((array) $save['host_array']) . ")</b>";
        $devices .=     "<ul style='margin:6px 0 0 18px;'>" . $host_list . "</ul>";
        $devices .= "</div>";

        print "<tr><td colspan='2' class='textArea'>" . $devices . "</td></tr>";
    } elseif (isset($save['drp_action']) && $save['drp_action'] == 'maint_add_to_schedule') {
        // Build device list
        $host_list = '';

        if (!empty($save['host_array']) && is_array($save['host_array'])) {
            foreach ($save['host_array'] as $host_id) {
                $row = db_fetch_row_prepared('SELECT description FROM host WHERE id = ?', [(int) $host_id]);

                if (!empty($row)) {
                    $host_list .= '<li>' . html_escape($row['description']) . '</li>';
                }
            }
        }

        // Load schedules to choose from
        $schedules = db_fetch_assoc('SELECT id, name, enabled, mtype, stime, etime, minterval 
			FROM plugin_maint_schedules 
			ORDER BY name');

        $select = "<select name='maint_schedule_id' style='min-width:360px'>";
        if (!empty($schedules)) {
            foreach ($schedules as $sc) {
                $label_name = !empty($sc['name']) ? $sc['name'] : ('#' . (int) $sc['id']);
                $window     = date(date_time_format(), (int) $sc['stime']) . ' → ' . date(date_time_format(), (int) $sc['etime']);
                $state      = ($sc['enabled'] === 'on') ? '' : ' (' . __('disabled', 'maint') . ')';
                $select    .= "<option value='" . (int) $sc['id'] . "'>" . html_escape($label_name . ' — ' . $window . $state) . "</option>";
            }
        } else {
            $select .= "<option value='' disabled>" . html_escape(__('No schedules found', 'maint')) . "</option>";
        }
        $select .= '</select>';

        print "<tr><td class='textArea'>" . __('Select schedule', 'maint') . ":</td><td class='textArea'>" . $select . "</td></tr>";
        print "<tr><td colspan='2' class='textArea'><div style='border:1px solid #efefef;background:#fafafa;padding:8px 12px;'><b>" . __('Devices', 'maint') . " (" . count((array) $save['host_array']) . ")</b><ul style='margin:6px 0 0 18px;'>" . $host_list . "</ul></div></td></tr>";
    }

    return $save;
}

function maint_device_action_execute($action)
{
    if ($action == 'maint') {
        // One-hour quick maintenance: create a new schedule and attach devices
        $now = time();
        $one_hour_later = $now + 3600;
        $name = '';

        if (isset($_POST['maint_schedule_name'])) {
            $name = trim($_POST['maint_schedule_name']);
        }

        if ($name === '') {
            $name = __('Quick Maintenance', 'maint');
        }
        $mtype = isset($_POST['maint_mtype']) ? (int) $_POST['maint_mtype'] : 1;
        $minterval = 0;
        if ($mtype === 2) {
            $minterval = isset($_POST['maint_minterval']) ? (int) $_POST['maint_minterval'] : 86400;
            if ($minterval !== 86400 && $minterval !== 604800) {
                $minterval = 86400;
            }
        }

        // Create a new schedule (one-time or recurring)
        db_execute_prepared(
            'INSERT INTO plugin_maint_schedules 
			(enabled, name, mtype, stime, etime, minterval) 
			VALUES ("on", ?, ?, ?, ?, ?)',
            [$name, (int) $mtype, (int) $now, (int) $one_hour_later, (int) $minterval],
        );

        $schedule_id = db_fetch_insert_id();

        // Associate selected devices to the new schedule
        $associated = 0;

        if ($schedule_id && isset($_POST['selected_items'])) {
            $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

            if (is_array($selected_items)) {
                foreach ($selected_items as $host_id) {
                    db_execute_prepared(
                        'REPLACE INTO plugin_maint_hosts 
						(type, host, schedule) 
						VALUES (1, ?, ?)',
                        [(int) $host_id, (int) $schedule_id],
                    );

                    $associated++;
                }
            }
        }

        // Show info message like other Cacti actions
        raise_message('maint_created', __esc("Maintenance schedule '%s' created and %d device(s) associated.", $name, $associated, 'maint'), MESSAGE_LEVEL_INFO);

        return true;
    } elseif ($action == 'maint_add_to_schedule') {
        $schedule_id = isset($_POST['maint_schedule_id']) ? (int) $_POST['maint_schedule_id'] : 0;
        if ($schedule_id <= 0 || !db_fetch_cell_prepared('SELECT id FROM plugin_maint_schedules WHERE id = ?', [$schedule_id])) {
            return false;
        }

        $added = 0;
        if (isset($_POST['selected_items'])) {
            $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
            if (is_array($selected_items)) {
                foreach ($selected_items as $host_id) {
                    db_execute_prepared('REPLACE INTO plugin_maint_hosts (type, host, schedule) VALUES (1, ?, ?)', [(int) $host_id, $schedule_id]);
                    $added++;
                }
            }
        }

        $sname = db_fetch_cell_prepared('SELECT name FROM plugin_maint_schedules WHERE id = ?', [$schedule_id]);

        if ($sname === null || $sname === '') {
            $sname = '#' . $schedule_id;
        }

        raise_message('maint_added', __esc("%d device(s) added to maintenance schedule '%s'.", $added, $sname, 'maint'), MESSAGE_LEVEL_INFO);

        return true;
    }

    return false;
}

function maint_setup_database()
{
    $data = [];
    $data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
    $data['columns'][] = ['name' => 'enabled', 'type' => 'varchar(3)', 'NULL' => false, 'default' => 'on'];
    $data['columns'][] = ['name' => 'name', 'type' => 'varchar(128)', 'NULL' => true];
    $data['columns'][] = ['name' => 'mtype', 'type' => 'int(11)', 'NULL' => false];
    $data['columns'][] = ['name' => 'stime', 'type' => 'int(22)', 'NULL' => false];
    $data['columns'][] = ['name' => 'etime', 'type' => 'int(22)', 'NULL' => false];
    $data['columns'][] = ['name' => 'minterval', 'type' => 'int(11)', 'NULL' => false];
    $data['primary'] = 'id';
    $data['keys'][] = ['name' => 'mtype', 'columns' => 'mtype'];
    $data['keys'][] = ['name' => 'enabled', 'columns' => 'enabled'];
    $data['type'] = 'InnoDB';
    $data['comment'] = 'Maintenance Schedules';

    api_plugin_db_table_create('maint', 'plugin_maint_schedules', $data);

    $data = [];
    $data['columns'][] = ['name' => 'type', 'type' => 'int(6)', 'NULL' => false];
    $data['columns'][] = ['name' => 'host', 'type' => 'int(12)', 'NULL' => false];
    $data['columns'][] = ['name' => 'schedule', 'type' => 'int(12)', 'NULL' => false];
    $data['primary'] = 'type`,`schedule`,`host';
    $data['keys'][] = ['name' => 'type', 'columns' => 'type'];
    $data['keys'][] = ['name' => 'schedule', 'columns' => 'schedule'];
    $data['type'] = 'InnoDB';
    $data['comment'] = 'Maintenance Schedules Hosts';

    api_plugin_db_table_create('maint', 'plugin_maint_hosts', $data);
}
