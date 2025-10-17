import type { MemberSummary, PaginatedResponse } from "@/types/member";

interface MemberTableProps {
  data: PaginatedResponse<MemberSummary> | null;
  isLoading?: boolean;
  error?: string | null;
  onRefresh: () => void;
  onRestore: (uuid: string) => Promise<void>;
}

export function MemberTable({
  data,
  isLoading = false,
  error,
  onRefresh,
  onRestore,
}: MemberTableProps) {
  const total = data?.meta.total ?? 0;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-slate-800">
          Members ({total})
        </h2>
        <button
          onClick={onRefresh}
          disabled={isLoading}
          className="rounded border px-3 py-1 text-sm hover:bg-slate-100 disabled:opacity-50"
        >
          {isLoading ? "Refreshing…" : "Refresh"}
        </button>
      </div>
      {error && (
        <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {error}
        </div>
      )}
      <div className="overflow-x-auto rounded border border-slate-200">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-2 font-medium text-slate-500">Name</th>
              <th className="px-4 py-2 font-medium text-slate-500">Status</th>
              <th className="px-4 py-2 font-medium text-slate-500">
                Preferred Contact
              </th>
              <th className="px-4 py-2 font-medium text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody>
            {(data?.data ?? []).map((member) => (
              <tr
                key={member.uuid}
                className="border-t border-slate-100 hover:bg-slate-50"
              >
                <td className="px-4 py-2">
                  <a
                    href={`/members/${member.uuid}`}
                    className="text-slate-800 hover:underline"
                  >
                    {member.first_name} {member.last_name}
                  </a>
                </td>
                <td className="px-4 py-2 text-slate-600">
                  {member.membership_status}
                </td>
                <td className="px-4 py-2 text-slate-600">
                  {member.preferred_contact?.value ?? "—"}
                </td>
                <td className="px-4 py-2 text-slate-600">
                  <button
                    onClick={() => onRestore(member.uuid)}
                    className="rounded border border-slate-300 px-3 py-1 text-xs uppercase tracking-wide text-slate-600 transition hover:border-slate-400 hover:text-slate-700"
                  >
                    Restore
                  </button>
                </td>
              </tr>
            ))}
            {!data?.data?.length && (
              <tr>
                <td colSpan={4} className="px-4 py-3 text-center text-sm text-slate-500">
                  {isLoading ? "Loading…" : "No members found."}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
