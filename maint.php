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

define("MAX_DISPLAY_PAGES", 21);

// Maint Schedule Actions
$mactions = array(
	1 => 'Update Time (Now + 1 Hour)',
	2 => 'Delete'
	);

// Host Maint Schedule Actions
$assoc_actions = array(
	1 => "Associate",
	2 => "Disassociate"
);

// Present a tabbed interface
$tabs = array(
	"general" => "General"
);

if (api_plugin_is_enabled('thold')) {
	$tabs["hosts"] = "Devices";
}

if (api_plugin_is_enabled('webseer')) {
	$tabs["webseer"] = "WebSeer";
}

$tabs = api_plugin_hook_function('maint_tabs', $tabs);

if (!isset($_REQUEST['action'])) $_REQUEST['action'] = '';

switch ($_REQUEST['action']) {
	case 'save':
		form_save();
		break;
	case 'actions':
		form_actions();
		break;
	case 'edit':
		include_once('./include/top_header.php');
		schedule_edit();
		include_once('./include/bottom_footer.php');
		break;
	default:
		include_once('./include/top_header.php');
		schedules();
		include_once('./include/bottom_footer.php');
		break;
}

function schedule_delete() {
	$selected_items = unserialize(stripslashes($_POST["selected_items"]));
	foreach($selected_items as $id) {
		input_validate_input_number($id);
		db_fetch_assoc("DELETE FROM plugin_maint_schedules WHERE id = $id LIMIT 1");
		db_fetch_assoc("DELETE FROM plugin_maint_hosts WHERE schedule = $id");
	}

	Header('Location: maint.php');
	exit;
}

function schedule_update() {
	$selected_items = unserialize(stripslashes($_POST["selected_items"]));
	foreach($selected_items as $id) {
		input_validate_input_number($id);
		$stime = intval(time()/60)*60;
		$etime = $stime + 3600;
		db_fetch_assoc("UPDATE plugin_maint_schedules SET stime = $stime, etime = $etime WHERE id = $id LIMIT 1");
	}
	Header('Location: maint.php');
	exit;
}


function form_save() {
	global $plugins;

	if (isset($_POST["save_component"])) {
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

		$save['id']    = $_POST['id'];
		$save['name']  = $_POST['name'];
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
				raise_message(1);
			} else {
				raise_message(2);
			}
		}
		header('Location: maint.php?tab=general&action=edit&id=' . (empty($id) ? $_POST['id'] : $id));
		exit;
	}
}

