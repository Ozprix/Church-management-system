export interface GatheringSummary {
  id: number;
  uuid: string;
  name: string;
  status: string;
  starts_at: string;
  ends_at: string;
  location: string | null;
  notes: string | null;
  service?: {
    id: number;
    name: string;
  } | null;
}

export interface GatheringDetail extends GatheringSummary {
  metadata: Record<string, unknown> | null;
  ticket_types?: GatheringTicketType[];
  registrations?: GatheringRegistration[];
  attendance?: {
    total: number;
    present: number;
    absent: number;
    excused: number;
  } | null;
}

export interface GatheringTicketType {
  id: number;
  gathering_id: number;
  name: string;
  capacity: number | null;
  price: number;
  currency: string;
  sales_start_at: string | null;
  sales_end_at: string | null;
  metadata: Record<string, unknown> | null;
}

export interface GatheringRegistration {
  id: number;
  gathering_id: number;
  ticket_type_id: number | null;
  member_id: number | null;
  status: string;
  quantity: number;
  name: string;
  email: string;
  phone: string | null;
  amount: number;
  currency: string;
  checked_in_at: string | null;
  notes: string | null;
  metadata: Record<string, unknown> | null;
  ticket_type?: GatheringTicketType | null;
  member?: {
    id: number;
    first_name: string;
    last_name: string;
    preferred_name?: string | null;
  } | null;
}

export interface NotificationRule {
  id: number;
  name: string;
  slug: string;
  trigger_type: string;
  trigger_config: Record<string, unknown>;
  channel: string;
  notification_template_id: number | null;
  delivery_config: Record<string, unknown> | null;
  status: string;
  throttle_minutes: number | null;
  metadata: Record<string, unknown> | null;
  created_at: string | null;
  updated_at: string | null;
  template?: NotificationTemplateSummary | null;
  runs?: NotificationRuleRun[];
}

export interface NotificationTemplateSummary {
  id: number;
  name: string;
  channel: string;
}

export interface NotificationRuleRun {
  id: number;
  ran_at: string | null;
  matched_count: number;
  sent_count: number;
  status: string;
  error_message: string | null;
  metadata?: Record<string, unknown> | null;
}
