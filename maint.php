<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010 The Cacti Group                                      |
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
include_once($config["base_path"] . '/plugins/maint/functions.php');



$ds_actions = array(
	1 => 'Delete'
	);

$action = '';
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

if (isset($_POST['drp_action']) && $_POST['drp_action'] == 1) {
	$action = 'delete';
}

switch ($action) {
	case 'edit':
		include_once('./include/top_header.php');
		schedule_edit();
		include_once('./include/bottom_footer.php');
		break;
	case 'save':
		if (isset($_POST['save']) && $_POST['save'] == 'edit') {
			schedule_save_edit();
		}
		break;
	case 'delete':
		schedule_delete();
		break;
	default:
		include_once('./include/top_header.php');
		schedules();
		include_once('./include/bottom_footer.php');
		break;
}

function schedule_delete() {
	foreach($_POST as $t=>$v) {
		if (substr($t, 0,4) == 'chk_') {
			$id = substr($t, 4);
			input_validate_input_number($id);
			db_fetch_assoc("delete from plugin_maint_schedules where id = $id LIMIT 1");
			db_fetch_assoc("delete from plugin_maint_hosts where schedule = $id");
		}
	}

	Header('Location: maint.php');
	exit;
}

function schedule_save_edit() {
	global $plugins;
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('id'));
	input_validate_input_number(get_request_var_post('mtype'));
	input_validate_input_number(get_request_var_post('minterval'));

	if (isset($_POST['name'])) {
		$_POST['name'] = trim(str_replace(array("\\", "'", '"'), '', get_request_var_post('name')));
	}
	if (isset($_POST['stime'])) {
		$_POST['stime'] = trim(str_replace(array("\\", "'", '"'), '', get_request_var_post('stime')));
	}
	if (isset($_POST['etime'])) {
		$_POST['etime'] = trim(str_replace(array("\\", "'", '"'), '', get_request_var_post('etime')));
	}

	/* ==================================================== */

	$save['id'] = $_POST['id'];
	$save['name'] = $_POST['name'];
	$save['mtype'] = $_POST['mtype'];
	$save['stime'] = strtotime($_POST['stime']);
	$save['etime'] = strtotime($_POST['etime']);
	$save['minterval'] = $_POST['minterval'];

	if (isset($_POST['enabled']))
		$save['enabled'] = 'on';
	else
		$save['enabled'] = 'off';

	if ($save['mtype'] == 1) {
		$save['minterval'] = 0;
	}

	if ($save['stime'] >= $save['etime']) {
		raise_message(2);
	}





	if (!is_error_message()) {
		$id = sql_save($save, 'plugin_maint_schedules');
		if ($id) {
			if (api_plugin_is_enabled('thold')) {
				db_execute("DELETE FROM plugin_maint_hosts WHERE type = 1 AND schedule = " . $id);
				if (isset($_POST['hosts'])) {
					foreach ($_POST['hosts'] as $i) {
						input_validate_input_number($i);
						db_execute("INSERT INTO plugin_maint_hosts (type, host, schedule) VALUES (1, $i, $id)");
					}
				}
			}

			if (api_plugin_is_enabled('webseer') || in_array('webseer', $plugins)) {
				db_execute("DELETE FROM plugin_maint_hosts WHERE type = 2 AND schedule = " . $id);
				if (isset($_POST['webseer_hosts'])) {
					foreach ($_POST['webseer_hosts'] as $i) {
						input_validate_input_number($i);
						db_execute("INSERT INTO plugin_maint_hosts (type, host, schedule) VALUES (2, $i, $id)");
					}
				}
			}

			raise_message(1);
		} else {
			raise_message(2);
		}
	}

	if (is_error_message()) {
		header('Location: maint.php?action=edit&id=' . (empty($id) ? $_POST['id'] : $id));
	}else{
		header('Location: maint.php');
	}
}

