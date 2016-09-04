#!/bin/sh

set -e

service smbd start
#testparm -s Uncomment to check samba configuration

[ -f composer.json ] && gosu samba composer install

export USER=samba
export HOME=/home/samba

exec gosu samba "$@"
