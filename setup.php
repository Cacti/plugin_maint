<?php

declare(strict_types = 1);

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
 * Return the CSP nonce attribute for inline <script> tags, safely across
 * Cacti versions. Newer Cacti releases enforce a Content-Security-Policy that
 * requires a per-request nonce on parser-inserted scripts; older releases lack
 * the CactiSecureHeaders class, so this returns an empty string there.
 *
 * @return string The nonce attribute when supported, otherwise empty string.
 */
function plugin_maint_csp_nonce(): string {
	if (class_exists('CactiSecureHeaders')) {
		return CactiSecureHeaders::getNonceAttribute();
	}

	return '';
}

/**
 * Get the plugin version information from INFO file
 *
 * Used by Cacti's plugin architecture via the api_plugin_version hook,
 * and internally wherever this plugin needs to report its own version.
 *
 * @return array Plugin metadata array containing version, author, homepage, etc.
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_maint_version(): array {
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/maint/INFO', true);

	if (!isset($info['info']) || !is_array($info['info'])) {
		return [];
	}

	return $info['info'];
}

/**
 * Install the maintenance plugin
 *
 * Registers all hooks, realms, and creates database tables. Invoked by
 * Cacti's plugin architecture when an administrator installs this plugin
 * from Console > Plugin Management.
 *
 * @return void
 */
function plugin_maint_install(): void {
	global $config;

	api_plugin_register_hook('maint', 'config_arrays', 'maint_config_arrays', 'setup.php');
	api_plugin_register_hook('maint', 'draw_navigation_text', 'maint_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('maint', 'device_edit_top_links', 'maint_device_edit_top_links', 'setup.php');
	api_plugin_register_hook('maint', 'is_device_in_maintenance', 'plugin_maint_check_cacti_host', 'includes/functions.php');
	api_plugin_register_hook('maint', 'device_action_array', 'maint_device_action_array', 'setup.php');
	api_plugin_register_hook('maint', 'device_action_prepare', 'maint_device_action_prepare', 'setup.php');
	api_plugin_register_hook('maint', 'device_action_execute', 'maint_device_action_execute', 'setup.php');
	api_plugin_register_realm('maint', 'maint.php', 'Maintenance Schedules', 1);

	require_once($config['base_path'] . '/plugins/maint/includes/database.php');

	maint_setup_database();
}

/**
 * Uninstall the maintenance plugin
 *
 * Currently a no-op placeholder (this plugin's tables/settings are left
 * in place rather than being dropped on uninstall). Invoked by Cacti's
 * plugin architecture when an administrator uninstalls this plugin from
 * Console > Plugin Management.
 *
 * @return void
 */
function plugin_maint_uninstall(): void {
}

/**
 * Runs any pending schema/version upgrade for this plugin. Invoked by
 * Cacti's plugin architecture on relevant page loads.
 *
 * @return bool Always returns true
 */
function plugin_maint_check_config(): bool {
	plugin_maint_check_upgrade();

	return true;
}

/**
 * Upgrade the maintenance plugin
 *
 * Runs any pending schema/version upgrade for this plugin. Invoked by
 * Cacti's plugin architecture when an installed plugin's version
 * increases.
 *
 * @return bool Always returns false
 */
function plugin_maint_upgrade(): bool {
	plugin_maint_check_upgrade();

	return false;
}

/**
 * Applies any pending schema migration for this plugin on a version change,
 * based on comparing the installed version recorded in plugin_config
 * against the current INFO file version. Only runs on plugins.php or
 * maint.php to avoid the version lookup on every page. Refreshes the schema
 * through includes/database.php and updates the full plugin_config row.
 * Called from plugin_maint_check_config()/plugin_maint_upgrade().
 *
 * @return void
 *
 * @global array  $config           Cacti global configuration array; used
 *                                   to locate the database/functions
 *                                   libraries and this plugin's schema file.
 * @global object $database_default  Reserved/declared for parity with the
 *                                   included library files; not used
 *                                   directly here.
 */
function plugin_maint_check_upgrade(): void {
	global $config, $database_default;

	// Only run this check on a page that actually needs the plugin's data.
	$files = ['plugins.php', 'maint.php'];

	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files, true)) {
		return;
	}

	require_once($config['library_path'] . '/database.php');
	require_once($config['library_path'] . '/functions.php');
	require_once($config['base_path'] . '/plugins/maint/includes/database.php');

	$info = plugin_maint_version();

	if (empty($info['version']) || empty($info['longname']) || empty($info['author']) || empty($info['homepage'])) {
		return;
	}

	$current = $info['version'];
	$old     = db_fetch_cell_prepared('SELECT version FROM plugin_config WHERE directory = ?', ['maint']);

	if ($current != $old) {
		// Refresh the schema from the shared definition (create when missing,
		// db_update_table() diff when it already exists). Only record the new
		// version once every table reconciled, so a failed refresh is retried on
		// the next request instead of being masked by a now-matching version.
		if (maint_upgrade_tables()) {
			// Re-register the is_device_in_maintenance hook so existing installs
			// pick up the relocated includes/functions.php file. plugin_maint_install()
			// registers the new path, but it never re-runs on upgrade, and core's
			// upgrade path only touches plugin_config, never plugin_hooks.
			api_plugin_register_hook('maint', 'is_device_in_maintenance', 'plugin_maint_check_cacti_host', 'includes/functions.php');

			db_execute_prepared('UPDATE plugin_config
				SET version = ?, name = ?, author = ?, webpage = ?
				WHERE directory = ?',
				[$info['version'], $info['longname'], $info['author'], $info['homepage'], 'maint']);

			// Remove files tombstoned in manifest.json plus the dev-only tests/ tree.
			maint_prune_files();
		}
	}
}

