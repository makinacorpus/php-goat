#!/bin/bash

echo "Running tests on PHP 7.4"
APP_DIR="`dirname $PWD`" docker-compose -p goat_testing run php74 vendor/bin/phpunit "$@"

echo "Running tests on PHP 8"
APP_DIR="`dirname $PWD`" docker-compose -p goat_testing run php80 vendor/bin/phpunit "$@"
