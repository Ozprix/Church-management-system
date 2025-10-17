'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { Button, Card, Input, Label, Select, useToast, classNames } from '@church/ui';
import {
  createFamily,
  FamilyDetail,
  FamilyMemberPayload,
  FamilyPayload,
  updateFamily,
} from '@/lib/api/families';
import { useTenantId } from '@/lib/tenant';
import { useMembers } from '@/hooks/use-members';
import { ApiError } from '@/lib/api/http';

interface FamilyFormProps {
  family?: FamilyDetail;
  mode: 'create' | 'edit';
}

interface EditableFamilyMember {
  member_id: number;
  relationship?: string;
  is_primary_contact?: boolean;
  is_emergency_contact?: boolean;
}

export function FamilyForm({ family, mode }: FamilyFormProps) {
  const tenantId = useTenantId();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  const { data: membersQuery } = useMembers({ per_page: 100 });
  const members = useMemo(() => membersQuery?.data ?? [], [membersQuery?.data]);

  const [familyName, setFamilyName] = useState(family?.family_name ?? '');
  const [notes, setNotes] = useState((family?.notes as string) ?? '');
  const [addressLine1, setAddressLine1] = useState<string>((family?.address?.['line1'] as string) ?? '');
  const [addressLine2, setAddressLine2] = useState<string>((family?.address?.['line2'] as string) ?? '');
  const [city, setCity] = useState<string>((family?.address?.['city'] as string) ?? '');
  const [state, setState] = useState<string>((family?.address?.['state'] as string) ?? '');
  const [postalCode, setPostalCode] = useState<string>((family?.address?.['postal_code'] as string) ?? '');
  const [country, setCountry] = useState<string>((family?.address?.['country'] as string) ?? '');

  const [assignedMembers, setAssignedMembers] = useState<EditableFamilyMember[]>(
    family?.members?.map((member) => ({
      member_id: member.id,
      relationship: member.pivot?.relationship ?? '',
      is_primary_contact: Boolean(member.pivot?.is_primary_contact),
      is_emergency_contact: Boolean(member.pivot?.is_emergency_contact),
    })) ?? []
  );
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    if (!family) return;
    setFamilyName(family.family_name ?? '');
    setNotes((family.notes as string) ?? '');
    setAddressLine1((family.address?.['line1'] as string) ?? '');
    setAddressLine2((family.address?.['line2'] as string) ?? '');
    setCity((family.address?.['city'] as string) ?? '');
    setState((family.address?.['state'] as string) ?? '');
    setPostalCode((family.address?.['postal_code'] as string) ?? '');
    setCountry((family.address?.['country'] as string) ?? '');
    setAssignedMembers(
      family.members?.map((member) => ({
        member_id: member.id,
        relationship: member.pivot?.relationship ?? '',
        is_primary_contact: Boolean(member.pivot?.is_primary_contact),
        is_emergency_contact: Boolean(member.pivot?.is_emergency_contact),
      })) ?? []
    );
  }, [family]);

  const selectedMemberIds = useMemo(
    () => assignedMembers.map((member) => member.member_id),
    [assignedMembers]
  );

  const availableMembers = useMemo(
    () => members.filter((member) => !selectedMemberIds.includes(member.id)),
    [members, selectedMemberIds]
  );

  const getFieldErrors = (path: string) =>
    Object.entries(fieldErrors)
      .filter(([key]) => key === path || key.startsWith(`${path}.`))
      .flatMap(([, messages]) => messages);

  const mutation = useMutation({
    mutationFn: async (payload: FamilyPayload) => {
      if (!tenantId) {
        throw new Error('Missing tenant id');
      }
      if (mode === 'create') {
        return createFamily(tenantId, payload);
      }
      if (!family) {
        throw new Error('Missing family for update');
      }
      return updateFamily(tenantId, family.id, payload);
    },
    onSuccess: (data) => {
      if (!tenantId) return;
      setFieldErrors({});
      queryClient.invalidateQueries({ queryKey: ['families', tenantId] });
      if (mode === 'edit' && family) {
        queryClient.invalidateQueries({ queryKey: ['family', tenantId, family.id] });
      }
      pushToast({
        title: mode === 'create' ? 'Family created' : 'Family updated',
        variant: 'success',
      });
      if (mode === 'create') {
        router.push(`/families/${data.id}`);
      }
    },
    onError: (error: unknown) => {
      if (error instanceof ApiError) {
        if (error.status === 422 && error.errors) {
          setFieldErrors(error.errors);
          const aggregated = Object.values(error.errors).flat();
          pushToast({
            title: 'Please fix the highlighted issues',
            description: aggregated[0],
            variant: 'error',
          });
          return;
        }

        pushToast({ title: 'Error', description: error.message, variant: 'error' });
        return;
      }

      const message = error instanceof Error ? error.message : 'Unable to save family';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    },
  });

  const handleAddMember = (memberId: number) => {
    if (!memberId) return;
    const selected = members.find((member) => member.id === memberId);
    if (!selected) return;

    setAssignedMembers((prev) => [
      ...prev,
      {
        member_id: memberId,
        relationship: 'member',
        is_primary_contact: prev.length === 0,
        is_emergency_contact: false,
      },
    ]);
  };

  const handleMemberChange = (
    memberId: number,
    key: keyof EditableFamilyMember,
    value: string | boolean
  ) => {
    setAssignedMembers((prev) =>
      prev.map((member) =>
        member.member_id === memberId ? { ...member, [key]: value } : member
      )
    );
  };

  const handleRemoveMember = (memberId: number) => {
    setAssignedMembers((prev) => prev.filter((member) => member.member_id !== memberId));
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    setFieldErrors({});

    if (!familyName.trim()) {
      const message = 'Family name is required';
      setFieldErrors({ family_name: [message] });
      pushToast({ title: message, variant: 'error' });
      return;
    }

    const payload: FamilyPayload = {
      family_name: familyName.trim(),
      notes: notes.trim() || null,
      address: {
        line1: addressLine1.trim() || undefined,
        line2: addressLine2.trim() || undefined,
        city: city.trim() || undefined,
        state: state.trim() || undefined,
        postal_code: postalCode.trim() || undefined,
        country: country.trim() || undefined,
      },
      members: assignedMembers.map<FamilyMemberPayload>((member) => ({
        member_id: member.member_id,
        relationship: member.relationship ?? undefined,
        is_primary_contact: Boolean(member.is_primary_contact),
        is_emergency_contact: Boolean(member.is_emergency_contact),
      })),
    };

    mutation.mutate(payload);
  };

  const nameErrors = getFieldErrors('family_name');
  const notesErrors = getFieldErrors('notes');
  const addressLine1Errors = getFieldErrors('address.line1');
  const addressLine2Errors = getFieldErrors('address.line2');
  const cityErrors = getFieldErrors('address.city');
  const stateErrors = getFieldErrors('address.state');
  const postalErrors = getFieldErrors('address.postal_code');
  const countryErrors = getFieldErrors('address.country');

  return (
    <Card className="space-y-6">
      <form className="space-y-6" onSubmit={handleSubmit}>
        <section className="grid gap-4 md:grid-cols-2">
          <div>
            <Label htmlFor="family-name" required>
              Family name
            </Label>
            <Input
              id="family-name"
              value={familyName}
              onChange={(event) => setFamilyName(event.target.value)}
              required
              aria-invalid={nameErrors.length > 0}
            />
            <FieldError errors={nameErrors} />
          </div>
          <div>
            <Label htmlFor="notes">Notes</Label>
            <textarea
              id="notes"
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
              rows={3}
              aria-invalid={notesErrors.length > 0}
              className={classNames(
                'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200',
                notesErrors.length > 0 &&
                  'border-rose-400 focus:border-rose-500 focus:ring-rose-100 focus:ring-2 focus:ring-offset-0'
              )}
            />
            <FieldError errors={notesErrors} />
          </div>
          <div>
            <Label htmlFor="address-line1">Address line 1</Label>
            <Input
              id="address-line1"
              value={addressLine1}
              onChange={(event) => setAddressLine1(event.target.value)}
              aria-invalid={addressLine1Errors.length > 0}
            />
            <FieldError errors={addressLine1Errors} />
          </div>
          <div>
            <Label htmlFor="address-line2">Address line 2</Label>
            <Input
              id="address-line2"
              value={addressLine2}
              onChange={(event) => setAddressLine2(event.target.value)}
              aria-invalid={addressLine2Errors.length > 0}
            />
            <FieldError errors={addressLine2Errors} />
          </div>
          <div>
            <Label htmlFor="city">City</Label>
            <Input
              id="city"
              value={city}
              onChange={(event) => setCity(event.target.value)}
              aria-invalid={cityErrors.length > 0}
            />
            <FieldError errors={cityErrors} />
          </div>
          <div>
            <Label htmlFor="state">State/Province</Label>
            <Input
              id="state"
              value={state}
              onChange={(event) => setState(event.target.value)}
              aria-invalid={stateErrors.length > 0}
            />
            <FieldError errors={stateErrors} />
          </div>
          <div>
            <Label htmlFor="postal">Postal code</Label>
            <Input
              id="postal"
              value={postalCode}
              onChange={(event) => setPostalCode(event.target.value)}
              aria-invalid={postalErrors.length > 0}
            />
            <FieldError errors={postalErrors} />
          </div>
          <div>
            <Label htmlFor="country">Country</Label>
            <Input
              id="country"
              value={country}
              onChange={(event) => setCountry(event.target.value)}
              aria-invalid={countryErrors.length > 0}
            />
            <FieldError errors={countryErrors} />
          </div>
        </section>

        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Household members</h3>
            {availableMembers.length > 0 && (
              <Select
                defaultValue=""
                onChange={(event) => {
                  const id = Number(event.target.value);
                  if (id) {
                    handleAddMember(id);
                    event.target.value = '';
                  }
                }}
              >
                <option value="">Add existing member…</option>
                {availableMembers.map((member) => (
                  <option key={member.id} value={member.id}>
                    {member.first_name} {member.last_name}
                  </option>
                ))}
              </Select>
            )}
          </div>
          <div className="space-y-4">
            {assignedMembers.length === 0 && (
              <p className="text-sm text-slate-500">No members assigned to this family.</p>
            )}
            {assignedMembers.map((member, index) => {
              const relationshipErrors = getFieldErrors(`members.${index}.relationship`);

              return (
                <div
                  key={member.member_id}
                  className="grid gap-4 rounded-lg border border-slate-200 p-4 md:grid-cols-4"
                >
                  <div className="md:col-span-2">
                    <Label>Member</Label>
                    <p className="text-sm font-medium text-slate-900">
                      {members.find((candidate) => candidate.id === member.member_id)?.first_name}{' '}
                      {members.find((candidate) => candidate.id === member.member_id)?.last_name}
                    </p>
                  </div>
                  <div>
                    <Label htmlFor={`member-relationship-${member.member_id}`}>Relationship</Label>
                    <Input
                      id={`member-relationship-${member.member_id}`}
                      value={member.relationship ?? ''}
                      onChange={(event) =>
                        handleMemberChange(member.member_id, 'relationship', event.target.value)
                      }
                      aria-invalid={relationshipErrors.length > 0}
                    />
                    <FieldError errors={relationshipErrors} />
                  </div>
                  <div className="space-y-3">
                    <div className="flex items-center gap-2">
                      <input
                        id={`member-primary-${member.member_id}`}
                        type="checkbox"
                        checked={Boolean(member.is_primary_contact)}
                        onChange={(event) =>
                          handleMemberChange(
                            member.member_id,
                            'is_primary_contact',
                            event.target.checked
                          )
                        }
                      />
                      <Label htmlFor={`member-primary-${member.member_id}`} className="!mb-0 text-sm">
                        Primary contact
                      </Label>
                    </div>
                    <div className="flex items-center gap-2">
                      <input
                        id={`member-emergency-${member.member_id}`}
                        type="checkbox"
                        checked={Boolean(member.is_emergency_contact)}
                        onChange={(event) =>
                          handleMemberChange(
                            member.member_id,
                            'is_emergency_contact',
                            event.target.checked
                          )
                        }
                      />
                      <Label htmlFor={`member-emergency-${member.member_id}`} className="!mb-0 text-sm">
                        Emergency contact
                      </Label>
                    </div>
                    <button
                      type="button"
                      className="text-sm text-emerald-600 hover:text-emerald-700"
                      onClick={() => handleRemoveMember(member.member_id)}
                    >
                      Remove
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        <div className="flex justify-end">
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending
              ? mode === 'create'
                ? 'Creating…'
                : 'Saving…'
              : mode === 'create'
                ? 'Create family'
                : 'Save changes'}
          </Button>
        </div>
      </form>
    </Card>
  );
}

function FieldError({ errors }: { errors?: string[] }) {
  if (!errors || errors.length === 0) {
    return null;
  }

  return <p className="mt-1 text-xs text-rose-600">{errors[0]}</p>;
}
