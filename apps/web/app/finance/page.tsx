'use client';

import Link from 'next/link';
import { useFinanceDashboard } from '@/hooks/use-finance-dashboard';
import { FinanceDashboardOverview } from '@/components/finance/dashboard-overview';

export default function FinanceDashboardPage() {
  const { data, isLoading, isError } = useFinanceDashboard();

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Finance Overview</h2>
          <p className="text-sm text-slate-500">
            Track giving trends, pledges, and fund performance across your tenant.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Link
            href="/finance/recurring"
            className="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
          >
            Manage Recurring Giving
          </Link>
          <Link
            href="/finance/payment-methods"
            className="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
          >
            Manage Payment Methods
          </Link>
        </div>
      </div>

      {isLoading && <p className="text-sm text-slate-500">Loading dashboard…</p>}
      {isError && <p className="text-sm text-rose-600">Unable to load finance data right now.</p>}
      {data ? <FinanceDashboardOverview data={data} /> : null}
    </div>
  );
}
