PROJECT := skybroker
DC := docker compose
APP := skybroker-app

.PHONY: up down restart logs sh composer-install composer artisan key migrate seed migrate-seed test qcache

up:
	$(DC) up -d --build

down:
	$(DC) down -v

restart:
	$(DC) restart

logs:
	$(DC) logs -f --tail=200

sh:
	$(DC) exec $(APP) bash

composer-install:
	$(DC) exec $(APP) bash -lc "composer install --no-interaction --prefer-dist"

composer:
	$(DC) exec $(APP) bash -lc "composer $(ARGS)"

artisan:
	$(DC) exec $(APP) php artisan $(ARGS)

key:
	$(DC) exec $(APP) php artisan key:generate

migrate:
	$(DC) exec $(APP) php artisan migrate

seed:
	$(DC) exec $(APP) php artisan db:seed

migrate-seed:
	$(DC) exec $(APP) php artisan migrate --seed

test:
	$(DC) exec $(APP) php artisan test

qcache:
	$(DC) exec $(APP) php artisan optimize:clear && $(DC) exec $(APP) php artisan optimize
