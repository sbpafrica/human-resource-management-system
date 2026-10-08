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

interface Props {
  allPermissions: Permission[];
}

export default function RoleCreate({ allPermissions }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    permissions: [] as string[],
  });

  const permissionOptions = allPermissions.map(p => ({
    label: p.name,
    value: p.name,
  }));

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/admin/roles');
  };

  return (
    <AppLayout>
      <Head title="Create New Role" />

      <div className="max-w-4xl mx-auto p-6">
        <h1 className="text-2xl font-bold mb-6">Create New Role</h1>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <Label>Role Name</Label>
            <Input
              value={data.name}
              onChange={e => setData('name', e.target.value)}
              required
            />
            {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
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
              {processing ? 'Creating...' : 'Create Role'}
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
