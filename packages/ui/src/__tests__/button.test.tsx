import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Button } from '../components/Button';

describe('Button', () => {
  it('renders the provided label', () => {
    render(<Button>Launch</Button>);
    expect(screen.getByRole('button', { name: 'Launch' })).toBeInTheDocument();
  });

  it('disables the button while loading', () => {
    render(
      <Button loading data-testid="loading-button">
        Syncing
      </Button>
    );
    const button = screen.getByTestId('loading-button');
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-busy', 'true');
  });
});
