"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { format, parseISO } from "date-fns";
import { useAuth } from "@/providers/auth-provider";
import { apiFetch } from "@/lib/api";
import type { GatheringDetail } from "@/types/events";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

type GatheringResponse = {
  data: GatheringDetail;
};

type TicketPayload = {
  name: string;
  price: number;
  capacity?: number | null;
  currency?: string;
};

type RegistrationPayload = {
  ticket_type_id?: number | null;
  quantity?: number;
  name?: string;
  email?: string;
  phone?: string | null;
};

export default function GatheringDetailPage(): React.ReactElement {
  const params = useParams<{ uuid: string }>();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { user, loading } = useAuth();

  const [ticketForm, setTicketForm] = useState<TicketPayload>({
    name: "",
    price: 0,
    capacity: null,
  });

  const [registrationForm, setRegistrationForm] = useState<RegistrationPayload>({
    ticket_type_id: undefined,
    quantity: 1,
    name: "",
    email: "",
    phone: "",
  });

  const gatheringQuery = useQuery<GatheringResponse>({
    queryKey: ["gathering-detail", params.uuid],
    queryFn: async () => apiFetch<GatheringResponse>(`/api/v1/gatherings/${params.uuid}`),
    enabled: !!user && !!params.uuid,
  });

  const gathering = gatheringQuery.data?.data;
  const ticketTypes = gathering?.ticket_types ?? [];
  const registrations = gathering?.registrations ?? [];

  const createTicketType = useMutation({
    mutationFn: async (payload: TicketPayload) =>
      apiFetch(`/api/v1/gatherings/${params.uuid}/ticket-types`, {
        method: "POST",
        body: JSON.stringify(payload),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["gathering-detail", params.uuid] });
      setTicketForm({
        name: "",
        price: 0,
        capacity: null,
      });
    },
  });

  const createRegistration = useMutation({
    mutationFn: async (payload: RegistrationPayload) =>
      apiFetch(`/api/v1/gatherings/${params.uuid}/registrations`, {
        method: "POST",
        body: JSON.stringify({
          ...payload,
          status: "confirmed",
        }),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["gathering-detail", params.uuid] });
      setRegistrationForm((prev) => ({
        ticket_type_id: prev.ticket_type_id,
        quantity: 1,
        name: "",
        email: "",
        phone: "",
      }));
    },
  });

  const checkInRegistration = useMutation({
    mutationFn: async (registrationId: number) =>
      apiFetch(`/api/v1/gatherings/${params.uuid}/registrations/${registrationId}/check-in`, {
        method: "POST",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["gathering-detail", params.uuid] });
    },
  });

  const primaryTicketTypeId = ticketTypes[0]?.id ?? undefined;

  useEffect(() => {
    if (gathering && primaryTicketTypeId) {
      setRegistrationForm((prev) => ({
        ...prev,
        ticket_type_id: prev.ticket_type_id ?? primaryTicketTypeId,
      }));
    }
  }, [gathering, primaryTicketTypeId]);

  if (loading || gatheringQuery.isLoading) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">Loading event…</p>
      </main>
    );
  }

  if (!user) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-slate-500">
          You must sign in to manage events.{" "}
          <a className="underline" href="/login">
            Go to login
          </a>
          .
        </p>
      </main>
    );
  }

  if (!gathering) {
    return (
      <main className="flex min-h-screen items-center justify-center">
        <div className="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          Unable to load gathering.{" "}
          <button onClick={() => router.push("/events/calendar")} className="underline">
            Return to calendar
          </button>
        </div>
      </main>
    );
  }

  const ticketCount = registrations.reduce((acc, registration) => acc + (registration.quantity ?? 0), 0);

  const error = gatheringQuery.error as Error | null;

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-6 py-10">
      <header className="flex flex-col gap-2">
        <button
          onClick={() => router.back()}
          className="w-fit text-sm text-slate-500 hover:underline"
          type="button"
        >
          ← Back to events
        </button>
        <h1 className="text-2xl font-semibold text-slate-900">{gathering.name}</h1>
        <p className="text-sm text-slate-600">
          {format(parseISO(gathering.starts_at), "EEEE, MMM d • h:mm a")} – {format(parseISO(gathering.ends_at), "h:mm a")}{" "}
          · {gathering.location ?? "Location TBD"}
        </p>
        <p className="text-xs uppercase tracking-wide text-slate-500">{gathering.status}</p>
      </header>

      {error && (
        <div className="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error.message}
        </div>
      )}

      <section className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Ticket types</CardTitle>
            <CardDescription>Manage ticket inventory and pricing for this gathering.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {ticketTypes.length === 0 && (
              <p className="text-sm text-slate-500">No ticket types yet. Create one below.</p>
            )}
            {ticketTypes.map((ticket) => (
              <div key={ticket.id} className="rounded border border-slate-200 bg-white p-3 text-sm text-slate-700">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="text-base font-semibold text-slate-900">{ticket.name}</p>
                    <p className="text-xs uppercase text-slate-500">
                      Capacity: {ticket.capacity ?? "Unlimited"} · Price:{" "}
                      {new Intl.NumberFormat(undefined, {
                        style: "currency",
                        currency: ticket.currency,
                      }).format(ticket.price)}
                    </p>
                  </div>
                  <div className="text-right text-xs text-slate-500">
                    {ticket.sales_start_at && (
                      <p>Sales start {format(parseISO(ticket.sales_start_at), "MMM d h:mm a")}</p>
                    )}
                    {ticket.sales_end_at && <p>Sales end {format(parseISO(ticket.sales_end_at), "MMM d h:mm a")}</p>}
                  </div>
                </div>
              </div>
            ))}
          </CardContent>
          <CardFooter className="flex-row items-start">
            <form
              className="grid w-full gap-3 sm:grid-cols-3"
              onSubmit={(event) => {
                event.preventDefault();
                createTicketType.mutate({
                  name: ticketForm.name,
                  price: Number(ticketForm.price ?? 0),
                  capacity: ticketForm.capacity ?? null,
                  currency: ticketForm.currency ?? "USD",
                });
              }}
            >
              <input
                type="text"
                className="rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Ticket name"
                value={ticketForm.name}
                onChange={(event) => setTicketForm((prev) => ({ ...prev, name: event.target.value }))}
                required
              />
              <input
                type="number"
                className="rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Capacity"
                min={1}
                value={ticketForm.capacity ?? ""}
                onChange={(event) =>
                  setTicketForm((prev) => ({
                    ...prev,
                    capacity: event.target.value ? Number(event.target.value) : null,
                  }))
                }
              />
              <input
                type="number"
                className="rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Price"
                step="0.01"
                min={0}
                value={ticketForm.price}
                onChange={(event) => setTicketForm((prev) => ({ ...prev, price: Number(event.target.value) }))}
              />
              <div className="sm:col-span-3 flex flex-wrap gap-2">
                <button
                  type="submit"
                  disabled={createTicketType.isLoading}
                  className="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                >
                  {createTicketType.isLoading ? "Saving…" : "Add ticket type"}
                </button>
                <button
                  type="button"
                  onClick={() =>
                    setTicketForm({
                      name: "",
                      price: 0,
                      capacity: null,
                    })
                  }
                  className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                >
                  Reset
                </button>
              </div>
            </form>
          </CardFooter>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Registrations</CardTitle>
            <CardDescription>Total tickets issued: {ticketCount}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {registrations.length === 0 && (
              <p className="text-sm text-slate-500">No registrations yet. Use the form below to add one.</p>
            )}
            {registrations.map((registration) => (
              <div key={registration.id} className="rounded border border-slate-200 bg-white p-3 text-sm text-slate-700">
                <div className="flex items-center justify-between gap-2">
                  <div>
                    <p className="text-base font-semibold text-slate-900">
                      {registration.name} · {registration.quantity} ticket(s)
                    </p>
                    <p className="text-xs uppercase text-slate-500">{registration.status}</p>
                    <p className="text-xs text-slate-500">{registration.email}</p>
                  </div>
                  <div className="text-right text-xs text-slate-500">
                    <p>
                      {new Intl.NumberFormat(undefined, {
                        style: "currency",
                        currency: registration.currency,
                      }).format(registration.amount)}
                    </p>
                    {registration.checked_in_at && (
                      <p>Checked in {format(parseISO(registration.checked_in_at), "MMM d h:mm a")}</p>
                    )}
                  </div>
                </div>
                <div className="mt-3 flex gap-2">
                  {registration.status !== "checked_in" && (
                    <button
                      type="button"
                      onClick={() => checkInRegistration.mutate(registration.id)}
                      disabled={checkInRegistration.isLoading}
                      className="rounded border border-slate-300 px-3 py-1 text-xs text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                    >
                      Check in
                    </button>
                  )}
                </div>
              </div>
            ))}
          </CardContent>
          <CardFooter className="flex-col items-stretch gap-3">
            <form
              className="grid gap-3 sm:grid-cols-2"
              onSubmit={(event) => {
                event.preventDefault();
                createRegistration.mutate({
                  ticket_type_id: registrationForm.ticket_type_id ?? undefined,
                  quantity: registrationForm.quantity ?? 1,
                  name: registrationForm.name,
                  email: registrationForm.email,
                  phone: registrationForm.phone ?? undefined,
                });
              }}
            >
              <select
                value={registrationForm.ticket_type_id ?? ""}
                onChange={(event) =>
                  setRegistrationForm((prev) => ({
                    ...prev,
                    ticket_type_id: event.target.value ? Number(event.target.value) : undefined,
                  }))
                }
                className="rounded border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">No ticket type</option>
                {ticketTypes.map((ticket) => (
                  <option key={ticket.id} value={ticket.id}>
                    {ticket.name}
                  </option>
                ))}
              </select>
              <input
                type="number"
                min={1}
                value={registrationForm.quantity ?? 1}
                onChange={(event) =>
                  setRegistrationForm((prev) => ({ ...prev, quantity: Number(event.target.value) }))
                }
                className="rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Quantity"
              />
              <input
                type="text"
                value={registrationForm.name ?? ""}
                onChange={(event) => setRegistrationForm((prev) => ({ ...prev, name: event.target.value }))}
                className="rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Attendee name"
              />
              <input
                type="email"
                value={registrationForm.email ?? ""}
                onChange={(event) => setRegistrationForm((prev) => ({ ...prev, email: event.target.value }))}
                className="rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Attendee email"
              />
              <input
                type="tel"
                value={registrationForm.phone ?? ""}
                onChange={(event) => setRegistrationForm((prev) => ({ ...prev, phone: event.target.value }))}
                className="rounded border border-slate-300 px-3 py-2 text-sm sm:col-span-2"
                placeholder="Phone (optional)"
              />
              <div className="sm:col-span-2 flex flex-wrap gap-2">
                <button
                  type="submit"
                  disabled={createRegistration.isLoading}
                  className="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                >
                  {createRegistration.isLoading ? "Adding…" : "Add registration"}
                </button>
                <button
                  type="button"
                  onClick={() =>
                    setRegistrationForm({
                      ticket_type_id: ticketTypes[0]?.id ?? undefined,
                      quantity: 1,
                      name: "",
                      email: "",
                      phone: "",
                    })
                  }
                  className="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                >
                  Reset
                </button>
              </div>
            </form>
          </CardFooter>
        </Card>
      </section>
    </main>
  );
}