function form_actions() {
	global $colors, $actions, $assoc_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		if (isset($_POST["save_list"])) {
			if ($_POST["drp_action"] == "2") { /* delete */
				schedule_delete();
			}elseif ($_POST["drp_action"] == "1") { /* update */
				schedule_update();
			}

			header("Location: maint.php");
			exit;
		}elseif (isset($_POST["save_hosts"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));
			input_validate_input_number(get_request_var_post('id'));

			if ($_POST["drp_action"] == "1") { /* associate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					db_execute("REPLACE INTO plugin_maint_hosts (type, host, schedule) VALUES (1, " . $selected_items[$i] . ", " . $_POST['id'] . ")");
				}
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					db_execute("DELETE FROM plugin_maint_hosts WHERE type=1 AND host=" . $selected_items[$i] . " AND schedule=" .  $_POST['id']);
				}
			}

			header("Location: maint.php?action=edit&tab=hosts&id=" . get_request_var_request("id"));
			exit;
		}elseif (isset($_POST["save_webseer"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));
			input_validate_input_number(get_request_var_post('id'));

			if ($_POST["drp_action"] == "1") { /* associate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					db_execute("REPLACE INTO plugin_maint_hosts (type, host, schedule) VALUES (2, " . $selected_items[$i] . ", " . $_POST['id'] . ")");
				}
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					db_execute("DELETE FROM plugin_maint_hosts WHERE type=2 AND host=" . $selected_items[$i] . " AND schedule=" .  $_POST['id']);
				}
			}

			header("Location: maint.php?action=edit&tab=webseer&id=" . get_request_var_request("id"));
			exit;
		}else{
			api_plugin_hook_function('maint_actions_execute');
		}
	}

	/* setup some variables */
	$list = ""; $array = array(); $list_name = "";
	if (isset($_POST["id"])) {
		$list_name = db_fetch_cell("SELECT name FROM plugin_maint_schedules WHERE id=" . $_POST["id"]);
	}

	if (isset($_POST["save_list"])) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= "<li><b>" . db_fetch_cell("SELECT name FROM plugin_maint_schedules WHERE id=" . $matches[1]) . "</b></li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $actions{$_POST["drp_action"]} . " $list_name</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='maint.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* update */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the following Maintenance Schedule(s) will be Updated.</p>
							<ul>$list</ul>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Notification List(s)'>";
			}elseif ($_POST["drp_action"] == "2") { /* delete */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the following Maintenance Schedule(s) will be Deleted.  Any Hosts(s) Associated with this Schedule will be lost.</p>
							<ul>$list</ul>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Notification List(s)'>";
			}
		} else {
			print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Maintenance Schedule.</span></td></tr>\n";
			$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
		}

		print "	<tr>
				<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='save_list' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		include_once("./include/bottom_footer.php");
	}elseif (isset($_POST["save_hosts"])) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= "<li><b>" . db_fetch_cell("SELECT description FROM host WHERE id=" . $matches[1]) . "</b></li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $assoc_actions{$_POST["drp_action"]} . " Host(s)</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='maint.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* associate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Maintenance Schedule '<b>" . $list_name . "</b>' will be associated with the Host(s) below.</p>
							<ul>$list</ul>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Associate Notification List(s)'>";
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Maintenance Schedule '<b>" . $list_name . "</b>' will be disassociated from the Host(s) below.</p>
							<ul>$list</ul>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disassociate Notification List(s)'>";
			}
		} else {
			print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Host.</span></td></tr>\n";
			$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
		}

		print "	<tr>
				<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var_request('id') . "'>
				<input type='hidden' name='save_hosts' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		include_once("./include/bottom_footer.php");
	}elseif (isset($_POST["save_webseer"])) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= "<li><b>" . db_fetch_cell("SELECT description FROM host WHERE id=" . $matches[1]) . "</b></li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $assoc_actions{$_POST["drp_action"]} . " Host(s)</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='maint.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* associate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Maintenance Schedule '<b>" . $list_name . "</b>' will be associated with the Host(s) below.</p>
							<ul>$list</ul>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Associate Notification List(s)'>";
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Maintenance Schedule '<b>" . $list_name . "</b>' will be disassociated from the Host(s) below.</p>
							<ul>$list</ul>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disassociate Notification List(s)'>";
			}
		} else {
			print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Host.</span></td></tr>\n";
			$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
		}

		print "	<tr>
				<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var_request('id') . "'>
				<input type='hidden' name='save_webseer' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		include_once("./include/bottom_footer.php");
	}else{
		api_plugin_hook_function('maint_actions_prepare');
	}
}

function get_header_label() {
	if (!empty($_REQUEST["id"])) {
		$_GET["id"] = $_REQUEST["id"];
		$list = db_fetch_row("SELECT * FROM plugin_maint_schedules WHERE id=" . $_REQUEST["id"]);
		$header_label = "[edit: " . $list["name"] . "]";
	} else {
		$header_label = "[new]";
	}

	return $header_label;
}

