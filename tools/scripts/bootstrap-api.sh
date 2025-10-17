#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/../.. && pwd)"
TARGET_DIR="$ROOT_DIR/apps/api"

if [ -d "$TARGET_DIR/vendor" ]; then
  echo "Laravel appears to be already installed under apps/api (vendor directory found)."
  exit 0
fi

entries=()
if compgen -G "$TARGET_DIR/*" >/dev/null 2>&1; then
  while IFS= read -r -d '' path; do
    entries+=("$path")
  done < <(find "$TARGET_DIR" -mindepth 1 -maxdepth 1 -print0)
fi

allowed_files=(".gitkeep" "README.md" ".env.example")

if [ ${#entries[@]} -gt 0 ]; then
  filtered=()
  for entry in "${entries[@]}"; do
    base=$(basename "$entry")
    allowed=false
    for allow in "${allowed_files[@]}"; do
      if [[ "$base" == "$allow" ]]; then
        allowed=true
        break
      fi
    done
    if [ "$allowed" = false ]; then
      echo "Found unexpected file or directory in apps/api: $base"
      echo "Please clean the directory before bootstrapping Laravel."
      exit 1
    fi
    filtered+=("$entry")
  done

  for entry in "${filtered[@]}"; do
    rm -f "$entry"
  done
fi

rm -f "$TARGET_DIR/.gitkeep"

docker run --rm \
  -e COMPOSER_MEMORY_LIMIT=-1 \
  -v "$ROOT_DIR":/workspace \
  -w /workspace \
  laravelsail/php82-composer:latest \
  composer create-project laravel/laravel apps/api --no-interaction

cat <<'ENV' > "$TARGET_DIR/.env.example"
APP_NAME=ChurchSaaS
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=church_saas
DB_USERNAME=church_saas
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
SESSION_LIFETIME=120

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000
TENANCY_DEFAULT_DOMAIN=localhost
ENV

cat <<'MD' > "$TARGET_DIR/README.md"
# API Service (Laravel)

This directory is managed by the bootstrap script in `tools/scripts/bootstrap-api.sh`. Run the script to generate the Laravel application using the official Sail Composer image:

```bash
./tools/scripts/bootstrap-api.sh
```

After installation, install Sail for local containers:

```bash
cd apps/api
php artisan sail:install --services=mysql,redis,meilisearch,mailpit
./vendor/bin/sail up -d
```

Key follow-up tasks:
- Add tenancy middleware & `TenantScopedModel` base trait.
- Configure Sanctum for SPA authentication.
- Set up module directories under `app/Modules/*`.
MD

