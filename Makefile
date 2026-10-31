.PHONY: help up down logs ps shell-php shell-db test openapi test-smoke

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

openapi: ## Régénère app/openapi.json depuis les attributs de app/ApiDoc.php
	docker compose run --rm phpunit sh -c "composer install --no-interaction --prefer-dist -q && vendor/bin/openapi app -o app/openapi.json --version 3.0.0"

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
	@echo "=== POST /tickets sans title (422 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8080/tickets \
		-H "Content-Type: application/json" \
		-d '{"description":"sans titre"}'
	@echo "=== PATCH /tickets/1/status (200 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X PATCH http://localhost:8080/tickets/1/status \
		-H "Content-Type: application/json" \
		-d '{"status":"open"}'
	@echo "=== PATCH /tickets/1/status invalide (422 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X PATCH http://localhost:8080/tickets/1/status \
		-H "Content-Type: application/json" \
		-d '{"status":"nimporte_quoi"}'
	@echo "=== PATCH /tickets/999999/status (404 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X PATCH http://localhost:8080/tickets/999999/status \
		-H "Content-Type: application/json" \
		-d '{"status":"open"}'
	@echo "=== PATCH /tickets/1/priority (200 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X PATCH http://localhost:8080/tickets/1/priority \
		-H "Content-Type: application/json" \
		-d '{"priority":"critical"}'
	@echo "=== PATCH /tickets/1/priority invalide (422 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X PATCH http://localhost:8080/tickets/1/priority \
		-H "Content-Type: application/json" \
		-d '{"priority":"urgent"}'
	@echo "=== PATCH /tickets/999999/priority (404 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X PATCH http://localhost:8080/tickets/999999/priority \
		-H "Content-Type: application/json" \
		-d '{"priority":"low"}'
	@echo "=== DELETE /tickets/1 (405 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X DELETE http://localhost:8080/tickets/1
	@echo "=== PUT /tickets/1/status (405 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X PUT http://localhost:8080/tickets/1/status
	@echo "=== GET /nimporte (404 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" -X GET http://localhost:8080/nimporte
	@echo "=== GET /tickets?status=open&limit=2 (200 attendu, X-Total-Count présent) ==="
	@curl -s -D - -o /dev/null "http://localhost:8080/tickets?status=open&limit=2" | grep -iE '^(HTTP|x-total-count)'
	@echo "=== GET /tickets?limit=0 (422 attendu) ==="
	@curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8080/tickets?limit=0"
	@echo "=== POST /tickets avec un corps de 600 Ko (413 attendu) ==="
	@head -c 600000 /dev/zero | tr '\0' 'a' | curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8080/tickets \
		-H "Content-Type: application/json" --data-binary @-
	@echo "=== GET /openapi (200 attendu, JSON) ==="
	@curl -s -D - -o /dev/null http://localhost:8080/openapi | grep -iE '^(HTTP|content-type)'
	@echo "=== GET /docs (200 attendu, Swagger UI sur http://localhost:8080/docs) ==="
	@curl -s -D - -o /dev/null http://localhost:8080/docs | grep -iE '^(HTTP|content-type)'
