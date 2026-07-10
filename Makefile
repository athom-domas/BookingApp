SSH_HOST     = su814880@access-5020661163.webspace-host.com
SSH_PATH     = ~
SSH_PHP      = /usr/bin/php8.5
STAGING_PATH = ~/staging
PROD_URL     = https://booking-app.it
STAGING_URL  = https://staging.booking-app.it
COMPOSER_INSTALL_FLAGS = --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
RSYNC_FLAGS  = -az --delete --delay-updates --exclude='.DS_Store'
RSYNC_DEPLOY_FILTERS = \
	--include='/app/***' \
	--include='/bootstrap/' --exclude='/bootstrap/cache/***' --include='/bootstrap/***' \
	--include='/config/***' \
	--include='/database/' --exclude='/database/database.sqlite' --include='/database/***' \
	--include='/lang/***' \
	--include='/resources/***' \
	--include='/routes/***' \
	--include='/public/' --exclude='/public/storage' --exclude='/public/storage/***' --exclude='/public/hot' --exclude='/public/fonts-manifest.dev.json' --include='/public/***' \
	--include='/artisan' \
	--include='/composer.json' \
	--include='/composer.lock' \
	--exclude='*'
REQUIRED_PROD_ENV = APP_KEY APP_URL APP_BASE_DOMAIN DB_DATABASE DB_USERNAME DB_PASSWORD STRIPE_PUBLIC_KEY STRIPE_SECRET_KEY STRIPE_PRICE_ID_BASE STRIPE_PRICE_ID_PLUS STRIPE_BILLING_WEBHOOK_SECRET STRIPE_CONNECT_WEBHOOK_SECRET STRIPE_WEBHOOK_SECRET
REQUIRED_STAGING_ENV = APP_KEY APP_URL APP_BASE_DOMAIN DB_DATABASE DB_USERNAME DB_PASSWORD

.PHONY: up down build restart logs shell \
        migrate migrate-fresh migrate-rollback seed \
        test test-filter \
        composer npm-install npm-dev npm-build vite \
        artisan tinker cache-clear queue-work \
        validate-prod-env validate-staging-env deploy-preflight deploy-build-assets deploy-version-file \
        deploy deploy-run deploy-env deploy-assets deploy-code deploy-vendor deploy-preview \
        deploy-remote-begin deploy-remote-lock deploy-remote-prepare \
        deploy-remote-down deploy-remote-up deploy-remote-unlock \
        deploy-env-file deploy-sync-files deploy-sync-files-preview deploy-remote-install \
        deploy-remote-finalize deploy-remote-release deploy-health \
        deploy-preview-prod deploy-lock-prod deploy-unlock-prod deploy-down-prod deploy-up-prod \
        deploy-prepare-prod deploy-env-prod deploy-code-prod deploy-public-prod \
        deploy-vendor-prod deploy-finalize-prod deploy-health-prod \
        staging-setup deploy-staging deploy-preview-staging deploy-staging-env deploy-staging-assets \
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

guard-main:
	@branch=$$(git rev-parse --abbrev-ref HEAD); \
	if [ "$$branch" != "main" ]; then \
		echo "Errore: il deploy in produzione può essere eseguito solo dal branch main (sei su $$branch)"; \
		exit 1; \
	fi

guard-staging:
	@branch=$$(git rev-parse --abbrev-ref HEAD); \
	if [ "$$branch" != "staging" ]; then \
		echo "Errore: il deploy in staging può essere eseguito solo dal branch staging (sei su $$branch)"; \
		exit 1; \
	fi

deploy: guard-main
	@$(MAKE) --no-print-directory deploy-run ENV_NAME=produzione ENV_FILE=.env.production REMOTE_PATH="$(SSH_PATH)" HEALTH_URL="$(PROD_URL)" ENV_ARCHIVE=.env.production VALIDATE_TARGET=validate-prod-env

validate-prod-env:
	@echo "[1/4] Verifico configurazione produzione (.env.production)..."
	@test -f .env.production || (echo "Errore: manca .env.production"; exit 1)
	@missing=0; for key in $(REQUIRED_PROD_ENV); do \
		if ! grep -Eq "^$${key}=.+" .env.production; then \
			echo "Errore: $${key} mancante o vuota in .env.production"; \
			missing=1; \
		fi; \
	done; \
	if [ $$missing -eq 0 ]; then echo "Configurazione produzione OK."; fi; \
	exit $$missing

