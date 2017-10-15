# Simple Content Management System

This CMS is made to be simple and flexible. There is no need for database installation, it works out of the box within the directoy tree it is in.

## Features

- Simplicity
- Caching of the most used files

## Prerequisits

Before starting, you need to make sure that the folling is available:
- PHP (5+)
- PHP extenstions: php-json and php-xml

## Getting Started

Simply go to the tools directory and run the command delploy, as this:
```bash
cd .ircms/tools/
./deploy.sh
```

## Configuration

The configuration is done within the file .ircms.php

### IRCMS_CONF_ROUTING

For example:
```php
define('IRCMS_CONF_ROUTING', "myCustomRoutingScript.php");
```

With myCustomRoutingScript.php containing the following:
```php
<?php
	$app->route(array("get"), "/hello", function($vars) {
		echo "Hello World!";
	});
?>
```

Sets a custom routing script to the CMS. It takes into argument the path of the PHP script.
Note, the should make use of the global variable $apps which is an instance of the class Routing
in order to set its configuration.
