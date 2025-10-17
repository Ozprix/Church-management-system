import { render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';
import AccountSecurityPage from './page';

vi.mock('@/lib/tenant', () => ({
  useTenantId: () => 'demo-tenant',
}));

const fetchCurrentUserMock = vi.fn();

vi.mock('@/lib/api/auth', () => {
  return {
    fetchCurrentUser: (...args: unknown[]) => fetchCurrentUserMock(...args),
    startTwoFactor: vi.fn(),
    confirmTwoFactor: vi.fn(),
    regenerateRecoveryCodes: vi.fn(),
    disableTwoFactor: vi.fn(),
    adminResetTwoFactor: vi.fn(),
  };
});

describe('AccountSecurityPage', () => {
  beforeEach(() => {
    fetchCurrentUserMock.mockResolvedValue({
      user: {
        id: 1,
        tenant_id: 1,
        name: 'Admin',
        email: 'admin@example.com',
        two_factor_enabled: false,
        permissions: [],
      },
      abilities: [],
    });
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it('shows operator reset controls when the user can manage security', async () => {
    fetchCurrentUserMock.mockResolvedValueOnce({
      user: {
        id: 1,
        tenant_id: 1,
        name: 'Owner',
        email: 'owner@example.com',
        two_factor_enabled: true,
        permissions: ['users.manage_security'],
      },
      abilities: ['users.manage_security'],
    });

    render(<AccountSecurityPage />);

    expect(await screen.findByText(/Two-factor authentication/i)).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getByText(/Operator reset/i)).toBeInTheDocument();
    });
  });

  it('hides operator reset controls without permission', async () => {
    render(<AccountSecurityPage />);

    expect(await screen.findByText(/Two-factor authentication/i)).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.queryByText(/Operator reset/i)).not.toBeInTheDocument();
    });
  });
});
