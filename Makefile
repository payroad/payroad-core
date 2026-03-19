.PHONY: build test filter shell

build:
	docker compose build

test: build
	docker compose run --rm php sh -c "composer install --no-interaction --no-progress -q && vendor/bin/phpunit"

filter: build
	docker compose run --rm php sh -c "composer install --no-interaction --no-progress -q && vendor/bin/phpunit --filter=$(FILTER)"

shell: build
	docker compose run --rm php sh
