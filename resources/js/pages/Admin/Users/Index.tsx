import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Plus } from 'lucide-react'; // ← new icon

interface Role {
  id: number;
  name: string;
}

interface Client {
  id: number;
  short_name: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  roles: Role[];
  clients: Client[];
}

interface Props {
  users: {
    data: User[];
  };
}

export default function UsersIndex({ users }: Props) {
  return (
    <AppLayout>
      <Head title="Admin - Users" />

      <div className="p-6">
        {/* Header with Add Button */}
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold">Users Management</h1>

          <Button asChild className="bg-blue-600 hover:bg-blue-700">
            <Link href="/admin/users/create">
              <Plus className="w-4 h-4 mr-2" />
              Add New User
            </Link>
          </Button>
        </div>

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Roles</TableHead>
              <TableHead>Clients</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {users.data.map((user) => (
              <TableRow key={user.id}>
                <TableCell className="font-medium">{user.name}</TableCell>
                <TableCell>{user.email}</TableCell>
                <TableCell>
                  {user.roles.map((role) => (
                    <Badge key={role.id} variant="secondary" className="mr-1">
                      {role.name}
                    </Badge>
                  ))}
                  {user.roles.length === 0 && <span className="text-gray-400 text-sm">No roles</span>}
                </TableCell>
                <TableCell>
                  {user.clients.length > 0 ? (
                    <span className="text-sm text-gray-600">
                      {user.clients.map((c) => c.short_name).join(', ')}
                    </span>
                  ) : (
                    <span className="text-gray-400">No clients</span>
                  )}
                </TableCell>
                <TableCell className="text-right">
                  <Button asChild size="sm">
                    <Link href={`/admin/users/${user.id}/edit`}>Edit</Link>
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </AppLayout>
  );
}
