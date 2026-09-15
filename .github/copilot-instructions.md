# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`maint`, "Maintenance Scheduler", version 1.3) targeting Cacti 1.2.0+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: Compatible with Cacti 1.2.x supported versions
- **Platform**: Cacti Plugin Architecture — schedules maintenance windows to suppress alerts for Thold/WebSeer/Servcheck
- **Database**: MySQL/MariaDB

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`)
- Optionally integrates with the `thold`, `webseer`, and `servcheck` plugins (tab visibility depends on which are installed)

## Project Structure

```
maint/                # Repository root (install to plugins/maint/ in Cacti)
├── locales/             # Internationalization files
├── functions.php          # Runtime maintenance checks (plugin_maint_check_* -> plugin_maint_check_schedule)
├── maint.php                # Main UI/controller: CRUD schedules, tabbed views, host associations
├── INFO                        # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                     # Plugin entry points and hooks (menu, realms, device actions, maintenance hook)
```

## Naming Conventions

### Function Names
Hook/lifecycle and runtime-check functions use the `plugin_maint_`/`maint_` prefixes: `plugin_maint_check_schedule()`, `maint_setup_database()`, `maint_device_action_prepare()`, `maint_device_action_execute()`. Match the existing prefix used by the function you are editing.

### Database Tables
Tables are created in `maint_setup_database()` via `api_plugin_db_table_create()`: `plugin_maint_schedules` and `plugin_maint_hosts`.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
Use prepared DB helpers (`db_fetch_*_prepared()`, `db_execute_prepared()`, `sql_save()`) instead of string-concatenated SQL:

```php
// CORRECT
db_fetch_row_prepared('SELECT * FROM plugin_maint_schedules WHERE id = ?', array($id));

// WRONG
db_fetch_row("SELECT * FROM plugin_maint_schedules WHERE id = $id");
```

### Input Validation
Use Cacti request helpers (`get_request_var()`, `get_filter_request_var()`, `sanitize_unserialize_selected_items()`) for input validation.

## Database Operations

Tables are created in `maint_setup_database()` via `api_plugin_db_table_create()`; `plugin_maint_check_schedule()` updates `stime`/`etime` for recurring schedules when windows pass, and schedule listing in `maint.php` uses it to compute "Active" state.

## Internationalization

All user-facing strings are localized via `__`/`__esc`, with translations in `locales/`.

## Plugin Architecture

### Integration Points
Maintenance checks are exposed through the `is_device_in_maintenance` hook in `setup.php`. Tab availability in `maint.php` depends on installed plugins via `api_plugin_is_enabled('thold'|'webseer'|'servcheck')`. Device action flows are implemented in `maint_device_action_prepare()` and `maint_device_action_execute()`.

### UI Rendering
UI rendering uses Cacti helpers (`top_header`, `bottom_footer`, `form_start`, `html_start_box`, `draw_edit_form`, `form_*`).

## Developer Workflows

No build/test commands are documented in this repo; changes are validated within a running Cacti instance. Install/enable the plugin through Cacti so `plugin_maint_install()` creates schema and hooks.

## Best Practices

1. Always use prepared DB helpers rather than string-concatenated SQL.
2. Gate optional tab/feature integration behind `api_plugin_is_enabled()` checks for the relevant sibling plugin.
3. Wrap all user-facing strings with `__()`/`__esc()`.
4. Keep schedule "Active" state logic centralized in `plugin_maint_check_schedule()` rather than duplicating the window-comparison logic elsewhere.

## Common Pitfalls to Avoid

```php
// WRONG - string-concatenated SQL
db_execute("UPDATE plugin_maint_schedules SET etime = '$etime' WHERE id = $id");

// CORRECT
db_execute_prepared('UPDATE plugin_maint_schedules SET etime = ? WHERE id = ?', array($etime, $id));
```

## Version Control

Document all changes via commit history; use descriptive commit messages referencing issue/PR numbers when applicable.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
