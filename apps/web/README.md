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

