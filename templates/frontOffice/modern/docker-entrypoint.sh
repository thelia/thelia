#!/bin/sh
set -e

yarn --production=false
exec "$@"
