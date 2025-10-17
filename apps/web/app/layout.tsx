import './globals.css';
import type { Metadata, Viewport } from 'next';
import Link from 'next/link';
import { Providers } from '@/providers/providers';
import clsx from 'clsx';

export const metadata: Metadata = {
  title: 'Church Management Portal',
  description: 'Multi-tenant church management system',
  applicationName: 'Church Management Portal',
  manifest: '/manifest.webmanifest',
  icons: {
    icon: [
      { url: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
      { url: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
      { url: '/icons/icon-maskable.png', sizes: '512x512', type: 'image/png' },
    ],
    apple: [
      { url: '/icons/icon-180.png', sizes: '180x180', type: 'image/png' },
    ],
  },
  appleWebApp: {
    capable: true,
    statusBarStyle: 'default',
    title: 'Church Management Portal',
  },
  formatDetection: {
    telephone: false,
  },
};

export const viewport: Viewport = {
  themeColor: '#047857',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body className={clsx('min-h-screen bg-slate-50 text-slate-900')}>
        <Providers>
          <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-8">
            <header className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <h1 className="text-xl font-semibold">Church Management</h1>
                <p className="text-sm text-slate-500">Multi-tenant SaaS platform</p>
              </div>
              <nav className="flex items-center gap-4 text-sm font-medium text-slate-600">
                <Link href="/members" className="hover:text-emerald-600">
                  Members
                </Link>
                <Link href="/account/security" className="hover:text-emerald-600">
                  Account security
                </Link>
                <Link href="/attendance" className="hover:text-emerald-600">
                  Attendance
                </Link>
                <Link href="/volunteers" className="hover:text-emerald-600">
                  Volunteers
                </Link>
                <Link href="/visitors" className="hover:text-emerald-600">
                  Visitors
                </Link>
                <Link href="/finance" className="hover:text-emerald-600">
                  Finance
                </Link>
                <Link href="/communication" className="hover:text-emerald-600">
                  Communication
                </Link>
                <Link href="/onboarding" className="hover:text-emerald-600">
                  Getting started
                </Link>
                <Link href="/docs/api" className="hover:text-emerald-600">
                  API docs
                </Link>
              </nav>
            </header>
            <main className="flex-1">{children}</main>
          </div>
        </Providers>
      </body>
    </html>
  );
}
