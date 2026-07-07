SSH_HOST     = su814880@access-5020661163.webspace-host.com
SSH_PATH     = ~
SSH_PHP      = /usr/bin/php8.5
STAGING_PATH = ~/staging
PROD_URL     = https://booking-app.it
STAGING_URL  = https://staging.booking-app.it
COMPOSER_INSTALL_FLAGS = --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
RSYNC_FLAGS  = -az --delete --exclude='.DS_Store'
REQUIRED_PROD_ENV = APP_KEY APP_URL APP_BASE_DOMAIN DB_DATABASE DB_USERNAME DB_PASSWORD STRIPE_PUBLIC_KEY STRIPE_SECRET_KEY STRIPE_PRICE_ID STRIPE_BILLING_WEBHOOK_SECRET STRIPE_CONNECT_WEBHOOK_SECRET STRIPE_WEBHOOK_SECRET
REQUIRED_STAGING_ENV = APP_KEY APP_URL APP_BASE_DOMAIN DB_DATABASE DB_USERNAME DB_PASSWORD

.PHONY: up down build restart logs shell \
        migrate migrate-fresh migrate-rollback seed \
        test test-filter \
        composer npm-install npm-dev npm-build vite \
        artisan tinker cache-clear queue-work \
        validate-prod-env validate-staging-env deploy-preflight deploy-build-assets \
        deploy deploy-env deploy-assets deploy-code deploy-vendor \
        deploy-lock-prod deploy-unlock-prod deploy-down-prod deploy-up-prod \
        deploy-prepare-prod deploy-env-prod deploy-code-prod deploy-public-prod \
        deploy-vendor-prod deploy-finalize-prod deploy-health-prod \
        staging-setup deploy-staging deploy-staging-env deploy-staging-assets \
        deploy-staging-code deploy-staging-vendor deploy-lock-staging \
        deploy-unlock-staging deploy-down-staging deploy-up-staging \
        deploy-prepare-staging deploy-staging-public deploy-staging-finalize \
        deploy-health-staging staging-reset-db \
        wa-test wa-reset wa-logs

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

deploy: deploy-preflight validate-prod-env deploy-build-assets
	@set -e; \
	$(MAKE) --no-print-directory deploy-lock-prod; \
	trap '$(MAKE) --no-print-directory deploy-up-prod >/dev/null 2>&1 || true; $(MAKE) --no-print-directory deploy-unlock-prod >/dev/null 2>&1 || true' EXIT; \
	$(MAKE) --no-print-directory deploy-down-prod; \
	$(MAKE) --no-print-directory deploy-env-prod; \
	$(MAKE) --no-print-directory deploy-prepare-prod; \
	$(MAKE) --no-print-directory deploy-code-prod; \
	$(MAKE) --no-print-directory deploy-public-prod; \
	$(MAKE) --no-print-directory deploy-vendor-prod; \
	$(MAKE) --no-print-directory deploy-finalize-prod; \
	$(MAKE) --no-print-directory deploy-up-prod; \
	$(MAKE) --no-print-directory deploy-health-prod; \
	echo "Deploy produzione completato."

validate-prod-env:
	@test -f .env.production || (echo "Errore: manca .env.production"; exit 1)
	@missing=0; for key in $(REQUIRED_PROD_ENV); do \
		if ! grep -Eq "^$${key}=.+" .env.production; then \
			echo "Errore: $${key} mancante o vuota in .env.production"; \
			missing=1; \
		fi; \
	done; exit $$missing

validate-staging-env:
	@test -f .env.staging || (echo "Errore: manca .env.staging"; exit 1)
	@missing=0; for key in $(REQUIRED_STAGING_ENV); do \
		if ! grep -Eq "^$${key}=.+" .env.staging; then \
			echo "Errore: $${key} mancante o vuota in .env.staging"; \
			missing=1; \
		fi; \
	done; exit $$missing

deploy-preflight:
	@test -f composer.lock || (echo "Errore: manca composer.lock"; exit 1)
	docker-compose run --rm --no-deps app composer validate --no-check-publish --strict

deploy-build-assets:
	docker-compose run --rm --no-deps app npm run build

deploy-lock-prod:
	ssh $(SSH_HOST) "cd $(SSH_PATH) && test ! -f .deploy.lock && date -Is > .deploy.lock || (echo 'Errore: deploy produzione gia in corso o lock presente'; exit 1)"

deploy-unlock-prod:
	ssh $(SSH_HOST) "cd $(SSH_PATH) && rm -f .deploy.lock"

deploy-down-prod:
	ssh $(SSH_HOST) "cd $(SSH_PATH) && $(SSH_PHP) artisan down --retry=60 || true"

deploy-up-prod:
	ssh $(SSH_HOST) "cd $(SSH_PATH) && $(SSH_PHP) artisan up || true"

deploy-prepare-prod:
	ssh $(SSH_HOST) "cd $(SSH_PATH) && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/build && chmod -R ug+rwX storage bootstrap/cache"