function schedule_edit() {
	global $colors, $plugins;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */
	if (isset($_REQUEST['id'])) {
		$id = $_REQUEST['id'];
		$maint_item_data = db_fetch_row('select * from plugin_maint_schedules where id = ' . $id);
	} else {
		$id = 0;
		$maint_item_data = array('id' => 0, 'name' => 'Scheduled Maintenance', 'enabled' => 'on', 'mtype' => 1, 'stime' => time(), 'etime' => time() + 3600, 'minterval' => 0);
	}




	$maint_types = array (1 => 'Once', 2 => 'Reoccurring');
	$intervals = array(0 => '', 86400 => 'Every Day', 604800 => 'Every Week');

	html_start_box('', '100%', $colors['header'], '3', 'center', '');
	print "<form name='maint' action=maint.php method=post><input type='hidden' name='save' value='edit'><input type='hidden' name='id' value='$id'>";
	$form_array = array(
		'general_header' => array(
			'friendly_name' => 'Schedule',
			'method' => 'spacer',
		),
		'name' => array(
			'friendly_name' => 'Schedule Name',
			'method' => 'textbox',
			'max_length' => 100,
			'default' => $maint_item_data['name'],
			'description' => 'Provide the Maintenance Schedule a meaningful name',
			'value' => isset($maint_item_data['name']) ? $maint_item_data['name'] : ''
		),
		'enabled' => array(
			'friendly_name' => 'Enabled',
			'method' => 'checkbox',
			'default' => 'on',
			'description' => 'Whether or not this threshold will be checked and alerted upon.',
			'value' => isset($maint_item_data['enabled']) ? $maint_item_data['enabled'] : ''
		),
		'mtype' => array(
			'friendly_name' => 'Schedule Type',
			'method' => 'drop_array',
			'on_change' => 'changemaintType()',
			'array' => $maint_types,
			'description' => 'The type of Threshold that will be monitored.',
			'value' => isset($maint_item_data['mtype']) ? $maint_item_data['mtype'] : ''
		),
		'stime' => array(
			'friendly_name' => 'Start Time',
			'method' => 'textbox',
			'max_length' => 100,
			'description' => 'This is the date / time this schedule will start to be in effect.',
			'default' => date("F j, Y, G:i", time()),
			'value' => isset($maint_item_data['stime']) ?  date("F j, Y, G:i", $maint_item_data['stime']) : ''
		),
		'etime' => array(
			'friendly_name' => 'End Time',
			'method' => 'textbox',
			'max_length' => 100,
			'default' => date("F j, Y, G:i", time() + 3600),
			'description' => 'This is the date / time this schedule will end.',
			'value' => isset($maint_item_data['etime']) ? date("F j, Y, G:i", $maint_item_data['etime']) : ''
		),
		'maint_header' => array(
			'friendly_name' => 'Interval Settings',
			'method' => 'spacer',
		),
		'minterval' => array(
			'friendly_name' => 'Interval',
			'method' => 'drop_array',
			'array' => $intervals,
			'default' => 86400,
			'description' => 'This is the interval in which the start / end time will repeat.',
			'value' => isset($maint_item_data['minterval']) ? $maint_item_data['minterval'] : '0'
		),
	);

	if (api_plugin_is_enabled('thold')) {
		$form_array['thold_header'] = array(
			'friendly_name' => 'Threshold Hosts',
			'method' => 'spacer',
		);
		$hosts = array();
		$cacti_hosts = db_fetch_assoc("SELECT id, description FROM host ORDER BY description ASC");
		if (!empty($cacti_hosts)) {
			foreach ($cacti_hosts as $h) {
				$hosts[$h['id']] = $h['description'];
			}
		}
		$sql = "SELECT host as id FROM plugin_maint_hosts WHERE type = 1 AND schedule = $id";
		$form_array['hosts'] = array(
			"friendly_name" => "Hosts",
			"method" => "drop_multi",
			"description" => "This is a listing of hosts that this schedule will apply to.<br><br><br><br><br><br><br><br><br><br><br><br><br>",
			"array" => $hosts,
			"sql" => $sql,
			);
	}

	if (api_plugin_is_enabled('webseer') || in_array('webseer', $plugins)) {
		$form_array['webseer_header'] = array(
			'friendly_name' => 'Webseer Hosts',
			'method' => 'spacer',
		);
		$hosts = array();
		$cacti_hosts = db_fetch_assoc("SELECT id, url FROM plugin_webseer_urls ORDER BY url ASC");
		if (!empty($cacti_hosts)) {
			foreach ($cacti_hosts as $h) {
				$hosts[$h['id']] = $h['url'];
			}
		}
		$sql = "SELECT host as id FROM plugin_maint_hosts WHERE type = 2 AND schedule = $id";
		$form_array['webseer_hosts'] = array(
			"friendly_name" => "URLs",
			"method" => "drop_multi",
			"description" => "This is a listing of Webseer URLs that this schedule will apply to.<br><br><br><br><br><br><br><br><br><br><br><br><br>",
			"array" => $hosts,
			"sql" => $sql,
			);
	}

	draw_edit_form(
		array(
			'config' => array(
				'no_form_tag' => true
				),
			'fields' => $form_array
			)
	);

	html_end_box();
	form_save_button('maint.php?id=' . $id, 'save');

	?>
	<!-- Make it look intelligent :) -->
	<script language="JavaScript">


	function changemaintType () {
		type = document.getElementById('mtype').value;
		switch(type) {
		case '1':
			maint_toggle_interval ('none');
			break;
		case '2':
			maint_toggle_interval ('');
			break;
		}
	}

	function maint_toggle_interval (status) {
		document.getElementById('row_maint_header').style.display  = status;
		document.getElementById('row_minterval').style.display  = status;
	}

	changemaintType ();

	</script>
	<?php

}

