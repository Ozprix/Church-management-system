#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/../.. && pwd)"
API_DIR="$ROOT_DIR/apps/api"
WEB_DIR="$ROOT_DIR/apps/web"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required to run Sail. Please install Docker Desktop and try again." >&2
  exit 1
fi

pushd "$ROOT_DIR" >/dev/null

if [ ! -d "node_modules" ]; then
  echo "Installing workspace dependencies..."
  pnpm install
fi

if [ ! -f "$API_DIR/.env" ]; then
  echo "Copying Laravel environment file..."
  cp "$API_DIR/.env.example" "$API_DIR/.env"
fi

pushd "$API_DIR" >/dev/null

if [ ! -f "vendor/autoload.php" ]; then
  echo "Installing Laravel dependencies..."
  composer install --no-interaction --prefer-dist
fi

echo "Starting Sail containers..."
./vendor/bin/sail up -d

echo "Running database migrations and seeders..."
./vendor/bin/sail artisan migrate --seed

popd >/dev/null

pushd "$WEB_DIR" >/dev/null

if [ ! -f ".env.local" ]; then
  echo "Copying Next.js environment file..."
  cp ".env.local.example" ".env.local"
fi

echo "All set! Start the web app with: pnpm --filter web dev"

popd >/dev/null
popd >/dev/null
