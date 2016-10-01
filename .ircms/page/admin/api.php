<?php
/*	if (!defined('IRCMSC_ADMIN') || !IRCMSC_ADMIN) {
		exception_throw("Admin area is disabled.");
		die();
	}*/

	require_once(".ircms/deps/ircom.php");
	require_once(".ircms/deps/irexplorer.php");

	/* Make sure the required query strings are present */
	if (!isset($_GET["type"])) {
		Ircom::error("Incomplete query string, `type' is missing.");
	}

	/* Select which type of saving it is */
	switch ($_GET["type"]) {
	/* Update the data */
	case "content":
		if (!isset($_GET["savepath"])) {
			Ircom::error("Incomplete query string, `savepath' is missing.");
		}
		/* Load the environement variable */
		$env = new IrcmsEnv($_GET['savepath']);
		/* Read the data */
		$ircom = new Ircom();
		$content = new IrcmsContent($env);
		$content->writeFile($env->get("fullpath", "content"), $ircom->read());
		Ircom::success();
		break;
	/* Support irexplorer */
	case "irexplorer":
		Irexplorer::server();
		break;
	default:
		Ircom::error("Unknown command type `".$_GET["type"]."'");
	}
?>
