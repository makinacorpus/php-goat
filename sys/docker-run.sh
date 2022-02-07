#!/bin/bash

echo "Running tests on PHP 8.0"
APP_DIR="`dirname $PWD`" docker-compose -p goat_testing run php80 vendor/bin/phpunit "$@"

echo "Running tests on PHP 8.1"
APP_DIR="`dirname $PWD`" docker-compose -p goat_testing run php81 vendor/bin/phpunit "$@"
