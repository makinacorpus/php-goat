#!/bin/bash
echo "Running coverage on PHP 8.1"
APP_DIR="`dirname $PWD`" docker-compose -p goat_testing run -e XDEBUG_MODE=coverage php81 vendor/bin/phpunit --coverage-html=coverage "$@"
