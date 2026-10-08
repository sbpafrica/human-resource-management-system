// resources/js/types/index.ts
import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href?: string; // Make href optional to support items without links (e.g., dropdown headers)
    icon?: LucideIcon | ComponentType<any>;
    isActive?: boolean;
    children?: NavItem[]; // Add children for dropdowns
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface Ssnit {
  staffid: string;
  shop: string;
  lastname: string;
  firstname: string;
  othername: string | null;
  ssnit1a: string;
  national: string | null;
}

export interface SsnitProps {
  ssnits: {
    data: Ssnit[];
    links: any[];
  };
  filters: {
    search: string;
    sort: string;
    order: string;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}


