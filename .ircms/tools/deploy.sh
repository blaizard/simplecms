#!/bin/bash

require () {
	fail=
	while read args; do
		eval "set -- $args"
		type "$1" &> /dev/null
		if [ $? -ne 0 ]; then
			fail=1
			echo -ne "\`$1' is missing"
			if [ "$2" ]; then
                echo -ne ": $2"
			fi
			echo -ne "\n"
		fi
	done
	if [ $fail ]; then
		echo "Aborting." && exit 1
	fi
}

require <<EOF
php "Please install 'apt-get install php-fpm php php-cli php-json php-xml'"
EOF

echo "Connect to the server: http://127.0.0.1:8000/"
kill -9 `fuser 8000/tcp`
php -S localhost:8000 -t ../../ php/router_script.php
