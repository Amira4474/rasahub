Docker is confirmed working now, and the memory limit has also been increased.

I restarted WSL and verified from Ubuntu:

`docker info | grep -i "total memory"`

Result:

`Total Memory: 3.703GiB`

So the Docker daemon is reachable and the WSL 2 memory limit is now approximately 4 GB. Please do not modify Windows/WSL resource settings.

Before I approve the implementation plan, please revise the plan with these corrections:

1. Docker Compose working directory

My required daily workflow is:

`cd ~/projects/rasahub/docker-local`
`docker compose up -d`
`docker compose ps`
`docker compose down`

All Docker Compose commands in the documentation should use `docker-local/` as the working directory.

The Compose file must be:

`~/projects/rasahub/docker-local/compose.yml`

Do not require me to run Compose from the Laravel project root.

2. Docker services

Keep the proposed four-service architecture:

* web — Nginx
* app — PHP/Laravel
* mysql — MySQL
* vite — Node/Vite

Do not add Redis, Mailpit, queue workers, Elasticsearch, MinIO, Selenium, or other services unless there is a concrete Phase 1 requirement.

3. Docker memory

Design the setup to work reasonably with approximately 4 GB WSL memory available.

Do not change my Windows or WSL configuration.

4. Dependency volumes

Please revise the volume strategy to avoid unnecessarily putting container dependencies into the WSL source tree.

Prefer:

* Laravel source → bind mount
* vendor/ → named Docker volume
* node_modules/ → named Docker volume
* MySQL data → named Docker volume

Normal `docker compose down` must preserve all these named volumes.

`docker compose down -v` may remove them.

Please ensure this does not create root-owned files in `/home/amira/projects/rasahub`.

5. Exact versions

Please show the complete exact versions in the revised plan:

* PHP
* MySQL
* Node
* Laravel
* Filament
* Livewire
* spatie/laravel-permission
* Nginx

Do not show truncated versions.

6. Project structure

Use this interpretation:

`rasahub/` is the Laravel project root.

Laravel's normal directories should be directly underneath it:

`app/`
`bootstrap/`
`config/`
`database/`
`public/`
`resources/`
`routes/`
`storage/`
`tests/`

Docker configuration must be under:

`docker-local/`

with the proposed structure such as:

`docker-local/compose.yml`
`docker-local/php/`
`docker-local/nginx/`
`docker-local/mysql/`
`docker-local/README.md`

7. SYSTEMCONCEPT.md

The current file is:

`~/projects/rasahub/SYSTEMCONCEPT.md`

`docs/` is currently empty.

When implementation is eventually approved:

* calculate the SHA-256 of the existing file
* move it to `docs/SYSTEMCONCEPT.md`
* calculate the SHA-256 again
* verify the hashes are identical
* do not rewrite, summarize, reformat, or otherwise modify its contents

Do not move it yet.

8. SYSTEMARCHITECTURE.md

The existing `SYSTEMARCHITECTURE.md` is currently at the project root.

Keep it at the root for now unless there is a specific reason to move it. Do not silently relocate it.

9. Phase 1

Keep Phase 1 limited to:

* Laravel foundation
* Docker development environment
* MySQL
* Filament
* authentication/foundation
* roles and permissions
* businesses
* branches
* terminals
* users
* branch/user relationships
* development documentation
* tests/Git foundation

Do not implement POS sales, products, modifiers, orders, payments, KDS, printing, QR ordering, offline synchronization, inventory, or reports yet.

10. Implementation process

This is still an approval step.

Do NOT create files, install dependencies, move documents, run migrations, initialize the Git repository, or modify anything yet.

Please now provide the corrected final architecture and implementation plan, including items 1–20 from my original request.

After that, stop and wait for my explicit approval.
