import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';

interface Permission {
  id: number;
  name: string;
}

interface Role {
  id: number;
  name: string;
  permissions: Permission[];
}

interface Props {
  role: Role;
  allPermissions: Permission[];
}

export default function RoleEdit({ role, allPermissions }: Props) {
  const { data, setData, put, processing } = useForm({
    name: role.name || '',
    permissions: role.permissions.map(p => p.name),
  });

  const permissionOptions = allPermissions.map(p => ({
    label: p.name,
    value: p.name,
  }));

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/admin/roles/${role.id}`);
  };

  return (
    <AppLayout>
      <Head title={`Edit Role - ${role.name}`} />

      <div className="max-w-4xl mx-auto p-6">
        <h1 className="text-2xl font-bold mb-6">Edit Role: {role.name}</h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <Label>Role Name</Label>
            <Input
              value={data.name}
              onChange={e => setData('name', e.target.value)}
              required
            />
          </div>

          <div>
            <Label>Permissions</Label>
            <MultiSelect
              options={permissionOptions}
              selected={data.permissions}
              onChange={(values) => setData('permissions', values as string[])}
              placeholder="Select permissions..."
            />
            <p className="text-sm text-gray-500 mt-2">
              {data.permissions.length} permission(s) selected
            </p>
          </div>

          <div className="flex gap-4">
            <Button type="submit" disabled={processing}>
              {processing ? 'Saving...' : 'Save Changes'}
            </Button>
            <Button variant="outline" asChild>
              <a href="/admin/roles">Cancel</a>
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
