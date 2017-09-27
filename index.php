<?php
	/**
	 * This code is used to measure the time it takes to build the page
	 */
	$ircms_benchmark_start = microtime(true);

	/* Load the framework */
	require_once(".ircms/load.php");

	// Create the routing and configure it
	$app = new Routing();

	$app->route(array("get", "post"), "{path:.*}", function($vars) {
		$ircms = new Ircms(
				/* If The path varaible is defined in the query string */
				(isset($_GET['path'])) ? $_GET['path']
				/* Else set the default one */
				: "/");

		/* Generate and print the page */
		echo $ircms->generate();
	});

	// Dispatch
	$app->dispatch($_SERVER['REQUEST_URI']);

	/**
	 * This code is used to print the time it takes to build the page
	 */
	echo "<!-- Page generated in ".round(microtime(true) - $ircms_benchmark_start, 4)."s //-->\n";
	if (IRCMS_DEBUG) {
		echo "<!-- //DEBUG Variable dump:\n";
		echo $ircms->dump();
		echo "//-->";
	}
	die();
?>
