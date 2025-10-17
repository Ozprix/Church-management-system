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
} from '@/lib/api/visitors';
import { useVisitorWorkflows } from '@/hooks/use-visitor-workflows';
import { useVisitorFollowups } from '@/hooks/use-visitor-followups';
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
} from '@church/ui';
import { ApiError } from '@/lib/api/http';

interface StepFormState {
  step_number: number;
  name: string;
  delay_minutes: number;
  channel: 'email' | 'sms' | 'task';
  notification_template_id?: number | '';
}

function defaultStepForm(): StepFormState {
  return {
    step_number: 1,
    name: '',
    delay_minutes: 0,
    channel: 'email',
    notification_template_id: '',
  };
}

export default function VisitorsPage() {
  const tenantId = useTenantId();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();

  const { data: workflows = [], isLoading: workflowsLoading } = useVisitorWorkflows();
  const { data: followups = [], isLoading: followupsLoading } = useVisitorFollowups();
  const { data: templatesResponse } = useNotificationTemplates();
  const templates = templatesResponse?.data ?? [];

  const [workflowName, setWorkflowName] = useState('');
  const [workflowDescription, setWorkflowDescription] = useState('');
  const [memberIdInput, setMemberIdInput] = useState('');
  const [selectedWorkflowId, setSelectedWorkflowId] = useState<number | ''>('');
  const [stepForms, setStepForms] = useState<Record<number, StepFormState>>({});

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
      return addVisitorWorkflowStep(tenantId, workflowId, {
        step_number: form.step_number,
        name: form.name || undefined,
        delay_minutes: form.delay_minutes,
        channel: form.channel,
        notification_template_id:
          form.channel === 'task' ? undefined : form.notification_template_id || undefined,
        metadata: undefined,
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
                              <TableCell colSpan={6} className="py-3 text-center text-sm text-slate-500">
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
                                },
                              }))
                            }
                          >
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                            <option value="task">Task</option>
                          </Select>
                        </div>
                        {stepForm.channel !== 'task' ? (
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
                        ) : (
                          <div className="text-sm text-slate-500">
                            <p>No template required for task steps.</p>
                          </div>
                        )}
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
                  <TableHeaderCell>Status</TableHeaderCell>
                  <TableHeaderCell>Next run</TableHeaderCell>
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
                {followups.map((followup: VisitorFollowup) => (
                  <TableRow key={followup.id}>
                    <TableCell>{followup.id}</TableCell>
                    <TableCell>{followup.member_id}</TableCell>
                    <TableCell>{followup.workflow?.name ?? followup.workflow_id}</TableCell>
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
                    <TableCell className="text-right">
                      {followup.status !== 'halted' && followup.status !== 'completed' ? (
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
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Card>
      </section>
    </div>
  );
}
