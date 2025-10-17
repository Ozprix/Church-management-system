'use client';

import Link from 'next/link';
import { MemberForm } from '@/components/members/member-form';

export default function NewMemberPage() {
  return (
    <section className="space-y-8">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">New Member</h2>
          <p className="text-sm text-slate-500">Create a new member profile for this tenant.</p>
        </div>
        <Link href="/members" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to members
        </Link>
      </div>

      <MemberForm mode="create" />
    </section>
  );
}