function schedules() {
	global $colors, $ds_actions;

	html_start_box('<strong>Scheduled Maintenance</strong>', '100%', $colors['header'], '3', 'center', 'maint.php?action=edit');

	html_header_checkbox(array('Name', 'Type', 'Start', 'End', 'Interval', 'Active', 'Enabled'));
	$yesno = array(0 => 'No', 1 => 'Yes', 'on' => 'Yes', 'off' => 'No');
	$schedules = db_fetch_assoc('SELECT * FROM plugin_maint_schedules ORDER BY name');

	$types = array(1 => "Once", 2 => "Reoccurring");
	$reoccurring = array(0 => "", 86400 => "Every Day", 604800 => "Every Week");

	$i = 0;
	if (sizeof($schedules) > 0) {
		foreach ($schedules as $schedule) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $schedule["id"]); $i++;
			form_selectable_cell('<a class="linkEditMain" href="maint.php?action=edit&id=' . $schedule['id'] . '">' . $schedule['name'] . '</a>', $schedule["id"]);
			form_selectable_cell($types[$schedule['mtype']], $schedule["id"]);
			switch($schedule['minterval']) {
				case 86400:
					if (date("j",$schedule['etime']) != date("j", $schedule['stime'])) {
						form_selectable_cell(date("F j, Y, G:i", $schedule['stime']), $schedule["id"]);
						form_selectable_cell(date("F j, Y, G:i", $schedule['etime']), $schedule["id"]);
					} else {
						form_selectable_cell(date("G:i", $schedule['stime']), $schedule["id"]);
						form_selectable_cell(date("G:i", $schedule['etime']), $schedule["id"]);
					}
					break;
				case 604800:
					form_selectable_cell(date("l G:i", $schedule['stime']), $schedule["id"]);
					form_selectable_cell(date("l G:i", $schedule['etime']), $schedule["id"]);
					break;
				default:
					form_selectable_cell(date("F j, Y, G:i", $schedule['stime']), $schedule["id"]);
					form_selectable_cell(date("F j, Y, G:i", $schedule['etime']), $schedule["id"]);
			}


			form_selectable_cell($reoccurring[$schedule['minterval']], $schedule["id"]);
			form_selectable_cell($yesno[plugin_maint_check_schedule ($schedule['id'])], $schedule["id"]);
			form_selectable_cell($yesno[$schedule['enabled']], $schedule["id"]);
			form_checkbox_cell($schedule['name'], $schedule["id"]);
			form_end_row();
		}
	}else{
		print "<tr><td><em>No Schedules</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ds_actions);

	print "</form>\n";
}