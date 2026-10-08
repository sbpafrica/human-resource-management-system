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
}

interface Props {
  allRoles: Role[];
  allClients: Client[];
}

export default function UserCreate({ allRoles, allClients }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
    clients: [] as number[],
  });

  const roleOptions = allRoles.map(r => ({
    label: r.name,
    value: r.name,
  }));

  const clientOptions = allClients.map(c => ({
    label: c.short_name || c.client_name,
    value: c.id,
  }));

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/admin/users');
  };

  return (
    <AppLayout>
      <Head title="Create New User" />

      <div className="max-w-4xl mx-auto p-6">
        <h1 className="text-2xl font-bold mb-6">Create New User</h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <Label>Name</Label>
            <Input
              value={data.name}
              onChange={e => setData('name', e.target.value)}
              required
            />
            {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
          </div>

          <div>
            <Label>Email</Label>
            <Input
              type="email"
              value={data.email}
              onChange={e => setData('email', e.target.value)}
              required
            />
            {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
          </div>

          <div>
            <Label>Password</Label>
            <Input
              type="password"
              value={data.password}
              onChange={e => setData('password', e.target.value)}
              required
            />
            {errors.password && <p className="text-red-500 text-sm mt-1">{errors.password}</p>}
          </div>

          <div>
            <Label>Confirm Password</Label>
            <Input
              type="password"
              value={data.password_confirmation}
              onChange={e => setData('password_confirmation', e.target.value)}
              required
            />
          </div>

          <div>
            <Label>Roles</Label>
            <MultiSelect
              options={roleOptions}
              selected={data.roles}
              onChange={(values) => setData('roles', values as string[])}
              placeholder="Select roles..."
            />
          </div>

          <div>
            <Label>Clients (optional)</Label>
            <MultiSelect
              options={clientOptions}
              selected={data.clients}
              onChange={(values) => setData('clients', values as number[])}
              placeholder="Select clients..."
            />
          </div>

          <div className="flex gap-4">
            <Button type="submit" disabled={processing}>
              {processing ? 'Creating...' : 'Create User'}
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
