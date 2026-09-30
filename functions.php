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
 * Back-compat shim.
 *
 * Cacti core's lib/snmpagent.php (snmpagent_poller_bottom) hard-codes
 * include_once('plugins/maint/functions.php') to load
 * plugin_maint_check_cacti_host() during the poller run. The plugin's
 * functions were relocated to includes/functions.php, so this root file
 * remains only to satisfy that fixed core path and forward to the new
 * location. Remove once no supported Cacti release includes this path.
 */

require_once(__DIR__ . '/includes/functions.php');