function schedule_edit() {
	global $colors, $plugins, $config, $tabs;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	/* set the default tab */
	load_current_session_value("tab", "sess_maint_tab", "general");
	$current_tab = $_REQUEST["tab"];

	if (sizeof($tabs) && isset($_REQUEST['id'])) {
		/* draw the tabs */
		print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<td style='padding:3px 10px 2px 5px;background-color:" . (($tab_short_name == $current_tab) ? "silver;" : "#DFDFDF;") .
				"white-space:nowrap;'" .
				" width='1%' " .
				" align='center' class='tab'>
				<span class='textHeader'><a href='" . htmlspecialchars($config['url_path'] .
				"plugins/maint/maint.php?action=edit&id=" . get_request_var_request('id') .
				"&tab=" . $tab_short_name) .
				"'>$tabs[$tab_short_name]</a></span>
				</td>\n
				<td width='1'></td>\n";
		}

		print "<td></td>\n</tr></table>\n";
	}

	if (isset($_REQUEST['id'])) {
		$id = $_REQUEST['id'];
		$maint_item_data = db_fetch_row('SELECT * FROM plugin_maint_schedules WHERE id = ' . $id);
	} else {
		$id = 0;
		$maint_item_data = array('id' => 0, 'name' => 'New Maintenance Schedule', 'enabled' => 'on', 'mtype' => 1, 'stime' => time(), 'etime' => time() + 3600, 'minterval' => 0);
	}

	$header_label = get_header_label();

	if ($_REQUEST["tab"] == "general") {
		$maint_types = array (1 => 'One Time', 2 => 'Reoccurring');
		$intervals = array(0 => '', 86400 => 'Every Day', 604800 => 'Every Week');

        html_start_box("<strong>General Settings</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

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
			'minterval' => array(
				'friendly_name' => 'Interval',
				'method' => 'drop_array',
				'array' => $intervals,
				'default' => 86400,
				'description' => 'This is the interval in which the start / end time will repeat.',
				'value' => isset($maint_item_data['minterval']) ? $maint_item_data['minterval'] : '0'
			),
			'stime' => array(
				'friendly_name' => 'Start Time',
				'method' => 'textbox',
				'max_length' => 100,
				'description' => 'The start date / time for this schedule. Most date / time formats accepted.',
				'default' => date("F j, Y, G:i", time()),
				'value' => isset($maint_item_data['stime']) ?  date("l, F j, Y, G:i", $maint_item_data['stime']) : ''
			),
			'etime' => array(
				'friendly_name' => 'End Time',
				'method' => 'textbox',
				'max_length' => 100,
				'default' => date("F j, Y, G:i", time() + 3600),
				'description' => 'The end date / time for this schedule. Most date / time formats accepted.',
				'value' => isset($maint_item_data['etime']) ? date("l, F j, Y, G:i", $maint_item_data['etime']) : ''
			),
			"save_component" => array(
				"method" => "hidden",
				"value" => "1"
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
		<!-- Make it look intelligent :) -->
		<script type="text/javascript">
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
			document.getElementById('row_minterval').style.display  = status;
		}
	
		changemaintType ();
		</script>
		<?php
	}elseif ($_REQUEST["tab"] == "hosts") {
		thold_hosts($header_label);
	}elseif ($_REQUEST["tab"] == "webseer") {
		webseer_urls($header_label);
	}else{
		api_plugin_hook_function('maint_show_tab', $header_label);
	}
}

