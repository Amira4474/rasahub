#!/bin/sh
# Runs once, when the MySQL data volume is first initialised.
# Every tenant has its own database, created by the application when the
# tenant is created. This lets the application user create and use them,
# for both the development and the testing database.
set -eu

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\_tenant\_%\`.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\_testing\_tenant\_%\`.* TO '${MYSQL_USER}'@'%';
SQL
