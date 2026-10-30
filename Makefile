.PHONY: help up down logs ps shell-php shell-db test

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

test: ## Lance les tests curl de l'API (nécessite que la stack soit up)
	@echo "\n=== GET / ==="
	curl -s http://localhost:8080/ | python3 -m json.tool || true

	@echo "\n=== GET /tickets ==="
	curl -s http://localhost:8080/tickets | python3 -m json.tool || true

	@echo "\n=== POST /tickets ==="
	curl -s -X POST http://localhost:8080/tickets \
		-H "Content-Type: application/json" \
		-d '{"title":"Test ticket","description":"Créé via make test","priority":"low"}' \
		| python3 -m json.tool || true
