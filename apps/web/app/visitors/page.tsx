'use client';

import { FormEvent, useMemo, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  addVisitorWorkflowStep,
  createVisitorWorkflow,
  deleteVisitorWorkflow,
  deleteVisitorWorkflowStep,
  haltVisitorFollowup,
  startVisitorFollowup,
  VisitorFollowup,
  VisitorWorkflow,
  VisitorWorkflowStep,
} from '@/lib/api/visitors';
import { useVisitorWorkflows } from '@/hooks/use-visitor-workflows';
import { useVisitorFollowups } from '@/hooks/use-visitor-followups';
import { useVisitorAnalytics } from '@/hooks/use-visitor-analytics';
import { useVisitorFollowupLogs } from '@/hooks/use-visitor-followup-logs';
import { useNotificationTemplates } from '@/hooks/use-notifications';
import { useTenantId } from '@/lib/tenant';
import {
  Badge,
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
  StatCard,
} from '@church/ui';
import { ApiError } from '@/lib/api/http';

type RuleOperator = 'equals' | 'not_equals' | 'contains' | 'greater_than' | 'less_than';
type RuleLogic = 'all' | 'any';

interface RuleFieldConfig {
  value: string;
  label: string;
  valueType: 'text' | 'number' | 'select';
  operatorOptions: RuleOperator[];
  selectOptions?: Array<{ value: string; label: string }>;
  placeholder?: string;
}

interface StepRule {
  id: string;
  field: string;
  operator: RuleOperator;
  value: string;
}

const RULE_OPERATOR_LABELS: Record<RuleOperator, string> = {
  equals: 'is',
  not_equals: 'is not',
  contains: 'contains',
  greater_than: 'is greater than',
  less_than: 'is less than',
};

const RULE_FIELD_CONFIG: RuleFieldConfig[] = [
  {
    value: 'member_status',
    label: 'Member status',
    valueType: 'text',
    operatorOptions: ['equals', 'not_equals', 'contains'],
    placeholder: 'visitor',
  },
  {
    value: 'member_stage',
    label: 'Member stage',
    valueType: 'text',
    operatorOptions: ['equals', 'not_equals', 'contains'],
    placeholder: 'first_time',
  },
  {
    value: 'member_tags',
    label: 'Member tags',
    valueType: 'text',
    operatorOptions: ['contains', 'not_equals'],
    placeholder: 'needs_followup',
  },
  {
    value: 'visit_count',
    label: 'Total visits',
    valueType: 'number',
    operatorOptions: ['equals', 'greater_than', 'less_than'],
    placeholder: '2',
  },
  {
    value: 'days_since_last_attended',
    label: 'Days since last attendance',
    valueType: 'number',
    operatorOptions: ['greater_than', 'less_than'],
    placeholder: '14',
  },
  {
    value: 'has_family_assignment',
    label: 'Has family assignment',
    valueType: 'select',
    operatorOptions: ['equals', 'not_equals'],
    selectOptions: [
      { value: 'true', label: 'Yes' },
      { value: 'false', label: 'No' },
    ],
  },
];

function getRuleFieldConfig(field: string): RuleFieldConfig | undefined {
  return RULE_FIELD_CONFIG.find((item) => item.value === field);
}

function createRuleId(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID();
  }
  return `rule-${Math.random().toString(36).slice(2, 10)}`;
}

interface StepFormState {
  step_number: number;
  name: string;
  delay_minutes: number;
  channel: 'email' | 'sms' | 'task' | 'staff_email';
  notification_template_id?: number | '';
  subject?: string;
  body?: string;
  staff_email?: string;
  task_notes?: string;
  rules: StepRule[];
  rules_logic: RuleLogic;
}

function defaultStepForm(): StepFormState {
  return {
    step_number: 1,
    name: '',
    delay_minutes: 0,
    channel: 'email',
    notification_template_id: '',
    subject: '',
    body: '',
    staff_email: '',
    task_notes: '',
    rules: [],
    rules_logic: 'all',
  };
}

