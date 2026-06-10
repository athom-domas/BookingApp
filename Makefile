SSH_HOST = su814880@access-5020661163.webspace-host.com
SSH_PATH = ~
SSH_PHP  = /usr/bin/php8.5

.PHONY: up down build restart logs shell \
        migrate migrate-fresh migrate-rollback seed \
        test test-filter \
        composer npm-install npm-dev npm-build vite \
        artisan tinker cache-clear queue-work \
        deploy deploy-env deploy-assets deploy-code

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

restart:
	docker compose restart

logs:
	docker compose logs -f app

# ── Shell ────────────────────────────────────────────────────────────────────

shell:
	docker compose exec app sh

tinker:
	docker compose exec app php artisan tinker

# ── Database ─────────────────────────────────────────────────────────────────

migrate:
	docker compose exec app php artisan migrate

migrate-fresh:
	docker compose exec app php artisan migrate:fresh --seed

migrate-rollback:
	docker compose exec app php artisan migrate:rollback

seed:
	docker compose exec app php artisan db:seed

# ── Testing ──────────────────────────────────────────────────────────────────

test:
	docker compose exec app ./vendor/bin/pest

test-filter:
	docker compose exec app ./vendor/bin/pest --filter "$(filter)"

# ── Assets ───────────────────────────────────────────────────────────────────

npm-install:
	docker compose exec app npm install

npm-dev:
	docker compose exec app npm run dev

npm-build:
	docker compose exec app npm run build

vite:
	docker compose exec app npx vite

# ── Misc ─────────────────────────────────────────────────────────────────────

composer:
	docker compose exec app composer $(cmd)

artisan:
	docker compose exec app php artisan $(cmd)

cache-clear:
	docker compose exec app php artisan optimize:clear

queue-work:
	docker compose exec app php artisan queue:work

# ── Deploy ───────────────────────────────────────────────────────────────────

deploy: deploy-env deploy-assets deploy-code
	ssh $(SSH_HOST) "cd $(SSH_PATH) && $(SSH_PHP) artisan config:clear && $(SSH_PHP) artisan route:clear && $(SSH_PHP) artisan view:clear"
	@echo "Deploy completato."

deploy-env:
	scp .env.production $(SSH_HOST):$(SSH_PATH)/.env

deploy-assets:
	docker compose run --rm --no-deps app npm run build
	rsync -avz --delete public/build/ $(SSH_HOST):$(SSH_PATH)/public/build/

deploy-code:
	rsync -avz \
		--exclude='.env' \
		--exclude='vendor/' \
		--exclude='node_modules/' \
		--exclude='storage/logs/' \
		--exclude='public/build/' \
		--exclude='.git/' \
		app/ $(SSH_HOST):$(SSH_PATH)/app/
	rsync -avz routes/ $(SSH_HOST):$(SSH_PATH)/routes/
	rsync -avz config/ $(SSH_HOST):$(SSH_PATH)/config/
	rsync -avz resources/ $(SSH_HOST):$(SSH_PATH)/resources/
