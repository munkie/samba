#!/bin/sh

set -e

service smbd start
testparm -s

composer install
exec "$@"
