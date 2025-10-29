'use client';

import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

export type AnalyticsDatum = {
  label: string;
  value: number;
};

interface StatCardProps {
  title: string;
  value: number | string;
  description?: string;
}

export function AnalyticsStatCard({ title, value, description }: StatCardProps) {
  return (
    <div className="rounded border border-slate-200 p-4">
      <p className="text-sm text-slate-500">{title}</p>
      <p className="mt-1 text-3xl font-semibold text-slate-900">{value}</p>
      {description ? <p className="mt-1 text-xs text-slate-500">{description}</p> : null}
    </div>
  );
}

interface BarChartCardProps {
  title: string;
  data: AnalyticsDatum[];
  color?: string;
  height?: number;
  onBarClick?: (datum: AnalyticsDatum) => void;
  valueFormatter?: (value: number) => string;
  helperText?: string;
}

export function AnalyticsBarChartCard({
  title,
  data,
  color = '#047857',
  height = 240,
  onBarClick,
  valueFormatter,
  helperText,
}: BarChartCardProps) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <header className="flex items-start justify-between gap-2">
        <div>
          <h3 className="text-base font-semibold text-slate-800">{title}</h3>
          {helperText ? <p className="mt-1 text-xs text-slate-500">{helperText}</p> : null}
        </div>
      </header>
      {data.length ? (
        <div className="mt-4" style={{ height }}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="label" stroke="#475569" tickLine={false} axisLine={false} />
              <YAxis allowDecimals={false} stroke="#475569" tickLine={false} axisLine={false} />
              <Tooltip
                cursor={{ fill: 'rgba(148, 163, 184, 0.15)' }}
                formatter={(value: number) =>
                  valueFormatter ? valueFormatter(value) : value.toString()
                }
              />
              <Bar
                dataKey="value"
                fill={color}
                radius={[4, 4, 0, 0]}
                onClick={(payload) => {
                  if (!onBarClick || !payload?.payload) return;
                  onBarClick(payload.payload as AnalyticsDatum);
                }}
                cursor={onBarClick ? 'pointer' : 'default'}
              />
            </BarChart>
          </ResponsiveContainer>
        </div>
      ) : (
        <p className="mt-4 text-sm text-slate-500">No data available.</p>
      )}
    </section>
  );
}

interface AreaChartCardProps {
  title: string;
  data: AnalyticsDatum[];
  stroke?: string;
  fill?: string;
  height?: number;
  valueFormatter?: (value: number) => string;
  helperText?: string;
}

export function AnalyticsAreaChartCard({
  title,
  data,
  stroke = '#0ea5e9',
  fill = '#bae6fd',
  height = 240,
  valueFormatter,
  helperText,
}: AreaChartCardProps) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <header>
        <h3 className="text-base font-semibold text-slate-800">{title}</h3>
        {helperText ? <p className="mt-1 text-xs text-slate-500">{helperText}</p> : null}
      </header>
      {data.length ? (
        <div className="mt-4" style={{ height }}>
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={data}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="label" stroke="#475569" tickLine={false} axisLine={false} />
              <YAxis allowDecimals={false} stroke="#475569" tickLine={false} axisLine={false} />
              <Tooltip
                cursor={{ stroke, strokeWidth: 1 }}
                formatter={(value: number) =>
                  valueFormatter ? valueFormatter(value) : value.toString()
                }
              />
              <Area
                type="monotone"
                dataKey="value"
                stroke={stroke}
                fill={fill}
                strokeWidth={2}
                dot={{ r: 3, strokeWidth: 1, stroke }}
                activeDot={{ r: 5 }}
              />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      ) : (
        <p className="mt-4 text-sm text-slate-500">No trend data available.</p>
      )}
    </section>
  );
}
