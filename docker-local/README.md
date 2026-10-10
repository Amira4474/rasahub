# RasaHub local Docker environment

Everything Docker-related for local development lives in this directory.
Run every `docker compose` command from here:

```
cd ~/projects/rasahub/docker-local
```

The application's runtime is entirely in Docker. PHP, Node and MySQL installed
on the host are not used by RasaHub.

## Daily use

```
docker compose up -d      # start
docker compose ps         # status
docker compose down       # stop; keeps the database and dependencies
```

| What | URL |
|---|---|
| Application | http://localhost |
| Platform panel | http://localhost/platform |
| Tenant admin panel | http://localhost/admin |
| Staff POS | http://localhost/pos |
| Vite dev server (hot reload) | http://localhost:5173 |
| MySQL from a database client | `127.0.0.1:3307` |

## Services

| Service | Image | Purpose |
|---|---|---|
| `web` | `nginx:1.30.5-alpine` | Serves `public/` and passes PHP requests to `app` |
| `app` | built from `php:8.5.11-fpm-trixie` (see `php/Dockerfile`) | PHP-FPM, Composer, artisan, tests |
| `mysql` | `mysql:9.7.2` | Database |
| `vite` | built from `node:24.21.0-trixie-slim` (see `node/Dockerfile`) | `npm run dev` with hot reload |

Containers reach each other by service name on the `rasahub_default` network.
Laravel connects to the database at `mysql:3306`. Host port 3307 exists only
for database clients on your machine and does not interfere with any MySQL
already listening on 3306.

## Data and persistence

| Data | Stored in | Survives `down` | Removed by `down -v` |
|---|---|---|---|
| Laravel source | this repository (bind mount) | yes | no |
| MySQL data (central and every tenant database) | volume `rasahub_mysql_data` | yes | **yes** |
| `vendor/` | volume `rasahub_vendor` | yes | yes |
| `node_modules/` | volume `rasahub_node_modules` | yes | yes |

**`docker compose down` never deletes data.**

**`docker compose down -v` deletes all three volumes, which destroys the local
database.** Afterwards, repeat the first-time setup from the `docker compose up
-d` step: reinstall dependencies and run the migrations and seeder again.

Because dependencies live in volumes, `vendor/` and `node_modules/` in the
project are empty folders on the host. They are only mount points.

## First-time setup

After cloning the repository:

```
cd ~/projects/rasahub/docker-local

cp .env.example .env            # then set both MySQL passwords in it
cp ../.env.example ../.env      # then set DB_PASSWORD to the same MYSQL_PASSWORD

# Create the dependency mount points as yourself, so Docker does not create
# them as root.
mkdir -p ../vendor ../node_modules

docker compose build
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan app:create-super-admin
```

`HOST_UID` and `HOST_GID` in `.env` must match `id -u` and `id -g`. The
containers run as that user, so files they write into the project belong to
you. If you change them, run `docker compose build` again.

The `vite` container runs `npm install` by itself the first time it starts,
which takes a minute or two before http://localhost:5173 responds.

## Common tasks

```
docker compose exec app php artisan test          # run the test suite
docker compose exec app php artisan migrate       # run new central migrations
docker compose exec app php artisan tenants:migrate   # run new migrations in every tenant database
docker compose exec app php artisan db:seed       # re-sync roles and permissions
docker compose exec app composer require <pkg>    # add a PHP package
docker compose exec app ./vendor/bin/pint         # format PHP code
docker compose exec vite npm install <pkg>        # add a Node package
docker compose exec vite npm run build            # production asset build
docker compose logs -f app                        # follow a service's logs
docker compose exec mysql sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

Tests use a separate database, `rasahub_testing`, created by
`mysql/init/01-testing-db.sh` the first time the MySQL volume is initialised.

Every tenant has a database of its own, named `rasahub_tenant_<id>`
(`rasahub_testing_tenant_<id>` during tests), which the application creates
when the tenant is created. `mysql/init/02-tenant-databases.sh` gives the
application's database user the right to do that. On a MySQL volume that
already existed before that script was added, run it once by hand:

```
docker compose exec mysql sh /docker-entrypoint-initdb.d/02-tenant-databases.sh
```

`php artisan migrate:fresh` rebuilds the central database only. It leaves the
tenant databases behind, and creating a tenant then fails because its
database already exists. To start completely clean, list them and drop each
one first:

```
docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SHOW DATABASES LIKE \"%tenant%\""'
docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE rasahub_tenant_1"'
```

## Files

```
docker-local/
├── compose.yml                   services, ports, volumes
├── .env                          your local values (not committed)
├── .env.example                  template for .env
├── php/Dockerfile                app image
├── php/php.ini                   PHP overrides
├── php/php-fpm.conf              PHP-FPM pool size
├── nginx/default.conf            web server configuration
├── mysql/conf.d/rasahub.cnf      MySQL memory settings
├── mysql/init/01-testing-db.sh   creates the testing database
├── mysql/init/02-tenant-databases.sh  lets the app create tenant databases
└── node/Dockerfile               vite image
```

`php.ini`, `php-fpm.conf`, `default.conf` and `rasahub.cnf` are mounted into
the containers. After editing one, restart that service, for example
`docker compose restart app`. After editing a Dockerfile, run
`docker compose build` and then `docker compose up -d`.
