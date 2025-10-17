'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Button, Input, Label, Select, Card, useToast, classNames, FileUploadField } from '@church/ui';
import { ApiError } from '@/lib/api/http';
import { MemberDetail, MemberPayload, updateMember, createMember } from '@/lib/api/members';
import {
  CustomFieldFileMetadata,
  uploadMemberCustomFieldFile,
} from '@/lib/api/custom-fields';
import { useTenantId } from '@/lib/tenant';
import { useFamilies } from '@/hooks/use-families';
import { useMemberCustomFields } from '@/hooks/use-member-custom-fields';

interface MemberFormProps {
  member?: MemberDetail;
  mode: 'create' | 'edit';
}

interface EditableContact {
  id?: number;
  type: string;
  value: string;
  label?: string | null;
  is_primary?: boolean;
  is_emergency?: boolean;
  communication_preference?: string | null;
}

interface EditableFamily {
  family_id: number;
  relationship?: string;
  is_primary_contact?: boolean;
  is_emergency_contact?: boolean;
}

type FileMetadata = CustomFieldFileMetadata;

interface EditableCustomValue {
  field_id: number;
  label: string;
  data_type: string;
  value: string;
  options?: string[];
  file?: FileMetadata | null;
  accept?: string;
}

type CustomValueChangeHandler = (fieldId: number, value: string) => void;

function formatCustomValueForPayload(field: EditableCustomValue): unknown {
  if (field.data_type === 'file' || field.data_type === 'signature') {
    return field.file ?? null;
  }

  const trimmed = field.value.trim();
  if (!trimmed) {
    return null;
  }

  switch (field.data_type) {
    case 'number':
      return Number(trimmed);
    case 'boolean':
      return trimmed === 'true' || trimmed === '1' || trimmed.toLowerCase() === 'yes';
    case 'multi_select':
      return trimmed
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
    default:
      return trimmed;
  }
}

