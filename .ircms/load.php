<?php
	/**
	 * This script will load the environment and everything else
	 */

	/* Check if the config file exists, if not create it from the example */
	if (!file_exists(".ircms/conf.php")) {
		copy(".ircms/conf.php.example", ".ircms/conf.php");
	}
	require_once(".ircms/conf.php");

	/* Load the dependencies */
	require_once(".ircms/core/path.php");
	require_once(".ircms/core/url.php");
	require_once(".ircms/core/env.php");
	require_once(".ircms/core/content.php");
	require_once(".ircms/core/ircms.php");
	require_once(".ircms/core/content.php");
	require_once(".ircms/core/page.php");
	require_once(".ircms/core/cache.php");
?>