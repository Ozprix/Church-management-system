'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMember } from '@/hooks/use-member';
import { MemberForm } from '@/components/members/member-form';

export default function MemberDetailPage() {
  const params = useParams<{ uuid: string }>();
  const { data: member, isLoading } = useMember(params?.uuid);

  if (isLoading) {
    return <p className="text-slate-500">Loading member…</p>;
  }

  if (!member) {
    return (
      <div className="space-y-4">
        <p className="text-slate-500">Member not found.</p>
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
          <h2 className="text-2xl font-semibold text-slate-900">
            {member.first_name} {member.last_name}
          </h2>
          <p className="text-sm text-slate-500">Manage profile, families, and custom data.</p>
        </div>
        <Link href="/members" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to members
        </Link>
      </div>

      <MemberForm member={member} mode="edit" />
    </section>
  );
}
