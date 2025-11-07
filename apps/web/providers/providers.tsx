"use client";

import { ToastProvider } from "@church/ui";
import { RootProvider } from "@/providers/root-provider";
import type { ReactNode } from "react";

export function Providers({ children }: { children: ReactNode }) {
  return (
    <RootProvider>
      <ToastProvider>{children}</ToastProvider>
    </RootProvider>
  );
}