function renderCustomFieldInput(
  field: EditableCustomValue,
  onChange: CustomValueChangeHandler,
  onFileUpload: (fieldId: number, file: File) => void,
  onFileRemove: (fieldId: number) => void,
  isUploading: boolean,
  hasError: boolean
) {
  const inputId = `custom-${field.field_id}`;
  const handleChange = (value: string) => onChange(field.field_id, value);

  switch (field.data_type) {
    case 'file':
    case 'signature': {
      const accept = field.accept;
      return (
        <FileUploadField
          label={undefined}
          fileName={field.file?.name ?? null}
          fileSize={field.file?.size ?? null}
          downloadUrl={field.file?.url ?? null}
          accept={accept}
          uploading={isUploading}
          error={hasError ? 'Please upload a valid file' : null}
          buttonLabel={field.data_type === 'signature' ? 'Capture signature' : 'Upload file'}
          onSelectFile={(file) => onFileUpload(field.field_id, file)}
          onRemove={() => onFileRemove(field.field_id)}
        />
      );
    }
    case 'date':
      return (
        <Input
          id={inputId}
          type="date"
          value={field.value}
          onChange={(event) => handleChange(event.target.value)}
          aria-invalid={hasError}
        />
      );
    case 'boolean':
      return (
        <Select
          id={inputId}
          value={field.value}
          onChange={(event) => handleChange(event.target.value)}
          aria-invalid={hasError}
        >
          <option value="">Select…</option>
          <option value="true">Yes</option>
          <option value="false">No</option>
        </Select>
      );
    case 'select':
      if (field.options?.length) {
        return (
          <Select
            id={inputId}
            value={field.value}
            onChange={(event) => handleChange(event.target.value)}
            aria-invalid={hasError}
          >
            <option value="">Select…</option>
            {field.options.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </Select>
        );
      }
      return (
        <Input
          id={inputId}
          value={field.value}
          onChange={(event) => handleChange(event.target.value)}
          aria-invalid={hasError}
        />
      );
    case 'multi_select':
      if (field.options?.length) {
        const selected = field.value
          ? field.value.split(',').map((item) => item.trim()).filter(Boolean)
          : [];
        return (
          <Select
            id={inputId}
            multiple
            value={selected}
            onChange={(event) => {
              const values = Array.from(event.target.selectedOptions).map((option) => option.value);
              handleChange(values.join(','));
            }}
            aria-invalid={hasError}
          >
            {field.options.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </Select>
        );
      }
      return (
        <Input
          id={inputId}
          placeholder="Separate values with commas"
          value={field.value}
          onChange={(event) => handleChange(event.target.value)}
          aria-invalid={hasError}
        />
      );
    default:
      return (
        <Input
          id={inputId}
          value={field.value}
          onChange={(event) => handleChange(event.target.value)}
          aria-invalid={hasError}
        />
      );
  }
}

export function MemberForm({ member, mode }: MemberFormProps) {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const router = useRouter();
  const { pushToast } = useToast();
  const { data: familiesResponse } = useFamilies({ per_page: 100 });
  const families = familiesResponse?.data ?? [];
  const { data: customFields = [] } = useMemberCustomFields();

  const [firstName, setFirstName] = useState(member?.first_name ?? '');
  const [lastName, setLastName] = useState(member?.last_name ?? '');
  const [preferredName, setPreferredName] = useState(member?.preferred_name ?? '');
  const [status, setStatus] = useState(member?.membership_status ?? 'prospect');
  const [stage, setStage] = useState(member?.membership_stage ?? '');
  const [notes, setNotes] = useState(member?.notes ?? '');
  const [dob, setDob] = useState(member?.dob ?? '');

  const [contacts, setContacts] = useState<EditableContact[]>(() =>
    member?.contacts?.map((contact) => ({ ...contact })) ?? [
      {
        type: 'email',
        value: '',
        label: 'Primary email',
        is_primary: true,
        is_emergency: false,
      },
    ]
  );
  const [assignedFamilies, setAssignedFamilies] = useState<EditableFamily[]>(
    member?.families?.map((family) => ({
      family_id: family.id,
      relationship: family.pivot?.relationship ?? '',
      is_primary_contact: Boolean(family.pivot?.is_primary_contact),
      is_emergency_contact: Boolean(family.pivot?.is_emergency_contact),
    })) ?? []
  );
  const [formErrors, setFormErrors] = useState<string[]>([]);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const customValues = useMemo(() => {
    const existing = member?.custom_values ?? [];
    return customFields.map<EditableCustomValue>((field) => {
      const current = existing.find((value) => value.field_id === field.id);
      let value = '';
      let fileMetadata: FileMetadata | null = null;

      if (field.data_type === 'file' || field.data_type === 'signature') {
        if (current?.value && typeof current.value === 'object') {
          fileMetadata = current.value as FileMetadata;
        }
      } else if (typeof current?.value === 'string') {
        value = current.value;
      } else if (Array.isArray(current?.value)) {
        value = current?.value.map((item) => String(item)).join(',');
      } else if (current?.value !== null && current?.value !== undefined) {
        value = String(current.value);
      }

      const options = Array.isArray(field.config?.options)
        ? field.config.options.map((option: unknown) => String(option))
        : undefined;

      const allowedMimeTypes = Array.isArray(field.config?.allowed_mimetypes)
        ? field.config.allowed_mimetypes.map((type: unknown) => String(type))
        : [];
      const allowedExtensions = Array.isArray(field.config?.allowed_extensions)
        ? field.config.allowed_extensions.map((ext: unknown) => {
            const normalized = String(ext);
            return normalized.startsWith('.') ? normalized : `.${normalized}`;
          })
        : [];

      let accept = allowedMimeTypes.length
        ? allowedMimeTypes.join(',')
        : allowedExtensions.length
        ? allowedExtensions.join(',')
        : undefined;

      if (!accept && field.data_type === 'signature') {
        accept = 'image/png,image/jpeg';
      }

      return {
        field_id: field.id,
        label: field.name,
        data_type: field.data_type,
        value,
        options,
        file: fileMetadata,
        accept,
      };
    });
  }, [customFields, member?.custom_values]);

  const [customValueState, setCustomValueState] = useState(customValues);
  const [uploadingFiles, setUploadingFiles] = useState<Record<number, boolean>>({});

  useEffect(() => {
    setCustomValueState(customValues);
  }, [customValues]);

  const getFieldErrors = (path: string) =>
    Object.entries(fieldErrors)
      .filter(([key]) => key === path || key.startsWith(`${path}.`))
      .flatMap(([, messages]) => messages);

  useEffect(() => {
    if (!member) {
      return;
    }
    setFirstName(member.first_name ?? '');
    setLastName(member.last_name ?? '');
    setPreferredName(member.preferred_name ?? '');
    setStatus(member.membership_status ?? 'prospect');
    setStage(member.membership_stage ?? '');
    setNotes(member.notes ?? '');
    setDob(member.dob ?? '');
    setContacts(member.contacts?.map((contact) => ({ ...contact })) ?? []);
    setAssignedFamilies(
      member.families?.map((family) => ({
        family_id: family.id,
        relationship: family.pivot?.relationship ?? '',
        is_primary_contact: Boolean(family.pivot?.is_primary_contact),
        is_emergency_contact: Boolean(family.pivot?.is_emergency_contact),
      })) ?? []
    );
  }, [member]);

  const mutation = useMutation({
    mutationFn: async (payload: MemberPayload) => {
      if (!tenantId) {
        throw new Error('Missing tenant id');
      }
      if (mode === 'create') {
        return createMember(tenantId, payload);
      }
      if (!member) {
        throw new Error('Missing member for update');
      }
      return updateMember(tenantId, member.uuid, payload);
    },
    onSuccess: (data) => {
      if (!tenantId) return;
      setFieldErrors({});
      setFormErrors([]);
      queryClient.invalidateQueries({ queryKey: ['members', tenantId] });
      if (mode === 'edit' && member) {
        queryClient.invalidateQueries({ queryKey: ['member', tenantId, member.uuid] });
      }
      pushToast({
        title: mode === 'create' ? 'Member created' : 'Changes saved',
        variant: 'success',
      });
      if (mode === 'create') {
        router.push(`/members/${data.uuid}`);
      }
    },
    onError: (error: unknown) => {
      if (error instanceof ApiError) {
        if (error.status === 422 && error.errors) {
          setFieldErrors(error.errors);
          const aggregated = Object.values(error.errors).flat();
          setFormErrors(aggregated);
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

      const message = error instanceof Error ? error.message : 'Unable to save member';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    },
  });

  const handleAddContact = () => {
    setContacts((prev) => [
      ...prev,
      {
        type: 'email',
        value: '',
        label: '',
        is_primary: prev.length === 0,
        is_emergency: false,
      },
    ]);
  };

  const handleRemoveContact = (index: number) => {
    setContacts((prev) => prev.filter((_, idx) => idx !== index));
  };

  const handleContactChange = (index: number, key: keyof EditableContact, value: string | boolean) => {
    setContacts((prev) =>
      prev.map((contact, idx) => (idx === index ? { ...contact, [key]: value } : contact))
    );
  };

  const availableFamilies = families.filter(
    (family) => !assignedFamilies.some((assigned) => assigned.family_id === family.id)
  );

  const handleAddFamily = (familyId: number) => {
    if (!familyId) return;
    setAssignedFamilies((prev) => [
      ...prev,
      {
        family_id: familyId,
        relationship: 'member',
        is_primary_contact: prev.length === 0,
        is_emergency_contact: false,
      },
    ]);
  };

  const handleRemoveFamily = (familyId: number) => {
    setAssignedFamilies((prev) => prev.filter((family) => family.family_id !== familyId));
  };

  const handleFamilyChange = (
    familyId: number,
    key: keyof EditableFamily,
    value: string | boolean
  ) => {
    setAssignedFamilies((prev) =>
      prev.map((family) =>
        family.family_id === familyId ? { ...family, [key]: value } : family
      )
    );
  };

  const handleCustomValueChange = (fieldId: number, value: string) => {
    setCustomValueState((prev) =>
      prev.map((item) => (item.field_id === fieldId ? { ...item, value } : item))
    );
  };

  const handleCustomFileUpload = async (fieldId: number, file: File) => {
    if (!tenantId) {
      pushToast({ title: 'Error', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    setUploadingFiles((prev) => ({ ...prev, [fieldId]: true }));
    try {
      const metadata = await uploadMemberCustomFieldFile(tenantId, fieldId, file);
      setCustomValueState((prev) =>
        prev.map((item) =>
          item.field_id === fieldId
            ? {
                ...item,
                file: metadata,
              }
            : item
        )
      );
      pushToast({ title: 'File uploaded', variant: 'success' });
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Upload failed';
      pushToast({ title: 'Upload failed', description: message, variant: 'error' });
    } finally {
      setUploadingFiles((prev) => {
        const copy = { ...prev };
        delete copy[fieldId];
        return copy;
      });
    }
  };

  const handleCustomFileRemove = (fieldId: number) => {
    setCustomValueState((prev) =>
      prev.map((item) =>
        item.field_id === fieldId
          ? {
              ...item,
              value: '',
              file: null,
            }
          : item
      )
    );
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const errors: string[] = [];

    setFieldErrors({});

    if (!dob) {
      errors.push('Birthdate is required.');
    }

    const emailContacts = contacts.filter(
      (contact) => contact.type === 'email' && contact.value.trim().length > 0
    );
    if (emailContacts.length === 0) {
      errors.push('At least one email contact is required.');
    }

    if (errors.length > 0) {
      setFormErrors(errors);
      pushToast({
        title: 'Please fix the highlighted issues',
        description: errors[0],
        variant: 'error',
      });
      return;
    }

    setFormErrors([]);

    const normalizedContacts = contacts.map((contact) => ({
      ...contact,
      value: contact.value.trim(),
      label: contact.label?.trim() || undefined,
      is_primary: Boolean(contact.is_primary),
      is_emergency: Boolean(contact.is_emergency),
    }));

    const payload: MemberPayload = {
      first_name: firstName,
      last_name: lastName,
      preferred_name: preferredName || null,
      dob: dob || null,
      membership_status: status,
      membership_stage: stage || null,
      notes: notes || null,
      contacts: normalizedContacts,
      families: assignedFamilies,
      custom_values: customValueState.map((item) => ({
        field_id: item.field_id,
        value: formatCustomValueForPayload(item),
      })),
    };

    mutation.mutate(payload);
  };

  const firstNameErrors = getFieldErrors('first_name');
  const lastNameErrors = getFieldErrors('last_name');
  const dobErrors = getFieldErrors('dob');
  const statusErrors = getFieldErrors('membership_status');
  const stageErrors = getFieldErrors('membership_stage');
  const notesErrors = getFieldErrors('notes');

  return (
    <Card className="space-y-6">
      <form className="space-y-6" onSubmit={handleSubmit}>
        {formErrors.length > 0 && (
          <div className="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <ul className="list-disc space-y-1 pl-5">
              {formErrors.map((error) => (
                <li key={error}>{error}</li>
              ))}
            </ul>
          </div>
        )}
        <section>
          <h3 className="text-lg font-semibold text-slate-900">Profile</h3>
          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <div>
              <Label htmlFor="first-name" required>
                First name
              </Label>
              <Input
                id="first-name"
                value={firstName}
                onChange={(event) => setFirstName(event.target.value)}
                required
                aria-invalid={firstNameErrors.length > 0}
              />
              <FieldError errors={firstNameErrors} />
            </div>
            <div>
              <Label htmlFor="last-name" required>
                Last name
              </Label>
              <Input
                id="last-name"
                value={lastName}
                onChange={(event) => setLastName(event.target.value)}
                required
                aria-invalid={lastNameErrors.length > 0}
              />
              <FieldError errors={lastNameErrors} />
            </div>
            <div>
              <Label htmlFor="dob" required>
                Birthdate
              </Label>
              <Input
                id="dob"
                type="date"
                value={dob}
                onChange={(event) => setDob(event.target.value)}
                required
                aria-invalid={dobErrors.length > 0}
              />
              <FieldError errors={dobErrors} />
            </div>
            <div>
              <Label htmlFor="preferred-name">Preferred name</Label>
              <Input
                id="preferred-name"
                value={preferredName}
                onChange={(event) => setPreferredName(event.target.value)}
              />
            </div>
            <div>
              <Label htmlFor="status" required>
                Membership status
              </Label>
              <Select
                id="status"
                value={status}
                onChange={(event) => setStatus(event.target.value)}
                aria-invalid={statusErrors.length > 0}
              >
                <option value="prospect">Prospect</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="visitor">Visitor</option>
                <option value="suspended">Suspended</option>
                <option value="transferred">Transferred</option>
              </Select>
              <FieldError errors={statusErrors} />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="stage">Membership stage</Label>
              <Input
                id="stage"
                value={stage}
                onChange={(event) => setStage(event.target.value)}
                aria-invalid={stageErrors.length > 0}
              />
              <FieldError errors={stageErrors} />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="notes">Notes</Label>
              <textarea
                id="notes"
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                aria-invalid={notesErrors.length > 0}
                className={classNames(
                  'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200',
                  notesErrors.length > 0 &&
                    'border-rose-400 focus:border-rose-500 focus:ring-rose-100 focus:ring-2 focus:ring-offset-0'
                )}
                rows={4}
              />
              <FieldError errors={notesErrors} />
            </div>
          </div>
        </section>

        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Contacts</h3>
            <Button type="button" variant="secondary" onClick={handleAddContact}>
              Add contact
            </Button>
          </div>
          <div className="space-y-4">
            {contacts.length === 0 && <p className="text-sm text-slate-500">No contacts yet.</p>}
            {contacts.map((contact, index) => {
              const typeErrors = getFieldErrors(`contacts.${index}.type`);
              const valueErrors = getFieldErrors(`contacts.${index}.value`);

              return (
                <div
                  key={index}
                  className="grid gap-4 rounded-lg border border-slate-200 p-4 md:grid-cols-4"
                >
                  <div>
                    <Label htmlFor={`contact-type-${index}`}>Type</Label>
                    <Select
                      id={`contact-type-${index}`}
                      value={contact.type}
                      onChange={(event) =>
                        handleContactChange(index, 'type', event.target.value)
                      }
                      aria-invalid={typeErrors.length > 0}
                    >
                      <option value="email">Email</option>
                      <option value="mobile">Mobile</option>
                      <option value="home_phone">Home phone</option>
                      <option value="address">Address</option>
                      <option value="social">Social</option>
                      <option value="other">Other</option>
                    </Select>
                    <FieldError errors={typeErrors} />
                  </div>
                  <div className="md:col-span-2">
                    <Label htmlFor={`contact-value-${index}`} required>
                      Value
                    </Label>
                    <Input
                      id={`contact-value-${index}`}
                      value={contact.value}
                      onChange={(event) =>
                        handleContactChange(index, 'value', event.target.value)
                      }
                      required
                      aria-invalid={valueErrors.length > 0}
                    />
                    <FieldError errors={valueErrors} />
                  </div>
                  <div className="space-y-3">
                    <div className="flex items-center gap-2">
                      <input
                        id={`contact-primary-${index}`}
                        type="checkbox"
                        checked={Boolean(contact.is_primary)}
                        onChange={(event) =>
                          handleContactChange(index, 'is_primary', event.target.checked)
                        }
                      />
                      <Label htmlFor={`contact-primary-${index}`} className="!mb-0 text-sm">
                        Primary
                      </Label>
                    </div>
                    <div className="flex items-center gap-2">
                      <input
                        id={`contact-emergency-${index}`}
                        type="checkbox"
                        checked={Boolean(contact.is_emergency)}
                        onChange={(event) =>
                          handleContactChange(index, 'is_emergency', event.target.checked)
                        }
                      />
                      <Label htmlFor={`contact-emergency-${index}`} className="!mb-0 text-sm">
                        Emergency
                      </Label>
                    </div>
                    <button
                      type="button"
                      className="text-sm text-emerald-600 hover:text-emerald-700"
                      onClick={() => handleRemoveContact(index)}
                    >
                      Remove
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Families</h3>
            {availableFamilies.length > 0 && (
              <Select
                defaultValue=""
                onChange={(event) => {
                  const id = Number(event.target.value);
                  if (id) {
                    handleAddFamily(id);
                    event.target.value = '';
                  }
                }}
              >
                <option value="">Add to family…</option>
                {availableFamilies.map((family) => (
                  <option key={family.id} value={family.id}>
                    {family.family_name}
                  </option>
                ))}
              </Select>
            )}
          </div>
          <div className="space-y-4">
            {assignedFamilies.length === 0 && (
              <p className="text-sm text-slate-500">No families assigned.</p>
            )}
            {assignedFamilies.map((family, index) => {
              const relationshipErrors = getFieldErrors(`families.${index}.relationship`);

              return (
                <div
                  key={family.family_id}
                  className="grid gap-4 rounded-lg border border-slate-200 p-4 md:grid-cols-4"
                >
                  <div className="md:col-span-2 space-y-1">
                    <Label>Family</Label>
                    <Link
                      href={`/families/${family.family_id}`}
                      className="text-sm font-medium text-emerald-600 hover:text-emerald-700"
                    >
                      {families.find((f) => f.id === family.family_id)?.family_name ?? 'Unknown family'}
                    </Link>
                  </div>
                  <div>
                    <Label htmlFor={`family-relationship-${family.family_id}`}>Relationship</Label>
                    <Input
                      id={`family-relationship-${family.family_id}`}
                      value={family.relationship ?? ''}
                      onChange={(event) =>
                        handleFamilyChange(family.family_id, 'relationship', event.target.value)
                      }
                      aria-invalid={relationshipErrors.length > 0}
                    />
                    <FieldError errors={relationshipErrors} />
                  </div>
                  <div className="space-y-3">
                    <div className="flex items-center gap-2">
                      <input
                        id={`family-primary-${family.family_id}`}
                        type="checkbox"
                        checked={Boolean(family.is_primary_contact)}
                        onChange={(event) =>
                          handleFamilyChange(
                            family.family_id,
                            'is_primary_contact',
                            event.target.checked
                          )
                        }
                      />
                      <Label htmlFor={`family-primary-${family.family_id}`} className="!mb-0 text-sm">
                        Primary contact
                      </Label>
                    </div>
                    <div className="flex items-center gap-2">
                      <input
                        id={`family-emergency-${family.family_id}`}
                        type="checkbox"
                        checked={Boolean(family.is_emergency_contact)}
                        onChange={(event) =>
                          handleFamilyChange(
                            family.family_id,
                            'is_emergency_contact',
                            event.target.checked
                          )
                        }
                      />
                      <Label htmlFor={`family-emergency-${family.family_id}`} className="!mb-0 text-sm">
                        Emergency contact
                      </Label>
                    </div>
                    <button
                      type="button"
                      className="text-sm text-emerald-600 hover:text-emerald-700"
                      onClick={() => handleRemoveFamily(family.family_id)}
                    >
                      Remove
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        {customValueState.length > 0 && (
          <section className="space-y-4">
            <h3 className="text-lg font-semibold text-slate-900">Custom fields</h3>
            <div className="grid gap-4 md:grid-cols-2">
              {customValueState.map((field, index) => {
                const customErrors = getFieldErrors(`custom_values.${index}.value`);
                const uploading = Boolean(uploadingFiles[field.field_id]);

                return (
                  <div key={field.field_id}>
                    <Label htmlFor={`custom-${field.field_id}`}>{field.label}</Label>
                    {renderCustomFieldInput(
                      field,
                      handleCustomValueChange,
                      handleCustomFileUpload,
                      handleCustomFileRemove,
                      uploading,
                      customErrors.length > 0
                    )}
                    <FieldError errors={customErrors} />
                  </div>
                );
              })}
            </div>
          </section>
        )}

        <div className="flex justify-end gap-2">
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending
              ? mode === 'create'
                ? 'Creating…'
                : 'Saving…'
              : mode === 'create'
                ? 'Create member'
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
