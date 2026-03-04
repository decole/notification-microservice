up:
	docker compose up -d --remove-orphans

down:
	docker compose down

restart: down up

build:
	docker compose build

ps:
	docker compose ps

console-in:
	docker compose exec php bash

migration:
	docker compose exec php php bin/console d:m:m --no-interaction

prev-migration:
	docker compose exec php php bin/console d:m:m prev --no-interaction

new-migration:
	docker compose exec php php bin/console make:migration

prepare-test:
	docker compose exec -e DATABASE_URL="pgsql://app:app@postgres:5432/notifications_test?serverVersion=13&charset=utf8" php php bin/console doctrine:database:drop --force --if-exists --env=test
	docker compose exec -e DATABASE_URL="pgsql://app:app@postgres:5432/notifications_test?serverVersion=13&charset=utf8" php php bin/console doctrine:database:create --if-not-exists --env=test
	docker compose exec -e DATABASE_URL="pgsql://app:app@postgres:5432/notifications_test?serverVersion=13&charset=utf8" php php bin/console d:m:m --no-interaction --env=test

test:
	docker compose exec -e DATABASE_URL="pgsql://app:app@postgres:5432/notifications_test?serverVersion=13&charset=utf8" php php bin/phpunit -c phpunit.xml.dist --testdox

backup:
	sudo bash backup.sh
