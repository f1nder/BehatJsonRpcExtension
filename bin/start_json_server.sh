#!/usr/bin/env bash
set -e

echo "    Starting the PHP builtin webserver"
php -S 127.0.0.1:8080 -t "testapp" > /dev/null 2> "server.log" &