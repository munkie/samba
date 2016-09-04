#!/bin/sh

# Entrypoint for PhpStorm to run phpunit in docker
# Behaves like php executable, but also starts smbd service for functional tests

/root/entrypoint.sh php "$@"
