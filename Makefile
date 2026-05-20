.PHONY: up down build restart logs shell \
        migrate migrate-fresh migrate-rollback seed \
        test test-filter \
        composer npm-install npm-dev npm-build vite \
        artisan tinker cache-clear queue-work

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
