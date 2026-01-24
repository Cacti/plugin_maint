# ChangeLog

--- 1.3 ---

* security: Unsafe unserialization when working with schedules
* issue: Fix to keep same local hour for standard and daylight saving time
* issue: Initial start/end date/time no longer kept, always has next interval
* issue: Log calculated next interval
* issue: Fix some PHP 8.1.2 compatibility issues
* issue#15: Fix webseer tab to not show items before schedule is created
* feature#14: Webseer tab functional (webseer plugin update required to use schedule)
* feature#18: Device tab filter
* featuer#29: Add Servcheck

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

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
