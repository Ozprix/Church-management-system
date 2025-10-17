'use client';

import { RedocStandalone } from 'redoc';

export default function ApiDocsPage() {
  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <h1 className="text-3xl font-semibold text-slate-900">API reference</h1>
        <p className="text-sm text-slate-600">
          This documentation is generated from the shared OpenAPI contract. Keep the contract in
          <code className="mx-1 rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">packages/contracts/openapi/church.json</code>
          up to date to reflect backend changes.
        </p>
      </header>
      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <RedocStandalone specUrl="/api/docs" options={{ theme: { colors: { primary: { main: '#047857' } } } }} />
      </div>
    </section>
  );
}