/**
 * Configure plugin menu arrays and augment roles
 *
 * Adds the maintenance schedules menu item to the Management menu
 * and augments System Administration roles if the function exists. Called
 * by Cacti core via api_plugin_hook('config_arrays', ...) while building
 * the navigation menu.
 *
 * @return void
 *
 * @global array $menu Cacti's main navigation menu array, extended here
 *                      with this plugin's entry.
 */
function maint_config_arrays(): void {
	global $menu;

	$menu[__('Management')]['plugins/maint/maint.php'] = __('Maintenance Schedules', 'maint');

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('System Administration'), ['maint.php']);
	}
}

/**
 * Configure navigation breadcrumb text for maintenance pages
 *
 * Called by Cacti core via api_plugin_hook('draw_navigation_text', ...)
 * while rendering the page breadcrumb trail.
 *
 * @param array<string, array<string, mixed>> $nav Navigation array
 *
 * @return array<string, array<string, mixed>> Modified navigation array
 */
function maint_draw_navigation_text(array $nav): array {
	$nav['maint.php:']        = ['title' => __('Maintenance Schedules', 'maint'), 'mapping' => 'index.php:', 'url' => 'maint.php', 'level' => '1'];
	$nav['maint.php:edit']    = ['title' => __('(edit)', 'maint'), 'mapping' => 'index.php:', 'url' => 'maint.php', 'level' => '2'];
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

/**
 * Add maintenance action links to device edit page
 *
 * Displays two hidden forms with links:
 * 1. Enable maintenance now (now + 1 hour)
 * 2. Add device to existing schedule
 * Called by Cacti core via
 * api_plugin_hook('device_edit_top_links', ...) while rendering the top
 * of the Device edit page.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to build
 *                        the hidden forms' action URL.
 */
function maint_device_edit_top_links(): void {
	global $config;

	// Get current device id from edit context
	$host_id = get_filter_request_var('id');

	if (empty($host_id)) {
		return;
	}

	$action_url = $config['url_path'] . 'host.php';
	$uid        = 'maint_' . (int) $host_id . '_' . mt_rand(1000, 9999);

	// Hidden form: Enable maintenance (now+1h) using same flow as device list dropdown
	print "<form id='{$uid}_f1' method='post' action='" . html_escape($action_url) . "' style='display:none'>";
	print "<input type='hidden' name='action' value='actions'>";
	print "<input type='hidden' name='drp_action' value='maint'>";
	// Simulate selection of this device as if checked in list
	print "<input type='hidden' name='chk_" . (int) $host_id . "' value='on'>";
	print '</form>';

	// Hidden form: Add device to existing schedule
	print "<form id='{$uid}_f2' method='post' action='" . html_escape($action_url) . "' style='display:none'>";
	print "<input type='hidden' name='action' value='actions'>";
	print "<input type='hidden' name='drp_action' value='maint_add_to_schedule'>";
	print "<input type='hidden' name='chk_" . (int) $host_id . "' value='on'>";
	print '</form>';

	// Links that submit the forms above, reusing the exact same confirmation UI
	print "<br><span class='linkMarker'>*</span><a class='hyperLink' href='#' onclick=\"document.getElementById('{$uid}_f1').submit(); return false;\">" . MAINT_LABEL_ENABLE_NOW . '</a><br>';
	print "<span class='linkMarker'>*</span><a class='hyperLink' href='#' onclick=\"document.getElementById('{$uid}_f2').submit(); return false;\">" . MAINT_LABEL_ADD_TO_SCHEDULE . '</a>';
}

/**
 * Add maintenance actions to device action dropdown
 *
 * Called by Cacti core via api_plugin_hook('device_action_array', ...)
 * while building the device list's bulk-actions dropdown.
 *
 * @param array<string, string> $actions Existing device actions
 *
 * @return array<string, string> Modified actions array with maintenance options
 */
function maint_device_action_array(array $actions): array {
	$actions['maint']                 = MAINT_LABEL_ENABLE_NOW;
	$actions['maint_add_to_schedule'] = MAINT_LABEL_ADD_TO_SCHEDULE;

	return $actions;
}

/**
 * Prepare device action confirmation UI
 *
 * Renders the confirmation dialog for maintenance actions:
 * - Quick maintenance (now + 1 hour)
 * - Add devices to existing schedule
 * Called by Cacti core via api_plugin_hook('device_action_prepare', ...)
 * while rendering the device list's bulk-action confirmation dialog,
 * for the 'maint'/'maint_add_to_schedule' actions added by
 * maint_device_action_array().
 *
 * @param array<string, mixed> $save Action data including drp_action and host_array
 *
 * @return array<string, mixed> Modified save array
 */
function maint_device_action_prepare(array $save): array {
	// Render confirmation details for the maint action
	if (isset($save['drp_action']) && $save['drp_action'] == 'maint') {
		$now            = time();
		$one_hour_later = $now + 3600;

		// Human readable time window
		$time_window = date(date_time_format(), $now) . ' - ' . date(date_time_format(), $one_hour_later);

		// Build device list
		$host_list = '';

		if (!empty($save['host_array']) && is_array($save['host_array'])) {
			foreach ($save['host_array'] as $host_id) {
				$row = db_fetch_row_prepared('SELECT description FROM host WHERE id = ?', [(int) $host_id]);

				if (is_array($row) && !empty($row)) {
					$host_list .= '<li>' . html_escape($row['description']) . '</li>';
				}
			}
		}

		// Output a styled summary box with the window and devices
		$tz           = function_exists('date_default_timezone_get') ? date_default_timezone_get() : '';
		$duration_min = round(($one_hour_later - $now) / 60);

		$summary  = "<div style='border:1px solid #e0e0e0;border-left:4px solid #4caf50;background:#f9fffa;padding:8px 12px;margin:6px 0;'>";
		$summary .= "<div style='display:flex;'>";
		$summary .= '<b> ' . __('Maintenance Window', 'maint') . "</b><span style='color:#666'>(now + " . intval($duration_min) . ' min): </span>';
		$summary .= "<span style='font-family:monospace;margin-left:2em;'>" . html_escape($time_window) . '</span>';
		$summary .= '</div>';

		if (!empty($tz)) {
			$summary .= "<div style='margin-top:6px;color:#666;font-size:11px;'>" . __('Timezone', 'maint') . ': ' . html_escape($tz) . ' • ' . __('Duration', 'maint') . ': ' . intval($duration_min) . ' ' . __('min', 'maint') . '</div>';
		}
		$summary .= '</div>';

		// Maintenance window full-width box
		print "<tr><td colspan='2' class='textArea'>" . $summary . '</td></tr>';

		// Aligned Schedule Name full-width row
		$label_name = __('Schedule name', 'maint');
		$ph_name    = __esc('e.g. Emergency patching', 'maint');

		print "<tr><td colspan='2' class='textArea'>"
			. "<div style='border:1px solid #efefef;background:#fafafa;padding:8px 12px;'>"
			. "<div style='display:flex;align-items:center;gap:12px;'>"
			. "<div style='min-width:180px;font-weight:bold;'>" . html_escape($label_name) . ':</div>'
			. "<input type='text' name='maint_schedule_name' value='' size='50' placeholder='" . $ph_name . "'>"
			. '</div>'
			. '</div>'
			. '</td></tr>';

		// Aligned Schedule Type full-width row
		$one_time_label  = __('One Time', 'maint');
		$recurring_label = __('Recurring', 'maint');

		$every_day  = __('Every Day', 'maint');
		$every_week = __('Every Week', 'maint');
		$label_type = __('Schedule Type', 'maint');

		$row  = "<div style='border:1px solid #efefef;background:#fafafa;padding:8px 12px;'>";
		$row .= "<div style='display:flex;align-items:center;gap:12px;'>";
		$row .= "<div style='min-width:180px;font-weight:bold;'>" . html_escape($label_type) . ':</div>';

		$row .= "<select name='maint_mtype' id='maint_mtype' style='min-width:220px' onchange=\"document.getElementById('maint_interval_wrap').style.display=(this.value==='2')?'':'none'\">"
			. "<option value='1' selected>" . html_escape($one_time_label) . '</option>'
			. "<option value='2'>" . html_escape($recurring_label) . '</option>'
			. '</select>';

		$row .= "<span id='maint_interval_wrap' style='margin-left:12px; display:none;'>"
			. "<select name='maint_minterval' id='maint_minterval'>"
			. "<option value='86400'>" . html_escape($every_day) . '</option>'
			. "<option value='604800'>" . html_escape($every_week) . '</option>'
			. '</select>'
			. '</span>';

		$row .= '</div>';
		$row .= '</div>';

		print "<tr><td colspan='2' class='textArea'>" . $row . '</td></tr>';

		// Initialize visibility on load
		print "<tr style='display:none'><td colspan='2'><script " . plugin_maint_csp_nonce() . ">(function(){try{var e=document.getElementById('maint_mtype'),w=document.getElementById('maint_interval_wrap');if(e&&w){w.style.display=(e.value==='2')?'':'none';}}catch(ex){}})();</script></td></tr>";

		$devices  = "<div style='border:1px solid #efefef;background:#fafafa;padding:8px 12px;'>";
		$devices .= '<b>' . __('Devices', 'maint') . ' (' . count((array) $save['host_array']) . ')</b>';
		$devices .= "<ul style='margin:6px 0 0 18px;'>" . $host_list . '</ul>';
		$devices .= '</div>';

		print "<tr><td colspan='2' class='textArea'>" . $devices . '</td></tr>';
	} elseif (isset($save['drp_action']) && $save['drp_action'] == 'maint_add_to_schedule') {
		// Build device list
		$host_list = '';

		if (!empty($save['host_array']) && is_array($save['host_array'])) {
			foreach ($save['host_array'] as $host_id) {
				$row = db_fetch_row_prepared('SELECT description FROM host WHERE id = ?', [(int) $host_id]);

				if (is_array($row) && !empty($row)) {
					$host_list .= '<li>' . html_escape($row['description']) . '</li>';
				}
			}
		}

		// Load schedules to choose from
		$schedules = db_fetch_assoc_prepared('SELECT id, name, enabled, mtype, stime, etime, minterval
			FROM plugin_maint_schedules
			ORDER BY name', []);

		$select = "<select name='maint_schedule_id' style='min-width:360px'>";

		if (!empty($schedules)) {
			foreach ($schedules as $sc) {
				$label_name = !empty($sc['name']) ? $sc['name'] : ('#' . (int) $sc['id']);
				$window     = date(date_time_format(), (int) $sc['stime']) . ' → ' . date(date_time_format(), (int) $sc['etime']);
				$state      = ($sc['enabled'] === 'on') ? '' : ' (' . __('disabled', 'maint') . ')';
				$select .= "<option value='" . (int) $sc['id'] . "'>" . html_escape($label_name . ' — ' . $window . $state) . '</option>';
			}
		} else {
			$select .= "<option value='' disabled>" . html_escape(__('No schedules found', 'maint')) . '</option>';
		}
		$select .= '</select>';

		print "<tr><td class='textArea'>" . __('Select schedule', 'maint') . ":</td><td class='textArea'>" . $select . '</td></tr>';
		print "<tr><td colspan='2' class='textArea'><div style='border:1px solid #efefef;background:#fafafa;padding:8px 12px;'><b>" . __('Devices', 'maint') . ' (' . count((array) $save['host_array']) . ")</b><ul style='margin:6px 0 0 18px;'>" . $host_list . '</ul></div></td></tr>';
	}

	return $save;
}

/**
 * Execute device maintenance actions
 *
 * Handles two types of actions:
 * 1. 'maint' - Creates a new schedule and associates devices
 * 2. 'maint_add_to_schedule' - Adds devices to existing schedule
 * Called by Cacti core via api_plugin_hook('device_action_execute', ...)
 * once the bulk-action confirmation dialog rendered by
 * maint_device_action_prepare() is submitted.
 *
 * @param string $action The action to execute
 *
 * @return bool True if action was handled, false otherwise
 */
function maint_device_action_execute(string $action): bool {
	if ($action == 'maint') {
		// One-hour quick maintenance: create a new schedule and attach devices
		$now            = time();
		$one_hour_later = $now + 3600;
		$name           = '';

		if (isset($_POST['maint_schedule_name'])) {
			$name = trim($_POST['maint_schedule_name']);
		}

		if ($name === '') {
			$name = __('Quick Maintenance', 'maint');
		}
		$mtype     = isset($_POST['maint_mtype']) ? (int) $_POST['maint_mtype'] : 1;
		$minterval = 0;

		if ($mtype === 2) {
			$minterval = isset($_POST['maint_minterval']) ? (int) $_POST['maint_minterval'] : 86400;

			if ($minterval !== 86400 && $minterval !== 604800) {
				$minterval = 86400;
			}
		}

		// Create a new schedule (one-time or recurring)
		db_execute_prepared('INSERT INTO plugin_maint_schedules
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
					db_execute_prepared('REPLACE INTO plugin_maint_hosts
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
	}

	if ($action == 'maint_add_to_schedule') {
		$schedule_id = isset($_POST['maint_schedule_id']) ? (int) $_POST['maint_schedule_id'] : 0;

		if ($schedule_id <= 0 || ! db_fetch_cell_prepared('SELECT id FROM plugin_maint_schedules WHERE id = ?', [$schedule_id])) {
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

/**
 * Setup database tables for maintenance plugin
 *
 * Moved to includes/database.php; see maint_setup_database() and
 * maint_upgrade_tables() there.
 */

/**
 * Removes files and directories that a previous version of this plugin
 * shipped but that have since moved or been deleted, using the tombstone
 * and whitelist lists in manifest.json. Whitelisted (user-data) paths and
 * any VCS metadata (.git*) are never touched; the dev-only tests/ tree is
 * removed. Any path that resolves outside the plugin directory (a tampered
 * manifest.json) is refused, and any file/directory that cannot be removed
 * (e.g. read-only) is reported to the Cacti log. Any top-level entry that is
 * neither expected nor a tombstone nor whitelisted is logged to the Cacti
 * log and left in place. Called on a plugin version change.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to resolve
 *                       the plugin directory.
 */
function maint_prune_files(): void {
	global $config;

	$plugin_dir    = $config['base_path'] . '/plugins/maint';
	$manifest_path = $plugin_dir . '/manifest.json';

	if (!is_readable($manifest_path)) {
		return;
	}

	$manifest = json_decode((string) file_get_contents($manifest_path), true);

	if (!is_array($manifest)) {
		cacti_log('WARNING: maint manifest.json could not be parsed; skipping file prune', false, 'MAINT');

		return;
	}

	$tombstones = isset($manifest['tombstones']) && is_array($manifest['tombstones']) ? $manifest['tombstones'] : [];
	$expected   = isset($manifest['expected'])   && is_array($manifest['expected'])   ? $manifest['expected']   : [];
	$whitelist  = isset($manifest['whitelist'])  && is_array($manifest['whitelist'])  ? $manifest['whitelist']  : [];

	$protected = function (string $rel) use ($whitelist): bool {
		if (strncmp($rel, '.git', 4) === 0 || strncmp($rel, '.md', 3) === 0) {
			return true;
		}

		foreach ($whitelist as $entry) {
			$entry = trim((string) $entry, '/');

			if ($entry !== '' && ($rel === $entry
				|| strncmp($rel, $entry . '/', strlen($entry) + 1) === 0
				|| strncmp($entry, $rel . '/', strlen($rel) + 1) === 0)) {
				return true;
			}
		}

		return false;
	};

	// Security: resolve the plugin directory so a tampered manifest.json
	// cannot steer the prune outside of it.
	$plugin_real = realpath($plugin_dir);

	// Remove tombstoned (moved/deleted) paths plus the dev-only tests/
	// tree and the phpunit.xml test configuration.
	$remove   = $tombstones;
	$remove[] = 'tests/';
	$remove[] = 'phpunit.xml';

	foreach ($remove as $rel) {
		$rel = trim((string) $rel, '/');

		if ($rel === '' || $protected($rel)) {
			continue;
		}

		// A tombstone must never contain '.'/'..' segments; a tampered manifest
		// could use them to escape the plugin directory or target its root.
		$segments = explode('/', $rel);

		if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
			cacti_log(sprintf('WARNING: maint prune refused to remove %s: path contains a traversal segment (tampered manifest.json?)', $rel), false, 'MAINT');

			continue;
		}

		$path = $plugin_dir . '/' . $rel;

		if (!is_link($path) && !file_exists($path)) {
			continue;
		}

		// Refuse any path that, after resolving symlinks and ../ segments,
		// escapes the plugin directory (protects user data from a tampered
		// manifest.json).
		$anchor = is_link($path) ? dirname($path) : $path;
		$real   = realpath($anchor);

		if ($real === false || ($real !== $plugin_real && strncmp($real, $plugin_real . DIRECTORY_SEPARATOR, strlen((string) $plugin_real) + 1) !== 0)) {
			cacti_log(sprintf('WARNING: maint prune refused to remove %s: path resolves outside the plugin directory (tampered manifest.json?)', $rel), false, 'MAINT');

			continue;
		}

		if (is_dir($path) && !is_link($path)) {
			$removed = maint_rmtree($path);
		} else {
			$removed = @unlink($path);
		}

		if (!$removed) {
			cacti_log(sprintf('WARNING: maint upgrade could not remove %s (check file/directory permissions)', $rel), false, 'MAINT');
		}
	}

	// Surface any top-level entry the manifest does not account for.
	$known = [];

	foreach (array_merge($expected, $tombstones) as $entry) {
		$top = explode('/', trim((string) $entry, '/'))[0];

		if ($top !== '') {
			$known[$top] = true;
		}
	}

	$entries = scandir($plugin_dir);

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..' || $entry === 'tests' || $entry === 'phpunit.xml' || $protected($entry) || isset($known[$entry])) {
			continue;
		}

		cacti_log(sprintf('WARNING: maint upgrade found a file/directory not described in manifest.json: %s (left in place)', $entry), false, 'MAINT');
	}
}

/**
 * Recursively deletes a directory and its contents. Symlinks are removed
 * without being followed. Helper for maint_prune_files().
 *
 * @param string $dir Absolute path to the directory to remove.
 *
 * @return bool True if the directory and everything under it was removed;
 *              false if any entry could not be deleted.
 */
function maint_rmtree(string $dir): bool {
	$entries = scandir($dir);
	$ok      = true;

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $dir . '/' . $entry;

		if (is_dir($path) && !is_link($path)) {
			if (!maint_rmtree($path)) {
				$ok = false;
			}
		} elseif (!@unlink($path)) {
			$ok = false;
		}
	}

	if (!@rmdir($dir)) {
		$ok = false;
	}

	return $ok;
}
