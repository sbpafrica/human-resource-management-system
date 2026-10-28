// resources/js/layouts/app-sidebar-layout.tsx
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import EmployeeChatbotModal from '@/components/EmployeeChatbotModal';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({
  children,
  breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
  return (
    <AppShell variant="sidebar">
      {/* NO PROPS → uses usePage internally */}
      <AppSidebar />

      <AppContent variant="sidebar" className="overflow-x-hidden">
        <AppSidebarHeader breadcrumbs={breadcrumbs} />
        {children}
      </AppContent>

      {/* Floating HRS Employee Copilot */}
      <EmployeeChatbotModal />
    </AppShell>
  );
}
