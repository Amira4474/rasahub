#!/bin/sh
# Runs once, when the MySQL data volume is first initialised.
# Every tenant has its own database, created by the application when the
# tenant is created and named after its company acronym: rasahub_dc for DC,
# or rasahub_testing_dc during tests. This lets the application user create
# and use them.
set -eu

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\_%\`.* TO '${MYSQL_USER}'@'%';
SQL
