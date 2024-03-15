<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/maint/functions.php');

define('MAINT_HOST_FILTER_LOC_ANY', '__any'); // internal value unlikely in data
define('MAINT_HOST_FILTER_LOC_NONE', '__none');
define('MAINT_HOST_FILTER_ANY', '-1');
define('MAINT_HOST_FILTER_NONE', '0'); // Must be zero to match host table values for None

// Maint Schedule Actions
$actions = array(
	1 => __('Update Time (Now + 1 Hour)', 'maint'),
	2 => __('Delete', 'maint')
);

// Host Maint Schedule Actions
$assoc_actions = array(
	1 => __('Associate', 'maint'),
	2 => __('Disassociate', 'maint')
);

$maint_types = array (
	1 => __('One Time', 'maint'),
	2 => __('Recurring', 'maint')
);

$maint_intervals = array(
	0      => __('Not Defined', 'maint'),
	86400  => __('Every Day', 'maint'),
	604800 => __('Every Week', 'maint')
);

$yesno = array(
	''    => __('No', 'maint'), // table value
	0     => __('No', 'maint'),
	1     => __('Yes', 'maint'),
	'on'  => __('Yes', 'maint'),
	'off' => __('No', 'maint')
);

// Present a tabbed interface
$tabs = array(
	'general' => __('General', 'maint')
);

if (api_plugin_is_enabled('thold')) {
	$tabs['hosts'] = __('Devices', 'maint');
	define('MAINT_HOST_TYPE_HOSTS', '1');
}

if (api_plugin_is_enabled('webseer')) {
	$tabs['webseer'] = __('WebSeer', 'maint');
	define('MAINT_HOST_TYPE_WEBSEER', '2');
}

$tabs = api_plugin_hook_function('maint_tabs', $tabs);

set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();
		break;
	case 'actions':
		form_actions();
		break;
	case 'edit':
		top_header();
		schedule_edit();
		bottom_footer();
		break;
	default:
		top_header();
		schedules();
		bottom_footer();
		break;
}

function schedule_delete() {
	$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

	if ($selected_items != false) {
		foreach($selected_items as $id) {
			db_execute_prepared('DELETE FROM plugin_maint_schedules WHERE id=? LIMIT 1', array($id));
			db_execute_prepared('DELETE FROM plugin_maint_hosts WHERE schedule = ?', array($id));
		}
	}

	header('Location: maint.php?header=false');

	exit;
}

function schedule_update() {
	$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
	if ($selected_items != false) {
		foreach($selected_items as $id) {
			$stime = intval(time()/60)*60;
			$etime = $stime + 3600;
			db_execute_prepared('UPDATE plugin_maint_schedules
				SET stime = ?, etime = ?
				WHERE id = ?
				LIMIT 1',
				array($stime, $etime, $id));
		}
	}

	header('Location: maint.php?header=false');

	exit;
}


function form_save() {
	global $plugins;

	if (isset_request_var('save_component')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('mtype');
		get_filter_request_var('minterval');

		if (isset_request_var('name')) {
			/* Remove HTML <> */
			set_request_var('name', trim(str_replace(array("\\", "'", '"', '<', '>'), '', get_nfilter_request_var('name'))));
		}
		if (isset_request_var('stime')) {
			set_request_var('stime', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('stime'))));
		}
		if (isset_request_var('etime')) {
			set_request_var('etime', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('etime'))));
		}
		/* ==================================================== */

		$save['id']        = get_nfilter_request_var('id');
		$save['name']      = get_nfilter_request_var('name');
		$save['mtype']     = get_nfilter_request_var('mtype');
		$save['stime']     = strtotime(get_nfilter_request_var('stime'));
		$save['etime']     = strtotime(get_nfilter_request_var('etime'));
		$save['minterval'] = get_nfilter_request_var('minterval');

		if (isset_request_var('enabled')) {
			$save['enabled'] = 'on';
		} else {
			$save['enabled'] = '';
		}

		if ($save['mtype'] == 1) {
			$save['minterval'] = 0;
		}

		if ($save['stime'] >= $save['etime']) {
			raise_message(2);
		}

		if (!is_error_message()) {
			$id = sql_save($save, 'plugin_maint_schedules');
			if ($id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: maint.php?tab=general&action=edit&header=false&id=' . (empty($id) ? $save['id'] : $id));

		exit;
	}
}

