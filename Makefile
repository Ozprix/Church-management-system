.PHONY: bootstrap-all bootstrap-api bootstrap-web lint test

bootstrap-all: bootstrap-api bootstrap-web

bootstrap-api:
	./tools/scripts/bootstrap-api.sh

bootstrap-web:
	./tools/scripts/bootstrap-web.sh

lint:
	pnpm lint

test:
	pnpm test

