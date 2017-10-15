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

### Using php command line

Simply go to the tools directory and run the command delploy, as this:
```bash
cd .ircms/tools/
./deploy.sh
```

### Using Apache

Simply clone this repository to the root of www/

### Using Lighttpd

Add the following lines into the configuration file (/etc/lighttpd/lighttpd.conf):
```bash
server.modules = (
        "mod_rewrite"
)

url.rewrite-once = ("^/(.*)" => "/index.php?path=$1")
```

## Configuration

The configuration is done within the file .ircms.php

### IRCMS_CONF_ROUTING

Sets a custom routing script to the CMS. It takes into argument the path of the PHP script.
Note, the should make use of the global variable $apps which is an instance of the class Routing
in order to set its configuration.

For example, if you want to direct the page /hello to a specific script
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
