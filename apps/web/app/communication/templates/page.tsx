'use client';

import { FormEvent, useState } from 'react';
import Link from 'next/link';
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
} from '@church/ui';
import { NotificationTemplateSummary, NotificationTemplatePayload } from '@/lib/api/notifications';
import {
  useNotificationTemplates,
  useCreateNotificationTemplate,
  useUpdateNotificationTemplate,
  useDeleteNotificationTemplate,
} from '@/hooks/use-notifications';
import { ApiError } from '@/lib/api/http';

const CHANNEL_OPTIONS = [
  { value: 'sms', label: 'SMS' },
  { value: 'email', label: 'Email' },
] as const;

type ChannelOption = (typeof CHANNEL_OPTIONS)[number]['value'];

export default function NotificationTemplatesPage() {
  const { pushToast } = useToast();
  const { data: response, isLoading } = useNotificationTemplates({ per_page: 100 });
  const templates = response?.data ?? [];

  const [editingTemplate, setEditingTemplate] = useState<NotificationTemplateSummary | null>(null);
  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [channel, setChannel] = useState<ChannelOption>('sms');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [placeholders, setPlaceholders] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const createMutation = useCreateNotificationTemplate();
  const updateMutation = useUpdateNotificationTemplate(editingTemplate?.id ?? null);
  const deleteMutation = useDeleteNotificationTemplate();
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const isEmailChannel = channel === 'email';

  const isPending = createMutation.isPending || updateMutation.isPending || deleteMutation.isPending;

  const resetForm = () => {
    setEditingTemplate(null);
    setName('');
    setSlug('');
    setChannel('sms');
    setSubject('');
    setBody('');
    setPlaceholders('');
    setErrors({});
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setErrors({});

    if (!name.trim()) {
      setErrors({ name: ['Name is required'] });
      pushToast({ title: 'Name is required', variant: 'error' });
      return;
    }
    if (!body.trim()) {
      setErrors({ body: ['Message body is required'] });
      pushToast({ title: 'Message body is required', variant: 'error' });
      return;
    }

    const placeholderList = placeholders
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean);

    const payload: NotificationTemplatePayload = {
      name: name.trim(),
      slug: slug.trim() || undefined,
      channel,
      subject: isEmailChannel ? subject.trim() || null : null,
      body,
      placeholders: placeholderList.length ? placeholderList : undefined,
    };

    try {
      if (editingTemplate) {
        await updateMutation.mutateAsync(payload);
        pushToast({ title: 'Template updated', variant: 'success' });
      } else {
        await createMutation.mutateAsync(payload);
        pushToast({ title: 'Template created', variant: 'success' });
      }
      resetForm();
    } catch (error) {
      if (error instanceof ApiError && error.status === 422 && error.errors) {
        setErrors(error.errors);
        pushToast({ title: 'Please fix the highlighted errors', description: Object.values(error.errors)[0]?.[0], variant: 'error' });
        return;
      }
      const message = error instanceof Error ? error.message : 'Unable to save template';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    }
  };

  const startEditing = (template: NotificationTemplateSummary) => {
    setEditingTemplate(template);
    setName(template.name);
    setSlug(template.slug ?? '');
    setChannel(template.channel);
    setSubject(template.subject ?? '');
    setBody(template.body ?? '');
    setPlaceholders(Array.isArray(template.placeholders) ? template.placeholders.join(',') : '');
    setErrors({});
  };

  const handleDelete = async (template: NotificationTemplateSummary) => {
    setDeletingId(template.id);
    try {
      await deleteMutation.mutateAsync(template.id);
      pushToast({ title: 'Template deleted', variant: 'success' });
      if (editingTemplate?.id === template.id) {
        resetForm();
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to delete template';
      pushToast({ title: 'Delete failed', description: message, variant: 'error' });
    } finally {
      setDeletingId(null);
    }
  };

  const getError = (field: string) => errors[field]?.[0];

  return (
    <section className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Notification templates</h2>
          <p className="text-sm text-slate-500">Create reusable SMS and email content for outreach.</p>
        </div>
        <Link href="/communication" className="text-sm text-emerald-600 hover:text-emerald-700">
          Back to communication center
        </Link>
      </div>

      <Card className="space-y-6">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{editingTemplate ? 'Edit template' : 'Create template'}</h3>
          {editingTemplate ? (
            <Button type="button" variant="ghost" size="sm" onClick={resetForm}>
              Cancel edit
            </Button>
          ) : null}
        </div>

        <form className="grid gap-4 md:grid-cols-2" onSubmit={handleSubmit}>
          <div>
            <Label htmlFor="template-name" required>
              Name
            </Label>
            <Input
              id="template-name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              aria-invalid={Boolean(getError('name'))}
            />
            {getError('name') ? <p className="mt-1 text-xs text-rose-600">{getError('name')}</p> : null}
          </div>

          <div>
            <Label htmlFor="template-slug">Slug (optional)</Label>
            <Input
              id="template-slug"
              value={slug}
              onChange={(event) => setSlug(event.target.value)}
              aria-invalid={Boolean(getError('slug'))}
            />
          </div>

          <div>
            <Label htmlFor="template-channel" required>
              Channel
            </Label>
            <Select
              id="template-channel"
              value={channel}
              onChange={(event) => setChannel(event.target.value as ChannelOption)}
              disabled={Boolean(editingTemplate)}
            >
              {CHANNEL_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          </div>

          <div>
            <Label htmlFor="template-placeholders">Merge fields (comma separated)</Label>
            <Input
              id="template-placeholders"
              placeholder="e.g. {{first_name}},{{event_date}}"
              value={placeholders}
              onChange={(event) => setPlaceholders(event.target.value)}
            />
          </div>

          {isEmailChannel ? (
            <div className="md:col-span-2">
              <Label htmlFor="template-subject">Subject</Label>
              <Input
                id="template-subject"
                value={subject}
                onChange={(event) => setSubject(event.target.value)}
              />
            </div>
          ) : null}

          <div className="md:col-span-2">
            <Label htmlFor="template-body" required>
              Message body
            </Label>
            <textarea
              id="template-body"
              rows={6}
              className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
              value={body}
              onChange={(event) => setBody(event.target.value)}
              aria-invalid={Boolean(getError('body'))}
            />
            {getError('body') ? <p className="mt-1 text-xs text-rose-600">{getError('body')}</p> : null}
          </div>

          <div className="md:col-span-2 flex items-center justify-end gap-3">
            <Button type="submit" loading={isPending}>
              {editingTemplate ? (isPending ? 'Saving…' : 'Save changes') : isPending ? 'Creating…' : 'Create template'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Existing templates</h3>
          <p className="text-xs text-slate-500">{templates.length} total</p>
        </div>
        {isLoading ? (
          <p className="text-sm text-slate-500">Loading templates…</p>
        ) : templates.length === 0 ? (
          <p className="text-sm text-slate-500">No templates yet. Use the form above to create one.</p>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Name</TableHeaderCell>
                  <TableHeaderCell>Slug</TableHeaderCell>
                  <TableHeaderCell>Channel</TableHeaderCell>
                  <TableHeaderCell>Placeholders</TableHeaderCell>
                  <TableHeaderCell></TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {templates.map((template) => (
                  <TableRow key={template.id}>
                    <TableCell className="font-medium text-slate-900">{template.name}</TableCell>
                    <TableCell className="text-sm text-slate-500">{template.slug}</TableCell>
                    <TableCell className="text-sm uppercase">{template.channel}</TableCell>
                    <TableCell className="text-sm text-slate-500">
                      {Array.isArray(template.placeholders) && template.placeholders.length
                        ? template.placeholders.join(', ')
                        : '—'}
                    </TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-2">
                        <Button type="button" variant="ghost" size="sm" onClick={() => startEditing(template)}>
                          Edit
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="text-rose-600"
                          onClick={() => handleDelete(template)}
                          loading={deleteMutation.isPending && deletingId === template.id}
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
    </section>
  );
}
