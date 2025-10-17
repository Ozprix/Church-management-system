import { apiFetch } from '@/lib/api/http';

export interface MemberCustomField {
  id: number;
  name: string;
  slug: string;
  data_type: string;
  is_required: boolean;
  is_active?: boolean;
  config?: Record<string, unknown> | null;
}

export interface CreateMemberCustomFieldPayload {
  name: string;
  slug?: string | null;
  data_type: string;
  is_required?: boolean;
  is_active?: boolean;
  config?: Record<string, unknown> | null;
}

export interface CustomFieldFileMetadata {
  disk: string;
  path: string;
  name: string;
  mime?: string | null;
  size?: number | null;
  url?: string | null;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: unknown;
}

export async function fetchMemberCustomFields(tenantId: string): Promise<MemberCustomField[]> {
  const response = await apiFetch<PaginatedResponse<MemberCustomField>>('/v1/member-custom-fields', {}, tenantId);
  return response.data;
}

export async function createMemberCustomField(
  tenantId: string,
  payload: CreateMemberCustomFieldPayload
): Promise<MemberCustomField> {
  return apiFetch<MemberCustomField>(
    '/v1/member-custom-fields',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function updateMemberCustomField(
  tenantId: string,
  fieldId: number,
  payload: Partial<CreateMemberCustomFieldPayload>
): Promise<MemberCustomField> {
  return apiFetch<MemberCustomField>(
    `/v1/member-custom-fields/${fieldId}`,
    {
      method: 'PUT',
      body: JSON.stringify(payload),
    },
    tenantId
  );
}

export async function deleteMemberCustomField(tenantId: string, fieldId: number): Promise<void> {
  await apiFetch(`/v1/member-custom-fields/${fieldId}`,
    {
      method: 'DELETE',
    },
    tenantId
  );
}

interface UploadResponse {
  data: {
    field_id: number;
    file: CustomFieldFileMetadata;
  };
}

export async function uploadMemberCustomFieldFile(
  tenantId: string,
  fieldId: number,
  file: File
): Promise<CustomFieldFileMetadata> {
  const formData = new FormData();
  formData.append('file', file);

  const response = await apiFetch<UploadResponse>(
    `/v1/member-custom-fields/${fieldId}/uploads`,
    {
      method: 'POST',
      body: formData,
    },
    tenantId
  );

  return response.data.file;
}
