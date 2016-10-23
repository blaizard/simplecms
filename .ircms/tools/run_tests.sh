#!/bin/bash

cd ../../
# Run all tests
phpunit --bootstrap .ircms/tests/env/set.php .ircms/tests/