function form_actions() {
	global $actions, $assoc_actions;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ================= input validation ================= */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		if (isset_request_var('save_list')) {
			if (get_request_var('drp_action') == '2') { /* delete */
				schedule_delete();
			}elseif (get_request_var('drp_action') == '1') { /* update */
				schedule_update();
			}

			header('Location: maint.php?header=false');

			exit;
		}elseif (isset_request_var('save_hosts')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				if (get_request_var('drp_action') == '1') { /* associate */
					for ($i = 0; ($i < count($selected_items)); $i++) {
						db_execute_prepared('REPLACE INTO plugin_maint_hosts (type, host, schedule)
							VALUES (?, ?, ?)',
							array(MAINT_HOST_TYPE_HOSTS, $selected_items[$i], get_request_var('id')));
					}
				}elseif (get_request_var('drp_action') == '2') { /* disassociate */
					for ($i = 0; ($i < count($selected_items)); $i++) {
						db_execute_prepared('DELETE FROM plugin_maint_hosts
							WHERE type = ? AND host = ? AND schedule = ?',
							array(MAINT_HOST_TYPE_HOSTS, $selected_items[$i], get_request_var('id')));
					}
				}
			}

			header('Location: maint.php?action=edit&tab=hosts&header=false&id=' . get_request_var('id'));

			exit;
		}elseif (isset_request_var('save_webseer')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				if (get_request_var('drp_action') == '1') { /* associate */
					for ($i = 0; ($i<count($selected_items)); $i++) {
						db_execute_prepared('REPLACE INTO plugin_maint_hosts (type, host, schedule)
							VALUES (?, ?, ?)',
							array(MAINT_HOST_TYPE_WEBSEER, $selected_items[$i], get_request_var('id')));
					}
				}elseif (get_request_var('drp_action') == '2') { /* disassociate */
					for ($i = 0; ($i < count($selected_items)); $i++) {
						db_execute_prepared('DELETE FROM plugin_maint_hosts
							WHERE type = ? AND host = ? AND schedule = ?',
							array(MAINT_HOST_TYPE_WEBSEER, $selected_items[$i], get_request_var('id')));
					}
				}
			}

			header('Location: maint.php?action=edit&tab=webseer&header=false&id=' . get_request_var('id'));

			exit;
		}else{
			api_plugin_hook_function('maint_actions_execute');
		}
	}

	/* setup some variables */
	$list = ''; $array = array(); $list_name = '';
	if (isset_request_var('id')) {
		$list_name = html_escape(
			db_fetch_cell_prepared('SELECT name
			FROM plugin_maint_schedules
			WHERE id = ?',
			array(get_request_var('id'))));
	}

	if (isset_request_var('save_list')) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		foreach($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= '<li><b>' . html_escape(
					db_fetch_cell_prepared('SELECT name
						FROM plugin_maint_schedules
						WHERE id=?',
						array($matches[1]))) .
					'</b></li>';
				$array[] = $matches[1];
			}
		}

		top_header();

		form_start('maint.php');

		html_start_box($actions[get_request_var('drp_action')] . " $list_name", '60%', '', '3', 'center', '');

		if (cacti_sizeof($array)) {
			if (get_request_var('drp_action') == '1') { /* update */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Update the following Maintenance Schedule(s).', 'maint') . "</p>
						<ul>$list</ul>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'maint') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'maint') . "' title='" . __esc('Update Maintenance Schedule(s)', 'maint') . "'>";
			}elseif (get_request_var('drp_action') == '2') { /* delete */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to Delete the following Maintenance Schedule(s).  Any Devices(s) Associated with this Schedule will be Disassociated.', 'maint') . "</p>
						<ul>$list</ul>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'maint') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'maint') . "' title='" . __esc('Delete Maintenance Schedule(s)', 'maint') . "'>";
			}
		} else {
			print "<tr><td><span class='textError'>" . __('You must select at least one Maintenance Schedule.', 'maint') . "</span></td></tr>\n";
			$save_html = "<input type='button' value='" . __esc('Return', 'maint') . "' onClick='cactiReturnTo()'>";
		}

		print "<tr class='saveRow'>
			<td>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='save_list' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
				<input type='hidden' name='id' value='" . get_request_var('id') . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		form_end();

		bottom_footer();
	}elseif (isset_request_var('save_hosts')) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		foreach($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */
				$description = db_fetch_cell_prepared('SELECT description
					FROM host
					WHERE id = ?',
					array($matches[1]));

				$list .= '<li><b>' . html_escape($description) . '</b></li>';
				$array[] = $matches[1];
			}
		}

		top_header();

		form_start('maint.php');

		html_start_box($assoc_actions[get_request_var('drp_action')] . ' ' . __('Device(s)', 'maint'), '60%', '', '3', 'center', '');

		if (cacti_sizeof($array)) {
			if (get_request_var('drp_action') == '1') { /* associate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to associate the following Device(s) with the Maintenance Schedule \'<b>%s</b>\'.', $list_name, 'maint') . "</p>
						<ul>$list</ul>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'maint') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'maint') . "' title='" . __esc('Associate Maintenance Schedule(s)', 'maint') . "'>";
			}elseif (get_request_var('drp_action') == '2') { /* disassociate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to disassociate the following Device(s) with the Maintenance Schedule \'<b>%s</b>\'.', $list_name, 'maint') . "</p>
						<ul>$list</ul>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'maint') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'maint') . "' title='" . __esc('Disassociate Maintenance Schedule(s)', 'maint') . "'>";
			}
		} else {
			print "<tr><td><span class='textError'>" . __('You must select at least one Device.', 'maint') . "</span></td></tr>\n";
			$save_html = "<input type='button' value='" . __esc('Return', 'maint') . "' onClick='cactiReturnTo()'>";
		}

		print "<tr class='saveRow'>
			<td>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var('id') . "'>
				<input type='hidden' name='save_hosts' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		form_end();

		bottom_footer();
	}elseif (isset_request_var('save_webseer')) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$description = db_fetch_cell_prepared('SELECT display_name
					FROM plugin_webseer_urls
					WHERE id = ?',
					array($matches[1]));

				$list .= '<li><b>' . html_escape($description) . '</b></li>';
				$array[] = $matches[1];
			}
		}

		top_header();

		form_start('maint.php');

		html_start_box($assoc_actions[get_request_var('drp_action')] . ' ' . __('Webseer(s)', 'maint'), '60%', '', '3', 'center', '');

		if (cacti_sizeof($array)) {
			if (get_request_var('drp_action') == '1') { /* associate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to associate the Webseer(s) below with the Maintenance Schedule \'<b>%s</b>\'.', $list_name, 'maint') . "</p>
						<ul>$list</ul>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'maint') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'maint') . "' title='" . __esc('Associate Maintenance Schedule(s)', 'maint') . "'>";
			}elseif (get_request_var('drp_action') == '2') { /* disassociate */
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to disassociate the Webseer(s) below from the Maintenance Schedule \'<b>%s</b>\'.', $list_name, 'maint') . "</p>
						<ul>$list</ul>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='" . __esc('Cancel', 'maint') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'maint') . "' title='" . __esc('Disassociate Maintenance Schedule(s)', 'maint') . "'>";
			}
		} else {
			print "<tr><td><span class='textError'>" . __('You must select at least one Webseer.', 'maint') . "</span></td></tr>\n";
			$save_html = "<input type='button' value='" . __esc('Return', 'maint') . "' onClick='cactiReturnTo()'>";
		}

		print "<tr class='saveRow'>
			<td>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var('id') . "'>
				<input type='hidden' name='save_webseer' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		form_end();

		bottom_footer();
	} else {
		api_plugin_hook_function('maint_actions_prepare');
	}
}

