'use client';

import Link from 'next/link';
import { FamilyForm } from '@/components/families/family-form';

export default function NewFamilyPage() {
  return (
    <section className="space-y-8">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">New Family</h2>
          <p className="text-sm text-slate-500">Create a household and assign members for coordinated care.</p>
        </div>
        <Link href="/members" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to members
        </Link>
      </div>

      <FamilyForm mode="create" />
    </section>
  );
}