validate-staging-env:
	@echo "[1/4] Verifico configurazione staging (.env.staging)..."
	@test -f .env.staging || (echo "Errore: manca .env.staging"; exit 1)
	@missing=0; for key in $(REQUIRED_STAGING_ENV); do \
		if ! grep -Eq "^$${key}=.+" .env.staging; then \
			echo "Errore: $${key} mancante o vuota in .env.staging"; \
			missing=1; \
		fi; \
	done; \
	if [ $$missing -eq 0 ]; then echo "Configurazione staging OK."; fi; \
	exit $$missing

deploy-preflight:
	@echo "[2/4] Eseguo preflight Composer..."
	@test -f composer.lock || (echo "Errore: manca composer.lock"; exit 1)
	docker-compose run --rm --no-deps app composer validate --no-check-publish --strict

deploy-build-assets:
	@echo "[3/4] Compilo asset frontend..."
	docker-compose run --rm --no-deps app npm run build

deploy-version-file:
	@commit=$$(git log --format="%H" -1); \
	datetime=$$(date "+%d/%m/%Y %H:%M"); \
	printf "Ultima commit: %s\nData deploy: %s\n" "$$commit" "$$datetime" > public/DEPLOY.TXT
	@echo "[4/4] DEPLOY.TXT generato:"; cat public/DEPLOY.TXT

deploy-run:
	@set -e; \
	started_at=$$(date +%s); \
	format_elapsed() { elapsed=$$(($$(date +%s) - started_at)); printf "%dm %02ds" $$((elapsed / 60)) $$((elapsed % 60)); }; \
	test -n "$(ENV_NAME)" || (echo "Errore: ENV_NAME non impostato"; exit 1); \
	test -n "$(ENV_FILE)" || (echo "Errore: ENV_FILE non impostato"; exit 1); \
	test -n "$(REMOTE_PATH)" || (echo "Errore: REMOTE_PATH non impostato"; exit 1); \
	test -n "$(HEALTH_URL)" || (echo "Errore: HEALTH_URL non impostato"; exit 1); \
	test -n "$(VALIDATE_TARGET)" || (echo "Errore: VALIDATE_TARGET non impostato"; exit 1); \
	echo "==> Deploy $(ENV_NAME) avviato"; \
	echo "    Destinazione remota: $(SSH_HOST):$(REMOTE_PATH)"; \
	echo "    Healthcheck finale:  $(HEALTH_URL)"; \
	echo ""; \
	$(MAKE) --no-print-directory "$(VALIDATE_TARGET)"; \
	$(MAKE) --no-print-directory deploy-preflight; \
	$(MAKE) --no-print-directory deploy-build-assets; \
	$(MAKE) --no-print-directory deploy-version-file; \
	echo ""; \
	echo "[Remote 1/5] Creo lock, preparo directory e abilito manutenzione..."; \
	$(MAKE) --no-print-directory deploy-remote-begin ENV_NAME="$(ENV_NAME)" REMOTE_PATH="$(REMOTE_PATH)"; \
	trap 'status=$$?; $(MAKE) --no-print-directory deploy-remote-up REMOTE_PATH="$(REMOTE_PATH)" >/dev/null 2>&1 || true; $(MAKE) --no-print-directory deploy-remote-unlock REMOTE_PATH="$(REMOTE_PATH)" >/dev/null 2>&1 || true; if [ $$status -ne 0 ]; then echo ""; echo "Deploy $(ENV_NAME) fallito dopo $$(format_elapsed). Lock rimosso e manutenzione disattivata se possibile."; fi; exit $$status' EXIT; \
	echo "[Remote 2/5] Carico file env..."; \
	$(MAKE) --no-print-directory deploy-env-file ENV_FILE="$(ENV_FILE)" REMOTE_PATH="$(REMOTE_PATH)" ENV_ARCHIVE="$(ENV_ARCHIVE)"; \
	echo "[Remote 3/5] Sincronizzo codice e asset..."; \
	$(MAKE) --no-print-directory deploy-sync-files REMOTE_PATH="$(REMOTE_PATH)"; \
	echo "[Remote 4/5] Installo dipendenze, eseguo migrazioni e ricostruisco cache..."; \
	$(MAKE) --no-print-directory deploy-remote-release REMOTE_PATH="$(REMOTE_PATH)"; \
	echo "[Remote 5/5] Riporto online l'applicazione..."; \
	$(MAKE) --no-print-directory deploy-remote-up REMOTE_PATH="$(REMOTE_PATH)"; \
	echo "Verifico risposta HTTP..."; \
	$(MAKE) --no-print-directory deploy-health ENV_NAME="$(ENV_NAME)" HEALTH_URL="$(HEALTH_URL)"; \
	echo "Pulisco lock remoto..."; \
	$(MAKE) --no-print-directory deploy-remote-unlock REMOTE_PATH="$(REMOTE_PATH)"; \
	trap - EXIT; \
	echo ""; \
	echo "Deploy $(ENV_NAME) completato in $$(format_elapsed)."

