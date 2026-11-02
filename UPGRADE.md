Upgrading this plugin
=====================

This is an internal documentation for plugin developers with some notes what has to be considered when updating this plugin to a new Moodle major version.

General
-------

* Generally, this is a quite simple plugin with just one purpose.
* It does not rely on any fluctuating library functions and should remain quite stable between Moodle major versions.
* Thus, the upgrading effort is low.


Upstream changes
----------------

* This plugin relies on the thiry-party apc.php file (https://github.com/krakjoe/apcu/commits/master/apc.php) which is dropped manually or automatically into the MOODLEDATA/tool_apcu directory. This file is under active development.
* The download of the apc.php file is pinned to a particular upstream commit and verified with a SHA-256 checksum. With each plugin release, check if there are newer upstream commits which changed apc.php. If there are, test the new file with this plugin and bump the pinned version in all of these places:
  * The commit hash in the download URL in tool_apcu_get_guidrop_url() in locallib.php
  * The SHA-256 checksum in tool_apcu_get_guidrop_checksum() in locallib.php (e.g. computed with `curl -s <URL> | shasum -a 256`)
  * The download URL in README.md
  * The version (latest upstream release tag plus commit hash) in thirdpartylibs.xml
* The APCu management GUI's own authentication is disabled by this plugin. Instead, the write operations of the APCu management GUI (currently clearing the cache with the `CC` parameter and deleting a user cache entry with the `DU` parameter) are protected by a sesskey check in index.php, the sesskey is added to the corresponding links in the APCu management GUI and the page is redirected after the write operation. With each update of the apc.php file, check if new write operations (i.e. request parameters which make the APCu management GUI change the APCu cache or anything else) have been added upstream. If there are, add their request parameters together with a language string for the message which is shown after the write operation to tool_apcu_get_write_params() in locallib.php, add this language string to lang/en/tool_apcu.php and extend the tests.


Automated tests
---------------

* The plugin has a good coverage with Behat tests which test all of the plugin's user stories.


Manual tests
------------

* There aren't any manual tests needed to upgrade this plugin.


Visual checks
-------------

* There aren't any additional visual checks in the Moodle GUI needed to upgrade this plugin.
