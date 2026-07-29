# Makefile for Routing Kit Bundle Demo - Symfony 8
# Run from this directory: make up, make install, etc.

SHELL := /bin/bash
# Prefer Compose V2; absolute docker path avoids shadowing by local docker/ when PATH has "." (REQ-MAKE-010).
DOCKER_BIN := $(shell command -v docker 2>/dev/null)
COMPOSE_FILE := docker-compose.yml
ifeq ($(DOCKER_BIN),)
COMPOSE_BIN := docker-compose
else
COMPOSE_BIN := $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
endif
DOCKER_COMPOSE := $(COMPOSE_BIN) -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down restart build install shell logs cache-clear update-bundle ensure-up test test-coverage verify release-check

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)

help:
	@echo "Symfony 8 Demo - Routing Kit Bundle"
	@echo ""
	@echo "  make up            Start FrankenPHP demo"
	@echo "  make down          Stop demo"
	@echo "  make restart       Restart container"
	@echo "  make build         Build image (no cache)"
	@echo "  make install       Composer install in container"
	@echo "  make shell         Open shell in PHP container"
	@echo "  make logs          Show container logs"
	@echo "  make cache-clear   Clear Symfony cache"
	@echo "  make update-bundle Sync path-repo bundle + clear cache"
	@echo "  make test          Run PHPUnit smoke tests"
	@echo "  make test-coverage Run tests with coverage (if configured)"
	@echo "  make verify        HTTP healthcheck on PORT"
	@echo "  make release-check update-bundle + test + verify"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@PORT_VALUE=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT_VALUE" ] && PORT_VALUE=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	PORT=$$PORT_VALUE $(DOCKER_COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(DOCKER_COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

ensure-up:
	@if ! $(DOCKER_COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting demo container..."; \
		PORT_VALUE=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
		[ -z "$$PORT_VALUE" ] && PORT_VALUE=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
		PORT=$$PORT_VALUE $(DOCKER_COMPOSE) up -d; \
		sleep 5; \
		$(DOCKER_COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction; \
	fi

down:
	$(DOCKER_COMPOSE) down

restart: ensure-up
	$(DOCKER_COMPOSE) restart

build:
	$(DOCKER_COMPOSE) build --no-cache

install: ensure-up
	$(DOCKER_COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction

shell: ensure-up
	$(DOCKER_COMPOSE) exec $(SERVICE_PHP) sh

logs:
	$(DOCKER_COMPOSE) logs -f

test: ensure-up update-bundle
	$(DOCKER_COMPOSE) exec -T -e APP_ENV=test $(SERVICE_PHP) php bin/phpunit

test-coverage: ensure-up update-bundle
	$(DOCKER_COMPOSE) exec -T -e APP_ENV=test $(SERVICE_PHP) php bin/phpunit --coverage-text

cache-clear: ensure-up
	$(DOCKER_COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear

# REQ-DEMO-007
update-bundle: ensure-up
	$(DOCKER_COMPOSE) exec -T $(SERVICE_PHP) composer update nowo-tech/routing-kit-bundle --no-interaction
	$(DOCKER_COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear --env=dev

verify: ensure-up
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8058; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT/" 2>/dev/null || echo "000"); \
	if [ "$$code" != "200" ]; then echo "Healthcheck failed: $$code"; exit 1; fi; \
	echo "Healthcheck OK ($$code)"

release-check: update-bundle test verify

# REQ-MAKE-008: update-deps
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
