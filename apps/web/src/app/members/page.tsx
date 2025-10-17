"use client";

import { useEffect, useState } from "react";
import { MemberTable } from "./_components/member-table";
import { MemberImportUploader } from "./_components/member-import-uploader";
import { MemberImportHistory } from "./_components/member-import-history";
import { apiFetch } from "@/lib/api";
import type { MemberSummary, PaginatedResponse } from "@/types/member";
import { useAuth } from "@/providers/auth-provider";

export default function MembersPage() {
  const { user, loading } = useAuth();
  const [members, setMembers] = useState<PaginatedResponse<MemberSummary> | null>(
    null
  );
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const loadMembers = async () => {
    setIsLoading(true);
    try {
      const data = await apiFetch<PaginatedResponse<MemberSummary>>(
        "/api/v1/members?per_page=25"
      );
      setMembers(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to load members.");
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (user) {
      loadMembers();
    }
  }, [user]);

  const handleRestore = async (uuid: string) => {
    try {
      await apiFetch(`/api/v1/members/${uuid}/restore`, { method: "POST" });
      await loadMembers();
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Unable to restore member right now."
      );
    }
  };

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
          You must sign in to view members. <a className="underline" href="/login">Go to login</a>.
        </p>
      </main>
    );
  }

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-6 py-10">
      <header className="flex flex-col gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Members</h1>
          <p className="text-sm text-slate-600">
            Manage member roster, restore archived records, and track CSV imports.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <a
            href="/members/analytics"
            className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
          >
            Member analytics
          </a>
          <a
            href="/families/analytics"
            className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
          >
            Family analytics
          </a>
          <a
            href="/finance/analytics"
            className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
          >
            Finance analytics
          </a>
        </div>
      </header>

      <MemberImportUploader />

      <section className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <MemberTable
          data={members}
          isLoading={isLoading}
          error={error}
          onRefresh={loadMembers}
          onRestore={handleRestore}
        />
        <div className="space-y-4">
          <MemberImportHistory />
        </div>
      </section>
    </main>
  );
}
