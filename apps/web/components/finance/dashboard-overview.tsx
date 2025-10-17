'use client';

import { FinanceDashboardResponse } from '@/lib/api/finance';
import {
  Badge,
  Card,
  StatCard,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableHeaderCell,
  TableRow,
} from '@church/ui';

function formatCurrency(amount: number, currency = 'USD') {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(amount);
}

export function FinanceDashboardOverview({ data }: { data: FinanceDashboardResponse }) {
  return (
    <div className="space-y-8">
      <section>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <StatCard
            title="Total Donations"
            value={formatCurrency(data.totals.donations)}
            helperText="All-time successful donations"
            tone="success"
          />
          <StatCard
            title="Month to Date"
            value={formatCurrency(data.totals.month_to_date)}
            helperText="Received since the start of this month"
          />
          <StatCard
            title="Average Donation"
            value={formatCurrency(data.totals.average_donation)}
            helperText="Average per successful donation"
          />
          <StatCard
            title="Active Pledges"
            value={formatCurrency(data.totals.active_pledges)}
            helperText={`Fulfilled: ${formatCurrency(data.totals.fulfilled_pledges)}`}
          />
        </div>
      </section>

      <section className="grid gap-6 lg:grid-cols-2">
        <Card
          title="Top Funds"
          subtitle="Highest funding sources this year"
          className="h-full"
        >
          <div className="space-y-3">
            {data.top_funds.length === 0 ? (
              <p className="text-sm text-slate-500">No fund activity yet.</p>
            ) : (
              data.top_funds.map((fund) => (
                <div key={fund.fund_id} className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                  <div>
                    <p className="text-sm font-medium text-slate-800">{fund.fund_name ?? 'General Fund'}</p>
                    <p className="text-xs text-slate-500">Fund ID: {fund.fund_id}</p>
                  </div>
                  <span className="text-sm font-semibold text-emerald-600">
                    {formatCurrency(fund.total_amount)}
                  </span>
                </div>
              ))
            )}
          </div>
        </Card>

        <Card
          title="Recurring Pledges"
          subtitle="Active scheduled commitments"
          className="h-full"
        >
          <div className="space-y-3">
            {data.recurring_pledges.length === 0 ? (
              <p className="text-sm text-slate-500">No recurring pledges recorded.</p>
            ) : (
              data.recurring_pledges.map((pledge) => (
                <div key={pledge.id} className="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2">
                  <div>
                    <p className="text-sm font-medium text-slate-800">Pledge #{pledge.id}</p>
                    <p className="text-xs text-slate-500">Frequency: {pledge.frequency}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold text-slate-900">
                      {formatCurrency(pledge.amount)}
                    </p>
                    <p className="text-xs text-slate-500">
                      Fulfilled: {formatCurrency(pledge.fulfilled_amount)}
                    </p>
                  </div>
                </div>
              ))
            )}
          </div>
        </Card>
      </section>

      <section>
        <Card title="Recent Donations" subtitle="Latest inbound giving activity">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Donor</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Amount</TableHeaderCell>
                  <TableHeaderCell>Date</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.recent_donations.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="py-6 text-center text-sm text-slate-500">
                      No donations recorded yet.
                    </TableCell>
                  </TableRow>
                ) : (
                  data.recent_donations.map((donation) => (
                    <TableRow key={donation.id}>
                      <TableCell>
                        <div>
                          <p className="text-sm font-medium text-slate-800">
                            {donation.member ? `${donation.member.first_name} ${donation.member.last_name}` : 'Guest Donor'}
                          </p>
                          <p className="text-xs text-slate-500">#{donation.id}</p>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant={donation.status === 'succeeded' ? 'success' : donation.status === 'refunded' ? 'warning' : 'info'}>
                          {donation.status}
                        </Badge>
                      </TableCell>
                      <TableCell>{formatCurrency(Number(donation.amount ?? 0), donation.currency)}</TableCell>
                      <TableCell>
                        <p className="text-sm text-slate-700">
                          {donation.received_at ? new Date(donation.received_at).toLocaleString() : 'Pending'}
                        </p>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Card>
      </section>
    </div>
  );
}