function schedules() {
	global $colors, $mactions;

	html_start_box('<strong>Maintenance Schedules</strong>', '100%', $colors['header'], '3', 'center', 'maint.php?tab=general&action=edit');

	html_header_checkbox(array('Name', 'Type', 'Start', 'End', 'Interval', 'Active', 'Enabled'));
	$yesno = array(0 => 'No', 1 => 'Yes', 'on' => 'Yes', 'off' => 'No');
	$schedules = db_fetch_assoc('SELECT * FROM plugin_maint_schedules ORDER BY name');

	$types = array(1 => "One Time", 2 => "Reoccurring");
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

	form_hidden_box('save_list', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($mactions);

	print "</form>\n";
}

function thold_hosts($header_label) {
	global $colors, $assoc_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_template_id"));
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("id"));

	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up associated string */
	if (isset($_REQUEST["associated"])) {
		$_REQUEST["associated"] = sanitize_search_string(get_request_var_request("associated"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clearf"])) {
		kill_session_var("sess_maint_current_page");
		kill_session_var("sess_maint_filter");
		kill_session_var("sess_maint_host_template_id");
		kill_session_var("sess_maint_rows");
		kill_session_var("sess_maint_associated");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["associated"]);
		unset($_REQUEST["host_template_id"]);
		unset($_REQUEST["rows"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_maint_current_page", "1");
	load_current_session_value("filter", "sess_maint_filter", "");
	load_current_session_value("associated", "sess_maint_associated", "true");
	load_current_session_value("host_template_id", "sess_maint_host_template_id", "-1");
	load_current_session_value("rows", "sess_maint_rows", read_config_option("num_rows_device"));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_device");
	}

	?>
	<script type="text/javascript">
	<!--

	function applyViewDeviceFilterChange(objForm) {
		strURL = '?tab=hosts&action=edit&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&associated=' + objForm.associated.checked;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	function clearViewDeviceFilterChange(objForm) {
		strURL = '?tab=hosts&action=edit&id=<?php print get_request_var_request('id');?>&clearf=true'
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>Associated Devices</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_devices" method="post" action="maint.php?action=edit&tab=hosts">
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Type:&nbsp;
					</td>
					<td width="1">
						<select name="host_template_id" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if (get_request_var_request("host_template_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("host_template_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc("SELECT DISTINCT ht.id, ht.name 
								FROM host_template AS ht
								INNER JOIN host AS h
								ON h.host_template_id=ht.id 
								ORDER BY ht.name");

							if (sizeof($host_templates) > 0) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template["id"] . "'"; if (get_request_var_request("host_template_id") == $host_template["id"]) { print " selected"; } print ">" . htmlspecialchars($host_template["name"]) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="20">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>" onChange="applyViewDeviceFilterChange(document.form_devices)">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' name='associated' id='associated' onChange='applyViewDeviceFilterChange(document.form_devices)' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'>Associated</label>
					</td>
					<td nowrap>
						&nbsp;<input type="button" value="Go" onClick='applyViewDeviceFilterChange(document.form_devices)' title="Set/Refresh Filters">
					</td>
					<td nowrap>
						<input type="button" name="clearf" value="Clear" onClick='clearViewDeviceFilterChange(document.form_devices)' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE (h.hostname LIKE '%%" . get_request_var_request("filter") . "%%' 
			OR h.description LIKE '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var_request("host_template_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_template_id") == "0") {
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " h.host_template_id=0";
	}elseif (!empty($_REQUEST["host_template_id"])) {
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " h.host_template_id=" . get_request_var_request("host_template_id");
	}

	if (get_request_var_request("associated") == "false") {
		/* Show all items */
	} else {
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " type=1 AND schedule=" . get_request_var_request('id');
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='maint.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM host AS h
		INNER JOIN (SELECT DISTINCT host_id FROM thold_data) AS td 
		ON h.id=td.host_id
		LEFT JOIN plugin_maint_hosts AS pmh
		ON h.id=pmh.host
		$sql_where");

	$host_graphs       = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id"), "host_id", "graphs");
	$host_data_sources = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id"), "host_id", "data_sources");

	//$sql_query = "SELECT h.*, pmh.host AS associated 
	//	FROM host AS h
	//	INNER JOIN (SELECT DISTINCT host_id FROM thold_data) AS td 
	//	ON h.id=td.host_id
	//	LEFT JOIN plugin_maint_hosts AS pmh
	//	ON h.id=pmh.host
	//	$sql_where 
	//	LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows");

	$sql_query = "SELECT h.*, (SELECT schedule FROM plugin_maint_hosts WHERE host = h.id AND schedule = " . get_request_var_request("id") . ") AS associated 
		FROM host as h

		$sql_where 
		LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows");

	//print $sql_query;

	$hosts = db_fetch_assoc($sql_query);

	/* generate page list */
	if ($total_rows > 0) {
		$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rows"), $total_rows, "notify_lists.php?action=edit&id=" . get_request_var_request('id'));

		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
						<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("maint.php?tab=hosts&action=edit&id=" . get_request_var_request("id") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . ((get_request_var_request("rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
										<strong>"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("maint.php?tab=hosts&action=edit&id=" . get_request_var_request("id") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='center' class='textHeaderDark'>
								No Rows Found
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}

	print $nav;

	$display_text = array("Description", "ID", "Associated Schedules", "Graphs", "Data Sources", "Status", "Hostname");

	html_header_checkbox($display_text);

	$i = 0;
	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $host["id"]); $i++;
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($host["description"])) : htmlspecialchars($host["description"])), $host["id"], 250);
			form_selectable_cell(round(($host["id"]), 2), $host["id"]);
			if ($host['associated'] != '') {
				$names = '<span style="color:green;font-weight:bold;">Current List</span>';
			} else {
				$names = '';
			}
			if (sizeof($lists = db_fetch_assoc("SELECT name FROM plugin_maint_schedules INNER JOIN plugin_maint_hosts ON plugin_maint_schedules.id=plugin_maint_hosts.schedule WHERE type=1 AND host=" . $host['id'] . " AND plugin_maint_schedules.id != " . get_request_var_request('id')))) {
				foreach($lists as $name) {
					$names .= (strlen($names) ? ", ":"") . "<span style='color:purple;font-weight:bold;'>" . $name['name'] . "</span>";
				}
			}
			if ($names == '') {
				form_selectable_cell('<span style="color:red;font-weight:bold;">No Schedules</span>', $host['id']);
			} else {
				form_selectable_cell($names, $host['id']);
			}
			form_selectable_cell((isset($host_graphs[$host["id"]]) ? $host_graphs[$host["id"]] : 0), $host["id"]);
			form_selectable_cell((isset($host_data_sources[$host["id"]]) ? $host_data_sources[$host["id"]] : 0), $host["id"]);
			form_selectable_cell(get_colored_device_status(($host["disabled"] == "on" ? true : false), $host["status"]), $host["id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($host["hostname"])) : htmlspecialchars($host["hostname"])), $host["id"]);
			form_checkbox_cell($host["description"], $host["id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	} else {
		print "<tr><td><em>No Associated Hosts Found</em></td></tr>";
	}
	html_end_box(false);

	form_hidden_box("id", get_request_var_request("id"), "");
	form_hidden_box("save_hosts", "1", "");

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($assoc_actions);

	print "</form>\n";
}

function webseer_urls($header_label) {
	global $colors, $assoc_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up associated string */
	if (isset($_REQUEST["associated"])) {
		$_REQUEST["associated"] = sanitize_search_string(get_request_var_request("associated"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clearf"])) {
		kill_session_var("sess_maint_current_page");
		kill_session_var("sess_maint_filter");
		kill_session_var("sess_maint_rows");
		kill_session_var("sess_maint_associated");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["associated"]);
		unset($_REQUEST["rows"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_maint_ws_current_page", "1");
	load_current_session_value("filter", "sess_maint_ws_filter", "");
	load_current_session_value("associated", "sess_maint_ws_associated", "true");
	load_current_session_value("rows", "sess_maint_ws_rows", read_config_option("num_rows_device"));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_device");
	}

	?>
	<script type="text/javascript">
	<!--

	function applyViewFilterChange(objForm) {
		strURL = '?tab=webseer&action=edit&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&associated=' + objForm.associated.checked;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	function clearViewFilterChange(objForm) {
		strURL = '?tab=webseer&action=edit&id=<?php print get_request_var_request('id');?>&clearf=true'
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>Associated Web URL's</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_devices" method="post" action="maint.php?action=edit&tab=webseer">
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="20">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>" onChange="applyViewFilterChange(document.form_devices)">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyViewFilterChange(document.form_devices)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' name='associated' id='associated' onChange='applyViewFilterChange(document.form_devices)' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'>Associated</label>
					</td>
					<td nowrap>
						&nbsp;<input type="button" value="Go" onClick='applyViewFilterChange(document.form_devices)' title="Set/Refresh Filters">
					</td>
					<td nowrap>
						<input type="button" name="clearf" value="Clear" onClick='clearViewDeviceFilterChange(document.form_devices)' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE ((u.url LIKE '%%" . get_request_var_request("filter") . "%%') 
			OR (u.display_name LIKE '%%" . get_request_var_request("filter") . "%%') 
			OR (u.ip LIKE '%%" . get_request_var_request("filter") . "%%'))";
	}else{
		$sql_where = '';
	}

	if (get_request_var_request("associated") == "false") {
		/* Show all items */
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " (pmh.type=2 OR pmh.type IS NULL)";
		
	} else {
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " pmh.type=2 AND pmh.schedule=" . get_request_var_request('id');
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='maint.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM plugin_webseer_urls AS u
		LEFT JOIN plugin_maint_hosts AS pmh
		ON u.id=pmh.host
		$sql_where");

	$sql_query = "SELECT u.*, pmh.host AS associated, pmh.type AS maint_type
		FROM plugin_webseer_urls AS u
		LEFT JOIN plugin_maint_hosts AS pmh
		ON u.id=pmh.host
		$sql_where 
		LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows");

	//print $sql_query;

	$urls = db_fetch_assoc($sql_query);

	/* generate page list */
	if ($total_rows > 0) {
		$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rows"), $total_rows, "notify_lists.php?action=edit&id=" . get_request_var_request('id'));

		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
						<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("maint.php?tab=webseer&action=edit&id=" . get_request_var_request("id") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . ((get_request_var_request("rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
										<strong>"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("maint.php?tab=webseer&action=edit&id=" . get_request_var_request("id") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='center' class='textHeaderDark'>
								No Rows Found
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}

	print $nav;

	$display_text = array("Description", "ID", "Associated Schedules", "Enabled" , "Hostname", "URL");

	html_header_checkbox($display_text);

	$i = 0;
	if (sizeof($urls)) {
		foreach ($urls as $url) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $url["id"]); $i++;
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($url["display_name"])) : htmlspecialchars($url["display_name"])), $url["id"], 250);
			form_selectable_cell(round(($url["id"]), 2), $url["id"]);
			if ($url['associated'] != '' && $url['maint_type'] == '2') {
				form_selectable_cell('<span style="color:green;font-weight:bold;">Current List</span>', $url['id']);
			}else{
				if (sizeof($lists = db_fetch_assoc("SELECT name FROM plugin_maint_schedules INNER JOIN plugin_maint_hosts ON plugin_maint_schedules.id=plugin_maint_hosts.schedule WHERE type=2 AND host=" . $url['id']))) {
					$names = '';
					foreach($lists['name'] as $name) {
						$names .= (strlen($names) ? ", ":"") . "<span style='color:purple;font-weight:bold;'>$name</span>";
					}
					form_selectable_cell($names, $url['id']);
				}else{
					form_selectable_cell('<span style="color:red;font-weight:bold;">No Schedules</span>', $url['id']);
				}
			}
			form_selectable_cell(($url['enabled'] == 'on' ? 'Enabled':'Disabled'), $url['id']);
			if (empty($url['ip'])) {
				$url['ip'] = 'USING DNS';
			}
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", "<i>" . htmlspecialchars($url["ip"])) . "</i>" : "<i>" . htmlspecialchars($url["ip"]) . "</i>"), $url["id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($url["url"])) : htmlspecialchars($url["url"])), $url["id"]);
			form_checkbox_cell($url["display_name"], $url["id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	} else {
		print "<tr><td><em>No Associated WebSeer URL's Found</em></td></tr>";
	}
	html_end_box(false);

	form_hidden_box("id", get_request_var_request("id"), "");
	form_hidden_box("save_webseer", "1", "");

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($assoc_actions);

	print "</form>\n";
}