deploy-env: deploy-env-prod

deploy-env-prod: validate-prod-env
	scp .env.production $(SSH_HOST):$(SSH_PATH)/.env
	scp .env.production $(SSH_HOST):$(SSH_PATH)/.env.production
	ssh $(SSH_HOST) "chmod 640 $(SSH_PATH)/.env $(SSH_PATH)/.env.production"

deploy-assets: deploy-build-assets deploy-public-prod

deploy-code: deploy-code-prod

deploy-code-prod:
	rsync $(RSYNC_FLAGS) app/ $(SSH_HOST):$(SSH_PATH)/app/
	rsync $(RSYNC_FLAGS) --exclude='cache/*' bootstrap/ $(SSH_HOST):$(SSH_PATH)/bootstrap/
	rsync $(RSYNC_FLAGS) config/ $(SSH_HOST):$(SSH_PATH)/config/
	rsync $(RSYNC_FLAGS) --exclude='database.sqlite' database/ $(SSH_HOST):$(SSH_PATH)/database/
	rsync $(RSYNC_FLAGS) lang/ $(SSH_HOST):$(SSH_PATH)/lang/
	rsync $(RSYNC_FLAGS) resources/ $(SSH_HOST):$(SSH_PATH)/resources/
	rsync $(RSYNC_FLAGS) routes/ $(SSH_HOST):$(SSH_PATH)/routes/
	rsync -az artisan composer.json composer.lock $(SSH_HOST):$(SSH_PATH)/

deploy-public-prod:
	rsync $(RSYNC_FLAGS) --exclude='build/' --exclude='storage/' --exclude='hot' --exclude='fonts-manifest.dev.json' public/ $(SSH_HOST):$(SSH_PATH)/public/
	rsync $(RSYNC_FLAGS) public/build/ $(SSH_HOST):$(SSH_PATH)/public/build/
	ssh $(SSH_HOST) "cd $(SSH_PATH) && rm -f public/hot"

deploy-vendor: deploy-vendor-prod

deploy-vendor-prod:
	ssh $(SSH_HOST) "cd $(SSH_PATH) && $(SSH_PHP) ~/composer.phar install $(COMPOSER_INSTALL_FLAGS)"

deploy-finalize-prod:
	ssh $(SSH_HOST) "set -e; cd $(SSH_PATH); $(SSH_PHP) artisan optimize:clear; $(SSH_PHP) artisan migrate --force; $(SSH_PHP) artisan config:cache; $(SSH_PHP) artisan route:cache; $(SSH_PHP) artisan view:cache; $(SSH_PHP) artisan storage:link >/dev/null 2>&1 || true; $(SSH_PHP) artisan queue:restart >/dev/null 2>&1 || true; touch public/index.php"

deploy-health-prod:
	@printf "Healthcheck produzione... "
	@curl -fsSIL --max-time 20 $(PROD_URL) >/dev/null
	@echo "OK"

# ── Staging ──────────────────────────────────────────────────────────────────

staging-setup: validate-staging-env deploy-build-assets
	@echo "Creo struttura staging sul server..."
	$(MAKE) --no-print-directory deploy-prepare-staging
	$(MAKE) --no-print-directory deploy-staging-env
	$(MAKE) --no-print-directory deploy-staging-code
	$(MAKE) --no-print-directory deploy-staging-public
	$(MAKE) --no-print-directory deploy-staging-vendor
	$(MAKE) --no-print-directory deploy-staging-finalize
	@echo "Staging setup completato. Visita https://staging.booking-app.it"

deploy-staging: deploy-preflight validate-staging-env deploy-build-assets
	@set -e; \
	$(MAKE) --no-print-directory deploy-lock-staging; \
	trap '$(MAKE) --no-print-directory deploy-up-staging >/dev/null 2>&1 || true; $(MAKE) --no-print-directory deploy-unlock-staging >/dev/null 2>&1 || true' EXIT; \
	$(MAKE) --no-print-directory deploy-down-staging; \
	$(MAKE) --no-print-directory deploy-staging-env; \
	$(MAKE) --no-print-directory deploy-prepare-staging; \
	$(MAKE) --no-print-directory deploy-staging-code; \
	$(MAKE) --no-print-directory deploy-staging-public; \
	$(MAKE) --no-print-directory deploy-staging-vendor; \
	$(MAKE) --no-print-directory deploy-staging-finalize; \
	$(MAKE) --no-print-directory deploy-up-staging; \
	$(MAKE) --no-print-directory deploy-health-staging; \
	echo "Deploy staging completato."

deploy-lock-staging:
	ssh $(SSH_HOST) "mkdir -p $(STAGING_PATH) && cd $(STAGING_PATH) && test ! -f .deploy.lock && date -Is > .deploy.lock || (echo 'Errore: deploy staging gia in corso o lock presente'; exit 1)"

deploy-unlock-staging:
	ssh $(SSH_HOST) "cd $(STAGING_PATH) && rm -f .deploy.lock"

