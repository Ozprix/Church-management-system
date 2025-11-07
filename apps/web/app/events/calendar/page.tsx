"use client";

import { useEffect, useMemo, useRef, useState, type ReactElement } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  DndContext,
  PointerSensor,
  type DragEndEvent,
  useDroppable,
  useDraggable,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import clsx from "clsx";
import {
  addDays,
  addMonths,
  addMinutes,
  differenceInMinutes,
  endOfMonth,
  endOfWeek,
  format,
  isSameDay,
  isSameMonth,
  parseISO,
  startOfMonth,
  startOfWeek,
  subMonths,
} from "date-fns";
import { Button, Input, useToast } from "@church/ui";
import { useAuth } from "@/providers/auth-provider";
import { apiFetch } from "@/lib/api";
import type { GatheringSummary } from "@/types/events";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

type CalendarResponse = {
  data: GatheringSummary[];
};

type CreateGatheringResponse = {
  data: GatheringSummary;
};

type CalendarDayInfo = {
  date: Date;
  key: string;
  isCurrentMonth: boolean;
  isToday: boolean;
};

type CalendarDay = CalendarDayInfo & {
  events: GatheringSummary[];
};

type EditFormState = {
  id: number;
  name: string;
  date: string;
  startTime: string;
  endTime: string;
  location: string;
};

type UpdateGatheringPayload = {
  id: number;
  payload: {
    name?: string;
    location?: string | null;
    starts_at?: string;
    ends_at?: string;
    status?: string;
    service_id?: number | null;
  };
};

type NewEventFormState = {
  name: string;
  date: string;
  startTime: string;
  endTime: string;
  location: string;
  status: string;
};

type CreateGatheringPayload = {
  name: string;
  status?: string;
  starts_at: string;
  ends_at: string;
  location?: string | null;
  service_id?: number | null;
  notes?: string | null;
};

