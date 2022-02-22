# maint

The Cacti maint plugin is for the scheduling of maintenance so that Thold /
Webseer / Other plugins will not alert during that time period.

## Installation

To install the plugin, please refer to the Plugin Installation Documentation

## Possible Bugs

If you find a problem, let us know! [Report a bug!](http://cacti.net/bugs.php)

## Future Changes

Threshold Escalation

Add feature to run scripts or do other things besides email

Got any ideas or complaints, please see the forums.  If you find a bug, they can
be logged on GitHub.

## Changelog

* issue: Fix some PHP 8.1.2 compatibility issues

* issue#15: Fix webseer tab to not show items before schedule is created

* feature#14: webseer tab functional (webseer plugin update required to use schedule)

* feature#18: device tab filter

--- 1.2 ---

* issue#11: PHP 7.2 compatibility: The each() function is deprecated

* issue#9: Maintenance filtering causes undefined variable error

* feature: New hook for maintenance checks (is_device_in_maintenance)

--- 1.1 ---

* Updates for i18n by contributors

* feature: Update Spanish translation

--- 1.0 ---

* Updates for Cacti 1.0

--- 0.3 ---

* Add dropdown for quick updating of the Scheduled Time

* Add User Friendly Way of Associating/Disassociating Objects

* Allow Plugins to Hook Maintenance Plugin

--- 0.2 ---

* Order by Name by default

* Don't check disabled schedules

--- 0.1 ---

* Initial Version
