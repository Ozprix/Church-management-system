"use client";

import { useState } from "react";
import { apiFormFetch } from "@/lib/api";
import type { MemberImportSummary } from "@/types/member";

export function MemberImportUploader() {
  const [file, setFile] = useState<File | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!file) {
      setMessage("Select a CSV file to upload.");
      return;
    }

    const formData = new FormData();
    formData.append("file", file);

    setIsSubmitting(true);
    setMessage(null);

    try {
      const response = await apiFormFetch<MemberImportSummary>(
        "/api/v1/member-imports",
        formData
      );

      setMessage(
        `Import queued successfully. Track status for batch #${response.id}.`
      );
      setFile(null);
    } catch (error) {
      setMessage(
        error instanceof Error ? error.message : "Import failed unexpectedly."
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-3 rounded border p-4">
      <div>
        <h3 className="text-base font-semibold text-slate-800">
          Upload Member CSV
        </h3>
        <p className="text-sm text-slate-500">
          Columns supported: <code>first_name</code>, <code>last_name</code>,
          optional <code>membership_status</code>, <code>email</code>.
        </p>
      </div>
      <input
        type="file"
        accept=".csv,text/csv"
        onChange={(event) => setFile(event.target.files?.[0] ?? null)}
        className="block w-full rounded border border-slate-300 px-3 py-2 text-sm"
      />
      <button
        type="submit"
        disabled={isSubmitting}
        className="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
      >
        {isSubmitting ? "Uploading…" : "Queue Import"}
      </button>
      {message && (
        <p className="text-sm text-slate-600" role="status">
          {message}
        </p>
      )}
    </form>
  );
}
