// resources/js/types/inertia.d.ts
import { PageProps as InertiaPagePropsBase } from '@inertiajs/core';
import { Client } from './client';

declare module '@inertiajs/core' {
  interface PageProps extends Record<string, unknown> {
    clients?: Client[];
    quote?: { message: string; author: string };
    sidebarOpen?: boolean;
    // Add any other shared props here
  }
}

export type PageProps = InertiaPagePropsBase & {
  clients?: Client[];
  quote?: { message: string; author: string };
  sidebarOpen?: boolean;
};
