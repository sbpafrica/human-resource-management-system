// resources/js/Pages/Admin/Users/Edit.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';

interface Role {
  id: number;
  name: string;
}

interface Client {
  id: number;
  client_name: string;
  short_name: string | null;
  slug: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  roles: Role[];
  clients: Client[];
}

interface Props {
  user: User;
  allRoles: Role[];
  allClients: Client[];
}

export default function UserEdit({ user, allRoles, allClients }: Props) {
  const { data, setData, put, processing } = useForm({
    name: user.name || '',
    email: user.email || '',
    roles: user.roles.map(r => r.name) as string[],
    clients: user.clients.map(c => c.id) as number[],
  });

  const roleOptions = allRoles.map(r => ({
    label: r.name,
    value: r.name, // ← string
  }));

  const clientOptions = allClients.map(c => ({
    label: c.short_name || c.client_name,
    value: c.id, // ← number
  }));

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/admin/users/${user.id}`);
  };

  return (
    <AppLayout>
      <Head title={`Edit User - ${user.name}`} />

      <div className="max-w-4xl mx-auto p-6">
        <h1 className="text-2xl font-bold mb-6">Edit User</h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <Label>Name</Label>
            <Input value={data.name} onChange={e => setData('name', e.target.value)} required />
          </div>

          <div>
            <Label>Email</Label>
            <Input type="email" value={data.email} onChange={e => setData('email', e.target.value)} required />
          </div>

          <div>
            <Label>Roles</Label>
            <MultiSelect
              options={roleOptions}
              selected={data.roles}
              onChange={(values) => setData('roles', values as string[])}  // ← Explicit cast
              placeholder="Select roles..."
            />
          </div>

          <div>
            <Label>Clients (Leave empty = no client access)</Label>
            <MultiSelect
              options={clientOptions}
              selected={data.clients}
              onChange={(values) => setData('clients', values as number[])}  // ← Explicit cast
              placeholder="Select clients..."
            />
          </div>

          <div className="flex gap-4">
            <Button type="submit" disabled={processing}>
              {processing ? 'Saving...' : 'Save Changes'}
            </Button>
            <Button variant="outline" asChild>
              <a href="/admin/users">Cancel</a>
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
