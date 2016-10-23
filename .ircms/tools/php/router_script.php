<?php
	/* To display the error */
	ini_set('display_errors', 1);
	chdir("../..");

	/* Script used to replace the .htaccess that is not supported by the built-in webserver */
	require_once(".ircms/core/routing.php");

	$app = new Routing();
	$app->route("get", "/404{.*}", function($vars) {
		$_GET["path"] = "/.ircms/page/error/404/";
	});
	$app->route(array("get", "post"), "/admin{path:.*}", function($vars) {
		$_GET["path"] = "/.ircms/page/admin".$vars["path"];
	});
	$app->route(array("get", "post"), "{path:.*}", function($vars) {
		$_GET["path"] = $vars["path"];
	});
	$app->dispatch($_SERVER['REQUEST_URI']);

	/* Call the main script */
	include("index.php");
?>
