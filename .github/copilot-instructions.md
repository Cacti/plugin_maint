# Copilot instructions for maint plugin

## Project overview
- This repo is a Cacti plugin that schedules maintenance windows to suppress alerts for Thold/WebSeer/Servcheck.
- Main UI/controller is `maint.php` (CRUD schedules, tabbed views, host associations).
- Plugin entry points and hooks live in `setup.php` (menu, realms, device actions, maintenance hook).
- Runtime maintenance checks are in `functions.php` (`plugin_maint_check_*` → `plugin_maint_check_schedule`).

## Data model and flow
- Tables are created in `maint_setup_database()` via `api_plugin_db_table_create`: `plugin_maint_schedules` and `plugin_maint_hosts`.
- `plugin_maint_check_schedule()` updates `stime/etime` for recurring schedules when windows pass.
- Schedule listing in `maint.php` uses `plugin_maint_check_schedule()` for “Active” state.

## Conventions to follow
- Use Cacti request helpers (`get_request_var`, `get_filter_request_var`, `sanitize_unserialize_selected_items`) for input validation.
- Use prepared DB helpers (`db_fetch_*_prepared`, `db_execute_prepared`, `sql_save`) instead of string-concatenated SQL.
- UI rendering uses Cacti helpers (`top_header`, `bottom_footer`, `form_start`, `html_start_box`, `draw_edit_form`, `form_*`).
- All user-facing strings are localized via `__`/`__esc` with translations in `locales/`.

## Integration points
- Maintenance checks are exposed through the `is_device_in_maintenance` hook in `setup.php`.
- Tab availability in `maint.php` depends on installed plugins (`api_plugin_is_enabled('thold'|'webseer'|'servcheck')`).
- Device action flows are implemented in `maint_device_action_prepare()` and `maint_device_action_execute()`.

## Developer workflows
- No build/test commands are documented in this repo; changes are validated within a running Cacti instance.
- Install/enable the plugin through Cacti so `plugin_maint_install()` creates schema and hooks.
