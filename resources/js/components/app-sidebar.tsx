// resources/js/components/app-sidebar.tsx
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/react';
import {
  LayoutGrid,
  Building2,
  ListTodo,
  Database,
  Banknote,
  BarChart,
  Folder,
  Calendar,
  FileText,
  FileSpreadsheet,
  Receipt,
  ReceiptText,
  Shield,
  Laptop,
  Globe,
  Clock,
  ClipboardCheck,
} from 'lucide-react';
import AppLogo from './app-logo';
import { useEffect, useState, useRef } from 'react';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';

interface Client {
  short_name: string;
  slug: string;
  route_slug: string;
  color?: string;
}

interface AuthUser {
  permissions?: string[];
  clients?: Client[];
  name?: string;
  email?: string;
  is_super_admin?: boolean;
}

interface CustomPageProps extends InertiaPageProps {
  auth?: {
    user?: AuthUser;
  };
}

interface MenuItem {
  title: string;
  href?: string;
  icon?: any;
  children?: MenuItem[];
}

export function AppSidebar() {
  const page = usePage<CustomPageProps>();
  const user = page.props.auth?.user;

  const [menuItems, setMenuItems] = useState<MenuItem[]>([]);
  const prevUserRef = useRef<AuthUser | undefined>(undefined);

  useEffect(() => {
    if (!user) {
      setMenuItems([]);
      return;
    }

    // Only rebuild if user data actually changed
    if (user === prevUserRef.current) return;
    prevUserRef.current = user;

    const can = (perm: string) => Boolean(user.is_super_admin || user.permissions?.includes(perm));
    const items: MenuItem[] = [];

    if (can('view dashboard')) {
      items.push({ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid });
    }

    if (can('view clients')) {
      items.push({ title: 'Clients', href: '/clients', icon: Building2 });
    }

    if (can('view bankcodes')) {
      items.push({ title: 'BankCodes', href: '/bankcodes', icon: ListTodo });
    }

    if (can('view receipts') || can('manage receipts')) {
      items.push({ title: 'Receipts', href: '/receipts', icon: ReceiptText });
    }

    if (can('view attendance')) {
      items.push({ title: 'Attendance', href: '/attendance', icon: Clock });
    }

    if (can('view database') || can('view employees')) {
      items.push({
        title: 'Database',
        icon: Database,
        children: [
          { title: 'Employee Portal', href: '/employeeportal' },
          { title: 'Employee Contact', href: '/employeecontact' },
          { title: 'General Pin Code', href: '/employeepincode' },
          { title: 'Twillium Pin Code', href: '/twelliumpincode' },
          { title: 'Twillium Accra 2', href: '/twelliumaccra2' },
          { title: 'Archive', href: '/employeearchived' },
        ],
      });
    }

    if (can('view ssnit') || can('view bank details') || can('view petra')) {
      items.push({
        title: 'SSNIT & BANK',
        icon: Banknote,
        children: [
          { title: 'Bank Details', href: '/bankdetails' },
          { title: 'Tier1/SSNIT Details', href: '/ssnitdetails' },
          { title: 'Tier2/PETRA Details', href: '/petradetails' },
        ],
      });
    }

    if (can('view hr audit') || can('view audit')) {
      items.push({
        title: 'HR Audit',
        icon: ClipboardCheck,
        children: [
          { title: 'Bank Audit', href: '/hr-audit/bank' },
          { title: 'SSNIT Audit', href: '/hr-audit/ssnit' },
          { title: 'PETRA Audit', href: '/hr-audit/petra' },
          { title: 'Contacts Audit', href: '/hr-audit/contacts' },
          { title: 'New Joiners', href: '/hr-audit/new-joiners' },
          { title: 'Exited Staff', href: '/hr-audit/exited-staff' },
          { title: 'Melcom Basic', href: '/hr-audit/melcom-basic' },
        ],
      });
    }

    if (can('view performance')) {
      items.push({
        title: 'Performance Tracker',
        icon: BarChart,
        children: [{ title: 'Performance', href: '/performance' }],
      });
    }

    if (can('view leave')) {
      items.push({
        title: 'Leave System',
        icon: Calendar,
        children: [
          { title: 'Leave Applications', href: '/leave-applications' },
          { title: 'Employee Leaves', href: '/employee-leaves' },
        ],
      });
    }

    if (can('view assets') || can('view it assets') || user.is_super_admin) {
      items.push({
        title: 'IT Assets',
        icon: Laptop,
        children: [
          { title: 'Assets List', href: '/it-assets' },
          { title: 'Archived Assets', href: '/it-assets/archived' },
        ],
      });
    }

    if (can('view contracts')) {
      const clients = user.clients || [];

      const contractChildren: MenuItem[] = [
        {
          title: 'Melcom',
          children: [
            { title: 'Contracts', href: '/contracts' },
            { title: 'Renewals', href: '/contract_renewals' },
          ],
        },
      ];

      clients.forEach(c => {
        contractChildren.push({
          title: c.short_name,
          children: [
            { title: 'Contracts', href: `/${c.route_slug}/contracts` },
            { title: 'Renewals', href: `/${c.route_slug}/contract-renewals` },
          ],
        });
      });

      items.push({
        title: 'CONTRACT',
        icon: Folder,
        children: contractChildren,
      });
    }

    // DYNAMIC CLIENT SECTIONS
    const clients = user.clients || [];

    if (clients.length > 0) {
      const salaryItems = clients.map(c => ({
        title: c.short_name,
        href: `/${c.route_slug}pay`,
      }));

      const ssnitItems = clients.map(c => ({
        title: c.short_name,
        href: `/${c.route_slug}ssnitpay`,
      }));

      const petraItems = clients.map(c => ({
        title: c.short_name,
        href: `/${c.route_slug}petrapay`,
      }));

      const payslipItems = clients.map(c => ({
        title: c.short_name,
        href: `/payslips/${c.route_slug}`,
      }));

      const graItems = clients.map(c => ({
        title: c.short_name,
        href: `/${c.route_slug}stat`,
      }));

      if (can('view payroll')) {
        items.push({ title: 'Salary Payment', icon: FileText, children: salaryItems });
      }
      if (can('view ssnit')) {
        items.push({ title: 'SSNIT Payment', icon: FileSpreadsheet, children: ssnitItems });
      }
      if (can('view petra')) {
        items.push({ title: 'PETRA Payment', icon: FileSpreadsheet, children: petraItems });
      }
      if (can('view payslips')) {
        items.push({ title: 'Payslips', icon: Receipt, children: payslipItems });
      }
      if (can('view gra')) {
        items.push({ title: 'GRA', icon: FileSpreadsheet, children: graItems });
      }
    }

    // TRANSSION BFA SECTION
    if (can('view transsion bfa')) {
      items.push({
        title: 'Transsion BFA',
        icon: Globe,
        children: [
          { title: 'Payroll Summary', href: '/transsion-bfa' },
          { title: 'Bank Details', href: '/transsion-bfa/bank' },
          { title: 'IUTS & TPA', href: '/transsion-bfa/iuts-tpa' },
          { title: 'DN13', href: '/transsion-bfa/dn13' },
        ],
      });
    }

    // BUNGE WEST AFRICA SECTION
    if (can('view bunge')) {
      items.push({
        title: 'Bunge West Africa',
        icon: Globe,
        children: [
          { title: 'Bunge Ghana', href: '/bungepay' },
          { title: 'CIV Bunge', href: '/civ-bunge' },
          { title: 'Togo Bunge', href: '/togo-bunge' },
          { title: 'Benin Bunge', href: '/benin-bunge' },
        ],
      });
    }

    if (can('manage users')) {
      items.push({
        title: 'Admin Management',
        icon: Shield,
        children: [
          { title: 'Users', href: '/admin/users' },
          { title: 'Roles & Permissions', href: '/admin/roles' },
        ],
      });
    }

    setMenuItems(items);
  }, [user]);

  // Guest view
  if (!user) {
    return (
      <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton size="lg" asChild>
                <Link href="/dashboard" prefetch>
                  <AppLogo />
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarHeader>
        <SidebarContent />
        <SidebarFooter>
          <NavUser />
        </SidebarFooter>
      </Sidebar>
    );
  }

  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href="/dashboard" prefetch>
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <SidebarMenu>
          <NavMain
            items={menuItems}
            itemClassName="mb-2"
            buttonClassName="text-sm font-medium"
            subClassName=""
            subItemClassName=""
            subButtonClassName="text-xs"
          />
        </SidebarMenu>
      </SidebarContent>

      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
