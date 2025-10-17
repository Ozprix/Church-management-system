"use client";

import { useAuth } from "@/providers/auth-provider";
import { apiFetch } from "@/lib/api";
import type { FamilyDetail } from "@/types/member";
import { useQuery } from "@tanstack/react-query";
import { format, parseISO } from "date-fns";
import { useRouter } from "next/navigation";

interface FamilyPageProps {
  params: {
    id: string;
  };
}

export default function FamilyDetailPage({ params }: FamilyPageProps) {
  const { user, loading } = useAuth();
  const router = useRouter();
  const familyId = params.id;

  const familyQuery = useQuery<{ data: FamilyDetail }>({
    queryKey: ["family", familyId],
    queryFn: async () => apiFetch<{ data: FamilyDetail }>(`/api/v1/families/${familyId}`),
    enabled: !!user,
    staleTime: 60_000,
  });

  if (loading || familyQuery.isLoading) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">Loading family…</p>
      </main>
    );
  }

  if (!user) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">
          You must sign in to view this family. <a className="underline" href="/login">Go to login</a>.
        </p>
      </main>
    );
  }

  if (familyQuery.isError || !familyQuery.data) {
    return (
      <main className="mx-auto flex w-full max-w-4xl flex-col gap-4 px-6 py-10">
        <button
          onClick={() => router.back()}
          className="self-start text-sm text-slate-500 hover:underline"
        >
          ← Back
        </button>
        <div className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          Unable to load family details.
        </div>
      </main>
    );
  }

  const family = familyQuery.data.data;
  const createdLabel = family.created_at
    ? format(parseISO(family.created_at), "MMM d, yyyy")
    : "—";

  return (
    <main className="mx-auto flex w-full max-w-4xl flex-col gap-8 px-6 py-10">
      <header className="space-y-2">
        <button
          onClick={() => router.back()}
          className="text-sm text-slate-500 hover:underline"
        >
          ← Back
        </button>
        <h1 className="text-2xl font-semibold text-slate-900">{family.family_name}</h1>
        <p className="text-sm text-slate-600">Created {createdLabel}</p>
      </header>

      <section className="grid gap-4 rounded border border-slate-200 p-4 md:grid-cols-2">
        <div>
          <h2 className="text-base font-semibold text-slate-800">Household info</h2>
          <dl className="mt-2 space-y-1 text-sm text-slate-600">
            <div className="grid grid-cols-[120px_1fr] gap-2">
              <dt className="font-medium text-slate-500">Members</dt>
              <dd>{family.members_count ?? family.members?.length ?? 0}</dd>
            </div>
            {family.address && (
              <>
                <div className="grid grid-cols-[120px_1fr] gap-2">
                  <dt className="font-medium text-slate-500">Address</dt>
                  <dd>{family.address.line1 ?? "—"}</dd>
                </div>
                <div className="grid grid-cols-[120px_1fr] gap-2">
                  <dt className="font-medium text-slate-500">City</dt>
                  <dd>{family.address.city ?? "—"}</dd>
                </div>
                <div className="grid grid-cols-[120px_1fr] gap-2">
                  <dt className="font-medium text-slate-500">State</dt>
                  <dd>{family.address.state ?? "—"}</dd>
                </div>
                <div className="grid grid-cols-[120px_1fr] gap-2">
                  <dt className="font-medium text-slate-500">Postal code</dt>
                  <dd>{family.address.postal_code ?? "—"}</dd>
                </div>
              </>
            )}
          </dl>
        </div>
        <div>
          <h2 className="text-base font-semibold text-slate-800">Notes</h2>
          <p className="mt-2 text-sm text-slate-600">{family.notes ?? "No notes recorded."}</p>
        </div>
      </section>

      <section className="rounded border border-slate-200 p-4">
        <h2 className="text-base font-semibold text-slate-800">Family members</h2>
        <div className="mt-3 overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-2">Name</th>
                <th className="px-4 py-2">Status</th>
                <th className="px-4 py-2">Relationship</th>
                <th className="px-4 py-2">Primary?</th>
                <th className="px-4 py-2">Emergency?</th>
              </tr>
            </thead>
            <tbody>
              {family.members?.map((member) => (
                <tr key={member.id} className="border-t border-slate-100">
                  <td className="px-4 py-2">
                    <a
                      href={`/members/${member.uuid}`}
                      className="text-slate-800 hover:underline"
                    >
                      {member.first_name} {member.last_name}
                    </a>
                  </td>
                  <td className="px-4 py-2 text-slate-600">
                    {member.membership_status ?? "—"}
                  </td>
                  <td className="px-4 py-2 text-slate-600">
                    {member.pivot?.relationship ?? "—"}
                  </td>
                  <td className="px-4 py-2 text-slate-600">
                    {member.pivot?.is_primary_contact ? "Yes" : "No"}
                  </td>
                  <td className="px-4 py-2 text-slate-600">
                    {member.pivot?.is_emergency_contact ? "Yes" : "No"}
                  </td>
                </tr>
              ))}
              {!family.members?.length && (
                <tr>
                  <td className="px-4 py-3 text-center text-slate-500" colSpan={5}>
                    No members linked to this household yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <h3 className="font-semibold">Primary contact quick tip</h3>
        <p className="mt-1">
          To assign a primary contact, update the family via the API or upcoming family management UI.
          Choose one member and set <code>is_primary_contact</code> to <code>true</code> on the family_members pivot.
        </p>
      </section>
    </main>
  );
}
