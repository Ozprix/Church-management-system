'use client';

import Link from 'next/link';
import { Button, Card, Table, TableBody, TableCell, TableContainer, TableHead, TableHeaderCell, TableRow } from '@church/ui';

const checklist = [
  {
    title: 'Install dependencies',
    description: 'Run pnpm install at the repository root to install workspace packages.',
    command: 'pnpm install',
  },
  {
    title: 'Start backend services',
    description: 'Bring Laravel Sail containers online, run migrations, and seed demo data.',
    command: './vendor/bin/sail up -d && ./vendor/bin/sail artisan migrate --seed',
  },
  {
    title: 'Launch the Next.js app',
    description: 'Start the web experience and open http://localhost:3000 in your browser.',
    command: 'pnpm --filter web dev',
  },
  {
    title: 'Generate API contracts (optional)',
    description: 'Regenerate the OpenAPI client if you update backend routes.',
    command: 'pnpm run generate:contracts',
  },
];

const resources = [
  {
    name: 'System architecture',
    description: 'High-level diagrams, tenancy decisions, and ADRs.',
    href: '/docs/architecture',
  },
  {
    name: 'API reference',
    description: 'OpenAPI documentation for every service endpoint.',
    href: '/docs/api',
  },
  {
    name: 'Saved member reports',
    description: 'Create recurring exports and analytics snapshots.',
    href: '/members/reports',
  },
];

export default function OnboardingPage() {
  return (
    <section className="space-y-8">
      <header className="space-y-2">
        <h1 className="text-3xl font-semibold text-slate-900">Getting started</h1>
        <p className="text-sm text-slate-600">
          Follow this checklist to provision a tenant, explore the UI, and wire in email/SMS providers. Bookmark this page and
          share it with new teammates to accelerate onboarding.
        </p>
      </header>

      <Card className="space-y-4">
        <h2 className="text-lg font-semibold text-slate-900">Launch checklist</h2>
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableHeaderCell>Task</TableHeaderCell>
                <TableHeaderCell>Description</TableHeaderCell>
                <TableHeaderCell>Command</TableHeaderCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {checklist.map((item) => (
                <TableRow key={item.title}>
                  <TableCell className="font-medium text-slate-900">{item.title}</TableCell>
                  <TableCell className="text-sm text-slate-500">{item.description}</TableCell>
                  <TableCell>
                    <code className="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">{item.command}</code>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      <Card className="space-y-4">
        <h2 className="text-lg font-semibold text-slate-900">Recommended next steps</h2>
        <ul className="grid gap-3 md:grid-cols-3">
          {resources.map((resource) => (
            <li key={resource.href} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <h3 className="text-base font-medium text-slate-900">{resource.name}</h3>
              <p className="mt-1 text-sm text-slate-500">{resource.description}</p>
              <Link href={resource.href} className="mt-3 inline-flex text-sm font-medium text-emerald-600 hover:text-emerald-700">
                View resource
              </Link>
            </li>
          ))}
        </ul>
      </Card>

      <Card className="space-y-4">
        <h2 className="text-lg font-semibold text-slate-900">Need a demo tenant?</h2>
        <p className="text-sm text-slate-600">
          Use the automation script below to provision a demo tenant with sample data, directories, and reminders. Run this from
          the repository root after Docker Desktop is running.
        </p>
        <code className="block rounded bg-slate-100 px-3 py-2 text-sm text-slate-700">tools/scripts/bootstrap-demo.sh</code>
        <div>
          <Link href="/communication/templates">
            <Button variant="secondary">Configure templates</Button>
          </Link>
        </div>
      </Card>
    </section>
  );
}
