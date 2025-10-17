'use client';

import { FormEvent, useMemo, useState } from 'react';
import Link from 'next/link';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Button,
  Card,
  Input,
  Label,
  Select,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableHeaderCell,
  TableRow,
  useToast,
  classNames,
} from '@church/ui';
import { ApiError } from '@/lib/api/http';
import {
  createMemberCustomField,
  updateMemberCustomField,
  deleteMemberCustomField,
  MemberCustomField,
} from '@/lib/api/custom-fields';
import { useMemberCustomFields } from '@/hooks/use-member-custom-fields';
import { useTenantId } from '@/lib/tenant';

const DATA_TYPES = [
  { value: 'text', label: 'Text' },
  { value: 'number', label: 'Number' },
  { value: 'date', label: 'Date' },
  { value: 'boolean', label: 'Yes / No' },
  { value: 'select', label: 'Single select' },
  { value: 'multi_select', label: 'Multi select' },
  { value: 'file', label: 'File upload' },
  { value: 'signature', label: 'Signature capture' },
];

export default function MemberCustomFieldsPage() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();
  const { data: fields = [], isLoading } = useMemberCustomFields();

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [dataType, setDataType] = useState('text');
  const [isRequired, setIsRequired] = useState(false);
  const [isActive, setIsActive] = useState(true);
  const [optionsInput, setOptionsInput] = useState('');
  const [extensionsInput, setExtensionsInput] = useState('');
  const [mimeTypesInput, setMimeTypesInput] = useState('');
  const [maxSize, setMaxSize] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [editingField, setEditingField] = useState<MemberCustomField | null>(null);
  const [confirmingDelete, setConfirmingDelete] = useState<MemberCustomField | null>(null);

  const mutation = useMutation({
    mutationFn: async () => {
      if (!tenantId) {
        throw new Error('Missing tenant id');
      }

      const trimmedName = name.trim();
      if (!trimmedName) {
        throw new Error('Name is required');
      }

      const config: Record<string, unknown> = {};
      if (['select', 'multi_select'].includes(dataType)) {
        const options = optionsInput
          .split(/\r?\n|,/) // allow comma or newline separated
          .map((item) => item.trim())
          .filter(Boolean);
        if (options.length) {
          config.options = options;
        }
      }

      if (['file', 'signature'].includes(dataType)) {
        const extensions = extensionsInput
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean);
        const mimeTypes = mimeTypesInput
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean);

        if (extensions.length) {
          config.allowed_extensions = extensions;
        }
        if (mimeTypes.length) {
          config.allowed_mimetypes = mimeTypes;
        }
        if (maxSize.trim()) {
          const parsed = Number(maxSize);
          if (!Number.isNaN(parsed) && parsed > 0) {
            config.max_size = parsed;
          }
        }
      }

      const payload = {
        name: trimmedName,
        slug: slug.trim() || undefined,
        data_type: dataType,
        is_required: isRequired,
        is_active: isActive,
        config: Object.keys(config).length ? config : undefined,
      };

      if (editingField) {
        return updateMemberCustomField(tenantId, editingField.id, payload);
      }

      return createMemberCustomField(tenantId, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['member-custom-fields'] });
      setErrors({});
      setName('');
      setSlug('');
      setOptionsInput('');
      setExtensionsInput('');
      setMimeTypesInput('');
      setMaxSize('');
      setIsRequired(false);
      setIsActive(true);
      setEditingField(null);
      pushToast({
        title: editingField ? 'Custom field updated' : 'Custom field created',
        variant: 'success',
      });
    },
    onError: (error: unknown) => {
      if (error instanceof ApiError && error.status === 422 && error.errors) {
        setErrors(error.errors);
        const first = Object.values(error.errors)[0]?.[0];
        pushToast({ title: 'Please fix the highlighted errors', description: first, variant: 'error' });
        return;
      }

      const message = error instanceof Error ? error.message : 'Unable to create custom field';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    },
  });

  const showOptionsField = useMemo(() => ['select', 'multi_select'].includes(dataType), [dataType]);
  const showFileConfig = useMemo(() => ['file', 'signature'].includes(dataType), [dataType]);

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    mutation.mutate();
  };

  const getError = (field: string) => errors[field]?.[0];

  const handleEdit = (field: MemberCustomField) => {
    setEditingField(field);
    setName(field.name);
    setSlug(field.slug ?? '');
    setDataType(field.data_type);
    setIsRequired(Boolean(field.is_required));
    setIsActive(field.is_active !== false);

    const config = (field.config ?? {}) as Record<string, unknown>;
    const options = Array.isArray(config['options']) ? (config['options'] as unknown[]).map(String).join('\n') : '';
    const extensions = Array.isArray(config['allowed_extensions'])
      ? (config['allowed_extensions'] as unknown[]).map(String).join(',')
      : '';
    const mimes = Array.isArray(config['allowed_mimetypes'])
      ? (config['allowed_mimetypes'] as unknown[]).map(String).join(',')
      : '';
    const maxSizeValue = typeof config['max_size'] === 'number' ? String(config['max_size']) : '';

    setOptionsInput(options);
    setExtensionsInput(extensions);
    setMimeTypesInput(mimes);
    setMaxSize(maxSizeValue);
    setErrors({});
  };

  const handleDelete = async (field: MemberCustomField) => {
    if (!tenantId) {
      pushToast({ title: 'Unable to delete', description: 'Missing tenant context', variant: 'error' });
      return;
    }

    try {
      await deleteMemberCustomField(tenantId, field.id);
      queryClient.invalidateQueries({ queryKey: ['member-custom-fields'] });
      if (editingField?.id === field.id) {
        setEditingField(null);
        setName('');
        setSlug('');
        setOptionsInput('');
        setExtensionsInput('');
        setMimeTypesInput('');
        setMaxSize('');
        setIsRequired(false);
        setIsActive(true);
      }
      pushToast({ title: 'Custom field deleted', variant: 'success' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to delete custom field';
      pushToast({ title: 'Delete failed', description: message, variant: 'error' });
    } finally {
      setConfirmingDelete(null);
    }
  };

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Member custom fields</h2>
          <p className="text-sm text-slate-500">Create and manage custom data points for member profiles.</p>
        </div>
        <Link href="/members" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to members
        </Link>
      </div>

      <Card className="space-y-6">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">
            {editingField ? 'Edit field' : 'Create new field'}
          </h3>
          {editingField ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => {
                setEditingField(null);
                setName('');
                setSlug('');
                setOptionsInput('');
                setExtensionsInput('');
                setMimeTypesInput('');
                setMaxSize('');
                setIsRequired(false);
                setIsActive(true);
                setErrors({});
              }}
            >
              Cancel edit
            </Button>
          ) : null}
        </div>
        <form className="grid gap-4 md:grid-cols-2" onSubmit={handleSubmit}>
          <div>
            <Label htmlFor="field-name" required>
              Field name
            </Label>
            <Input
              id="field-name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              aria-invalid={Boolean(getError('name'))}
            />
            {getError('name') ? <p className="mt-1 text-xs text-rose-600">{getError('name')}</p> : null}
          </div>
          <div>
            <Label htmlFor="field-slug">Slug (optional)</Label>
            <Input
              id="field-slug"
              value={slug}
              onChange={(event) => setSlug(event.target.value)}
              aria-invalid={Boolean(getError('slug'))}
            />
          </div>
          <div>
            <Label htmlFor="field-type" required>
              Data type
            </Label>
            <Select
              id="field-type"
              value={dataType}
              onChange={(event) => setDataType(event.target.value)}
            >
              {DATA_TYPES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>
          <div className="flex items-center gap-4">
            <label className="flex items-center gap-2 text-sm text-slate-600">
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-slate-300"
                checked={isRequired}
                onChange={(event) => setIsRequired(event.target.checked)}
              />
              Required
            </label>
            <label className="flex items-center gap-2 text-sm text-slate-600">
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-slate-300"
                checked={isActive}
                onChange={(event) => setIsActive(event.target.checked)}
              />
              Active
            </label>
          </div>

          {showOptionsField ? (
            <div className="md:col-span-2">
              <Label htmlFor="field-options" required>
                Options
              </Label>
              <textarea
                id="field-options"
                className={classNames(
                  'mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200',
                  getError('config.options') ? 'border-rose-400' : ''
                )}
                rows={3}
                placeholder="Enter options separated by commas or new lines"
                value={optionsInput}
                onChange={(event) => setOptionsInput(event.target.value)}
              />
            </div>
          ) : null}

          {showFileConfig ? (
            <div className="md:col-span-2 grid gap-4 md:grid-cols-3">
              <div>
                <Label htmlFor="field-extensions">Allowed extensions</Label>
                <Input
                  id="field-extensions"
                  placeholder="e.g. .pdf,.jpg"
                  value={extensionsInput}
                  onChange={(event) => setExtensionsInput(event.target.value)}
                />
              </div>
              <div>
                <Label htmlFor="field-mimes">Allowed MIME types</Label>
                <Input
                  id="field-mimes"
                  placeholder="e.g. image/png,application/pdf"
                  value={mimeTypesInput}
                  onChange={(event) => setMimeTypesInput(event.target.value)}
                />
              </div>
              <div>
                <Label htmlFor="field-max-size">Max size (KB)</Label>
                <Input
                  id="field-max-size"
                  type="number"
                  min="1"
                  value={maxSize}
                  onChange={(event) => setMaxSize(event.target.value)}
                />
              </div>
            </div>
          ) : null}

          <div className="md:col-span-2 flex items-center justify-end gap-3">
            <Button type="submit" loading={mutation.isPending}>
              {mutation.isPending ? 'Creating…' : 'Create field'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Existing fields</h3>
          <p className="text-xs text-slate-500">{fields.length} total</p>
        </div>
        {isLoading ? (
          <p className="text-sm text-slate-500">Loading fields…</p>
        ) : fields.length === 0 ? (
          <p className="text-sm text-slate-500">No custom fields yet. Use the form above to create one.</p>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Name</TableHeaderCell>
                  <TableHeaderCell>Slug</TableHeaderCell>
                  <TableHeaderCell>Type</TableHeaderCell>
                  <TableHeaderCell>Required</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Configuration</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {fields.map((field: MemberCustomField) => (
                <TableRow key={field.id}>
                  <TableCell className="font-medium text-slate-900">{field.name}</TableCell>
                  <TableCell className="text-sm text-slate-500">{field.slug}</TableCell>
                  <TableCell className="capitalize text-sm">{field.data_type.replace('_', ' ')}</TableCell>
                  <TableCell>{field.is_required ? 'Yes' : 'No'}</TableCell>
                  <TableCell>{field.is_active === false ? 'Inactive' : 'Active'}</TableCell>
                  <TableCell className="text-sm text-slate-500">{renderConfigDetails(field)}</TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => handleEdit(field)}
                      >
                        Edit
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-rose-600"
                        onClick={() => setConfirmingDelete(field)}
                      >
                        Delete
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
            </Table>
          </TableContainer>
        )}
      </Card>

      {confirmingDelete ? (
        <ConfirmDeleteModal
          field={confirmingDelete}
          onCancel={() => setConfirmingDelete(null)}
          onConfirm={handleDelete}
        />
      ) : null}
    </section>
  );
}

function renderConfigDetails(field: MemberCustomField) {
  const config = (field.config ?? {}) as Record<string, unknown>;

  if (field.data_type === 'select' || field.data_type === 'multi_select') {
    const rawOptions = config['options'];
    const options = Array.isArray(rawOptions)
      ? (rawOptions as unknown[]).map(String)
      : [];
    return options.length ? options.join(', ') : '—';
  }

  if (field.data_type === 'file' || field.data_type === 'signature') {
    const rawExtensions = config['allowed_extensions'];
    const extensions = Array.isArray(rawExtensions)
      ? (rawExtensions as unknown[]).map(String)
      : [];
    const rawMimes = config['allowed_mimetypes'];
    const mimes = Array.isArray(rawMimes)
      ? (rawMimes as unknown[]).map(String)
      : [];
    const configMaxSize = config['max_size'];
    const maxSizeValue = typeof configMaxSize === 'number' ? configMaxSize : null;
    const maxSize = maxSizeValue ? `${maxSizeValue} KB` : null;

    const parts = [
      extensions.length ? `Extensions: ${extensions.join(', ')}` : null,
      mimes.length ? `MIME: ${mimes.join(', ')}` : null,
      maxSize,
    ].filter(Boolean);

    return parts.length ? parts.join(' • ') : '—';
  }

  return '—';
}

function ConfirmDeleteModal({
  field,
  onCancel,
  onConfirm,
}: {
  field: MemberCustomField;
  onCancel: () => void;
  onConfirm: (field: MemberCustomField) => void;
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/20 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
        <h4 className="text-lg font-semibold text-slate-900">Delete custom field?</h4>
        <p className="mt-2 text-sm text-slate-600">
          This action cannot be undone. Any stored values linked to this field will be lost. Please confirm before
          proceeding.
        </p>
        <div className="mt-6 flex justify-end gap-3">
          <Button type="button" variant="ghost" onClick={onCancel}>
            Cancel
          </Button>
          <Button type="button" variant="secondary" className="bg-rose-600 text-white hover:bg-rose-700" onClick={() => onConfirm(field)}>
            Delete field
          </Button>
        </div>
      </div>
    </div>
  );
}
