run:
	docker compose down; docker compose up -d --build

run-local:
	docker compose down; sudo service mysql start; php index.php