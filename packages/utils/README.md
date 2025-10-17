# @church/utils

Shared utility helpers (formatters, tenant helpers, retry logic) used across the SaaS stack.

## Provided Helpers
- `formatCurrency` / `formatDate` / `formatPhoneNumber`
- `resolveTenantFromHostname` – derive tenant slug from host/custom domain mapping.
- `retry` – promise-based retry helper with exponential-friendly options.

## Scripts
```bash
pnpm --filter @church/utils build
pnpm --filter @church/utils lint
pnpm --filter @church/utils test
```

Keep utilities framework-agnostic and export them via `src/index.ts` for easy tree-shaking.