export default function VisitorsPage() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  const { data: workflows = [], isLoading: workflowsLoading } = useVisitorWorkflows();
  const { data: followups = [], isLoading: followupsLoading } = useVisitorFollowups();
  const { data: analytics } = useVisitorAnalytics();
  const { data: templatesResponse } = useNotificationTemplates();
  const templates = templatesResponse?.data ?? [];

  const [activeFollowup, setActiveFollowup] = useState<VisitorFollowup | null>(null);
  const { data: followupLogs = [], isLoading: logsLoading } = useVisitorFollowupLogs(activeFollowup?.id ?? null);

  const [workflowName, setWorkflowName] = useState('');
  const [workflowDescription, setWorkflowDescription] = useState('');
  const [memberIdInput, setMemberIdInput] = useState('');
  const [selectedWorkflowId, setSelectedWorkflowId] = useState<number | ''>('');
  const [stepForms, setStepForms] = useState<Record<number, StepFormState>>({});

  const stats = analytics?.stats;
  const followupStatusBreakdown = useMemo(
    () => analytics?.breakdown.followup_statuses ?? [],
    [analytics?.breakdown.followup_statuses]
  );
  const workflowPerformance = analytics?.breakdown.workflows ?? [];
  const recentActivity = analytics?.recent_activity ?? [];
  const funnelStages = useMemo(
    () => buildVisitorFunnel(followupStatusBreakdown),
    [followupStatusBreakdown]
  );
  const funnelTotal = funnelStages.reduce((acc, stage) => acc + stage.total, 0);

  const addRuleToWorkflow = (workflowId: number) => {
    const fieldConfig = RULE_FIELD_CONFIG[0];
    const initialValue =
      fieldConfig.valueType === 'select'
        ? fieldConfig.selectOptions?.[0]?.value ?? ''
        : '';
    setStepForms((prev) => {
      const current = prev[workflowId] ?? defaultStepForm();
      const updatedForm: StepFormState = {
        ...current,
        rules: [
          ...current.rules,
          {
            id: createRuleId(),
            field: fieldConfig.value,
            operator: fieldConfig.operatorOptions[0],
            value: initialValue,
          },
        ],
      };
      return { ...prev, [workflowId]: updatedForm };
    });
  };

  const updateRuleField = (
    workflowId: number,
    ruleId: string,
    updates: Partial<StepRule>
  ) => {
    setStepForms((prev) => {
      const current = prev[workflowId] ?? defaultStepForm();
      const updatedForm: StepFormState = {
        ...current,
        rules: current.rules.map((rule) =>
          rule.id === ruleId ? { ...rule, ...updates } : rule
        ),
      };
      return { ...prev, [workflowId]: updatedForm };
    });
  };

  const removeRuleFromWorkflow = (workflowId: number, ruleId: string) => {
    setStepForms((prev) => {
      const current = prev[workflowId] ?? defaultStepForm();
      const updatedForm: StepFormState = {
        ...current,
        rules: current.rules.filter((rule) => rule.id !== ruleId),
      };
      return { ...prev, [workflowId]: updatedForm };
    });
  };

  const updateRulesLogic = (workflowId: number, logic: RuleLogic) => {
    setStepForms((prev) => {
      const current = prev[workflowId] ?? defaultStepForm();
      const updatedForm: StepFormState = {
        ...current,
        rules_logic: logic,
      };
      return { ...prev, [workflowId]: updatedForm };
    });
  };

  const createWorkflowMutation = useMutation({
    mutationFn: async () => {
      if (!tenantId) throw new Error('Missing tenant context');
      if (!workflowName.trim()) throw new Error('Workflow name is required');
      const workflow = await createVisitorWorkflow(tenantId, {
        name: workflowName.trim(),
        description: workflowDescription.trim() || undefined,
      });
      return workflow;
    },
    onSuccess: (workflow) => {
      setWorkflowName('');
      setWorkflowDescription('');
      queryClient.invalidateQueries({ queryKey: ['visitor-workflows', tenantId] });
      pushToast({ title: 'Workflow created', description: workflow.name, variant: 'success' });
    },
    onError: (error: unknown) => {
      const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to create workflow';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    },
  });

  const addStepMutation = useMutation({
    mutationFn: async ({ workflowId, form }: { workflowId: number; form: StepFormState }) => {
      if (!tenantId) throw new Error('Missing tenant context');

      const metadata: Record<string, unknown> = {};
      const trimmedBody = form.body?.trim() ?? '';
      const trimmedSubject = form.subject?.trim() ?? '';
      const trimmedStaffEmail = form.staff_email?.trim() ?? '';
      const trimmedTaskNotes = form.task_notes?.trim() ?? '';

      if (form.channel === 'staff_email') {
        if (trimmedStaffEmail) {
          metadata.email = trimmedStaffEmail;
        }
        if (trimmedSubject) {
          metadata.subject = trimmedSubject;
        }
        if (trimmedBody) {
          metadata.body = trimmedBody;
        }
      } else if (form.channel === 'email') {
        if (trimmedSubject) {
          metadata.subject = trimmedSubject;
        }
        if (trimmedBody) {
          metadata.body = trimmedBody;
        }
      } else if (form.channel === 'sms') {
        if (trimmedBody) {
          metadata.body = trimmedBody;
        }
      } else if (form.channel === 'task' && trimmedTaskNotes) {
        metadata.body = trimmedTaskNotes;
      }

      const normalizedRules = form.rules
        .map((rule) => {
          const config = getRuleFieldConfig(rule.field);
          if (!config) {
            return null;
          }

          let value: unknown = rule.value;
          if (config.valueType === 'text') {
            const trimmed = rule.value.trim();
            if (!trimmed) return null;
            value = trimmed;
          } else if (config.valueType === 'number') {
            if (rule.value.trim() === '') return null;
            const parsed = Number(rule.value);
            if (Number.isNaN(parsed)) return null;
            value = parsed;
          } else if (config.valueType === 'select') {
            if (!rule.value) return null;
            value = rule.value;
          }

          return {
            field: rule.field,
            operator: rule.operator,
            value,
          };
        })
        .filter((rule): rule is { field: string; operator: RuleOperator; value: unknown } => Boolean(rule));

      if (normalizedRules.length) {
        metadata.rules = normalizedRules;
        metadata.rules_logic = form.rules_logic;
      }

      return addVisitorWorkflowStep(tenantId, workflowId, {
        step_number: form.step_number,
        name: form.name || undefined,
        delay_minutes: form.delay_minutes,
        channel: form.channel,
        notification_template_id:
          form.channel === 'task' || form.channel === 'staff_email'
            ? undefined
            : form.notification_template_id || undefined,
        metadata: Object.keys(metadata).length ? metadata : undefined,
        is_active: true,
      });
    },
    onSuccess: (_step, variables) => {
      queryClient.invalidateQueries({ queryKey: ['visitor-workflows', tenantId] });
      setStepForms((prev) => ({ ...prev, [variables.workflowId]: defaultStepForm() }));
      pushToast({ title: 'Step added', variant: 'success' });
    },
    onError: (error: unknown) => {
      const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to add step';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    },
  });

  const startFollowupMutation = useMutation({
    mutationFn: async () => {
      if (!tenantId) throw new Error('Missing tenant context');
      const memberId = Number(memberIdInput);
      if (!memberId || !selectedWorkflowId) {
        throw new Error('Member ID and workflow are required');
      }
      return startVisitorFollowup(tenantId, {
        member_id: memberId,
        workflow_id: selectedWorkflowId,
      });
    },
    onSuccess: () => {
      setMemberIdInput('');
      setSelectedWorkflowId('');
      queryClient.invalidateQueries({ queryKey: ['visitor-followups', tenantId] });
      pushToast({ title: 'Follow-up started', variant: 'success' });
    },
    onError: (error: unknown) => {
      const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to start follow-up';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    },
  });

  const haltFollowupMutation = useMutation({
    mutationFn: async (followupId: number) => {
      if (!tenantId) throw new Error('Missing tenant context');
      return haltVisitorFollowup(tenantId, followupId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['visitor-followups', tenantId] });
      pushToast({ title: 'Follow-up halted', variant: 'success' });
    },
    onError: (error: unknown) => {
      const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to halt follow-up';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    },
  });

  const handleCreateWorkflow = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    createWorkflowMutation.mutate();
  };

  const handleAddStep = (workflow: VisitorWorkflow) => {
    const form = stepForms[workflow.id] ?? defaultStepForm();
    addStepMutation.mutate({ workflowId: workflow.id, form });
  };

  const handleRemoveWorkflow = async (workflow: VisitorWorkflow) => {
    if (!tenantId) {
      pushToast({ title: 'Error', description: 'Missing tenant context', variant: 'error' });
      return;
    }
    try {
      await deleteVisitorWorkflow(tenantId, workflow.id);
      queryClient.invalidateQueries({ queryKey: ['visitor-workflows', tenantId] });
      pushToast({ title: 'Workflow removed', variant: 'success' });
    } catch (error) {
      const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to delete workflow';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    }
  };

  const handleRemoveStep = async (stepId: number) => {
    if (!tenantId) {
      pushToast({ title: 'Error', description: 'Missing tenant context', variant: 'error' });
      return;
    }
    try {
      await deleteVisitorWorkflowStep(tenantId, stepId);
      queryClient.invalidateQueries({ queryKey: ['visitor-workflows', tenantId] });
      pushToast({ title: 'Step removed', variant: 'success' });
    } catch (error) {
      const message = error instanceof ApiError ? error.message : (error as Error)?.message ?? 'Unable to delete step';
      pushToast({ title: 'Error', description: message, variant: 'error' });
    }
  };

  const workflowOptions = useMemo(
    () => workflows.map((workflow) => ({ value: workflow.id, label: workflow.name })),
    [workflows]
  );

  return (
    <div className="space-y-8">
      {stats && (
        <section className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          <StatCard title="Total visitors" value={stats.total_visitors} helperText="Members currently marked as visitors" />
          <StatCard
            title="Converted"
            value={stats.converted_visitors}
            helperText="Visitors moved into membership"
            tone="success"
          />
          <StatCard
            title="Active follow-ups"
            value={stats.active_followups}
            helperText="Follow-ups pending or in progress"
            tone="warning"
          />
          <StatCard
            title="Completed (30 days)"
            value={stats.completed_last_30_days}
            helperText="Follow-ups finished in the last 30 days"
            tone="success"
          />
          <StatCard
            title="Conversion rate"
            value={typeof stats.conversion_rate === 'number' ? `${stats.conversion_rate}%` : '—'}
            helperText="Visitors converted overall"
            tone={typeof stats.conversion_rate === 'number' && stats.conversion_rate >= 50 ? 'success' : 'default'}
          />
        </section>
      )}

      {funnelStages.length > 0 && (
        <section>
          <Card className="space-y-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-lg font-semibold text-slate-900">Visitor follow-up funnel</h2>
                <p className="text-xs text-slate-500">
                  Track where visitors sit across pending, in-progress, and completed steps.
                </p>
              </div>
              <p className="text-xs text-slate-500">
                Total follow-ups tracked: <span className="font-semibold text-slate-700">{funnelTotal}</span>
              </p>
            </div>
            <VisitorFunnel stages={funnelStages} total={funnelTotal} />
          </Card>
        </section>
      )}

      {analytics && (
        <section className="grid gap-4 lg:grid-cols-3">
          <Card className="space-y-3 lg:col-span-1">
            <h3 className="text-sm font-semibold text-slate-700">Follow-up statuses</h3>
            <ul className="space-y-2 text-sm text-slate-600">
              {followupStatusBreakdown.length === 0 && <li>No follow-ups yet.</li>}
              {followupStatusBreakdown.map((item) => (
                <li key={item.status} className="flex items-center justify-between">
                  <span className="capitalize">{item.status.replace('_', ' ')}</span>
                  <span className="font-semibold text-slate-900">{item.total}</span>
                </li>
              ))}
            </ul>
          </Card>
          <Card className="space-y-3 lg:col-span-1">
            <h3 className="text-sm font-semibold text-slate-700">Workflow performance</h3>
            <ul className="space-y-3 text-sm text-slate-600">
              {workflowPerformance.length === 0 && <li>No workflow data yet.</li>}
              {workflowPerformance.map((item) => (
                <li key={item.workflow_id} className="space-y-1">
                  <p className="font-semibold text-slate-900">{item.workflow_name}</p>
                  <div className="flex flex-wrap gap-2 text-xs text-slate-500">
                    <Badge variant="info">Pending {item.pending}</Badge>
                    <Badge variant="info">In progress {item.in_progress}</Badge>
                    <Badge variant="success">Completed {item.completed}</Badge>
                    <Badge variant="warning">Halted {item.halted}</Badge>
                  </div>
                </li>
              ))}
            </ul>
          </Card>
          <Card className="space-y-3 lg:col-span-1">
            <h3 className="text-sm font-semibold text-slate-700">Recent activity</h3>
            <ul className="space-y-2 text-sm text-slate-600">
              {recentActivity.length === 0 && <li>No recent activity.</li>}
              {recentActivity.map((entry) => (
                <li key={entry.id} className="rounded border border-slate-200 p-2">
                  <p className="font-semibold text-slate-900">{entry.step ?? 'Workflow step'}</p>
                  <p className="text-xs text-slate-500">
                    {entry.run_at ? new Date(entry.run_at).toLocaleString() : '—'} • {entry.status}
                  </p>
                  {entry.member && (
                    <p className="text-xs text-slate-500">
                      {entry.member.first_name} {entry.member.last_name}
                    </p>
                  )}
                  {entry.notes && <p className="text-xs text-slate-600">{entry.notes}</p>}
                </li>
              ))}
            </ul>
          </Card>
        </section>
      )}

      <section>
        <Card className="space-y-4">
          <h2 className="text-xl font-semibold text-slate-900">Visitor Workflows</h2>
          <form className="grid gap-4 md:grid-cols-3" onSubmit={handleCreateWorkflow}>
            <div>
              <Label htmlFor="workflow-name" required>
                Name
              </Label>
              <Input
                id="workflow-name"
                value={workflowName}
                onChange={(event) => setWorkflowName(event.target.value)}
                required
              />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="workflow-description">Description</Label>
              <Input
                id="workflow-description"
                value={workflowDescription}
                onChange={(event) => setWorkflowDescription(event.target.value)}
              />
            </div>
            <div className="md:col-span-3 flex justify-end">
              <Button type="submit" disabled={createWorkflowMutation.isPending}>
                {createWorkflowMutation.isPending ? 'Creating…' : 'Create workflow'}
              </Button>
            </div>
          </form>

          <div className="space-y-4">
            {workflowsLoading && <p className="text-sm text-slate-500">Loading workflows…</p>}
            {!workflowsLoading && workflows.length === 0 && (
              <p className="text-sm text-slate-500">No workflows yet. Create one to begin automations.</p>
            )}
            {workflows.map((workflow) => {
              const stepForm = stepForms[workflow.id] ?? defaultStepForm();
              return (
                <div key={workflow.id} className="rounded-lg border border-slate-200 p-4 space-y-4">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <h3 className="text-lg font-semibold text-slate-900">{workflow.name}</h3>
                      {workflow.description ? (
                        <p className="text-sm text-slate-600">{workflow.description}</p>
                      ) : null}
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge variant={workflow.is_active ? 'success' : 'warning'}>
                        {workflow.is_active ? 'Active' : 'Inactive'}
                      </Badge>
                      <Button
                        type="button"
                        variant="ghost"
                        onClick={() => handleRemoveWorkflow(workflow)}
                      >
                        Delete
                      </Button>
                    </div>
                  </div>

                  <div className="space-y-3">
                    <h4 className="text-sm font-medium text-slate-700">Steps</h4>
                    <TableContainer>
                      <Table>
                        <TableHead>
                          <TableRow>
                            <TableHeaderCell>#</TableHeaderCell>
                            <TableHeaderCell>Name</TableHeaderCell>
                            <TableHeaderCell>Delay</TableHeaderCell>
                            <TableHeaderCell>Channel</TableHeaderCell>
                            <TableHeaderCell>Template</TableHeaderCell>
                            <TableHeaderCell>Details</TableHeaderCell>
                            <TableHeaderCell className="text-right">Actions</TableHeaderCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {workflow.steps && workflow.steps.length > 0 ? (
                            workflow.steps.map((step) => (
                              <TableRow key={step.id}>
                                <TableCell>{step.step_number}</TableCell>
                                <TableCell>{step.name ?? '—'}</TableCell>
                                <TableCell>{step.delay_minutes} min</TableCell>
                                <TableCell className="capitalize">{step.channel}</TableCell>
                                <TableCell>
                                  {step.notification_template_id
                                    ? templates.find((template) => template.id === step.notification_template_id)?.name ?? step.notification_template_id
                                    : '—'}
                                </TableCell>
                                <TableCell className="text-sm text-slate-500">
                                  {describeStepMetadata(step)}
                                </TableCell>
                                <TableCell className="text-right">
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => handleRemoveStep(step.id)}
                                  >
                                    Remove
                                  </Button>
                                </TableCell>
                              </TableRow>
                            ))
                          ) : (
                            <TableRow>
                              <TableCell colSpan={7} className="py-3 text-center text-sm text-slate-500">
                                No steps configured yet.
                              </TableCell>
                            </TableRow>
                          )}
                        </TableBody>
                      </Table>
                    </TableContainer>

                    <div className="rounded-md border border-dashed border-slate-300 p-4">
                      <h5 className="text-sm font-semibold text-slate-700">Add step</h5>
                      <div className="mt-3 grid gap-3 md:grid-cols-5">
                        <div>
                          <Label htmlFor={`step-number-${workflow.id}`}>Order</Label>
                          <Input
                            id={`step-number-${workflow.id}`}
                            type="number"
                            min={1}
                            value={stepForm.step_number}
                            onChange={(event) =>
                              setStepForms((prev) => ({
                                ...prev,
                                [workflow.id]: {
                                  ...stepForm,
                                  step_number: Number(event.target.value),
                                },
                              }))
                            }
                          />
                        </div>
                        <div>
                          <Label htmlFor={`step-name-${workflow.id}`}>Name</Label>
                          <Input
                            id={`step-name-${workflow.id}`}
                            value={stepForm.name}
                            onChange={(event) =>
                              setStepForms((prev) => ({
                                ...prev,
                                [workflow.id]: {
                                  ...stepForm,
                                  name: event.target.value,
                                },
                              }))
                            }
                          />
                        </div>
                        <div>
                          <Label htmlFor={`step-delay-${workflow.id}`}>Delay (minutes)</Label>
                          <Input
                            id={`step-delay-${workflow.id}`}
                            type="number"
                            min={0}
                            value={stepForm.delay_minutes}
                            onChange={(event) =>
                              setStepForms((prev) => ({
                                ...prev,
                                [workflow.id]: {
                                  ...stepForm,
                                  delay_minutes: Number(event.target.value),
                                },
                              }))
                            }
                          />
                        </div>
                        <div>
                          <Label htmlFor={`step-channel-${workflow.id}`}>Channel</Label>
                          <Select
                            id={`step-channel-${workflow.id}`}
                            value={stepForm.channel}
                            onChange={(event) =>
                              setStepForms((prev) => ({
                                ...prev,
                                [workflow.id]: {
                                  ...stepForm,
                                  channel: event.target.value as StepFormState['channel'],
                                  notification_template_id: '',
                                  subject: '',
                                  body: '',
                                  staff_email: '',
                                  task_notes: '',
                                },
                              }))
                            }
                          >
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                            <option value="task">Task</option>
                            <option value="staff_email">Staff email</option>
                          </Select>
                        </div>
                        {stepForm.channel === 'email' || stepForm.channel === 'sms' ? (
                          <div>
                            <Label htmlFor={`step-template-${workflow.id}`}>Template</Label>
                            <Select
                              id={`step-template-${workflow.id}`}
                              value={stepForm.notification_template_id ?? ''}
                              onChange={(event) =>
                                setStepForms((prev) => ({
                                  ...prev,
                                  [workflow.id]: {
                                    ...stepForm,
                                    notification_template_id: event.target.value
                                      ? Number(event.target.value)
                                      : '',
                                  },
                                }))
                              }
                            >
                              <option value="">None</option>
                              {templates
                                .filter((template) => template.channel === stepForm.channel)
                                .map((template) => (
                                  <option key={template.id} value={template.id}>
                                    {template.name}
                                  </option>
                                ))}
                            </Select>
                          </div>
                        ) : stepForm.channel === 'staff_email' ? (
                          <div className="text-sm text-slate-500">
                            <p>Staff email steps send a direct message to the configured operator.</p>
                          </div>
                        ) : (
                          <div className="text-sm text-slate-500">
                            <p>No template required for task steps.</p>
                          </div>
                        )}
                      </div>
                      <div className="grid gap-3 md:grid-cols-3">
                        {(stepForm.channel === 'email' || stepForm.channel === 'staff_email') && (
                          <div className="md:col-span-3">
                            <Label htmlFor={`step-subject-${workflow.id}`}>Subject (optional)</Label>
                            <Input
                              id={`step-subject-${workflow.id}`}
                              value={stepForm.subject ?? ''}
                              onChange={(event) =>
                                setStepForms((prev) => ({
                                  ...prev,
                                  [workflow.id]: {
                                    ...stepForm,
                                    subject: event.target.value,
                                  },
                                }))
                              }
                              placeholder="e.g. Welcome visitor follow-up"
                            />
                          </div>
                        )}
                        {(stepForm.channel === 'staff_email' || stepForm.channel === 'email' || stepForm.channel === 'sms') && (
                          <div className="md:col-span-3">
                            <Label htmlFor={`step-body-${workflow.id}`}>
                              {stepForm.channel === 'sms' ? 'Message body (optional)' : 'Body (optional)'}
                            </Label>
                            <textarea
                              id={`step-body-${workflow.id}`}
                              value={stepForm.body ?? ''}
                              onChange={(event) =>
                                setStepForms((prev) => ({
                                  ...prev,
                                  [workflow.id]: {
                                    ...stepForm,
                                    body: event.target.value,
                                  },
                                }))
                              }
                              rows={3}
                              className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-200"
                              placeholder={
                                stepForm.channel === 'sms'
                                  ? 'Text message sent to the visitor'
                                  : 'Overrides the notification template body when provided'
                              }
                            />
                          </div>
                        )}
                        {stepForm.channel === 'staff_email' && (
                          <div className="md:col-span-3">
                            <Label htmlFor={`step-staff-email-${workflow.id}`}>Recipient email (optional)</Label>
                            <Input
                              id={`step-staff-email-${workflow.id}`}
                              type="email"
                              value={stepForm.staff_email ?? ''}
                              onChange={(event) =>
                                setStepForms((prev) => ({
                                  ...prev,
                                  [workflow.id]: {
                                    ...stepForm,
                                    staff_email: event.target.value,
                                  },
                                }))
                              }
                              placeholder="pastoralcare@example.com"
                            />
                            <p className="mt-1 text-xs text-slate-500">
                              Leave blank to notify the default visitor intake owners.
                            </p>
                          </div>
                        )}
                        {stepForm.channel === 'task' && (
                          <div className="md:col-span-3">
                            <Label htmlFor={`step-task-notes-${workflow.id}`}>Task notes (optional)</Label>
                            <textarea
                              id={`step-task-notes-${workflow.id}`}
                              value={stepForm.task_notes ?? ''}
                              onChange={(event) =>
                                setStepForms((prev) => ({
                                  ...prev,
                                  [workflow.id]: {
                                    ...stepForm,
                                    task_notes: event.target.value,
                                  },
                                }))
                              }
                              rows={3}
                              className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-200"
                              placeholder="Instructions for the volunteer or staff member completing the task"
                            />
                          </div>
                        )}
                      </div>
                      <div className="mt-6 space-y-3 rounded-md border border-slate-200 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                          <div>
                            <h6 className="text-sm font-semibold text-slate-800">Automation rules</h6>
                            <p className="text-xs text-slate-500">
                              Optionally limit when this step runs based on visitor context.
                            </p>
                          </div>
                          <div className="flex items-center gap-2">
                            <Label className="!mb-0 text-xs font-medium text-slate-600">Match</Label>
                            <Select
                              value={stepForm.rules_logic}
                              onChange={(event) =>
                                updateRulesLogic(workflow.id, event.target.value as RuleLogic)
                              }
                              className="text-sm"
                            >
                              <option value="all">All conditions</option>
                              <option value="any">Any condition</option>
                            </Select>
                          </div>
                        </div>
                        <div className="space-y-3">
                          {stepForm.rules.length === 0 ? (
                            <p className="text-sm text-slate-500">
                              No conditions yet. Add a rule to trigger this step only when criteria are met.
                            </p>
                          ) : (
                            stepForm.rules.map((rule) => {
                              const config = getRuleFieldConfig(rule.field) ?? RULE_FIELD_CONFIG[0];
                              return (
                                <div
                                  key={rule.id}
                                  className="grid gap-3 rounded-md border border-slate-200 p-3 md:grid-cols-[1.2fr,1.2fr,1fr,auto]"
                                >
                                  <div>
                                    <Label className="text-xs font-medium text-slate-600" htmlFor={`rule-field-${workflow.id}-${rule.id}`}>
                                      Field
                                    </Label>
                                    <Select
                                      id={`rule-field-${workflow.id}-${rule.id}`}
                                      value={rule.field}
                                      onChange={(event) => {
                                        const fieldConfig =
                                          getRuleFieldConfig(event.target.value) ?? RULE_FIELD_CONFIG[0];
                                        const nextOperator = fieldConfig.operatorOptions[0];
                                        const nextValue =
                                          fieldConfig.valueType === 'select'
                                            ? fieldConfig.selectOptions?.[0]?.value ?? ''
                                            : '';
                                        updateRuleField(workflow.id, rule.id, {
                                          field: fieldConfig.value,
                                          operator: nextOperator,
                                          value: nextValue,
                                        });
                                      }}
                                    >
                                      {RULE_FIELD_CONFIG.map((option) => (
                                        <option key={option.value} value={option.value}>
                                          {option.label}
                                        </option>
                                      ))}
                                    </Select>
                                  </div>
                                  <div>
                                    <Label className="text-xs font-medium text-slate-600" htmlFor={`rule-operator-${workflow.id}-${rule.id}`}>
                                      Operator
                                    </Label>
                                    <Select
                                      id={`rule-operator-${workflow.id}-${rule.id}`}
                                      value={rule.operator}
                                      onChange={(event) =>
                                        updateRuleField(workflow.id, rule.id, {
                                          operator: event.target.value as RuleOperator,
                                        })
                                      }
                                    >
                                      {config.operatorOptions.map((operator) => (
                                        <option key={operator} value={operator}>
                                          {RULE_OPERATOR_LABELS[operator]}
                                        </option>
                                      ))}
                                    </Select>
                                  </div>
                                  <div>
                                    <Label className="text-xs font-medium text-slate-600" htmlFor={`rule-value-${workflow.id}-${rule.id}`}>
                                      Value
                                    </Label>
                                    {config.valueType === 'select' ? (
                                      <Select
                                        id={`rule-value-${workflow.id}-${rule.id}`}
                                        value={rule.value}
                                        onChange={(event) =>
                                          updateRuleField(workflow.id, rule.id, {
                                            value: event.target.value,
                                          })
                                        }
                                      >
                                        {(config.selectOptions ?? []).map((option) => (
                                          <option key={option.value} value={option.value}>
                                            {option.label}
                                          </option>
                                        ))}
                                      </Select>
                                    ) : (
                                      <Input
                                        id={`rule-value-${workflow.id}-${rule.id}`}
                                        type={config.valueType === 'number' ? 'number' : 'text'}
                                        value={rule.value}
                                        placeholder={config.placeholder}
                                        onChange={(event) =>
                                          updateRuleField(workflow.id, rule.id, {
                                            value: event.target.value,
                                          })
                                        }
                                      />
                                    )}
                                  </div>
                                  <div className="flex items-end justify-end">
                                    <Button
                                      type="button"
                                      variant="ghost"
                                      size="sm"
                                      onClick={() => removeRuleFromWorkflow(workflow.id, rule.id)}
                                    >
                                      Remove
                                    </Button>
                                  </div>
                                </div>
                              );
                            })
                          )}
                        </div>
                        <div>
                          <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => addRuleToWorkflow(workflow.id)}
                          >
                            Add condition
                          </Button>
                        </div>
                      </div>
                      <div className="mt-3 flex justify-end">
                        <Button
                          type="button"
                          onClick={() => handleAddStep(workflow)}
                          disabled={addStepMutation.isPending}
                        >
                          {addStepMutation.isPending ? 'Adding…' : 'Add step'}
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </Card>
      </section>

      <section>
        <Card className="space-y-4">
          <h2 className="text-xl font-semibold text-slate-900">Follow-ups</h2>
          <div className="grid gap-4 md:grid-cols-3">
            <div>
              <Label htmlFor="followup-member-id" required>
                Member ID
              </Label>
              <Input
                id="followup-member-id"
                type="number"
                min={1}
                value={memberIdInput}
                onChange={(event) => setMemberIdInput(event.target.value)}
                required
              />
            </div>
            <div>
              <Label htmlFor="followup-workflow" required>
                Workflow
              </Label>
              <Select
                id="followup-workflow"
                value={selectedWorkflowId}
                onChange={(event) => setSelectedWorkflowId(event.target.value ? Number(event.target.value) : '')}
                required
              >
                <option value="">Select workflow…</option>
                {workflowOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </Select>
            </div>
            <div className="md:col-span-1 flex items-end justify-end">
              <Button type="button" onClick={() => startFollowupMutation.mutate()} disabled={startFollowupMutation.isPending}>
                {startFollowupMutation.isPending ? 'Starting…' : 'Start follow-up'}
              </Button>
            </div>
          </div>

          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableHeaderCell>ID</TableHeaderCell>
                  <TableHeaderCell>Member</TableHeaderCell>
                  <TableHeaderCell>Workflow</TableHeaderCell>
                  <TableHeaderCell>Current step</TableHeaderCell>
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Next run</TableHeaderCell>
                  <TableHeaderCell>Logs</TableHeaderCell>
                  <TableHeaderCell className="text-right">Actions</TableHeaderCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {followupsLoading && (
                  <TableRow>
                    <TableCell colSpan={6} className="py-4 text-center text-sm text-slate-500">
                      Loading follow-ups…
                    </TableCell>
                  </TableRow>
                )}
                {!followupsLoading && followups.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="py-4 text-center text-sm text-slate-500">
                      No active follow-ups yet.
                    </TableCell>
                  </TableRow>
                ) : null}
                {followups.map((followup: VisitorFollowup) => {
                  const isActive = followup.status === 'pending' || followup.status === 'in_progress';

                  return (
                    <TableRow key={followup.id}>
                      <TableCell>{followup.id}</TableCell>
                      <TableCell>{followup.member_id}</TableCell>
                      <TableCell>{followup.workflow?.name ?? followup.workflow_id}</TableCell>
                      <TableCell>{followup.current_step?.name ?? '—'}</TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            followup.status === 'completed'
                              ? 'success'
                              : followup.status === 'halted'
                              ? 'warning'
                              : 'info'
                          }
                        >
                          {followup.status.replace('_', ' ')}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {followup.next_run_at ? new Date(followup.next_run_at).toLocaleString() : '—'}
                      </TableCell>
                      <TableCell>
                        <Button
                          type="button"
                          variant="ghost"
                          onClick={() =>
                            setActiveFollowup((current) =>
                              current?.id === followup.id ? null : followup
                            )
                          }
                        >
                          View logs {followup.logs_count ? `(${followup.logs_count})` : ''}
                        </Button>
                      </TableCell>
                      <TableCell className="text-right">
                        {isActive ? (
                          <Button
                            type="button"
                            variant="ghost"
                            onClick={() => haltFollowupMutation.mutate(followup.id)}
                          >
                            Halt
                          </Button>
                        ) : (
                          <span className="text-sm text-slate-500">No actions</span>
                        )}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </TableContainer>
        </Card>
      </section>

      {activeFollowup && (
        <section>
          <Card className="space-y-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="text-lg font-semibold text-slate-900">Follow-up logs</h3>
                <p className="text-sm text-slate-500">
                  Workflow: {activeFollowup.workflow?.name ?? activeFollowup.workflow_id} • Member ID {activeFollowup.member_id}
                </p>
              </div>
              <Button type="button" variant="ghost" onClick={() => setActiveFollowup(null)}>
                Close
              </Button>
            </div>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableHeaderCell>Step</TableHeaderCell>
                    <TableHeaderCell>Status</TableHeaderCell>
                    <TableHeaderCell>Channel</TableHeaderCell>
                    <TableHeaderCell>Run at</TableHeaderCell>
                    <TableHeaderCell>Notes</TableHeaderCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {logsLoading && (
                    <TableRow>
                      <TableCell colSpan={5} className="py-4 text-center text-sm text-slate-500">
                        Loading logs…
                      </TableCell>
                    </TableRow>
                  )}
                  {!logsLoading && followupLogs.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5} className="py-4 text-center text-sm text-slate-500">
                        No logs recorded yet.
                      </TableCell>
                    </TableRow>
                  )}
                  {followupLogs.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell>{log.step?.name ?? '—'}</TableCell>
                      <TableCell>{log.status}</TableCell>
                      <TableCell>{log.channel ?? '—'}</TableCell>
                      <TableCell>{log.run_at ? new Date(log.run_at).toLocaleString() : '—'}</TableCell>
                      <TableCell>{log.notes ?? '—'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </Card>
        </section>
      )}
    </div>
  );
}

type VisitorFunnelStage = {
  status: string;
  label: string;
  total: number;
};

function buildVisitorFunnel(
  breakdown: Array<{ status: string; total: number }>
): VisitorFunnelStage[] {
  if (!breakdown.length) {
    return [];
  }

  const statusLabels: Record<string, string> = {
    pending: 'Pending',
    in_progress: 'In progress',
    completed: 'Completed',
    halted: 'Halted',
    queued: 'Queued',
    new: 'New',
  };
  const stageOrder = ['pending', 'in_progress', 'completed', 'halted'];
  const aggregated = new Map<string, number>();

  breakdown.forEach((item) => {
    const total = Number(item.total ?? 0);
    if (!Number.isNaN(total)) {
      aggregated.set(item.status, (aggregated.get(item.status) ?? 0) + total);
    }
  });

  const ordered: VisitorFunnelStage[] = [];

  stageOrder.forEach((status) => {
    if (aggregated.has(status)) {
      const total = aggregated.get(status) ?? 0;
      ordered.push({
        status,
        label: statusLabels[status] ?? status.replace(/_/g, ' '),
        total,
      });
      aggregated.delete(status);
    }
  });

  [...aggregated.entries()]
    .sort((a, b) => b[1] - a[1])
    .forEach(([status, total]) => {
      ordered.push({
        status,
        label: statusLabels[status] ?? status.replace(/_/g, ' '),
        total,
      });
    });

  return ordered;
}

function VisitorFunnel({ stages, total }: { stages: VisitorFunnelStage[]; total: number }) {
  if (!stages.length) {
    return <p className="text-sm text-slate-500">No follow-up activity yet.</p>;
  }

  const max = stages.reduce((acc, stage) => Math.max(acc, stage.total), 0);

  return (
    <ul className="space-y-4">
      {stages.map((stage) => {
        const widthPct = max > 0 ? Math.round((stage.total / max) * 100) : 0;
        const sharePct =
          total > 0 ? Math.round((stage.total / total) * 100) : null;
        const barColor = getVisitorFunnelColor(stage.status);

        return (
          <li key={stage.status} className="space-y-1">
            <div className="flex items-center justify-between text-xs text-slate-500">
              <span className="font-medium text-slate-700">{stage.label}</span>
              <span className="font-semibold text-slate-800">
                {stage.total}
                {sharePct !== null ? ` • ${sharePct}%` : ''}
              </span>
            </div>
            <div className="h-3 rounded-full bg-slate-200">
              <div
                className={`h-full rounded-full transition-all ${barColor}`}
                style={{ width: `${widthPct}%` }}
                role="presentation"
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}

function getVisitorFunnelColor(status: string): string {
  switch (status) {
    case 'completed':
      return 'bg-emerald-500';
    case 'in_progress':
      return 'bg-sky-500';
    case 'halted':
      return 'bg-amber-500';
    case 'pending':
      return 'bg-slate-400';
    default:
      return 'bg-slate-500';
  }
}

function describeStepMetadata(step: VisitorWorkflowStep): string {
  const metadata = (step.metadata ?? {}) as Record<string, unknown>;
  const parts: string[] = [];

  switch (step.channel) {
    case 'staff_email': {
      const recipient = metadata.email ? `Notify ${String(metadata.email)}` : 'Notify visitor team';
      const subject = metadata.subject ? `Subject: ${String(metadata.subject)}` : null;
      parts.push([recipient, subject].filter(Boolean).join(' • ') || 'Email staff operator');
      break;
    }
    case 'email': {
      if (metadata.subject) {
        parts.push(`Subject: ${String(metadata.subject)}`);
      }
      if (metadata.body) {
        parts.push('Custom body provided');
      }
      if (!metadata.subject && !metadata.body) {
        parts.push('Uses notification template');
      }
      break;
    }
    case 'sms': {
      parts.push(metadata.body ? 'Custom SMS message' : 'Uses notification template');
      break;
    }
    case 'task': {
      parts.push(metadata.body ? String(metadata.body) : 'Manual follow-up required');
      break;
    }
    default:
      break;
  }

  const rules = Array.isArray((metadata as Record<string, unknown>).rules)
    ? ((metadata as Record<string, unknown>).rules as Array<Record<string, unknown>>)
    : [];

  if (rules.length) {
    const logic = ((metadata as Record<string, unknown>).rules_logic === 'any' ? 'any' : 'all') as RuleLogic;
    const ruleDescriptions = rules
      .map((rule) => formatRuleDescription(rule))
      .filter((rule): rule is string => Boolean(rule));
    if (ruleDescriptions.length) {
      parts.push(
        `${logic === 'all' ? 'All conditions' : 'Any condition'}: ${ruleDescriptions.join('; ')}`
      );
    }
  }

  return parts.length ? parts.join(' • ') : '—';
}

function formatRuleDescription(rule: Record<string, unknown>): string | null {
  const field = typeof rule.field === 'string' ? rule.field : null;
  const operator = typeof rule.operator === 'string' ? (rule.operator as RuleOperator) : null;

  if (!field || !operator || rule.value === undefined || rule.value === null) {
    return null;
  }

  const config = getRuleFieldConfig(field);
  const operatorLabel = RULE_OPERATOR_LABELS[operator] ?? operator;
  let valueLabel = String(rule.value);

  if (config) {
    if (config.valueType === 'select' && config.selectOptions) {
      const match = config.selectOptions.find((option) => option.value === String(rule.value));
      valueLabel = match ? match.label : String(rule.value);
    } else if (config.valueType === 'number' && typeof rule.value === 'number') {
      valueLabel = String(rule.value);
    }
    return `${config.label} ${operatorLabel} ${valueLabel}`;
  }

  return `${field} ${operatorLabel} ${valueLabel}`;
}