deploy-remote-begin:
	ssh $(SSH_HOST) "set -e; mkdir -p $(REMOTE_PATH); cd $(REMOTE_PATH); test ! -f .deploy.lock || (echo 'Errore: deploy $(ENV_NAME) gia in corso o lock presente'; exit 1); date -Is > .deploy.lock; mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache public/build; chmod -R ug+rwX storage bootstrap/cache; $(SSH_PHP) artisan down --retry=60 || true"

deploy-remote-lock:
	ssh $(SSH_HOST) "set -e; mkdir -p $(REMOTE_PATH); cd $(REMOTE_PATH); test ! -f .deploy.lock || (echo 'Errore: deploy $(ENV_NAME) gia in corso o lock presente'; exit 1); date -Is > .deploy.lock"

deploy-remote-prepare:
	ssh $(SSH_HOST) "set -e; mkdir -p $(REMOTE_PATH); cd $(REMOTE_PATH); mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache public/build; chmod -R ug+rwX storage bootstrap/cache"

deploy-remote-down:
	ssh $(SSH_HOST) "cd $(REMOTE_PATH) && $(SSH_PHP) artisan down --retry=60 || true"

deploy-remote-up:
	ssh $(SSH_HOST) "cd $(REMOTE_PATH) && $(SSH_PHP) artisan up || true"

deploy-remote-unlock:
	ssh $(SSH_HOST) "cd $(REMOTE_PATH) && rm -f .deploy.lock"

deploy-env-file:
	scp $(ENV_FILE) $(SSH_HOST):$(REMOTE_PATH)/.env
	@if [ -n "$(ENV_ARCHIVE)" ]; then \
		scp $(ENV_FILE) $(SSH_HOST):$(REMOTE_PATH)/$(ENV_ARCHIVE); \
		ssh $(SSH_HOST) "chmod 640 $(REMOTE_PATH)/.env $(REMOTE_PATH)/$(ENV_ARCHIVE)"; \
	else \
		ssh $(SSH_HOST) "chmod 640 $(REMOTE_PATH)/.env"; \
	fi

deploy-sync-files:
	rsync $(RSYNC_FLAGS) $(RSYNC_DEPLOY_FILTERS) ./ $(SSH_HOST):$(REMOTE_PATH)/

deploy-sync-files-preview:
	rsync $(RSYNC_FLAGS) --dry-run --itemize-changes $(RSYNC_DEPLOY_FILTERS) ./ $(SSH_HOST):$(REMOTE_PATH)/

deploy-remote-install:
	ssh $(SSH_HOST) "cd $(REMOTE_PATH) && $(SSH_PHP) ~/composer.phar install $(COMPOSER_INSTALL_FLAGS)"

deploy-remote-finalize:
	ssh $(SSH_HOST) "set -e; cd $(REMOTE_PATH); $(SSH_PHP) artisan optimize:clear; $(SSH_PHP) artisan migrate --force; $(SSH_PHP) artisan config:cache; $(SSH_PHP) artisan route:cache; $(SSH_PHP) artisan view:cache; $(SSH_PHP) artisan storage:link >/dev/null 2>&1 || true; $(SSH_PHP) artisan queue:restart >/dev/null 2>&1 || true; rm -f public/hot; touch public/index.php"

deploy-remote-release:
	ssh $(SSH_HOST) "set -e; cd $(REMOTE_PATH); $(SSH_PHP) ~/composer.phar install $(COMPOSER_INSTALL_FLAGS); $(SSH_PHP) artisan optimize:clear; $(SSH_PHP) artisan migrate --force; $(SSH_PHP) artisan config:cache; $(SSH_PHP) artisan route:cache; $(SSH_PHP) artisan view:cache; $(SSH_PHP) artisan storage:link >/dev/null 2>&1 || true; $(SSH_PHP) artisan queue:restart >/dev/null 2>&1 || true; rm -f public/hot; touch public/index.php"

deploy-health:
	@printf "Healthcheck $(ENV_NAME)... "
	@curl -fsSIL --max-time 20 $(HEALTH_URL) >/dev/null
	@echo "OK"

