USER = $$(id -u)

rector:
	docker-compose -f ./docker-compose-devel.yml run --rm -u ${USER} -w /src/www cli .vendor/bin/rector

# Composer

composer:
	docker run --init -it --rm -u $(USER) -v "$$(pwd):/app" -w /app \
		composer:latest \
		composer install --no-dev

composer-up:
	docker run --init -it --rm -u $(USER) -v "$$(pwd):/app" -w /app \
		composer:latest \
		composer update --no-cache
