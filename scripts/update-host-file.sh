#!/bin/sh
set -eu

ip="127.0.0.1"
suffix="${EDLIB_ROOT_DOMAIN:-edlib.test}"
subdomains="
api
ca
docs
facade
hub
hub-vite-hmr
hub-ndla-legacy
hub-test
hub-test-ndla-legacy
mailpit
moodle
npm.components
www
"

hosts="$(cat /etc/hosts)"

{
    echo "$hosts" | sed -e '/### EDLIB BLOCK (autogenerated) ###/,/### END EDLIB BLOCK ###/d'
    echo "### EDLIB BLOCK (autogenerated) ###"
    for subdomain in $subdomains; do
        echo "$ip\t$subdomain.$suffix"
    done
    echo "### END EDLIB BLOCK ###"
} > /etc/hosts