export default function EventsCalendarPage(): React.ReactElement {
  const { user, loading } = useAuth();
  const { pushToast } = useToast();
  const [currentMonth, setCurrentMonth] = useState(() => startOfMonth(new Date()));
  const queryClient = useQueryClient();
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
  const [editingForm, setEditingForm] = useState<EditFormState | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [newEventForm, setNewEventForm] = useState<NewEventFormState | null>(null);
  const [newEventError, setNewEventError] = useState<string | null>(null);
  const newEventNameRef = useRef<HTMLInputElement | null>(null);

  const rangeParams = useMemo(() => {
    const from = startOfMonth(currentMonth).toISOString();
    const to = endOfMonth(currentMonth).toISOString();
    return { from, to };
  }, [currentMonth]);

  const gatheringsQuery = useQuery<CalendarResponse>({
    queryKey: ["events-calendar", rangeParams.from, rangeParams.to],
    queryFn: async () =>
      apiFetch<CalendarResponse>(
        `/api/v1/gatherings/calendar?from=${encodeURIComponent(rangeParams.from)}&to=${encodeURIComponent(
          rangeParams.to
        )}`
      ),
    enabled: !!user,
    placeholderData: (previous) => previous,
  });

  const eventsByDay = useMemo(() => {
    const map = new Map<string, GatheringSummary[]>();
    const data = gatheringsQuery.data?.data ?? [];

    for (const gathering of data) {
      const startsAt = parseISO(gathering.starts_at);
      const dayKey = format(startsAt, "yyyy-MM-dd");
      const existing = map.get(dayKey) ?? [];
      existing.push(gathering);
      map.set(dayKey, existing);
    }

    for (const [key, list] of map.entries()) {
      list.sort((a, b) => a.starts_at.localeCompare(b.starts_at));
      map.set(key, list);
    }

    return map;
  }, [gatheringsQuery.data]);

  const calendarDays = useMemo<CalendarDayInfo[]>(() => {
    const start = startOfWeek(startOfMonth(currentMonth), { weekStartsOn: 0 });
    const end = endOfWeek(endOfMonth(currentMonth), { weekStartsOn: 0 });

    const days: CalendarDayInfo[] = [];

    for (let cursor = start; cursor <= end; cursor = addDays(cursor, 1)) {
      days.push({
        date: cursor,
        key: format(cursor, "yyyy-MM-dd"),
        isCurrentMonth: isSameMonth(cursor, currentMonth),
        isToday: isSameDay(cursor, new Date()),
      });
    }

    return days;
  }, [currentMonth]);

  const weeks = useMemo(() => {
    const calendarGrid: CalendarDay[] = calendarDays.map((day) => ({
      ...day,
      events: eventsByDay.get(day.key) ?? [],
    }));

    const weekRows: CalendarDay[][] = [];
    for (let index = 0; index < calendarGrid.length; index += 7) {
      weekRows.push(calendarGrid.slice(index, index + 7));
    }
    return weekRows;
  }, [calendarDays, eventsByDay]);

  const updateGatheringMutation = useMutation({
    mutationFn: async ({ id, payload }: UpdateGatheringPayload) =>
      apiFetch(`/api/v1/gatherings/${id}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
      }),
    onMutate: async ({ id, payload }) => {
      await queryClient.cancelQueries({ queryKey: ["events-calendar", rangeParams.from, rangeParams.to] });
      const previous = queryClient.getQueryData<CalendarResponse>(["events-calendar", rangeParams.from, rangeParams.to]);
      const affected = previous?.data.find((event) => event.id === id);

      if (previous) {
        queryClient.setQueryData<CalendarResponse>(["events-calendar", rangeParams.from, rangeParams.to], {
          data: previous.data.map((event) =>
            event.id === id
              ? {
                  ...event,
                  ...payload,
                }
              : event
          ),
        });
      }

      return { previous, affected };
    },
    onError: (error, _variables, context) => {
      if (context?.previous) {
        queryClient.setQueryData(["events-calendar", rangeParams.from, rangeParams.to], context.previous);
      }

      const message = resolveErrorMessage(error);

      if (context?.affected && editingForm?.id === context.affected.id) {
        setEditError(message);
      }

      pushToast({
        title: "Update failed",
        description: message,
        variant: "error",
      });
    },
    onSuccess: (_data, variables, context) => {
      setEditingForm((previous) => {
        if (previous?.id === variables.id) {
          return null;
        }
        return previous;
      });

      pushToast({
        title: "Event updated",
        description: context?.affected ? `Saved changes for ${context.affected.name}.` : undefined,
        variant: "success",
      });
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ["events-calendar", rangeParams.from, rangeParams.to] });
    },
  });

  const pendingEventId =
    updateGatheringMutation.isPending && updateGatheringMutation.variables
      ? (updateGatheringMutation.variables as UpdateGatheringPayload).id
      : null;

  const resolveErrorMessage = (error: unknown): string => {
    if (error instanceof Error) {
      let raw = error.message ?? "Something went wrong.";

      if (raw.startsWith("API request failed")) {
        const separator = raw.indexOf(":");
        if (separator !== -1) {
          raw = raw.slice(separator + 1).trim();
        }
      }

      const jsonStart = raw.indexOf("{");
      if (jsonStart !== -1) {
        try {
          const parsed = JSON.parse(raw.slice(jsonStart));
          if (parsed?.errors) {
            const firstKey = Object.keys(parsed.errors)[0];
            if (firstKey) {
              const issues = parsed.errors[firstKey];
              if (Array.isArray(issues) && issues.length > 0 && typeof issues[0] === "string") {
                return issues[0];
              }
            }
          }
          if (parsed?.message && typeof parsed.message === "string") {
            return parsed.message;
          }
        } catch {
          // ignore parse errors
        }
      }
      return raw;
    }
    return "Something went wrong.";
  };

  const createGatheringMutation = useMutation({
    mutationFn: async (payload: CreateGatheringPayload) =>
      apiFetch<CreateGatheringResponse>("/api/v1/gatherings", {
        method: "POST",
        body: JSON.stringify(payload),
      }),
    onMutate: async (payload) => {
      await queryClient.cancelQueries({ queryKey: ["events-calendar", rangeParams.from, rangeParams.to] });
      const previous = queryClient.getQueryData<CalendarResponse>(["events-calendar", rangeParams.from, rangeParams.to]);

      const tempId = Date.now() * -1;
      const optimisticEvent: GatheringSummary = {
        id: tempId,
        uuid: `temp-${tempId}`,
        name: payload.name,
        status: payload.status ?? "scheduled",
        starts_at: payload.starts_at,
        ends_at: payload.ends_at,
        location: payload.location ?? null,
        notes: null,
        service: null,
      };

      if (previous) {
        queryClient.setQueryData<CalendarResponse>(["events-calendar", rangeParams.from, rangeParams.to], {
          data: [...previous.data, optimisticEvent],
        });
      } else {
        queryClient.setQueryData<CalendarResponse>(["events-calendar", rangeParams.from, rangeParams.to], {
          data: [optimisticEvent],
        });
      }

      return { previous, tempId };
    },
    onError: (error, _variables, context) => {
      if (context?.previous) {
        queryClient.setQueryData(["events-calendar", rangeParams.from, rangeParams.to], context.previous);
      } else {
        queryClient.setQueryData<CalendarResponse>(["events-calendar", rangeParams.from, rangeParams.to], { data: [] });
      }

      const message = resolveErrorMessage(error);
      setNewEventError(message);
      pushToast({
        title: "Could not create event",
        description: message,
        variant: "error",
      });
    },
    onSuccess: (response, _variables, context) => {
      const created = response.data;
      queryClient.setQueryData<CalendarResponse>(["events-calendar", rangeParams.from, rangeParams.to], (current) => {
        if (!current) {
          return { data: [created] };
        }

        const withoutPlaceholder = context?.tempId
          ? current.data.filter((event) => event.id !== context.tempId)
          : current.data;

        return {
          data: [...withoutPlaceholder, created],
        };
      });

      setNewEventForm(null);
      setNewEventError(null);
      pushToast({
        title: "Event created",
        description: `Added ${created.name} to the calendar.`,
        variant: "success",
      });
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ["events-calendar", rangeParams.from, rangeParams.to] });
    },
  });

  const isCreatingEvent = createGatheringMutation.isPending;

  const openQuickEdit = (event: GatheringSummary) => {
    const startsAt = parseISO(event.starts_at);
    const endsAt = parseISO(event.ends_at);

    setEditingForm({
      id: event.id,
      name: event.name,
      date: format(startsAt, "yyyy-MM-dd"),
      startTime: format(startsAt, "HH:mm"),
      endTime: format(endsAt, "HH:mm"),
      location: event.location ?? "",
    });
    setEditError(null);
  };

  const closeQuickEdit = () => {
    setEditingForm(null);
    setEditError(null);
  };

  const handleQuickEditChange = <T extends keyof EditFormState>(field: T, value: EditFormState[T]) => {
    setEditError(null);
    setEditingForm((previous) => {
      if (!previous) {
        return previous;
      }

      return {
        ...previous,
        [field]: value,
      };
    });
  };

  const submitQuickEdit = () => {
    if (!editingForm) {
      return;
    }

    const trimmedName = editingForm.name.trim();
    if (trimmedName.length === 0) {
      setEditError("Name is required.");
      return;
    }

    const start = new Date(`${editingForm.date}T${editingForm.startTime}`);
    const end = new Date(`${editingForm.date}T${editingForm.endTime}`);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
      setEditError("Start and end times must be valid.");
      return;
    }

    if (end <= start) {
      setEditError("End time must be after the start time.");
      return;
    }

    const location = editingForm.location.trim();

    updateGatheringMutation.mutate({
      id: editingForm.id,
      payload: {
        name: trimmedName,
        location: location.length > 0 ? location : null,
        starts_at: start.toISOString(),
        ends_at: end.toISOString(),
      },
    });
  };

  const openNewEventModal = (date: Date) => {
    setNewEventForm({
      name: "",
      date: format(date, "yyyy-MM-dd"),
      startTime: "09:00",
      endTime: "10:30",
      location: "",
      status: "scheduled",
    });
    setNewEventError(null);
  };

  const closeNewEventModal = () => {
    setNewEventForm(null);
    setNewEventError(null);
  };

  const handleNewEventChange = <T extends keyof NewEventFormState>(field: T, value: NewEventFormState[T]) => {
    setNewEventError(null);
    setNewEventForm((previous) => {
      if (!previous) {
        return previous;
      }

      return {
        ...previous,
        [field]: value,
      };
    });
  };

  const submitNewEvent = () => {
    if (!newEventForm) {
      return;
    }

    const trimmedName = newEventForm.name.trim();
    if (trimmedName.length === 0) {
      setNewEventError("Name is required.");
      return;
    }

    const start = new Date(`${newEventForm.date}T${newEventForm.startTime}`);
    const end = new Date(`${newEventForm.date}T${newEventForm.endTime}`);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
      setNewEventError("Start and end times must be valid.");
      return;
    }

    if (end <= start) {
      setNewEventError("End time must be after the start time.");
      return;
    }

    const location = newEventForm.location.trim();

    createGatheringMutation.mutate({
      name: trimmedName,
      status: newEventForm.status,
      starts_at: start.toISOString(),
      ends_at: end.toISOString(),
      location: location.length > 0 ? location : null,
    });
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (!active || !over) {
      return;
    }

    const eventData = active.data.current?.event as GatheringSummary | undefined;
    const dropData = over.data.current as { key?: string; date?: Date } | undefined;

    if (!eventData || !dropData?.date) {
      return;
    }

    const originalStart = parseISO(eventData.starts_at);
    const originalEnd = parseISO(eventData.ends_at);

    const newStart = new Date(dropData.date);
    newStart.setHours(originalStart.getHours(), originalStart.getMinutes(), 0, 0);

    const durationMinutes = Math.max(differenceInMinutes(originalEnd, originalStart), 30);
    const newEnd = addMinutes(newStart, durationMinutes);

    if (newStart.toISOString() === eventData.starts_at && newEnd.toISOString() === eventData.ends_at) {
      return;
    }

    updateGatheringMutation.mutate({
      id: eventData.id,
      payload: {
        starts_at: newStart.toISOString(),
        ends_at: newEnd.toISOString(),
      },
    });
  };

  useEffect(() => {
    if (!newEventForm) {
      return;
    }

    const input = newEventNameRef.current;
    if (input) {
      input.focus();
      input.select();
    }

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        event.preventDefault();
        closeNewEventModal();
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [newEventForm]);

  if (loading) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">Loading…</p>
      </main>
    );
  }

  if (!user) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">
          You must sign in to view events.{" "}
          <a className="underline" href="/login">
            Go to login
          </a>
          .
        </p>
      </main>
    );
  }

  const isFetching = gatheringsQuery.isFetching;
  const error = gatheringsQuery.error as Error | null;

  const displayMonth = format(currentMonth, "MMMM yyyy");

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-6 py-10">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900">Events calendar</h1>
        <p className="text-sm text-slate-600">
          Browse upcoming gatherings, review conflicts, and drill into ticketing/registrations.
        </p>
      </header>

      <Card>
        <CardHeader className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <CardTitle className="text-lg">{displayMonth}</CardTitle>
            <CardDescription>
              Showing gatherings between {format(parseISO(rangeParams.from), "MMM d")} –{" "}
              {format(parseISO(rangeParams.to), "MMM d, yyyy")}.
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setCurrentMonth((prev) => subMonths(prev, 1))}
              className="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
            >
              Previous
            </button>
            <button
              type="button"
              onClick={() => setCurrentMonth(startOfMonth(new Date()))}
              className="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
            >
              Today
            </button>
            <button
              type="button"
              onClick={() => setCurrentMonth((prev) => addMonths(prev, 1))}
              className="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
            >
              Next
            </button>
          </div>
        </CardHeader>
        <CardContent className="space-y-6">
          {error && (
            <div className="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {error.message}
            </div>
          )}

          <DndContext sensors={sensors} onDragEnd={handleDragEnd}>
            <section className="rounded-lg border border-slate-200">
              <header className="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"].map((dayName) => (
                  <div key={dayName} className="px-3 py-2 text-center">
                    {dayName}
                  </div>
                ))}
              </header>
              <div className="grid grid-cols-7">
                {weeks.map((week, weekIndex) => (
                  <div key={`week-${weekIndex}`} className="contents">
                    {week.map((day) => (
                      <CalendarDayCell
                        key={day.key}
                        day={day}
                        onAddEvent={openNewEventModal}
                        onEditStart={openQuickEdit}
                        onEditChange={handleQuickEditChange}
                        onEditCancel={closeQuickEdit}
                        onEditSubmit={submitQuickEdit}
                        editingForm={editingForm}
                        editError={editError}
                        pendingEventId={pendingEventId}
                      />
                    ))}
                  </div>
                ))}
              </div>
            </section>
          </DndContext>

          {eventsByDay.size === 0 && !isFetching && (
            <p className="text-sm text-slate-500">No gatherings scheduled for this month.</p>
          )}

      {isFetching && (
        <p className="text-sm text-slate-500">Refreshing events…</p>
      )}
    </CardContent>
  </Card>
      {newEventForm && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="new-event-title"
          aria-describedby="new-event-description"
          className="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/40 px-4 py-10"
          onClick={closeNewEventModal}
        >
          <div
            className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="flex items-start justify-between">
              <div>
                <h2 id="new-event-title" className="text-lg font-semibold text-slate-900">
                  Schedule new gathering
                </h2>
                <p id="new-event-description" className="text-sm text-slate-500">
                  Set the basics now; you can fine-tune ticketing and automation from the event page later.
                </p>
              </div>
              <button
                type="button"
                onClick={closeNewEventModal}
                className="rounded-full p-1 text-slate-500 hover:bg-slate-100"
                aria-label="Close"
              >
                <span className="sr-only">Close</span>
                <span aria-hidden="true" className="text-lg font-semibold leading-none">
                  ×
                </span>
              </button>
            </div>
            <form
              className="mt-6 space-y-4"
              onSubmit={(event) => {
                event.preventDefault();
                submitNewEvent();
              }}
            >
              <div>
                <label htmlFor="new-event-name" className="mb-1 block text-xs font-semibold uppercase text-slate-500">
                  Name
                </label>
                <Input
                  id="new-event-name"
                  value={newEventForm.name}
                  onChange={(e) => handleNewEventChange("name", e.target.value)}
                  placeholder="e.g. Volunteer Rally"
                  ref={newEventNameRef}
                  required
                />
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label htmlFor="new-event-date" className="mb-1 block text-xs font-semibold uppercase text-slate-500">
                    Date
                  </label>
                  <input
                    id="new-event-date"
                    type="date"
                    value={newEventForm.date}
                    onChange={(e) => handleNewEventChange("date", e.target.value)}
                    className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                    required
                  />
                </div>
                <div>
                  <label htmlFor="new-event-status" className="mb-1 block text-xs font-semibold uppercase text-slate-500">
                    Status
                  </label>
                  <select
                    id="new-event-status"
                    value={newEventForm.status}
                    onChange={(e) => handleNewEventChange("status", e.target.value)}
                    className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                  >
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label htmlFor="new-event-start" className="mb-1 block text-xs font-semibold uppercase text-slate-500">
                    Starts at
                  </label>
                  <input
                    id="new-event-start"
                    type="time"
                    value={newEventForm.startTime}
                    onChange={(e) => handleNewEventChange("startTime", e.target.value)}
                    className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                    required
                  />
                </div>
                <div>
                  <label htmlFor="new-event-end" className="mb-1 block text-xs font-semibold uppercase text-slate-500">
                    Ends at
                  </label>
                  <input
                    id="new-event-end"
                    type="time"
                    value={newEventForm.endTime}
                    onChange={(e) => handleNewEventChange("endTime", e.target.value)}
                    className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                    required
                  />
                </div>
              </div>
              <div>
                <label htmlFor="new-event-location" className="mb-1 block text-xs font-semibold uppercase text-slate-500">
                  Location
                </label>
                <Input
                  id="new-event-location"
                  value={newEventForm.location}
                  onChange={(e) => handleNewEventChange("location", e.target.value)}
                  placeholder="Optional"
                />
              </div>
              {newEventError && (
                <p className="text-sm font-medium text-rose-600">{newEventError}</p>
              )}
              <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={closeNewEventModal}>
                  Cancel
                </Button>
                <Button type="submit" loading={isCreatingEvent}>
                  Create event
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
  </main>
  );
}

type CalendarDayCellProps = {
  day: CalendarDay;
  onAddEvent: (date: Date) => void;
  onEditStart: (event: GatheringSummary) => void;
  onEditChange: <T extends keyof EditFormState>(field: T, value: EditFormState[T]) => void;
  onEditCancel: () => void;
  onEditSubmit: () => void;
  editingForm: EditFormState | null;
  editError: string | null;
  pendingEventId: number | null;
};

function CalendarDayCell({
  day,
  onAddEvent,
  onEditStart,
  onEditChange,
  onEditCancel,
  onEditSubmit,
  editingForm,
  editError,
  pendingEventId,
}: CalendarDayCellProps): ReactElement {
  const { isOver, setNodeRef } = useDroppable({
    id: `day-${day.key}`,
    data: { key: day.key, date: day.date },
  });

  return (
    <div
      ref={setNodeRef}
      className={clsx(
        "flex min-h-[160px] flex-col gap-2 border-r border-b border-slate-200 p-3 text-sm transition",
        !day.isCurrentMonth && "bg-slate-50 text-slate-400",
        day.isToday && "bg-indigo-50",
        isOver && "ring-2 ring-indigo-400 ring-offset-1"
      )}
    >
      <div className="flex items-start justify-between text-xs font-semibold uppercase tracking-wide text-slate-500">
        <span>{format(day.date, "d")}</span>
        <button
          type="button"
          onClick={() => onAddEvent(day.date)}
          className="rounded border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 transition hover:bg-slate-100"
        >
          + New
        </button>
      </div>
      <div className="flex flex-1 flex-col gap-2 overflow-y-auto">
        {day.events.length === 0 ? (
          <p className="text-xs text-slate-400">No gatherings</p>
        ) : (
          day.events.map((event) => (
            <CalendarEventItem
              key={event.id}
              event={event}
              editingForm={editingForm}
              editError={editError}
              onEditStart={onEditStart}
              onEditChange={onEditChange}
              onEditCancel={onEditCancel}
              onEditSubmit={onEditSubmit}
              isUpdating={pendingEventId === event.id}
            />
          ))
        )}
      </div>
    </div>
  );
}

type CalendarEventItemProps = {
  event: GatheringSummary;
  editingForm: EditFormState | null;
  editError: string | null;
  onEditStart: (event: GatheringSummary) => void;
  onEditChange: <T extends keyof EditFormState>(field: T, value: EditFormState[T]) => void;
  onEditCancel: () => void;
  onEditSubmit: () => void;
  isUpdating: boolean;
};

function CalendarEventItem({
  event,
  editingForm,
  editError,
  onEditStart,
  onEditChange,
  onEditCancel,
  onEditSubmit,
  isUpdating,
}: CalendarEventItemProps): ReactElement {
  const isEditing = editingForm?.id === event.id;
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `event-${event.id}`,
    data: { event },
    disabled: isEditing || isUpdating,
  });

  const style = transform ? { transform: CSS.Translate.toString(transform) } : undefined;
  const baseId = `event-${event.id}`;

  return (
    <article
      ref={setNodeRef}
      style={style}
      className={clsx(
        "flex flex-col gap-2 rounded border border-slate-200 bg-white p-2 text-xs shadow-sm transition hover:border-slate-300",
        isDragging && "ring-2 ring-indigo-500 ring-offset-1",
        isUpdating && "opacity-60"
      )}
      {...(!isEditing ? { ...attributes, ...listeners } : {})}
    >
      {isEditing && editingForm ? (
        <form
          className="flex flex-col gap-2"
          onSubmit={(event) => {
            event.preventDefault();
            onEditSubmit();
          }}
        >
          <div>
            <label htmlFor={`${baseId}-name`} className="mb-1 block text-[11px] font-semibold uppercase text-slate-500">
              Name
            </label>
            <Input
              id={`${baseId}-name`}
              value={editingForm.name}
              onChange={(e) => onEditChange("name", e.target.value)}
              required
            />
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label htmlFor={`${baseId}-date`} className="mb-1 block text-[11px] font-semibold uppercase text-slate-500">
                Date
              </label>
              <input
                id={`${baseId}-date`}
                type="date"
                value={editingForm.date}
                onChange={(e) => onEditChange("date", e.target.value)}
                className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
              />
            </div>
            <div>
              <label htmlFor={`${baseId}-location`} className="mb-1 block text-[11px] font-semibold uppercase text-slate-500">
                Location
              </label>
              <Input
                id={`${baseId}-location`}
                value={editingForm.location}
                onChange={(e) => onEditChange("location", e.target.value)}
                placeholder="Optional"
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label htmlFor={`${baseId}-start`} className="mb-1 block text-[11px] font-semibold uppercase text-slate-500">
                Starts
              </label>
              <input
                id={`${baseId}-start`}
                type="time"
                value={editingForm.startTime}
                onChange={(e) => onEditChange("startTime", e.target.value)}
                className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
              />
            </div>
            <div>
              <label htmlFor={`${baseId}-end`} className="mb-1 block text-[11px] font-semibold uppercase text-slate-500">
                Ends
              </label>
              <input
                id={`${baseId}-end`}
                type="time"
                value={editingForm.endTime}
                onChange={(e) => onEditChange("endTime", e.target.value)}
                className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
              />
            </div>
          </div>
          {editError && editingForm.id === event.id && (
            <p className="text-[11px] font-medium text-rose-600">{editError}</p>
          )}
          <div className="flex justify-end gap-2">
            <Button type="button" variant="ghost" size="sm" onClick={onEditCancel}>
              Cancel
            </Button>
            <Button type="submit" size="sm" loading={isUpdating}>
              Save
            </Button>
          </div>
        </form>
      ) : (
        <>
          <div className="flex items-start justify-between gap-2">
            <div className="flex-1">
              <p className="truncate text-sm font-semibold text-slate-900">{event.name}</p>
              <p className="text-[11px] uppercase tracking-wide text-slate-500">{event.status}</p>
            </div>
            <div className="text-right text-[11px] text-slate-600">
              <p>{format(parseISO(event.starts_at), "h:mm a")}</p>
              <p>{format(parseISO(event.ends_at), "h:mm a")}</p>
            </div>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-600">
            <span>{event.location ?? "Location TBD"}</span>
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => onEditStart(event)}
                className="text-indigo-600 hover:underline"
              >
                Quick edit
              </button>
              <a href={`/events/${event.uuid}`} className="text-indigo-600 hover:underline">
                Manage
              </a>
            </div>
          </div>
          {event.service && (
            <p className="text-[11px] text-slate-500">
              Service: <span className="font-medium text-slate-700">{event.service.name}</span>
            </p>
          )}
        </>
      )}
    </article>
  );
}
