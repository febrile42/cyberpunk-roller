#!/bin/sh
# Ensure the DB volume mount is writable by www-data.
# Named volumes are created root-owned; this fixes ownership at startup.
chown www-data:www-data /var/lib/cyberpunk-roller
exec "$@"
