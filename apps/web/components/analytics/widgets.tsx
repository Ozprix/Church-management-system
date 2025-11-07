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
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

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
    <Card>
      <CardHeader className="pb-2">
        <CardDescription>{title}</CardDescription>
        <CardTitle className="mt-1 text-3xl font-semibold text-slate-900">{value}</CardTitle>
      </CardHeader>
      {description && (
        <CardContent className="pt-0">
          <p className="text-xs text-slate-500">{description}</p>
        </CardContent>
      )}
    </Card>
  );
}

interface BarChartCardProps {
  title: string;
  data: AnalyticsDatum[];
  color?: string;
  height?: number;
  onBarClick?: (datum: AnalyticsDatum) => void;
  valueFormatter?: (value: number) => string;
}

export function AnalyticsBarChartCard({
  title,
  data,
  color = "#1e293b",
  height = 260,
  onBarClick,
  valueFormatter,
}: BarChartCardProps) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-semibold text-slate-800">{title}</CardTitle>
        {data.length === 0 && (
          <CardDescription className="text-xs text-slate-500">
            No data available for the selected filters.
          </CardDescription>
        )}
      </CardHeader>
      {data.length ? (
        <CardContent className="pt-0" style={{ height }}>
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
              <Tooltip
                cursor={{ fill: "rgba(148, 163, 184, 0.15)" }}
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
                cursor={onBarClick ? "pointer" : "default"}
              />
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      ) : null}
    </Card>
  );
}

interface AreaChartCardProps {
  title: string;
  data: AnalyticsDatum[];
  stroke?: string;
  fill?: string;
  height?: number;
  valueFormatter?: (value: number) => string;
}

export function AnalyticsAreaChartCard({
  title,
  data,
  stroke = "#0284c7",
  fill = "#bae6fd",
  height = 260,
  valueFormatter,
}: AreaChartCardProps) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-semibold text-slate-800">{title}</CardTitle>
        {data.length === 0 && (
          <CardDescription className="text-xs text-slate-500">
            No trend data available for the selected filters.
          </CardDescription>
        )}
      </CardHeader>
      {data.length ? (
        <CardContent className="pt-0" style={{ height }}>
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
        </CardContent>
      ) : null}
    </Card>
  );
}
