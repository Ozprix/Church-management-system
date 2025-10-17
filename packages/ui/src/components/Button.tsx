import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { BUTTON_BASE_CLASSES, BUTTON_SIZE_CLASSES, BUTTON_VARIANT_CLASSES, type ButtonSize, type ButtonVariant } from '../theme';
import { classNames } from '../utils/classNames';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  icon?: ReactNode;
  loading?: boolean;
  fullWidth?: boolean;
}

export function Button({
  className,
  variant = 'primary',
  size = 'md',
  icon,
  loading = false,
  children,
  fullWidth = false,
  disabled,
  ...rest
}: ButtonProps) {
  const isDisabled = disabled ?? loading;
  return (
    <button
      className={classNames(
        BUTTON_BASE_CLASSES,
        BUTTON_VARIANT_CLASSES[variant],
        BUTTON_SIZE_CLASSES[size],
        fullWidth && 'w-full',
        className
      )}
      disabled={isDisabled}
      aria-busy={loading}
      {...rest}
    >
      {loading && (
        <span className="inline-flex items-center" aria-hidden="true">
          <svg
            className="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path
              className="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 017-7.938V1a10 10 0 100 20v-3.062A8 8 0 014 12z"
            />
          </svg>
        </span>
      )}
      {icon && !loading && <span className="inline-flex items-center" aria-hidden="true">{icon}</span>}
      <span>{children}</span>
    </button>
  );
}
