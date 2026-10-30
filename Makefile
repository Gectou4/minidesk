.PHONY: help up down logs ps shell-php shell-db test test-smoke

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Démarre la stack (build si nécessaire)
	docker compose up -d --build

down: ## Arrête et supprime les containers
	docker compose down

logs: ## Affiche les logs en temps réel
	docker compose logs -f

ps: ## Affiche l'état des services
	docker compose ps

shell-php: ## Ouvre un shell dans le container PHP
	docker compose exec php sh

shell-db: ## Ouvre un shell MySQL
	docker compose exec db sh -c 'mysql -u"$$MYSQL_USER" -p"$$(cat "$$MYSQL_PASSWORD_FILE")" "$$MYSQL_DATABASE"'

test: ## Lance les tests PHPUnit dans Docker (démarre la base si besoin)
	docker compose run --rm --build phpunit

test-smoke: ## Teste l'API avec curl (nécessite que la stack soit up)
	@echo "=== GET / ==="
	@curl -sf http://localhost:8080/ && echo
	@echo "=== GET /tickets ==="
	@curl -sf http://localhost:8080/tickets && echo
	@echo "=== GET /tickets/1 ==="
	@curl -sf http://localhost:8080/tickets/1 && echo
	@echo "=== POST /tickets ==="
	@curl -sf -X POST http://localhost:8080/tickets \
		-H "Content-Type: application/json" \
		-d '{"title":"Test ticket","description":"Créé via make test-smoke","priority":"low"}' && echo
