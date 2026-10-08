.PHONY: help dev backend frontend docker-full stop restart build install supplier-hub-build supplier-hub-test status logs wp-shell db-shell

help:
	@echo "Pupilovo"
	@echo ""
	@echo "Development:"
	@echo "  make dev          WordPress + MySQL w Dockerze + frontend lokalnie"
	@echo "  make backend      WordPress + MySQL w Dockerze"
	@echo "  make frontend     Frontend lokalnie"
	@echo "  make docker-full  Cały projekt w Dockerze"
	@echo ""
	@echo "Frontend:"
	@echo "  make install      Instalacja zależności npm"
	@echo "  make build        Production build frontendu"
	@echo "  make supplier-hub-build  Lint i build panelu Supplier Hub"
	@echo "  make supplier-hub-test   Test integracyjny Etapu A w Dockerze"
	@echo ""
	@echo "Docker:"
	@echo "  make stop         Zatrzymaj całe środowisko"
	@echo "  make restart      Restart WordPress + MySQL"
	@echo "  make status       Status kontenerów"
	@echo "  make logs         Logi WordPress + MySQL"
	@echo "  make wp-shell     Shell kontenera WordPress"
	@echo "  make db-shell     Konsola MySQL"

dev: backend
	cd frontend && npm run dev

backend:
	docker compose up -d wordpress mysql

frontend:
	cd frontend && npm run dev

docker-full:
	docker compose --profile full up -d

stop:
	docker compose --profile full down

restart:
	docker compose restart wordpress mysql

install:
	cd frontend && npm install

build:
	cd frontend && npm run build

supplier-hub-build:
	cd wordpress/plugins/pupilovo-dropshipping/admin-app && npm run lint && npm run build

supplier-hub-test:
	docker compose exec -T wordpress php /var/www/html/wp-content/plugins/pupilovo-dropshipping/tests/stage-a-integration.php

status:
	docker compose --profile full ps

logs:
	docker compose logs -f wordpress mysql

wp-shell:
	docker compose exec wordpress bash

db-shell:
	docker compose exec mysql mysql -uwordpress -pwordpress_password wordpress
