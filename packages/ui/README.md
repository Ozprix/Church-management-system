# @church/ui

Shared React component library for the church management SaaS. Components are authored with Tailwind-friendly class names and are intended to be consumed by the Next.js PWA as well as any internal admin tools.

## Available Components
- `Button` – multi-variant button with loading and icon states.
- `Card` – layout container with consistent spacing and action slots.
- `SectionHeading` – heading block for dashboard sections.
- `TenantBadge` – pill badge for displaying the active tenant or ministry.
- `useTenantBranding` – hook returning deterministic colour palettes per tenant slug.

## Scripts
```bash
pnpm --filter @church/ui build       # emit type definitions and ESM bundle
pnpm --filter @church/ui lint        # run ESLint against the package
pnpm --filter @church/ui test        # execute Vitest suite (jsdom)
```

Add new components under `src/components/` and export them through `src/index.ts` so they are discoverable to consumers.

