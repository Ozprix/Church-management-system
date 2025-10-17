#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/../.. && pwd)"
TARGET_DIR="$ROOT_DIR/apps/web"

if [ -f "$TARGET_DIR/package.json" ]; then
  echo "Next.js app already detected in apps/web."
  exit 0
fi

shopt -s nullglob dotglob
entries=("$TARGET_DIR"/*)
shopt -u dotglob

if ((${#entries[@]} > 1)) || { ((${#entries[@]} == 1)) && [[ $(basename "${entries[0]}") != ".gitkeep" ]]; }; then
  echo "apps/web is not empty. Please clean the directory before bootstrapping Next.js."
  exit 1
fi

rm -f "$TARGET_DIR/.gitkeep"

if command -v pnpm >/dev/null 2>&1; then
  echo "pnpm already available on PATH."
elif command -v corepack >/dev/null 2>&1; then
  echo "Enabling pnpm via corepack (requires permission to create global symlinks)..."
  if ! COREPACK_HOME="$ROOT_DIR/.corepack" corepack enable pnpm >/dev/null 2>&1; then
    echo "Warning: corepack could not enable pnpm automatically. Ensure pnpm is installed before rerunning."
  fi
fi

if ! command -v pnpm >/dev/null 2>&1; then
  echo "pnpm is required to scaffold the Next.js application. Install pnpm or enable corepack, then rerun this script."
  exit 1
fi

pnpm dlx create-next-app@latest apps/web \
  --ts \
  --tailwind \
  --eslint \
  --app \
  --src-dir \
  --no-example \
  --use-pnpm \
  --import-alias "@/*"

cd "$TARGET_DIR"
pnpm install

cat <<'ENV' > .env.local.example
NEXT_PUBLIC_API_URL=http://localhost/api
NEXT_PUBLIC_TENANT_MODE=subdomain
ENV

cat <<'MD' > README.md
# Web PWA (Next.js)

Generated with `create-next-app` using the bootstrap script from `tools/scripts/bootstrap-web.sh`.

Key stacks enabled:
- TypeScript App Router with `/src` directory
- Tailwind CSS + ESLint
- pnpm workspace integration

## Getting Started

```bash
./tools/scripts/bootstrap-web.sh   # run once to generate the project
cd apps/web
pnpm dev
```

Set up environment variables by copying `.env.local.example` to `.env.local`. Ensure the API URL points at the Laravel backend (Sail or production URL).

## Next Steps
- Implement tenant resolution middleware (subdomain/custom domain).
- Build shared UI components under `packages/ui` and import via the alias `@/components`.
- Integrate authentication flows with Laravel Sanctum endpoints.

MD

