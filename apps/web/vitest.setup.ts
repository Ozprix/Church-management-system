import '@testing-library/jest-dom/vitest';
import React from 'react';

// Ensure React is available globally for components compiled in automatic runtime during tests.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
(globalThis as any).React = React;