deploy-down-staging:
	ssh $(SSH_HOST) "cd $(STAGING_PATH) && $(SSH_PHP) artisan down --retry=60 || true"

deploy-up-staging:
	ssh $(SSH_HOST) "cd $(STAGING_PATH) && $(SSH_PHP) artisan up || true"

deploy-prepare-staging:
	ssh $(SSH_HOST) "mkdir -p $(STAGING_PATH)/storage/app/public $(STAGING_PATH)/storage/framework/cache/data $(STAGING_PATH)/storage/framework/sessions $(STAGING_PATH)/storage/framework/testing $(STAGING_PATH)/storage/framework/views $(STAGING_PATH)/storage/logs $(STAGING_PATH)/bootstrap/cache $(STAGING_PATH)/public/build && chmod -R ug+rwX $(STAGING_PATH)/storage $(STAGING_PATH)/bootstrap/cache"

deploy-staging-env: validate-staging-env
	scp .env.staging $(SSH_HOST):$(STAGING_PATH)/.env
	ssh $(SSH_HOST) "chmod 640 $(STAGING_PATH)/.env"

deploy-staging-assets: deploy-build-assets deploy-staging-public

deploy-staging-code:
	rsync $(RSYNC_FLAGS) app/ $(SSH_HOST):$(STAGING_PATH)/app/
	rsync $(RSYNC_FLAGS) --exclude='cache/*' bootstrap/ $(SSH_HOST):$(STAGING_PATH)/bootstrap/
	rsync $(RSYNC_FLAGS) config/ $(SSH_HOST):$(STAGING_PATH)/config/
	rsync $(RSYNC_FLAGS) --exclude='database.sqlite' database/ $(SSH_HOST):$(STAGING_PATH)/database/
	rsync $(RSYNC_FLAGS) lang/ $(SSH_HOST):$(STAGING_PATH)/lang/
	rsync $(RSYNC_FLAGS) resources/ $(SSH_HOST):$(STAGING_PATH)/resources/
	rsync $(RSYNC_FLAGS) routes/ $(SSH_HOST):$(STAGING_PATH)/routes/
	rsync -az artisan composer.json composer.lock $(SSH_HOST):$(STAGING_PATH)/

deploy-staging-public:
	rsync $(RSYNC_FLAGS) --exclude='build/' --exclude='storage/' --exclude='hot' --exclude='fonts-manifest.dev.json' public/ $(SSH_HOST):$(STAGING_PATH)/public/
	rsync $(RSYNC_FLAGS) public/build/ $(SSH_HOST):$(STAGING_PATH)/public/build/
	ssh $(SSH_HOST) "cd $(STAGING_PATH) && rm -f public/hot"

deploy-staging-vendor:
	ssh $(SSH_HOST) "cd $(STAGING_PATH) && $(SSH_PHP) ~/composer.phar install $(COMPOSER_INSTALL_FLAGS)"

deploy-staging-finalize:
	ssh $(SSH_HOST) "set -e; cd $(STAGING_PATH); $(SSH_PHP) artisan optimize:clear; $(SSH_PHP) artisan migrate --force; $(SSH_PHP) artisan config:cache; $(SSH_PHP) artisan route:cache; $(SSH_PHP) artisan view:cache; $(SSH_PHP) artisan storage:link >/dev/null 2>&1 || true; $(SSH_PHP) artisan queue:restart >/dev/null 2>&1 || true; touch public/index.php"

deploy-health-staging:
	@printf "Healthcheck staging... "
	@curl -fsSIL --max-time 20 $(STAGING_URL) >/dev/null
	@echo "OK"

staging-reset-db:
	ssh $(SSH_HOST) "set -e; cd $(STAGING_PATH); $(SSH_PHP) artisan migrate:fresh --force --seeder=StagingSeeder; $(SSH_PHP) artisan optimize:clear; $(SSH_PHP) artisan config:cache; $(SSH_PHP) artisan route:cache; $(SSH_PHP) artisan view:cache"
	@echo "Reset database staging completato."

# ── WhatsApp test ─────────────────────────────────────────────────────────────
# Esempi:
#   make wa-test MSG="taglio classico sabato"
#   make wa-test MSG="sì confermo" ENV=prod
#   make wa-test MSG="ciao" PHONE=393123456789 NAME=Mario
#   make wa-reset
#   make wa-logs MSG="voglio prenotare"

WA_ENV  ?= staging
WA_PHONE ?= 393298826230
WA_NAME  ?= Daniele

wa-test:
	@./scripts/wa-test.sh "$(WA_ENV)" "$(MSG)" --phone "$(WA_PHONE)" --name "$(WA_NAME)"

wa-reset:
	@./scripts/wa-test.sh staging --reset --phone "$(WA_PHONE)"

wa-logs:
	@./scripts/wa-test.sh "$(WA_ENV)" "$(MSG)" --phone "$(WA_PHONE)" --name "$(WA_NAME)" --logs
