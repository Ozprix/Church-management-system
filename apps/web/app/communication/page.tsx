'use client';

import Link from 'next/link';
import { FormEvent, Suspense, useCallback, useMemo, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
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
import { useNotifications, useCreateNotification, useNotificationTemplates, useRequeueNotification } from '@/hooks/use-notifications';
import type { NotificationChannel, NotificationStatus } from '@/lib/api/notifications';

const CHANNEL_OPTIONS = [
  { value: '', label: 'All channels' },
  { value: 'sms', label: 'SMS' },
  { value: 'email', label: 'Email' },
] as const satisfies ReadonlyArray<{ value: '' | NotificationChannel; label: string }>;

const SENDABLE_CHANNELS: NotificationChannel[] = ['sms', 'email'];

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'queued', label: 'Queued' },
  { value: 'sending', label: 'Sending' },
  { value: 'sent', label: 'Sent' },
  { value: 'failed', label: 'Failed' },
] as const satisfies ReadonlyArray<{ value: '' | NotificationStatus; label: string }>;

const isNotificationStatus = (value: string): value is NotificationStatus =>
  STATUS_OPTIONS.some((option) => option.value !== '' && option.value === value);

const isNotificationChannel = (value: string): value is NotificationChannel =>
  CHANNEL_OPTIONS.some((option) => option.value !== '' && option.value === value);

