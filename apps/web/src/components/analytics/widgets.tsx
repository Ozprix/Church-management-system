"use client";

import {
  ResponsiveContainer,
  BarChart,
  Bar,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  AreaChart,
  Area,
} from "recharts";

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
      {description && <p className="mt-1 text-xs text-slate-500">{description}</p>}
    </div>
  );
}

interface BarChartCardProps {
  title: string;
  data: AnalyticsDatum[];
  color?: string;
  height?: number;
}

export function AnalyticsBarChartCard({
  title,
  data,
  color = "#1e293b",
  height = 260,
}: BarChartCardProps) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <h3 className="text-base font-semibold text-slate-800">{title}</h3>
      {data.length ? (
        <div className="mt-4" style={{ height }}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="label" stroke="#475569" tickLine={false} axisLine={false} />
              <YAxis
                allowDecimals={false}
                stroke="#475569"
                tickLine={false}
                axisLine={false}
              />
              <Tooltip cursor={{ fill: "rgba(148, 163, 184, 0.15)" }} />
              <Bar dataKey="value" fill={color} radius={[4, 4, 0, 0]} />
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
}

export function AnalyticsAreaChartCard({
  title,
  data,
  stroke = "#0284c7",
  fill = "#bae6fd",
  height = 260,
}: AreaChartCardProps) {
  return (
    <section className="rounded border border-slate-200 p-4">
      <h3 className="text-base font-semibold text-slate-800">{title}</h3>
      {data.length ? (
        <div className="mt-4" style={{ height }}>
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={data}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="label" stroke="#475569" tickLine={false} axisLine={false} />
              <YAxis
                allowDecimals={false}
                stroke="#475569"
                tickLine={false}
                axisLine={false}
              />
              <Tooltip cursor={{ stroke, strokeWidth: 1 }} />
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
