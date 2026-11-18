# maint

The Cacti maint plugin is for the scheduling of maintenance so that Thold /
Webseer / Other plugins will not alert during that time period.

## Versions and compatibility

This README describes the `develop` source branch.

| Code | Plugin version | Minimum Cacti declared in `INFO` |
| --- | --- | --- |
| [Source branch](INFO) | 1.3 | 1.2.29 |
| [Published v1.3](https://github.com/Cacti/plugin_maint/releases/tag/v1.3) | 1.3 | 1.2.0 |

Use the published release’s assets and tagged documentation when installing that
package. Branch code can include later changes, even when its version number
matches the release. Cacti’s own PHP and database requirements also apply.

Install this plugin in **`<cacti-root>/plugins/maint`**, using the directory
name `maint` rather than `plugin_maint`.

See [CHANGELOG.md](CHANGELOG.md) for the version history.

## Cacti compatibility

If you are running a version of Cacti below 1.2.31, please add the function
below to the `applySkin()` function in `include/layout.js` to enable the Cancel
buttons on forms to work:

```js
$(document).off('click.cactiReturnTo', '.cactiReturnTo')
    .on('click.cactiReturnTo', '.cactiReturnTo', function(event) {
        event.preventDefault();
        cactiReturnTo($(this).attr('data-url'));
    });
```

## Installation

To install the plugin, please refer to the Plugin Installation Documentation

## Possible Bugs

If you find a problem, let us know! [Report a bug!](http://cacti.net/bugs.php)

## Future Changes

Threshold Escalation

Add feature to run scripts or do other things besides email

Got any ideas or complaints, please see the forums.  If you find a bug, they can
be logged on GitHub.

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
