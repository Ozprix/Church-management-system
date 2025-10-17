'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useFamily } from '@/hooks/use-family';
import { FamilyForm } from '@/components/families/family-form';

export default function FamilyDetailPage() {
  const params = useParams<{ id: string }>();
  const numericId = params?.id ? Number(params.id) : undefined;
  const { data: family, isLoading } = useFamily(numericId);

  if (isLoading) {
    return <p className="text-slate-500">Loading family…</p>;
  }

  if (!family) {
    return (
      <div className="space-y-4">
        <p className="text-slate-500">Family not found.</p>
        <Link href="/members" className="text-emerald-600 hover:text-emerald-700">
          Back to members
        </Link>
      </div>
    );
  }

  return (
    <section className="space-y-8">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">{family.family_name}</h2>
          <p className="text-sm text-slate-500">Update household address, notes, and member relationships.</p>
        </div>
        <Link href="/members" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to members
        </Link>
      </div>

      <FamilyForm family={family} mode="edit" />
    </section>
  );
}
