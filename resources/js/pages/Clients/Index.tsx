// resources/js/pages/Clients/Index.tsx
import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Plus,
  Pencil,
  Trash,
  CheckCircle2,
  XCircle,
  Download,
  Upload,
  Palette,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { Switch } from "@/components/ui/switch";
import { useState, useEffect } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";
import { Inertia } from "@inertiajs/inertia";

interface Client {
  id: number;
  client_name: string;
  client_dbname: string;
  slug: string | null;
  short_name: string | null;
  logo: string | null;
  color: string;
  is_active: boolean;
  display_order: number;
}

type ClientFormData = {
  client_name: string;
  client_dbname: string;
  short_name: string;
  color: string;
  display_order: number;
  is_active: boolean;
};

interface Props {
  clients: {
    data: Client[];
    links: any[];
  };
  filters: {
    search: string;
    sort: string;
    order: string;
  };
  totalRecords: number;
  searchCount: number | null;
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [{ title: "Clients", href: "/clients" }];

export default function Clients({ clients, filters, totalRecords, searchCount, flash }: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingClient, setEditingClient] = useState<Client | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteClientId, setDeleteClientId] = useState<number | null>(null);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "display_order");
  const [order, setOrder] = useState(filters.order || "asc");
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);

  // FIXED: ADD patch HERE
  const {
    data,
    setData,
    post,
    patch,
    delete: destroy,
    processing,
    reset,
    errors
  } = useForm<ClientFormData>({
    client_name: "",
    client_dbname: "",
    short_name: "",
    color: "#3b82f6",
    display_order: 0,
    is_active: true,
  });

  const bulkDeleteForm = useForm<{ ids: number[] }>({ ids: [] });
  const bulkUploadForm = useForm<{ file: File | null }>({ file: null });

  useEffect(() => {
    bulkDeleteForm.setData("ids", selectedIds);
  }, [selectedIds]);

  useEffect(() => {
    if (flash?.success || flash?.error) {
      setToastMessage(flash.success || flash.error || "");
      setToastType(flash.success ? "success" : "error");
      setShowToast(true);
    }
  }, [flash]);

  useEffect(() => {
    const allErrors = [errors, bulkDeleteForm.errors, bulkUploadForm.errors];
    if (allErrors.some(err => Object.keys(err).length > 0)) {
      setToastMessage("Please fix the errors below.");
      setToastType("error");
      setShowToast(true);
    }
  }, [errors, bulkDeleteForm.errors, bulkUploadForm.errors]);

  useEffect(() => {
    if (showToast) {
      const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 8000 : 4000);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleEdit = (client: Client) => {
    setEditingClient(client);
    setData({
      client_name: client.client_name,
      client_dbname: client.client_dbname,
      short_name: client.short_name || "",
      color: client.color,
      display_order: client.display_order,
      is_active: client.is_active,
    });
    setIsOpen(true);
  };

  // FINAL WORKING handleSubmit — NO ERRORS
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (editingClient) {
      patch(route("clients.update", editingClient.id), {
        onSuccess: () => {
          closeDialog();
          setToastMessage("Client updated successfully!");
          setToastType("success");
          setShowToast(true);
        },
        onError: () => {
          setToastMessage("Please fix the errors below.");
          setToastType("error");
          setShowToast(true);
        },
        preserveState: true,
        preserveScroll: true,
      });
    } else {
      post(route("clients.store"), {
        onSuccess: () => {
          closeDialog();
          reset();
          setToastMessage("Client created successfully!");
          setToastType("success");
          setShowToast(true);
        },
        preserveState: true,
      });
    }
  };

  const closeDialog = () => {
    setIsOpen(false);
    reset();
    setEditingClient(null);
  };

  const handleDelete = (id: number) => {
    setDeleteClientId(id);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteClientId) {
      destroy(route("clients.destroy", deleteClientId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteClientId(null);
        },
      });
    }
  };

  const handleSelect = (id: number, checked: boolean) => {
    setSelectedIds(prev => checked ? [...prev, id] : prev.filter(x => x !== id));
  };

  const handleSelectAll = (checked: boolean) => {
    setSelectedIds(checked ? clients.data.map(c => c.id) : []);
  };

  const handleBulkDelete = () => selectedIds.length > 0 && setIsBulkDeleteOpen(true);

  const confirmBulkDelete = () => {
    bulkDeleteForm.post(route("clients.bulk-destroy"), {
      onSuccess: () => {
        setIsBulkDeleteOpen(false);
        setSelectedIds([]);
      },
    });
  };

  const handleSearchSort = () => {
    Inertia.visit(route("clients.index", { search, sort, order }), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const handleExport = () => {
    window.location.href = route("clients.export", { search, sort, order });
  };

  const handleDownloadTemplate = () => {
    window.location.href = route("clients.download-template");
  };

  const handleBulkUpload = (e: React.FormEvent) => {
    e.preventDefault();
    if (bulkUploadForm.data.file) {
      bulkUploadForm.post(route("clients.bulk-upload"), {
        onSuccess: () => {
          setIsBulkUploadOpen(false);
          bulkUploadForm.reset();
        },
      });
    }
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Clients" />

        <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 lg:p-8">

          {/* Toast */}
          {showToast && (
            <div className={`fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg p-4 shadow-2xl text-white animate-in fade-in slide-in-from-top-5 ${
              toastType === "success" ? "bg-green-600" : "bg-red-600"
            }`}>
              {toastType === "success" ? <CheckCircle2 className="h-6 w-6" /> : <XCircle className="h-6 w-6" />}
              <span className="font-medium">{toastMessage}</span>
              <Button variant="ghost" size="sm" onClick={() => setShowToast(false)} className="text-white hover:bg-white/20">
                <XCircle className="h-5 w-5" />
              </Button>
            </div>
          )}

          <div className="flex flex-col gap-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <h1 className="text-3xl font-bold tracking-tight">Clients Management</h1>
                <p className="text-muted-foreground">Multi-tenant payroll clients</p>
              </div>

              <div className="flex flex-col sm:flex-row gap-3">
                <Input
                  placeholder="Search clients..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyUp={handleSearchSort}
                  className="w-full sm:w-64"
                />

                <Button onClick={handleExport} variant="outline">
                  <Download className="h-4 w-4 mr-2" /> Export
                </Button>

                <Dialog open={isOpen} onOpenChange={setIsOpen}>
  <DialogTrigger asChild>
    <Button>
      <Plus className="h-4 w-4 mr-2" /> New Client
    </Button>
  </DialogTrigger>

  <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
    <DialogHeader>
      <DialogTitle className="text-2xl">
        {editingClient ? "Edit" : "Create"} Client
      </DialogTitle>
    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-6">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-4">
                          <div>
                            <Label>Full Name</Label>
                            <Input value={data.client_name} onChange={e => setData("client_name", e.target.value)} required />
                            {errors.client_name && <p className="text-red-500 text-sm mt-1">{errors.client_name}</p>}
                          </div>
                          <div>
                            <Label>DB Name</Label>
                            <Input value={data.client_dbname} onChange={e => setData("client_dbname", e.target.value)} required disabled={!!editingClient} />
                            {errors.client_dbname && <p className="text-red-500 text-sm mt-1">{errors.client_dbname}</p>}
                          </div>
                          <div>
                            <Label>Short Name (Sidebar)</Label>
                            <Input value={data.short_name} onChange={e => setData("short_name", e.target.value)} placeholder="Transsion GH" />
                          </div>
                        </div>

                        <div className="space-y-4">
                          <div>
                            <Label>Accent Color</Label>
                            <div className="flex gap-3 items-center">
                              <Input type="color" value={data.color} onChange={e => setData("color", e.target.value)} className="w-20 h-10" />
                              <Input value={data.color} onChange={e => setData("color", e.target.value)} />
                              <div className="w-10 h-10 rounded border-2" style={{ backgroundColor: data.color }}></div>
                            </div>
                          </div>
                          <div>
                            <Label>Display Order</Label>
                            <Input type="number" value={data.display_order} onChange={e => setData("display_order", parseInt(e.target.value) || 0)} />
                          </div>
                          <div className="flex items-center justify-between">
                            <Label htmlFor="is_active">Show in Sidebar</Label>
                            <Switch
                              id="is_active"
                              checked={data.is_active}
                              onCheckedChange={(checked: boolean) => setData("is_active", checked)}
                            />
                          </div>
                        </div>
                      </div>

                      <DialogFooter>
                        <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                          {editingClient ? "Update" : "Create"} Client
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>

            <div className="flex flex-wrap gap-3 justify-end">
              <Button onClick={handleDownloadTemplate} variant="secondary">
                <Download className="h-4 w-4 mr-2" /> Template
              </Button>
              <Dialog open={isBulkUploadOpen} onOpenChange={setIsBulkUploadOpen}>
                <DialogTrigger asChild>
                  <Button variant="secondary">
                    <Upload className="h-4 w-4 mr-2" /> Bulk Upload
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader><DialogTitle>Bulk Upload</DialogTitle></DialogHeader>
                  <form onSubmit={handleBulkUpload}>
                    <Input type="file" accept=".xlsx,.xls" onChange={e => e.target.files?.[0] && bulkUploadForm.setData("file", e.target.files[0])} />
                    <DialogFooter className="mt-4">
                      <Button type="submit" disabled={!bulkUploadForm.data.file}>Upload</Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
              <Button variant="destructive" onClick={handleBulkDelete} disabled={selectedIds.length === 0}>
                <Trash className="h-4 w-4 mr-2" /> Delete ({selectedIds.length})
              </Button>
            </div>
          </div>

          {clients.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>All Clients</CardTitle>
                <p className="text-sm text-muted-foreground">
                  {searchCount !== null ? `Showing ${searchCount} of ${totalRecords}` : `Total: ${totalRecords}`}
                </p>
              </CardHeader>
              <CardContent>
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-12">
                          <Checkbox
                            checked={selectedIds.length === clients.data.length && clients.data.length > 0}
                            onCheckedChange={(checked: boolean) => handleSelectAll(checked)}
                          />
                        </TableHead>
                        <TableHead>Sidebar Name</TableHead>
                        <TableHead>DB Name</TableHead>
                        <TableHead>Color</TableHead>
                        <TableHead>Order</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {clients.data.map(client => (
                        <TableRow key={client.id}>
                          <TableCell>
                            <Checkbox
                              checked={selectedIds.includes(client.id)}
                              onCheckedChange={(checked: boolean) => handleSelect(client.id, checked)}
                            />
                          </TableCell>
                          <TableCell className="font-medium">
                            <div className="flex items-center gap-2">
                              <div className="w-4 h-4 rounded border" style={{ backgroundColor: client.color }}></div>
                              {client.short_name || client.client_name}
                            </div>
                          </TableCell>
                          <TableCell className="font-mono text-sm">{client.client_dbname}</TableCell>
                          <TableCell>
                            <div className="flex items-center gap-2">
                              <Palette className="h-4 w-4" style={{ color: client.color }} />
                              {client.color}
                            </div>
                          </TableCell>
                          <TableCell>{client.display_order}</TableCell>
                          <TableCell>
                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${client.is_active ? "bg-green-100 text-green-800" : "bg-gray-100 text-gray-600"}`}>
                              {client.is_active ? "Active" : "Hidden"}
                            </span>
                          </TableCell>
                          <TableCell className="text-right space-x-2">
                            <Button size="sm" variant="ghost" onClick={() => handleEdit(client)}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => handleDelete(client.id)}>
                              <Trash className="h-4 w-4 text-red-600" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>

                <div className="flex justify-center mt-6 gap-2">
                  {clients.links.map((link, i) => (
                    <Button key={i} variant={link.active ? "default" : "outline"} size="sm" disabled={!link.url} asChild={!!link.url}>
                      {link.url ? <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                    </Button>
                  ))}
                </div>
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent className="text-center py-12">
                <p className="text-2xl text-muted-foreground">No clients yet. Create your first one!</p>
              </CardContent>
            </Card>
          )}

          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent>
              <DialogHeader><DialogTitle>Delete Client?</DialogTitle></DialogHeader>
              <p>This action cannot be undone.</p>
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>Cancel</Button>
                <Button variant="destructive" onClick={confirmDelete}>Delete</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
            <DialogContent>
              <DialogHeader><DialogTitle>Delete {selectedIds.length} Clients?</DialogTitle></DialogHeader>
              <p>Permanently remove selected clients?</p>
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsBulkDeleteOpen(false)}>Cancel</Button>
                <Button variant="destructive" onClick={confirmBulkDelete}>Delete All</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
