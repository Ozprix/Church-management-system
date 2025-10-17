import { defineWorkspace } from 'vitest/config';

export default defineWorkspace([
  'packages/ui/vitest.config.ts',
  'packages/utils/vitest.config.ts',
  'packages/contracts/vitest.config.ts'
]);
