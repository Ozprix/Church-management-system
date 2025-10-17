/**
 * THIS FILE IS AUTO-GENERATED VIA `pnpm --filter @church/contracts run generate`.
 * Do not edit directly; update the OpenAPI definition instead.
 */

export interface Member {
  id: string;
  tenantId: string;
  firstName: string;
  lastName: string;
  status: 'active' | 'inactive' | 'visitor';
  email?: string;
  phone?: string;
  createdAt?: string;
}

export interface MemberCollection {
  data: Member[];
  meta?: {
    pagination?: {
      total?: number;
      page?: number;
      perPage?: number;
    };
  };
}

export interface CreateMemberPayload {
  firstName: string;
  lastName: string;
  status: 'active' | 'inactive' | 'visitor';
  email?: string;
  phone?: string;
}

export interface Donation {
  id: string;
  tenantId: string;
  amount: number;
  currency: string;
  fund?: string;
  receivedAt: string;
}

export interface RecordDonationPayload {
  memberId?: string;
  amount: number;
  currency: string;
  fund?: string;
  receivedAt?: string;
}

export interface Paths {
  '/tenants/{tenantId}/members': {
    get: {
      parameters: {
        path: {
          tenantId: string;
        };
        query?: {
          status?: 'active' | 'inactive' | 'visitor';
        };
      };
      responses: {
        200: MemberCollection;
      };
    };
    post: {
      parameters: {
        path: {
          tenantId: string;
        };
      };
      requestBody: {
        content: {
          'application/json': CreateMemberPayload;
        };
      };
      responses: {
        201: Member;
      };
    };
  };
  '/tenants/{tenantId}/donations': {
    post: {
      parameters: {
        path: {
          tenantId: string;
        };
      };
      requestBody: {
        content: {
          'application/json': RecordDonationPayload;
        };
      };
      responses: {
        201: Donation;
      };
    };
  };
}