deploy-lock-prod:
	$(MAKE) --no-print-directory deploy-remote-lock ENV_NAME=produzione REMOTE_PATH="$(SSH_PATH)"

deploy-unlock-prod:
	$(MAKE) --no-print-directory deploy-remote-unlock REMOTE_PATH="$(SSH_PATH)"

deploy-down-prod:
	$(MAKE) --no-print-directory deploy-remote-down REMOTE_PATH="$(SSH_PATH)"

deploy-up-prod:
	$(MAKE) --no-print-directory deploy-remote-up REMOTE_PATH="$(SSH_PATH)"

deploy-prepare-prod:
	$(MAKE) --no-print-directory deploy-remote-prepare REMOTE_PATH="$(SSH_PATH)"

deploy-env: deploy-env-prod

deploy-env-prod: validate-prod-env
	$(MAKE) --no-print-directory deploy-env-file ENV_FILE=.env.production REMOTE_PATH="$(SSH_PATH)" ENV_ARCHIVE=.env.production

deploy-assets: deploy-build-assets deploy-public-prod

deploy-code: deploy-code-prod

deploy-preview: deploy-preview-staging

deploy-preview-prod:
	$(MAKE) --no-print-directory deploy-sync-files-preview REMOTE_PATH="$(SSH_PATH)"

deploy-code-prod:
	$(MAKE) --no-print-directory deploy-sync-files REMOTE_PATH="$(SSH_PATH)"

deploy-public-prod:
	$(MAKE) --no-print-directory deploy-sync-files REMOTE_PATH="$(SSH_PATH)"

deploy-vendor: deploy-vendor-prod

deploy-vendor-prod:
	$(MAKE) --no-print-directory deploy-remote-install REMOTE_PATH="$(SSH_PATH)"

deploy-finalize-prod:
	$(MAKE) --no-print-directory deploy-remote-finalize REMOTE_PATH="$(SSH_PATH)"

deploy-health-prod:
	$(MAKE) --no-print-directory deploy-health ENV_NAME=produzione HEALTH_URL="$(PROD_URL)"

# ── Staging ──────────────────────────────────────────────────────────────────

staging-setup: deploy-staging
	@echo "Staging setup completato. Visita https://staging.booking-app.it"

deploy-staging: guard-staging
	@$(MAKE) --no-print-directory deploy-run ENV_NAME=staging ENV_FILE=.env.staging REMOTE_PATH="$(STAGING_PATH)" HEALTH_URL="$(STAGING_URL)" ENV_ARCHIVE=.env.staging VALIDATE_TARGET=validate-staging-env

deploy-lock-staging:
	$(MAKE) --no-print-directory deploy-remote-lock ENV_NAME=staging REMOTE_PATH="$(STAGING_PATH)"

deploy-unlock-staging:
	$(MAKE) --no-print-directory deploy-remote-unlock REMOTE_PATH="$(STAGING_PATH)"

deploy-down-staging:
	$(MAKE) --no-print-directory deploy-remote-down REMOTE_PATH="$(STAGING_PATH)"

deploy-up-staging:
	$(MAKE) --no-print-directory deploy-remote-up REMOTE_PATH="$(STAGING_PATH)"

deploy-prepare-staging:
	$(MAKE) --no-print-directory deploy-remote-prepare REMOTE_PATH="$(STAGING_PATH)"

deploy-staging-env: validate-staging-env
	$(MAKE) --no-print-directory deploy-env-file ENV_FILE=.env.staging REMOTE_PATH="$(STAGING_PATH)" ENV_ARCHIVE=.env.staging

deploy-staging-assets: deploy-build-assets deploy-staging-public

deploy-preview-staging:
	$(MAKE) --no-print-directory deploy-sync-files-preview REMOTE_PATH="$(STAGING_PATH)"

deploy-staging-code:
	$(MAKE) --no-print-directory deploy-sync-files REMOTE_PATH="$(STAGING_PATH)"

deploy-staging-public:
	$(MAKE) --no-print-directory deploy-sync-files REMOTE_PATH="$(STAGING_PATH)"

deploy-staging-vendor:
	$(MAKE) --no-print-directory deploy-remote-install REMOTE_PATH="$(STAGING_PATH)"

deploy-staging-finalize:
	$(MAKE) --no-print-directory deploy-remote-finalize REMOTE_PATH="$(STAGING_PATH)"

deploy-health-staging:
	$(MAKE) --no-print-directory deploy-health ENV_NAME=staging HEALTH_URL="$(STAGING_URL)"

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
