"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { format } from "date-fns";
import clsx from "clsx";
import { useAuth } from "@/providers/auth-provider";
import { apiFetch } from "@/lib/api";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import type {
  NotificationRule,
  NotificationRuleRun,
  NotificationTemplateSummary,
  NotificationChannelHealth,
} from "@/types/events";

type RuleListResponse = {
  data: NotificationRule[];
  meta?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
};

type RuleDetailResponse = {
  data: NotificationRule;
};

type TemplateListResponse = {
  data: NotificationTemplateSummary[];
};

type RulePayload = {
  tenant_id: number;
  name: string;
  trigger_type: string;
  trigger_config: Record<string, unknown>;
  channel: string;
  notification_template_id: number | null;
  status: string;
  throttle_minutes: number | null;
};

const MEMBERSHIP_STATUSES = [
  "prospect",
  "active",
  "inactive",
  "visitor",
  "suspended",
  "transferred",
] as const;

const CHANNEL_OPTIONS = [
  { value: "email", label: "Email" },
  { value: "sms", label: "SMS" },
] as const;

export default function NotificationRulesPage(): React.ReactElement {
  const { user, loading } = useAuth();
  const queryClient = useQueryClient();

  const [selectedRuleId, setSelectedRuleId] = useState<number | null>(null);
  const [formState, setFormState] = useState({
    name: "",
    triggerType: "member_status",
    membershipStatus: "active",
    membershipStage: "",
    channel: "email",
    templateId: "",
    status: "active",
    throttleMinutes: "",
    error: "",
    success: "",
  });

  const tenantId = useMemo(() => {
    const env = process.env.NEXT_PUBLIC_TENANT_ID;
    const parsed = env ? Number(env) : NaN;
    return Number.isFinite(parsed) ? parsed : null;
  }, []);

  const rulesQuery = useQuery<RuleListResponse>({
    queryKey: ["notification-rules"],
    queryFn: async () =>
      apiFetch<RuleListResponse>("/api/v1/notification-rules?per_page=50"),
    enabled: !!user,
    staleTime: 30_000,
  });

  const templatesQuery = useQuery<TemplateListResponse>({
    queryKey: ["notification-templates"],
    queryFn: async () =>
      apiFetch<TemplateListResponse>("/api/v1/notification-templates?per_page=100"),
    enabled: !!user,
    staleTime: 5 * 60_000,
  });

  const selectedRuleQuery = useQuery<RuleDetailResponse>({
    queryKey: ["notification-rule", selectedRuleId],
    queryFn: async () =>
      apiFetch<RuleDetailResponse>(`/api/v1/notification-rules/${selectedRuleId}`),
    enabled: !!user && !!selectedRuleId,
  });

  const createRule = useMutation({
    mutationFn: async (payload: RulePayload) =>
      apiFetch<RuleDetailResponse>("/api/v1/notification-rules", {
        method: "POST",
        body: JSON.stringify(payload),
      }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["notification-rules"] });
      if (response?.data?.id) {
        setSelectedRuleId(response.data.id);
      }
      setFormState((prev) => ({
        ...prev,
        name: "",
        membershipStatus: "active",
        membershipStage: "",
        channel: "email",
        templateId: "",
        status: "active",
        throttleMinutes: "",
        error: "",
        success: "Notification rule created.",
      }));
    },
    onError: (error: unknown) => {
      setFormState((prev) => ({
        ...prev,
        error:
          error instanceof Error
            ? error.message
            : "Unable to save notification rule.",
        success: "",
      }));
    },
  });

  const updateRule = useMutation({
    mutationFn: async (input: { id: number; payload: Partial<RulePayload> }) =>
      apiFetch<RuleDetailResponse>(`/api/v1/notification-rules/${input.id}`, {
        method: "PATCH",
        body: JSON.stringify(input.payload),
      }),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["notification-rules"] });
      queryClient.invalidateQueries({ queryKey: ["notification-rule", variables.id] });
    },
  });

  const runRule = useMutation({
    mutationFn: async (id: number) =>
      apiFetch<{ data: NotificationRuleRun }>(
        `/api/v1/notification-rules/${id}/run`,
        { method: "POST" }
      ),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ["notification-rule", id] });
    },
  });

  if (loading) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">Loading…</p>
      </main>
    );
  }

  if (!user) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">
          You must sign in to manage notification rules.{" "}
          <a className="underline" href="/login">
            Go to login
          </a>
          .
        </p>
      </main>
    );
  }

  if (!tenantId) {
    return (
      <main className="flex min-h-screen items-center justify-center px-6">
        <div className="max-w-md rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <p className="font-semibold">Tenant configuration required</p>
          <p className="mt-2">
            Set <code>NEXT_PUBLIC_TENANT_ID</code> in your web environment so new
            notification rules can be scoped correctly.
          </p>
        </div>
      </main>
    );
  }

  const rules = rulesQuery.data?.data ?? [];
  const selectedRule = selectedRuleQuery.data?.data ?? null;
  const ruleRuns = selectedRule?.runs ?? [];
  const templates = templatesQuery.data?.data ?? [];
  const channelHealth: NotificationChannelHealth[] = channelHealthQuery.data?.data ?? [];

  const handleCreateRule = (event: React.FormEvent) => {
    event.preventDefault();

    const triggerConfig =
      formState.triggerType === "member_status"
        ? { member_status: formState.membershipStatus }
        : formState.membershipStage
          ? { membership_stage: formState.membershipStage }
          : {};

    createRule.mutate({
      tenant_id: tenantId,
      name: formState.name,
      trigger_type: formState.triggerType,
      trigger_config: triggerConfig,
      channel: formState.channel,
      notification_template_id: formState.templateId
        ? Number(formState.templateId)
        : null,
      status: formState.status,
      throttle_minutes: formState.throttleMinutes
        ? Number(formState.throttleMinutes)
        : null,
    });
  };

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-6 py-10">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900">Notification automation</h1>
        <p className="text-sm text-slate-600">
          Define messaging rules, connect templates, and keep SMS/email delivery pipelines healthy.
        </p>
      </header>

      {channelHealthQuery.isLoading && (
        <p className="text-sm text-slate-500">Checking channel health…</p>
      )}

      {!channelHealthQuery.isLoading && channelHealth.length > 0 && (
        <section className="grid gap-4 lg:grid-cols-2">
          {channelHealth.map((health) => (
            <Card key={health.channel}>
              <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div>
                  <CardTitle className="text-base capitalize">{health.channel} delivery</CardTitle>
                  <CardDescription>
                    Last {health.window_days} days · {health.totals.sent} sent · {health.totals.failed} failed
                  </CardDescription>
                </div>
                <span
                  className={clsx(
                    "rounded-full px-3 py-1 text-xs font-semibold capitalize",
                    health.health === "healthy" && "bg-emerald-100 text-emerald-800",
                    health.health === "degraded" && "bg-amber-100 text-amber-800",
                    health.health === "idle" && "bg-slate-100 text-slate-600"
                  )}
                >
                  {health.health}
                </span>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <p className="text-sm font-medium text-slate-800">
                    Delivery rate:{" "}
                    {typeof health.delivery_rate === "number" ? `${health.delivery_rate}%` : "—"}
                  </p>
                  <div className="mt-2 h-2 w-full rounded-full bg-slate-100">
                    <div
                      className={clsx(
                        "h-2 rounded-full transition-all",
                        health.health === "healthy" ? "bg-emerald-500" : "bg-amber-500"
                      )}
                      style={{
                        width: `${Math.max(
                          0,
                          Math.min(100, typeof health.delivery_rate === "number" ? health.delivery_rate : 0)
                        )}%`,
                      }}
                    />
                  </div>
                </div>
                <div className="text-sm text-slate-600">
                  <p className="font-semibold">Provider</p>
                  <p>
                    {health.provider?.name ?? "Unconfigured"} ·{" "}
                    {health.provider?.configured ? (
                      <span className="text-emerald-600">Keys installed</span>
                    ) : (
                      <span className="text-amber-600">Needs keys</span>
                    )}
                  </p>
                  {health.provider?.details?.from && (
                    <p className="text-xs text-slate-500">From: {health.provider.details.from}</p>
                  )}
                </div>
                {health.last_error && (
                  <div className="rounded border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
                    Last error: {health.last_error}
                  </div>
                )}
              </CardContent>
            </Card>
          ))}
        </section>
      )}

      <section className="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Automation rules</CardTitle>
            <CardDescription>
              Manage cadence and delivery channel for member-triggered outreach.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {rulesQuery.error && (
              <div className="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {(rulesQuery.error as Error).message}
              </div>
            )}
            {rules.length === 0 && !rulesQuery.isLoading ? (
              <p className="text-sm text-slate-500">
                No automation rules yet. Create one to get started.
              </p>
            ) : null}

            {rules.map((rule) => {
              const isActive = rule.status === "active";
              return (
                <button
                  key={rule.id}
                  type="button"
                  onClick={() => setSelectedRuleId(rule.id)}
                  className={`w-full rounded border px-4 py-3 text-left text-sm shadow-sm transition ${
                    selectedRuleId === rule.id
                      ? "border-slate-900 bg-slate-50"
                      : "border-slate-200 bg-white hover:border-slate-300"
                  }`}
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <p className="text-base font-semibold text-slate-900">
                        {rule.name}
                      </p>
                      <p className="text-xs uppercase tracking-wide text-slate-500">
                        {rule.channel.toUpperCase()} • {rule.trigger_type}
                      </p>
                    </div>
                    <span
                      className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                        isActive
                          ? "bg-emerald-100 text-emerald-700"
                          : "bg-slate-200 text-slate-600"
                      }`}
                    >
                      {isActive ? "Active" : "Inactive"}
                    </span>
                  </div>
                  <p className="mt-2 text-xs text-slate-500">
                    Template:{" "}
                    {rule.template?.name
                      ? `${rule.template.name} (${rule.template.channel})`
                      : "Not connected"}
                  </p>
                </button>
              );
            })}

            {rulesQuery.isLoading && (
              <p className="text-sm text-slate-500">Loading rules…</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Create notification rule</CardTitle>
            <CardDescription>
              Choose a trigger, channel, and template. You can fine-tune details
              later.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {formState.error && (
              <div className="mb-3 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {formState.error}
              </div>
            )}
            {formState.success && (
              <div className="mb-3 rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {formState.success}
              </div>
            )}
            <form className="space-y-4" onSubmit={handleCreateRule}>
              <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">
                  Rule name
                </label>
                <input
                  type="text"
                  required
                  value={formState.name}
                  onChange={(event) =>
                    setFormState((prev) => ({
                      ...prev,
                      name: event.target.value,
                      error: "",
                      success: "",
                    }))
                  }
                  className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                  placeholder="e.g. New visitor welcome"
                />
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">
                  Trigger
                </label>
                <select
                  value={formState.triggerType}
                  onChange={(event) =>
                    setFormState((prev) => ({
                      ...prev,
                      triggerType: event.target.value,
                    }))
                  }
                  className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="member_status">Membership status changes</option>
                  <option value="membership_stage">
                    Membership stage changes
                  </option>
                </select>

                {formState.triggerType === "member_status" ? (
                  <select
                    value={formState.membershipStatus}
                    onChange={(event) =>
                      setFormState((prev) => ({
                        ...prev,
                        membershipStatus: event.target.value,
                      }))
                    }
                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                  >
                    {MEMBERSHIP_STATUSES.map((status) => (
                      <option key={status} value={status}>
                        {status}
                      </option>
                    ))}
                  </select>
                ) : (
                  <input
                    type="text"
                    value={formState.membershipStage}
                    onChange={(event) =>
                      setFormState((prev) => ({
                        ...prev,
                        membershipStage: event.target.value,
                      }))
                    }
                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Enter stage slug (e.g. welcome, serving)"
                  />
                )}
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">
                  Channel
                </label>
                <select
                  value={formState.channel}
                  onChange={(event) =>
                    setFormState((prev) => ({
                      ...prev,
                      channel: event.target.value,
                    }))
                  }
                  className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                  {CHANNEL_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">
                  Notification template
                </label>
                <select
                  value={formState.templateId}
                  onChange={(event) =>
                    setFormState((prev) => ({
                      ...prev,
                      templateId: event.target.value,
                    }))
                  }
                  className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Select template (optional)</option>
                  {templates.map((template) => (
                    <option key={template.id} value={template.id}>
                      {template.name} ({template.channel})
                    </option>
                  ))}
                </select>
                {templatesQuery.isLoading && (
                  <p className="text-xs text-slate-500">Loading templates…</p>
                )}
                {templatesQuery.error && (
                  <p className="text-xs text-red-600">
                    {(templatesQuery.error as Error).message}
                  </p>
                )}
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                  <label className="text-sm font-medium text-slate-700">
                    Status
                  </label>
                  <select
                    value={formState.status}
                    onChange={(event) =>
                      setFormState((prev) => ({
                        ...prev,
                        status: event.target.value,
                      }))
                    }
                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-medium text-slate-700">
                    Throttle (minutes)
                  </label>
                  <input
                    type="number"
                    min={0}
                    value={formState.throttleMinutes}
                    onChange={(event) =>
                      setFormState((prev) => ({
                        ...prev,
                        throttleMinutes: event.target.value,
                      }))
                    }
                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Optional"
                  />
                </div>
              </div>

              <CardFooter className="px-0 pb-0">
                <button
                  type="submit"
                  disabled={createRule.isLoading}
                  className="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                >
                  {createRule.isLoading ? "Creating…" : "Create rule"}
                </button>
              </CardFooter>
            </form>
          </CardContent>
        </Card>
      </section>

      {selectedRuleQuery.isLoading && (
        <Card>
          <CardHeader>
            <CardTitle>Rule details</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-slate-500">Loading rule details…</p>
          </CardContent>
        </Card>
      )}

      {selectedRuleQuery.error && (
        <Card>
          <CardHeader>
            <CardTitle>Rule details</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-red-600">
              {(selectedRuleQuery.error as Error).message}
            </p>
          </CardContent>
        </Card>
      )}

      {selectedRule && (
        <section className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
          <Card>
            <CardHeader>
              <CardTitle>{selectedRule.name}</CardTitle>
              <CardDescription>
                Slug: {selectedRule.slug ?? "—"} · Channel:{" "}
                {selectedRule.channel.toUpperCase()}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm text-slate-700">
              <div>
                <p className="text-xs uppercase tracking-wide text-slate-500">
                  Trigger
                </p>
                <p className="mt-1">
                  {selectedRule.trigger_type} ·{" "}
                  <code className="rounded bg-slate-100 px-1">
                    {JSON.stringify(selectedRule.trigger_config ?? {}, null, 0)}
                  </code>
                </p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-slate-500">
                  Template
                </p>
                <p className="mt-1">
                  {selectedRule.template?.name
                    ? `${selectedRule.template.name} (${selectedRule.template.channel})`
                    : "No template linked"}
                </p>
              </div>
              <div className="flex flex-wrap gap-3">
                <button
                  type="button"
                  onClick={() =>
                    updateRule.mutate({
                      id: selectedRule.id,
                      payload: {
                        status:
                          selectedRule.status === "active" ? "inactive" : "active",
                      },
                    })
                  }
                  disabled={updateRule.isLoading}
                  className="rounded border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                >
                  {selectedRule.status === "active"
                    ? "Pause rule"
                    : "Activate rule"}
                </button>
                <button
                  type="button"
                  onClick={() => runRule.mutate(selectedRule.id)}
                  disabled={runRule.isLoading}
                  className="rounded bg-slate-900 px-4 py-2 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                >
                  {runRule.isLoading ? "Running…" : "Run now"}
                </button>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Recent runs</CardTitle>
              <CardDescription>
                Monitor results from the last automation executions.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3 text-sm text-slate-700">
              {ruleRuns.length === 0 && (
                <p className="text-sm text-slate-500">
                  No automation runs recorded yet.
                </p>
              )}
              {ruleRuns.map((run) => (
                <div
                  key={run.id}
                  className="rounded border border-slate-200 bg-white p-3 text-sm text-slate-700"
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-semibold text-slate-900">
                      {run.status.toUpperCase()}
                    </p>
                    <p className="text-xs text-slate-500">
                      {run.ran_at
                        ? format(new Date(run.ran_at), "MMM d, yyyy h:mm a")
                        : "Pending"}
                    </p>
                  </div>
                  <p className="mt-1 text-xs text-slate-500">
                    Matched {run.matched_count} · Sent {run.sent_count}
                  </p>
                  {run.error_message && (
                    <p className="mt-2 text-xs text-red-600">
                      {run.error_message}
                    </p>
                  )}
                </div>
              ))}
            </CardContent>
          </Card>
        </section>
      )}
    </main>
  );
}
