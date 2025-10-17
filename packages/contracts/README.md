# @church/contracts

Generated TypeScript contracts and lightweight API client for the Church Management REST API. Contracts are produced from the OpenAPI specification stored under `openapi/`.

## Usage
```bash
pnpm --filter @church/contracts generate   # regenerate src/generated.ts from OpenAPI
pnpm --filter @church/contracts build      # emit types/esm bundle
pnpm --filter @church/contracts test       # run Vitest suite
```

The exported `createApiClient` helper wraps the fetch API with typed helper methods such as `listMembers`, `createMember`, and `recordDonation`.

Regenerate the contracts whenever the OpenAPI spec changes to keep the shared types in sync with the Laravel API.