function CommunicationContent() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const { pushToast } = useToast();

  const statusParam = searchParams.get('status') ?? '';
  const channelParam = searchParams.get('channel') ?? '';
  const page = Number(searchParams.get('page') ?? '1');

  const updateParams = useCallback(
    (next: Record<string, string | undefined>) => {
      const params = new URLSearchParams(searchParams.toString());
      Object.entries(next).forEach(([key, value]) => {
        if (value && value.length > 0) {
          params.set(key, value);
        } else {
          params.delete(key);
        }
      });
      const query = params.toString();
      router.push(`${pathname}${query ? `?${query}` : ''}`);
    },
    [pathname, router, searchParams]
  );

  const statusFilter = isNotificationStatus(statusParam) ? statusParam : undefined;
  const channelFilter = isNotificationChannel(channelParam) ? channelParam : undefined;

  const { data: notificationsResponse, isLoading } = useNotifications({
    status: statusFilter,
    channel: channelFilter,
    page,
    per_page: 10,
  });
  const notifications = notificationsResponse?.data ?? [];
  const meta = notificationsResponse?.meta;

  const { data: templatesResponse } = useNotificationTemplates({ per_page: 100 });
  const templates = useMemo(() => templatesResponse?.data ?? [], [templatesResponse?.data]);
  const templateLookup = useMemo(() => {
    const map = new Map<number, (typeof templates)[number]>();
    templates.forEach((template) => {
      map.set(template.id, template);
    });
    return map;
  }, [templates]);
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>('');
  const activeTemplate = selectedTemplateId ? templateLookup.get(Number(selectedTemplateId)) : undefined;

  const createMutation = useCreateNotification();
  const requeueMutation = useRequeueNotification();

  const handleFilterSubmit = useCallback(
    (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const formData = new FormData(event.currentTarget);
      updateParams({
        status: (formData.get('status') as string) ?? undefined,
        channel: (formData.get('channel') as string) ?? undefined,
        page: '1',
      });
    },
    [updateParams]
  );

  const handleNewNotification = useCallback(
    async (event: FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const formData = new FormData(event.currentTarget);
      const templateId = selectedTemplateId || (formData.get('template_id') as string | null);
      const recipient = (formData.get('recipient') as string) || undefined;
      const subject = (formData.get('subject') as string) || undefined;
      const body = (formData.get('body') as string) || undefined;

      if (!recipient) {
        pushToast({ title: 'Recipient is required', variant: 'error' });
        return;
      }

      let channels: NotificationChannel[] = [];
      if (templateId) {
        const template = templateLookup.get(Number(templateId));
        if (template) {
          channels = [template.channel];
        }
      } else {
        const selectedChannels = formData.getAll('channels').map((value) => String(value) as NotificationChannel);
        channels = selectedChannels.length ? (Array.from(new Set(selectedChannels)) as NotificationChannel[]) : ['sms'];
      }

      if (!channels.length) {
        pushToast({ title: 'Select a channel', variant: 'error' });
        return;
      }

      try {
        for (const channel of channels) {
          await createMutation.mutateAsync({
            notification_template_id: templateId ? Number(templateId) : undefined,
            channel: templateId ? undefined : channel,
            recipient,
            subject: templateId || channel === 'sms' ? undefined : subject,
            body: templateId ? undefined : body,
          });
        }

        pushToast({ title: 'Notification queued', variant: 'success' });
        event.currentTarget.reset();
        setSelectedTemplateId('');
      } catch (error) {
        pushToast({
          title: 'Unable to queue notification',
          description: error instanceof Error ? error.message : 'Unknown error',
          variant: 'error',
        });
      }
    },
    [createMutation, pushToast, selectedTemplateId, templateLookup]
  );

  const currentPage = meta?.current_page ?? 1;
  const lastPage = meta?.last_page ?? 1;

  return (
    <section className="space-y-6">
      <header className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-slate-900">Communication Center</h2>
          <p className="text-sm text-slate-500">Manage SMS and email notifications across your tenant.</p>
        </div>
        <div className="flex flex-wrap items-center gap-3 text-sm">
          <Link href="/communication/templates" className="text-emerald-600 hover:text-emerald-700">
            Manage templates
          </Link>
          <Link href="/attendance" className="text-emerald-600 hover:text-emerald-700">
            View attendance
          </Link>
        </div>
      </header>

      <Card className="space-y-4">
        <h3 className="text-lg font-semibold text-slate-900">Quick send</h3>
        <form className="grid gap-4 md:grid-cols-4" onSubmit={handleNewNotification}>
          <div>
            <Label htmlFor="template_id">Template</Label>
            <Select
              id="template_id"
              name="template_id"
              value={selectedTemplateId}
              onChange={(event) => setSelectedTemplateId(event.target.value)}
            >
              <option value="">Custom message</option>
              {templates.map((template) => (
                <option key={template.id} value={template.id}>
                  {template.name} ({template.channel.toUpperCase()})
                </option>
              ))}
            </Select>
          </div>
          <div className="md:col-span-2">
            <Label>Channels</Label>
            {activeTemplate ? (
              <p className="text-xs text-slate-500">
                Using template channel: <span className="font-medium uppercase">{activeTemplate.channel}</span>
              </p>
            ) : (
              <div className="mt-1 flex flex-wrap gap-4 text-sm text-slate-600">
                {SENDABLE_CHANNELS.map((channelOption) => (
                  <label key={channelOption} className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      name="channels"
                      value={channelOption}
                      defaultChecked={channelOption === 'sms'}
                    />
                    {channelOption.toUpperCase()}
                  </label>
                ))}
              </div>
            )}
          </div>
          <div>
            <Label htmlFor="recipient" required>
              Recipient
            </Label>
            <Input id="recipient" name="recipient" placeholder="Phone or email" required />
          </div>
          <div>
            <Label htmlFor="subject">Subject</Label>
            <Input
              id="subject"
              name="subject"
              placeholder="Subject (email only)"
              disabled={Boolean(activeTemplate)}
            />
          </div>
          <div className="md:col-span-4">
            <Label htmlFor="body">Message</Label>
            <textarea
              id="body"
              name="body"
              rows={3}
              className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
              placeholder="Message content or leave blank to use template copy"
              disabled={Boolean(activeTemplate)}
            />
          </div>
          <div className="md:col-span-4 flex justify-end">
            <Button type="submit" loading={createMutation.isPending}>
              {createMutation.isPending ? 'Queueing…' : 'Queue notification'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="space-y-4">
        <header className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Notification queue</h3>
          <form className="flex flex-wrap items-center gap-2" onSubmit={handleFilterSubmit}>
            <Label htmlFor="filter-status" className="!mb-0 text-sm text-slate-600">
              Status
            </Label>
            <Select id="filter-status" name="status" defaultValue={statusParam} className="w-32">
              {STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
            <Label htmlFor="filter-channel" className="!mb-0 text-sm text-slate-600">
              Channel
            </Label>
            <Select id="filter-channel" name="channel" defaultValue={channelParam} className="w-28">
              {CHANNEL_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
            <Button type="submit" size="sm">
              Apply
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={() => updateParams({ status: undefined, channel: undefined, page: undefined })}>
              Reset
            </Button>
          </form>
        </header>

        {isLoading ? (
          <p className="text-slate-500">Loading notifications…</p>
        ) : notifications.length === 0 ? (
          <p className="text-slate-500">No notifications found.</p>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>Recipient</TableHeaderCell>
                  <TableHeaderCell>Channel</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Subject</TableHeaderCell>
                  <TableHeaderCell>Sent</TableHeaderCell>
                  <TableHeaderCell className="text-right">Actions</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {notifications.map((notification) => (
                  <TableRow key={notification.id}>
                    <TableCell className="font-medium text-slate-900">{notification.recipient}</TableCell>
                    <TableCell className="uppercase text-xs text-slate-500">{notification.channel}</TableCell>
                    <TableCell className="capitalize">{notification.status}</TableCell>
                    <TableCell>{notification.subject ?? '—'}</TableCell>
                    <TableCell>
                      {notification.sent_at
                        ? new Date(notification.sent_at).toLocaleString()
                        : notification.scheduled_for
                          ? `Scheduled ${new Date(notification.scheduled_for).toLocaleString()}`
                          : '—'}
                    </TableCell>
                    <TableCell className="text-right">
                      {notification.status === 'failed' && (
                        <Button
                          type="button"
                          size="sm"
                          variant="secondary"
                          loading={requeueMutation.isPending}
                          onClick={async () => {
                            try {
                              await requeueMutation.mutateAsync({ id: notification.id });
                              pushToast({ title: 'Notification requeued', variant: 'success' });
                            } catch (error) {
                              pushToast({
                                title: 'Unable to requeue',
                                description: error instanceof Error ? error.message : 'Unknown error',
                                variant: 'error',
                              });
                            }
                          }}
                        >
                          Retry
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}

        {lastPage > 1 && (
          <div className="flex items-center justify-between text-sm text-slate-600">
            <span>
              Page {currentPage} of {lastPage}
            </span>
            <div className="flex items-center gap-2">
              <Button type="button" variant="secondary" size="sm" disabled={currentPage <= 1} onClick={() => updateParams({ page: String(currentPage - 1) })}>
                Previous
              </Button>
              <Button type="button" variant="secondary" size="sm" disabled={currentPage >= lastPage} onClick={() => updateParams({ page: String(currentPage + 1) })}>
                Next
              </Button>
            </div>
          </div>
        )}
      </Card>
    </section>
  );
}

export default function CommunicationPage() {
  return (
    <Suspense fallback={<p className="text-slate-500">Loading communication…</p>}>
      <CommunicationContent />
    </Suspense>
  );
}
