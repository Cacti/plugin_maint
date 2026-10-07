# maint

The Cacti maint plugin is for the scheduling of maintenance so that Thold /
Webseer / Other plugins will not alert during that time period.

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
