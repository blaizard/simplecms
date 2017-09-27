<?php
	/**
	 * This script will load the environment and everything else
	 */

	/* Check if the config file exists, if not create it from the example */
	if (!file_exists(".ircms.php")) {
		copy(".ircms/conf.php.example", ".ircms.php");
	}
	require_once(".ircms.php");

	/* Load the dependencies */
	require_once(".ircms/core/path.php");
	require_once(".ircms/core/url.php");
	require_once(".ircms/core/env.php");
	require_once(".ircms/core/content.php");
	require_once(".ircms/core/ircms.php");
	require_once(".ircms/core/content.php");
	require_once(".ircms/core/routing.php");
	require_once(".ircms/core/page.php");
	require_once(".ircms/core/pageContent.php");
	require_once(".ircms/core/cache.php");
	require_once(".ircms/core/html.php");
?>