function get_header_label() {
	if (!isempty_request_var('id')) {
		$list = db_fetch_row_prepared('SELECT *
			FROM plugin_maint_schedules
			WHERE id = ?',
			array(get_filter_request_var('id')));
		$header_label = __esc('[edit: %s]', $list['name'], 'maint');
	} else {
		$header_label = __('[new]', 'maint');
	}

	return $header_label;
}

function maint_tabs() {
	global $config, $tabs;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));
	/* ==================================================== */

	load_current_session_value('tab', 'sess_maint_tab', 'general');
	$current_tab = get_request_var('tab');

	print "<div class='tabs'><nav><ul>\n";

	if (cacti_sizeof($tabs)) {
		foreach (array_keys($tabs) as $tab_short_name) {
            print "<li><a class='tab" . (($tab_short_name == $current_tab) ? ' selected' : '') .  "' href='" . htmlspecialchars($config['url_path'] .
				'plugins/maint/maint.php?action=edit' .
				'&tab=' . $tab_short_name .
				(isset_request_var('id') ? '&id=' . get_request_var('id'):'')) .
				"'>" . $tabs[$tab_short_name] . "</a></li>\n";
		}
	}
	print "</ul></nav></div>\n";
}

function schedule_edit() {
	global $plugins, $config, $tabs, $maint_types, $maint_intervals;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	maint_tabs();

	if (isset_request_var('id')) {
		$id = get_request_var('id');
		$maint_item_data = db_fetch_row_prepared('SELECT *
			FROM plugin_maint_schedules
			WHERE id = ?',
			array($id));
	} else {
		$id = 0;
		$maint_item_data = array('id' => 0, 'name' => __('New Maintenance Schedule', 'maint'), 'enabled' => 'on', 'mtype' => 1, 'stime' => time(), 'etime' => time() + 3600, 'minterval' => 0);
	}

	$header_label = get_header_label();

	if (get_request_var('tab') == 'general') {
		form_start('maint.php', 'maint');

		html_start_box(__('General Settings %s', htmlspecialchars($header_label), 'maint'), '100%', '', '3', 'center', '');

		$form_array = array(
			'general_header' => array(
				'friendly_name' => __('Schedule', 'maint'),
				'method' => 'spacer'
			),
			'name' => array(
				'friendly_name' => __('Schedule Name', 'maint'),
				'method' => 'textbox',
				'max_length' => 100,
				'default' => $maint_item_data['name'],
				'description' => __('Provide the Maintenance Schedule a meaningful name', 'maint'),
				'value' => isset($maint_item_data['name']) ? $maint_item_data['name'] : ''
			),
			'enabled' => array(
				'friendly_name' => __('Enabled', 'maint'),
				'method' => 'checkbox',
				'default' => 'on',
				'description' => __('Whether or not this schedule will be checked.', 'maint'),
				'value' => isset($maint_item_data['enabled']) ? ($maint_item_data['enabled'] == 'on' ? 'on' : 'off') : ''
			),
			'mtype' => array(
				'friendly_name' => __('Schedule Type', 'maint'),
				'method' => 'drop_array',
				'on_change' => 'changemaintType()',
				'array' => $maint_types,
				'description' => __('The type of schedule, one time or recurring.', 'maint'),
				'value' => isset($maint_item_data['mtype']) ? $maint_item_data['mtype'] : ''
			),
			'minterval' => array(
				'friendly_name' => __('Interval', 'maint'),
				'method' => 'drop_array',
				'array' => $maint_intervals,
				'default' => 86400,
				'description' => __('This is the interval in which the start / end time will repeat.', 'maint'),
				'value' => isset($maint_item_data['minterval']) ? $maint_item_data['minterval'] : '1'
			),
			'stime' => array(
				'friendly_name' => __('Start Time', 'maint'),
				'method' => 'textbox',
				'max_length' => 22,
				'size' => 22,
				'description' => __('The start date / time for this schedule. Most date / time formats accepted.', 'maint'),
				'default' => date(date_time_format(), time()),
				'value' => isset($maint_item_data['stime']) ?  date(date_time_format(), $maint_item_data['stime']) : ''
			),
			'etime' => array(
				'friendly_name' => __('End Time', 'maint'),
				'method' => 'textbox',
				'max_length' => 22,
				'size' => 22,
				'default' => date(date_time_format(), time() + 3600),
				'description' => __('The end date / time for this schedule. Most date / time formats accepted.', 'maint'),
				'value' => isset($maint_item_data['etime']) ? date(date_time_format(), $maint_item_data['etime']) : ''
			),
			'save_component' => array(
				'method' => 'hidden',
				'value' => '1'
			),
			'save' => array(
				'method' => 'hidden',
				'value'  => 'edit'
			),
			'id' => array(
				'method' => 'hidden',
				'value' => $id
			)
		);

		draw_edit_form(
			array(
				'config' => array(
					'no_form_tag' => true
					),
				'fields' => $form_array
			)
		);

		html_end_box();

		form_save_button('maint.php', 'return');

		?>
		<script type='text/javascript'>

		var date1Open = false;
		var date2Open = false;

		function changemaintType () {
			type = $('#mtype').val();
			switch(type) {
			case '1':
				$('#row_minterval').hide();
				break;
			case '2':
				$('#row_minterval').show();
				break;
			}
		}

		$(function() {
			$('#stime').after('<i id="startDate" class="calendar fa fa-calendar" title="<?php print __esc('Start Date/Time Selector', 'maint');?>"></i>');
			$('#etime').after('<i id="endDate" class="calendar fa fa-calendar" title="<?php print __esc('End Date/Time Selector', 'maint');?>"></i>');
			$('#startDate').click(function() {
				if (date1Open) {
					date1Open = false;
					$('#stime').datetimepicker('hide');
				}else{
					date1Open = true;
					$('#stime').datetimepicker('show');
				}
			});

			$('#endDate').click(function() {
				if (date2Open) {
					date2Open = false;
					$('#etime').datetimepicker('hide');
				}else{
					date2Open = true;
					$('#etime').datetimepicker('show');
				}
			});

			changemaintType ();

			$('#stime').datetimepicker({
				minuteGrid: 10,
				stepMinute: 1,
				showAnim: 'slideDown',
				numberOfMonths: 1,
				timeFormat: 'HH:mm',
				dateFormat: 'yy-mm-dd',
				showButtonPanel: false
			});

			$('#etime').datetimepicker({
				minuteGrid: 10,
				stepMinute: 1,
				showAnim: 'slideDown',
				numberOfMonths: 1,
				timeFormat: 'HH:mm',
				dateFormat: 'yy-mm-dd',
				showButtonPanel: false
			});
		});
		</script>
		<?php
	}elseif (get_request_var('tab') == 'hosts') {
		thold_hosts($header_label);
	}elseif (get_request_var('tab') == 'webseer') {
		webseer_urls($header_label);
	}else{
		api_plugin_hook_function('maint_show_tab', $header_label);
	}
}

