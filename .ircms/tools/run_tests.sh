#!/bin/bash

cd ../../
# Run all tests
phpunit --bootstrap .ircms/tests/env/set.php --coverage-text --report-useless-tests --strict-global-state .ircms/tests/
