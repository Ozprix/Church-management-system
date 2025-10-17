"use client";

import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import type { MemberDetail } from "@/types/member";
import { MemberAuditTimeline } from "./_components/member-audit-timeline";
import { useAuth } from "@/providers/auth-provider";

interface MemberPageProps {
  params: {
    uuid: string;
  };
}

export default function MemberDetailPage({ params }: MemberPageProps) {
  const { uuid } = params;
  const { user, loading } = useAuth();
  const [member, setMember] = useState<MemberDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    const loadMember = async () => {
      setIsLoading(true);
      try {
        const response = await apiFetch<{ data: MemberDetail }>(
          `/api/v1/members/${uuid}`
        );
        setMember(response.data);
        setError(null);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Member not found.");
      } finally {
        setIsLoading(false);
      }
    };

    if (user) {
      loadMember();
    }
  }, [uuid, user]);

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
          You must sign in to view this member. <a className="underline" href="/login">Go to login</a>.
        </p>
      </main>
    );
  }

  if (error) {
    return (
      <main className="mx-auto flex w-full max-w-4xl flex-col gap-4 px-6 py-10">
        <a href="/members" className="text-sm text-slate-500 hover:underline">
          ← Back to members
        </a>
        <div className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </div>
      </main>
    );
  }

  if (!member || isLoading) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">Loading member…</p>
      </main>
    );
  }

  return (
    <main className="mx-auto flex w-full max-w-4xl flex-col gap-8 px-6 py-10">
      <header className="space-y-2">
        <a href="/members" className="text-sm text-slate-500 hover:underline">
          ← Back to members
        </a>
        <h1 className="text-2xl font-semibold text-slate-900">
          {member.first_name} {member.last_name}
        </h1>
        <p className="text-sm text-slate-600">
          Status: {member.membership_status}
        </p>
      </header>

      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-slate-800">Profile</h2>
        <dl className="grid gap-2 text-sm text-slate-700">
          <div>
            <dt className="font-medium text-slate-600">Preferred Name</dt>
            <dd>{member.preferred_name ?? "—"}</dd>
          </div>
          <div>
            <dt className="font-medium text-slate-600">Primary Contact</dt>
            <dd>
              {member.contacts?.find((contact) => contact.is_primary)?.value ??
                "—"}
            </dd>
          </div>
        </dl>
      </section>

      <MemberAuditTimeline memberUuid={uuid} />
    </main>
  );
}