function schedules() {
	global $actions, $maint_types, $maint_intervals, $yesno;

	$schedules = db_fetch_assoc('SELECT *
		FROM plugin_maint_schedules
		ORDER BY name');

	form_start('maint.php', 'chk');

	html_start_box(__('Maintenance Schedules', 'maint'), '100%', '', '2', 'center', 'maint.php?tab=general&action=edit');

	html_header_checkbox(array(
		__('Name', 'maint'),
		__('Active', 'maint'),
		__('Type', 'maint'),
		__('Start', 'maint'),
		__('End', 'maint'),
		__('Interval', 'maint'),
		__('Enabled', 'maint'))
	);

	if (cacti_sizeof($schedules)) {
		foreach ($schedules as $schedule) {
			$active = plugin_maint_check_schedule($schedule['id']);

			form_alternate_row('line' . $schedule['id']);
			form_selectable_cell(filter_value($schedule['name'], get_request_var('filter'), 'maint.php?action=edit&id=' . $schedule['id']), $schedule['id']);
			form_selectable_cell($yesno[plugin_maint_check_schedule($schedule['id'])], $schedule['id'], '', $active ? 'deviceUp':'');
			form_selectable_cell($maint_types[$schedule['mtype']], $schedule['id']);
			switch($schedule['minterval']) {
				case 86400:
					if (date('j',$schedule['etime']) != date('j', $schedule['stime'])) {
						form_selectable_cell(date(date_time_format(), $schedule['stime']), $schedule['id']);
						form_selectable_cell(date(date_time_format(), $schedule['etime']), $schedule['id']);
					} else {
						form_selectable_cell(date('G:i', $schedule['stime']), $schedule['id']);
						form_selectable_cell(date('G:i', $schedule['etime']), $schedule['id']);
					}
					break;
				case 604800:
					form_selectable_cell(date('l G:i', $schedule['stime']), $schedule['id']);
					form_selectable_cell(date('l G:i', $schedule['etime']), $schedule['id']);
					break;
				default:
					form_selectable_cell(date(date_time_format(), $schedule['stime']), $schedule['id']);
					form_selectable_cell(date(date_time_format(), $schedule['etime']), $schedule['id']);
			}


			form_selectable_cell($maint_intervals[$schedule['minterval']], $schedule['id']);
			form_selectable_cell($yesno[$schedule['enabled']], $schedule['id']);
			form_checkbox_cell($schedule['name'], $schedule['id']);
			form_end_row();
		}
	}else{
		print "<tr><td colspan='5'><em>" . __('No Schedules', 'maint') . "</em></td></tr>\n";
	}

	html_end_box(false);

	form_hidden_box('save_list', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}

function thold_hosts($header_label) {
	global $assoc_actions, $item_rows;

	$schedule_created = get_request_var('id') ? true : false;

    /* ================= input validation and session storage ================= */
	get_filter_request_var('id');

    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => MAINT_HOST_FILTER_ANY
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => MAINT_HOST_FILTER_ANY
			),
		'poller_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => MAINT_HOST_FILTER_ANY
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => MAINT_HOST_FILTER_ANY
			),
		'location' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => MAINT_HOST_FILTER_LOC_ANY,
			'options' => array('options' => 'sanitize_search_string')
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'associated' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_maint');

	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	/* Limit filter selection lists based on higher order items.  For example, list only templates used in the selected location */
	$sql_where = '';
	$sql_where_params = array();

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'maint.php?tab=hosts&action=edit&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		strURL += '&site_id=' + $('#site_id').val();
		strURL += '&poller_id=' + $('#poller_id').val();
		strURL += '&location=' + $('#location').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'maint.php?tab=hosts&action=edit&id=<?php print get_request_var('id');?>&clear=true&header=false'
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#rows, #site_id, #poller_id, #location, #host_template_id, #filter, #associated').change(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_devices').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Associated Devices %s', htmlspecialchars($header_label), 'maint'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='form_devices' action='maint.php?action=edit&tab=hosts'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Site');?>
					</td>
					<td>
						<select id='site_id'>
							<option value='<?php print MAINT_HOST_FILTER_ANY ?>' <?php if (get_request_var('site_id') == MAINT_HOST_FILTER_ANY) {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='<?php print MAINT_HOST_FILTER_NONE ?>' <?php if (get_request_var('site_id') == MAINT_HOST_FILTER_NONE) {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT id, name
								FROM sites
								WHERE id IN (SELECT site_id FROM host)
								ORDER BY name');
							if (cacti_sizeof($sites)) {
								foreach ($sites as $site) {
									print "<option value='" . $site['id'] . "'"; if (get_request_var('site_id') == $site['id']) { print ' selected'; } print '>' . html_escape($site['name']) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Data Collector');?>
					</td>
					<td>
						<select id='poller_id'>
							<option value='<?php print MAINT_HOST_FILTER_ANY ?>' <?php if (get_request_var('poller_id') == MAINT_HOST_FILTER_ANY) {?> selected<?php }?>><?php print __('Any', 'maint');?></option>
							<?php
							$pollers = db_fetch_assoc("SELECT id, name
								FROM poller
								ORDER BY name");

							if (cacti_sizeof($pollers)) {
								foreach ($pollers as $poller) {
									print "<option value='" . $poller['id'] . "'"; if (get_request_var('poller_id') == $poller['id']) { print ' selected'; } print '>' . html_escape($poller['name']) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Location');?>
					</td>
					<td>
						<select id='location'>
							<?php
							/* Filter location list based on other selections
							 * List item None for Null and empty values
							 * List item "Any" and "None" special values at top of list but can also be in data as another list entry for those; "None" could be listed twice*/

							if (get_request_var('site_id') != MAINT_HOST_FILTER_ANY) {
								$sql_where .= ' AND site_id = ?';
								$sql_where_params = array_merge($sql_where_params, array(get_request_var('site_id')));
							}

							if (get_request_var('poller_id') != MAINT_HOST_FILTER_ANY) {
								$sql_where .= ' AND poller_id = ?';
								$sql_where_params = array_merge($sql_where_params, array(get_request_var('poller_id')));
							}

							if (get_request_var('associated') == 'false') {
								/* Show all items */
							} else {
								$sql_where .= ' AND id IN (SELECT host FROM plugin_maint_hosts WHERE type = 1 AND schedule = ?)';
								$sql_where_params = array_merge($sql_where_params, array(get_request_var('id')));
							}

							/* Include (UNION) Any */
							$locations = db_fetch_assoc_prepared(
								"SELECT * FROM (
									SELECT DISTINCT IF(IFNULL(location,'') = '', ?, location) AS location
									FROM (SELECT location FROM host WHERE id = id $sql_where GROUP BY location) AS host
									UNION ALL
									SELECT location FROM (SELECT ? AS location ) AS tableany
								) tableunion
								ORDER BY location",
								array_merge(array(MAINT_HOST_FILTER_LOC_NONE), $sql_where_params, array(MAINT_HOST_FILTER_LOC_ANY)));

							/* If current selection is not in the result set, change selection to Any */
							if (get_request_var('location') != MAINT_HOST_FILTER_LOC_ANY) {
								$found = false;
								foreach ($locations as $location) {
									if ($location['location'] == get_request_var('location')) {
										$found = true;
										break;
									}
								}
								if (!$found) {
									set_request_var('location', MAINT_HOST_FILTER_LOC_ANY);
								}
							}

							if (cacti_sizeof($locations)) {
								foreach ($locations as $l) {
									echo "<option value='", html_escape($l['location']), "'";
									if (get_request_var('location') == $l['location']) {
										echo " selected";
									}
									echo ">";
									if ($l['location'] == MAINT_HOST_FILTER_LOC_ANY) {
										echo html_escape('Any');
									} elseif ($l['location'] == MAINT_HOST_FILTER_LOC_NONE) {
										echo html_escape('None');
									} else {
										echo html_escape($l['location']);
									}
									echo "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='host_template_id'>
							<?php
							/* Get only templates used for the selected location */
							if (get_request_var('location') != MAINT_HOST_FILTER_LOC_ANY) {
								if (get_request_var('location') == MAINT_HOST_FILTER_LOC_NONE) {
									$sql_where .= ' AND IFNULL(location,"") = ""';
								} else {
									$sql_where .= ' AND location = ?';
									$sql_where_params = array_merge($sql_where_params, array(get_request_var('location')));
								}
							}

							$sql_statement = "SELECT ht.id, ht.name
								FROM host_template AS ht
								WHERE ht.id IN (SELECT host_template_id FROM host WHERE id = id $sql_where)
								ORDER BY ht.name";
							$host_templates = db_fetch_assoc_prepared($sql_statement, $sql_where_params);
							$hosts_no_templates = db_fetch_assoc_prepared("SELECT id
								FROM host WHERE host_template_id = 0 $sql_where
								LIMIT 1",
								$sql_where_params);

							/* If current selected template is not in the result set, change selected template to Any */
							$found = false;
							if (get_request_var('host_template_id') != MAINT_HOST_FILTER_ANY && get_request_var('host_template_id') != MAINT_HOST_FILTER_NONE) {
								foreach ($host_templates as $host_template) {
									if ($host_template['id'] == get_request_var('host_template_id')) {
										$found = true;
										break;
									}
								}
							}
							if (!$found && (get_request_var('host_template_id') == MAINT_HOST_FILTER_NONE ) && $hosts_no_templates) {
								$found = true;
							}
							if (!$found) {
								set_request_var('host_template_id', MAINT_HOST_FILTER_ANY);
							}

							echo "<option value='", MAINT_HOST_FILTER_ANY, "'";
							if (get_request_var('host_template_id') == MAINT_HOST_FILTER_ANY) {
								echo " selected";
							}
							echo ">", __('Any'), "</option>\n";

							/* Include "None" if hosts with no template */
							if ($hosts_no_templates) {
								echo "\t\t\t\t\t\t\t<option value='", MAINT_HOST_FILTER_NONE . "'";
								if (get_request_var('host_template_id') == MAINT_HOST_FILTER_NONE) {
									echo " selected";
								}
								echo ">", __('None'), "</option>\n";
							}

							if (cacti_sizeof($host_templates)) {
								foreach ($host_templates as $host_template) {
									echo "\t\t\t\t\t\t\t<option value='", $host_template['id'], "'";
									if (get_request_var('host_template_id') == $host_template['id']) {
										echo " selected";
									}
									echo ">", html_escape($host_template['name']), "</option>\n";
								}
							}
							?>
						</select>
					</td>
        			<td>
						<span class='nowrap'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
		        </tr>
            </table>
            <table class='filterTable'>
                <tr>
					<td>
						<?php print __('Search', 'maint');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Devices', 'maint');?>
					</td>
					<td>
						<select id='rows''>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'maint');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='associated' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'><?php print __('Associated', 'maint');?></label>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' id='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';
	$sql_where_params = array();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var('filter'))) {
		$sql_where = ' AND (h.hostname LIKE ? OR h.description LIKE ?)';
		$sql_where_params = array_merge($sql_where_params,
			array('%' . get_request_var('filter') . '%', '%' . get_request_var('filter') . '%'));
	}

	if (get_request_var('site_id') == MAINT_HOST_FILTER_ANY) {
		/* Show all items */
	} else {
		$sql_where .= ' AND h.site_id = ?';
		$sql_where_params = array_merge($sql_where_params, array(get_request_var('site_id')));
	}

	if (get_request_var('poller_id') == MAINT_HOST_FILTER_ANY) {
		/* Show all items */
	} else {
		$sql_where .= ' AND h.poller_id = ?';
		$sql_where_params = array_merge($sql_where_params, array(get_request_var('poller_id')));
	}

	if (get_request_var('location') != MAINT_HOST_FILTER_LOC_ANY) {
		if (get_request_var('location') == MAINT_HOST_FILTER_LOC_NONE) {
			$sql_where .= ' AND IFNULL(h.location,"") = ""';
		} else {
			$sql_where .= ' AND h.location = ?';
			$sql_where_params = array_merge($sql_where_params, array(get_request_var('location')));
		}
	}

	if (get_request_var('host_template_id') == MAINT_HOST_FILTER_ANY) {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == MAINT_HOST_FILTER_NONE || !isempty_request_var('host_template_id')) {
		$sql_where .= ' AND h.host_template_id = ?';
		$sql_where_params = array_merge($sql_where_params, array(get_request_var('host_template_id')));
	}

	if (get_request_var('associated') == 'false') {
		/* Show all items */
	} else {
		$sql_where .= ' AND type = ? AND schedule = ?';
		$sql_where_params = array_merge($sql_where_params, array(MAINT_HOST_TYPE_HOSTS, get_request_var('id')));
	}

	/* Replace leading " AND" */
	if ($sql_where > '') {
		$sql_where = 'WHERE' . substr($sql_where, 4);
	}

	if ($schedule_created) {
		$sql_params = array_merge(array(get_request_var('id')), $sql_where_params);
		$total_rows = db_fetch_cell_prepared("SELECT
			COUNT(DISTINCT h.id)
			FROM host AS h
			LEFT JOIN (SELECT DISTINCT host_id FROM thold_data) AS td
			ON h.id = td.host_id
			LEFT JOIN plugin_maint_hosts AS pmh
			ON h.id = pmh.host
			AND pmh.schedule = ?
			$sql_where",
			$sql_params);
	} else {
		$total_rows = 0;
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page') - 1)) . ', ' . $rows;

	if ($schedule_created) {
		$sql_query = "SELECT h.*, pmh.type, gl.graphs, dl.data_sources, tholds,
			(SELECT schedule FROM plugin_maint_hosts WHERE host=h.id AND schedule=?) AS associated
			FROM host as h
			LEFT JOIN (SELECT COUNT(id) AS tholds, host_id FROM thold_data GROUP BY host_id) AS td
			ON td.host_id = h.id
			LEFT JOIN (SELECT COUNT(id) AS graphs, host_id FROM graph_local GROUP BY host_id) AS gl
			ON gl.host_id = h.id
			LEFT JOIN (SELECT COUNT(id) AS data_sources, host_id FROM data_local GROUP BY host_id) AS dl
			ON dl.host_id = h.id
			LEFT JOIN plugin_maint_hosts AS pmh
			ON pmh.host = h.id
			AND pmh.schedule = ?
			$sql_where
			GROUP BY h.id
			$sql_order
			$sql_limit";

		$sql_params = array_merge(array(get_request_var('id'), get_request_var('id')), $sql_where_params);
		$hosts = db_fetch_assoc_prepared($sql_query, $sql_params);
	} else {
		$hosts = array();
	}

	$display_text = array(
		'description' => array(
			'display' => __('Description', 'maint'),
			'align' => 'left',
			'sort' => 'ASC'),
		'id' => array(
			'display' => __('ID', 'maint'),
			'align' => 'right',
			'sort' => 'asc'),
		'nosort' => array(
			'display' => __('Associated Schedules', 'maint'),
			'align' => 'left',
			'sort' => ''),
		'graphs' => array(
			'display' => __('Graphs', 'maint'),
			'align' => 'right',
			'sort' => 'desc'),
		'data_sources' => array(
			'display' => __('Data Sources', 'maint'),
			'align' => 'right',
			'sort' => 'desc'),
		'tholds' => array(
			'display' => __('Thresholds', 'maint'),
			'align' => 'right',
			'sort' => 'desc'),
		'nosort1' => array(
			'display' => __('Status', 'maint'),
			'align' => 'center',
			'sort' => ''),
		'hostname' => array(
			'display' => __('Hostname', 'maint'),
			'align' => 'left',
			'sort' => 'desc')
	);

	/* generate page list */
	$nav = html_nav_bar('maint.php?action=edit&tab=hosts&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 13, __('Devices', 'maint'), 'page', 'main');

	form_start('maint.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'maint.php?action=edit&tab=hosts&id=' . get_request_var('id'));

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row('line' . $host['id']);
			form_selectable_cell(filter_value($host['description'], get_request_var('filter')), $host['id']);
			form_selectable_cell(number_format_i18n($host['id']), $host['id'], '', 'text-align:right');

			if ($host['associated'] != '') {
				$names = '<span class="deviceUp">' . __('Current Schedule', 'maint') . '</span>';
			} else {
				$names = '';
			}

			$lists = db_fetch_assoc_prepared('SELECT name
				FROM plugin_maint_schedules
				INNER JOIN plugin_maint_hosts
				ON plugin_maint_schedules.id = plugin_maint_hosts.schedule
				WHERE type = ? AND host = ? AND plugin_maint_schedules.id != ?',
				array(MAINT_HOST_TYPE_HOSTS, $host['id'], get_request_var('id')));

			if (cacti_sizeof($lists)) {
				foreach($lists as $name) {
					$names .= (strlen($names) ? ', ':'') . "<span class='deviceRecovering'>" . html_escape($name['name']) . '</span>';
				}
			}
			if ($names == '') {
				form_selectable_cell('<span class="deviceUnknown">' . __('No Schedules', 'maint') . '</span>', $host['id']);
			} else {
				form_selectable_cell($names, $host['id']);
			}
			form_selectable_cell(number_format_i18n($host['graphs']), $host['id'], '', 'text-align:right');
			form_selectable_cell(number_format_i18n($host['data_sources']), $host['id'], '', 'text-align:right');
			form_selectable_cell(number_format_i18n($host['tholds']), $host['id'], '', 'text-align:right');
			form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['id'], '', 'text-align:center');
			form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id']);
			form_checkbox_cell($host['description'], $host['id']);
			form_end_row();
		}
	} else {
		if ($schedule_created) {
			print "<tr><td colspan='8'><em>" . __('No Associated Devices Found', 'maint') . "</em></td></tr>";
		} else {
			print "<tr><td colspan='8'><em>" . __('Schedule must be created before associating', 'maint') . "</em></td></tr>";
		}
	}

	html_end_box(false);

	if (cacti_sizeof($hosts)) {
		print $nav;
	}

	form_hidden_box('id', get_request_var('id'), '');
	form_hidden_box('save_hosts', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($assoc_actions);

	form_end();
}

/**
 * webseer tab
 */

function webseer_urls($header_label) {
	global $assoc_actions, $item_rows;

	$schedule_created = get_request_var('id') ? true : false;

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'associated' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_maint_ws');

	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'maint.php?tab=webseer&action=edit&id=<?php print get_request_var('id');?>';
		strURL += '&rows=' + $('#rows').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'maint.php?tab=webseer&action=edit&id=<?php print get_request_var('id');?>&clear=true&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#rows, #associated').change(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_webseer').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Associated Web URL\'s %s', htmlspecialchars($header_label), 'maint'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='form_webseer' action='maint.php?action=edit&tab=webseer'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'maint');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Rows', 'maint');?>
					</td>
					<td>
						<select id='rows''>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'maint');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='associated' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'><?php print __('Associated', 'maint');?></label>
					</td>
					<td>
						<span class='nowrap'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var('filter'))) {
		$sql_where = 'WHERE ((u.url LIKE ?)
			OR (u.display_name LIKE ?)
			OR (u.ip LIKE ?))';
		$sql_where_params = array(
			'%' . get_request_var('filter') . '%',
			'%' . get_request_var('filter') . '%',
			'%' . get_request_var('filter') . '%'
		);
	} else {
		$sql_where = '';
		$sql_where_params =  array();
	}

	if (get_request_var('associated') == 'true') {
		$sql_where .= (strlen($sql_where) ? ' AND ' : ' WHERE ') .
			' (pmh.type IS NOT NULL)';
	}

	if ($schedule_created) {
		$sql_params = array_merge(array(get_request_var('id'), MAINT_HOST_TYPE_WEBSEER), $sql_where_params);
		$total_rows = db_fetch_cell_prepared("SELECT
			COUNT(*)
			FROM plugin_webseer_urls AS u
			LEFT JOIN plugin_maint_hosts AS pmh
				ON (u.id = pmh.host
				AND pmh.type = ?
				AND pmh.schedule = ?)
			$sql_where",
			$sql_params);

		$sql_params = array_merge(array(get_request_var('id'), MAINT_HOST_TYPE_WEBSEER, get_request_var('id')), $sql_where_params);
		$sql_query = "SELECT u.*,
			(SELECT schedule FROM plugin_maint_hosts WHERE host = u.id AND schedule = ?) AS associated,
			pmh.type AS maint_type
			FROM plugin_webseer_urls AS u
			LEFT JOIN plugin_maint_hosts AS pmh
				ON (u.id = pmh.host
				AND pmh.type = ?
				AND pmh.schedule = ?)
			$sql_where
			LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

		$urls = db_fetch_assoc_prepared($sql_query, $sql_params);
	} else {
		/* new schedule, no "id" yet */
		$total_rows = 0;
		$urls = array();
	}

	$nav = html_nav_bar('notify_lists.php?action=edit&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 13, __('Lists', 'maint'), 'page', 'main');

	form_start('maint.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		__('Description', 'maint'),
		__('ID', 'maint'),
		__('Associated Schedules', 'maint'),
		__('Enabled', 'maint'),
		__('Hostname', 'maint'),
		__('URL', 'maint')
	);

	html_header_checkbox($display_text);

	if (cacti_sizeof($urls)) {
		foreach ($urls as $url) {
			form_alternate_row('line' . $url['id']);
			form_selectable_cell(filter_value($url['display_name'], get_request_var('filter')), $url['id'], 250);
			form_selectable_cell(round(($url['id']), 2), $url['id']);

			if ($url['associated'] != '') {
				$names = '<span class="deviceUp">' . __('Current Schedule', 'maint') . '</span>';
			} else {
				$names = '';
			}

			$lists = db_fetch_assoc_prepared("SELECT name
				FROM plugin_maint_schedules
				INNER JOIN plugin_maint_hosts
				ON plugin_maint_schedules.id = plugin_maint_hosts.schedule
				WHERE type = ?
				AND host = ?
				AND plugin_maint_schedules.id != ?",
				array(MAINT_HOST_TYPE_WEBSEER, $url['id'], get_request_var('id')));

			if (cacti_sizeof($lists)) {
				foreach($lists as $name) {
					$names .= (strlen($names) ? ', ':'') . "<span class='deviceRecovering'>" . html_escape($name['name']) . "</span>";
				}
			}
			if ($names == '') {
				form_selectable_cell('<span class="deviceUnknown">' . __('No Schedules', 'maint') . '</span>', $url['id']);
			} else {
				form_selectable_cell($names, $url['id']);
			}

			form_selectable_cell(($url['enabled'] == 'on' ? __('Enabled', 'maint'):__('Disabled', 'maint')), $url['id']);
			if (empty($url['ip'])) {
				$url['ip'] = __('USING DNS', 'maint');
			}
			form_selectable_cell(filter_value($url['ip'], get_request_var('filter')), $url['id']);
			form_selectable_cell(filter_value($url['url'], get_request_var('filter')), $url['id']);
			form_checkbox_cell($url['display_name'], $url['id']);
			form_end_row();
		}
	} else {
		if ($schedule_created) {
			print "<tr><td colspan='8'><em>" . __('No Associated WebSeer URL\'s Found', 'maint') . "</em></td></tr>";
		} else {
			print "<tr><td colspan='8'><em>" . __('Schedule must be created before associating', 'maint') . "</em></td></tr>";
		}
	}

	html_end_box(false);

	if (cacti_sizeof($urls)) {
		print $nav;
	}

	form_hidden_box('id', get_request_var('id'), '');
	form_hidden_box('save_webseer', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($assoc_actions);

	form_end();
}
