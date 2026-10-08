// resources/js/types/client.d.ts
export interface Client {
  id: number;
  client_name: string;
  client_dbname: string;
  short_name: string | null;
  slug: string | null;        // ← REQUIRED
  color: string;
  is_active: boolean;
  display_order: number;
  logo?: string | null;
}
