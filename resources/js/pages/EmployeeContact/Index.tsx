import { Head, useForm, InertiaFormProps } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Pencil, Trash, CheckCircle, XCircle, Download, Upload, ArrowUpDown } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect, useCallback } from "react";
import { Input as ShadcnInput } from "@/components/ui/input";
import ErrorBoundary from "@/components/ErrorBoundary";
import debounce from "lodash/debounce";

interface Contact {
  staffid: string;
  name: string;
  shop: string | null;
  phone: string | null;
}

interface Props {
  contacts: {
    data: Contact[];
    links: any[];
  };
  filters: {
    search: string;
    sort: string;
    order: string;
  };
  totalRecords: number;
  searchCount: number | null;
  clients: string[];
  flash?: {
    success?: string;
    error?: string;
  };
}

interface FormErrors {
  [key: string]: string;
}

interface ExtendedFormProps<T> extends InertiaFormProps<T> {}

const breadcrumbs = [
  { title: "Employee Contact", href: "/employeecontact" },
];

export default function EmployeeContactIndex({ contacts, filters, totalRecords, searchCount, clients, flash }: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingContact, setEditingContact] = useState<Contact | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteContactId, setDeleteContactId] = useState<string | null>(null);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "name");
  const [order, setOrder] = useState(filters.order || "asc");
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);

  const { data, setData, get, post, put, delete: destroy, processing, reset, errors } = useForm({
    staffid: "",
    name: "",
    shop: "",
    phone: "",
  }) as ExtendedFormProps<{
    staffid: string;
    name: string;
    shop: string;
    phone: string;
  }>;

  const bulkDeleteForm = useForm({
    ids: [] as string[],
  });

  const bulkUploadForm = useForm({
    file: null as File | null,
  });

  // Debounced search
  const debouncedSearch = useCallback(
    debounce((searchValue: string, sortValue: string, orderValue: string) => {
      get(route("employeecontact.index", { search: searchValue, sort: sortValue, order: orderValue }), {
        preserveState: true,
        preserveScroll: true,
      });
    }, 300),
    []
  );

  useEffect(() => {
    bulkDeleteForm.setData("ids", selectedIds);
  }, [selectedIds]);

  useEffect(() => {
    if (flash?.success) {
      setToastMessage(flash.success);
      setToastType("success");
      setShowToast(true);
    } else if (flash?.error) {
      setToastMessage(flash.error);
      setToastType("error");
      setShowToast(true);
    }
  }, [flash]);

  useEffect(() => {
    const allErrors = [
      ...Object.values(errors),
      ...Object.values(bulkDeleteForm.errors),
      ...Object.values(bulkUploadForm.errors),
    ];
    if (allErrors.length > 0) {
      setToastMessage(allErrors.join(" | "));
      setToastType("error");
      setShowToast(true);
    }
  }, [errors, bulkDeleteForm.errors, bulkUploadForm.errors]);

  useEffect(() => {
    if (showToast) {
      const timeout = toastType === "error" ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  useEffect(() => {
    debouncedSearch(search, sort, order);
  }, [search, sort, order, debouncedSearch]);

  const handleEdit = (contact: Contact) => {
    setEditingContact(contact);
    setData({
      staffid: contact.staffid || "",
      name: contact.name || "",
      shop: contact.shop || "",
      phone: contact.phone || "",
    });
    setIsOpen(true);
  };

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (editingContact) {
      put(route("employeecontact.update", editingContact.staffid), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingContact(null);
        },
      });
    } else {
      post(route("employeecontact.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
      });
    }
  };

  const handleDelete = (id: string) => {
    setDeleteContactId(id);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteContactId) {
      destroy(route("employeecontact.destroy", deleteContactId), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setDeleteContactId(null);
        },
      });
    }
  };

  const handleSelect = (id: string, checked: boolean) => {
    setSelectedIds((prev) =>
      checked ? [...prev, id] : prev.filter((selectedId) => selectedId !== id)
    );
  };

  const handleSelectAll = (checked: boolean) => {
    setSelectedIds(checked ? contacts.data.map((contact) => contact.staffid) : []);
  };

  const handleBulkDelete = () => {
    if (selectedIds.length > 0) {
      setIsBulkDeleteOpen(true);
    }
  };

  const confirmBulkDelete = () => {
    bulkDeleteForm.post(route("employeecontact.bulk-destroy"), {
      onSuccess: () => {
        setIsBulkDeleteOpen(false);
        setSelectedIds([]);
      },
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleSort = (column: string) => {
    const newOrder = sort === column && order === "asc" ? "desc" : "asc";
    setSort(column);
    setOrder(newOrder);
  };

  const handleExport = async () => {
  try {
    const url = route("employeecontact.export", { search, sort, order });
    const response = await fetch(url);

    if (!response.ok) {
      const errorText = await response.text();
      setToastMessage("Export failed: " + errorText);
      setToastType("error");
      setShowToast(true);
      return;
    }

    const blob = await response.blob();
    const blobUrl = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = blobUrl;
    a.download = "employee_contacts.xlsx";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(blobUrl);
  } catch (error) {
    setToastMessage("Failed to export.");
    setToastType("error");
    setShowToast(true);
  }
};

  const handleDownloadTemplate = async () => {
  try {
    const response = await fetch(route("employeecontact.download-template"));

    if (!response.ok) {
      const errorText = await response.text();
      setToastMessage("Download failed: " + errorText);
      setToastType("error");
      setShowToast(true);
      return;
    }

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "employee_contacts_template.xlsx";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  } catch (error) {
    setToastMessage("Failed to download template.");
    setToastType("error");
    setShowToast(true);
  }
};

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (bulkUploadForm.data.file) {
      bulkUploadForm.post(route("employeecontact.bulk-upload"), {
        forceFormData: true,
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
        <Head title="Employee Contact" />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
              {toastType === "error" && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="ml-2 p-1 text-white hover:text-white hover:bg-red-600"
                  onClick={() => setShowToast(false)}
                >
                  <XCircle className="h-4 w-4" />
                </Button>
              )}
            </div>
          )}

          <div className="flex flex-col gap-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <h1 className="text-2xl font-bold">Employee Contact</h1>
              <div className="flex flex-col sm:flex-row gap-4">
                <ShadcnInput
                  placeholder="Search by staff ID, name, or shop..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="w-full sm:w-64"
                />
                <Button onClick={handleExport} className="w-full sm:w-auto">
                  <Download className="h-4 w-4 mr-2" />
                  Export to Excel
                </Button>
                <Dialog
                  open={isOpen}
                  onOpenChange={(open) => {
                    setIsOpen(open);
                    if (!open) {
                      reset();
                      setEditingContact(null);
                    }
                  }}
                >
                  <DialogTrigger asChild>
                    <Button className="w-full sm:w-auto">
                      <Plus className="h-4 w-4 mr-2" />
                      New Contact
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="p-4 sm:max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>{editingContact ? "Edit Contact" : "Create New Contact"}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                      <div className="grid sm:grid-cols-2 gap-4 py-4">
                        <div className="grid gap-2">
                          <Label htmlFor="staffid">Staff ID</Label>
                          <Input
                            id="staffid"
                            value={data.staffid}
                            onChange={(e) => setData("staffid", e.target.value)}
                            required
                            disabled={!!editingContact}
                          />
                          {errors.staffid && <p className="text-red-500 text-sm">{errors.staffid}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="name">Name</Label>
                          <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            required
                          />
                          {errors.name && <p className="text-red-500 text-sm">{errors.name}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="shop">Shop</Label>
                          <Select
                            value={data.shop}
                            onValueChange={(value) => setData("shop", value)}
                            required
                          >
                            <SelectTrigger id="shop">
                              <SelectValue placeholder="Select a shop" />
                            </SelectTrigger>
                            <SelectContent>
                              {clients.map((client) => (
                                <SelectItem key={client} value={client}>
                                  {client}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                          {errors.shop && <p className="text-red-500 text-sm">{errors.shop}</p>}
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="phone">Phone</Label>
                          <Input
                            id="phone"
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData("phone", e.target.value)}
                            required
                            placeholder="e.g., 0241234567"
                          />
                          {errors.phone && <p className="text-red-500 text-sm">{errors.phone}</p>}
                        </div>
                      </div>
                      <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                        <Button type="submit" disabled={processing}>
                          {editingContact ? "Update" : "Create"}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
              </div>
            </div>

            <div className="flex flex-col sm:flex-row gap-4 justify-end">
              <Button
                onClick={handleDownloadTemplate}
                className="w-full sm:w-auto bg-blue-500 hover:bg-blue-600"
              >
                <Download className="h-4 w-4 mr-2" />
                Download Template
              </Button>
              <Dialog
                open={isBulkUploadOpen}
                onOpenChange={(open) => {
                  setIsBulkUploadOpen(open);
                  if (!open) {
                    bulkUploadForm.reset();
                  }
                }}
              >
                <DialogTrigger asChild>
                  <Button className="w-full sm:w-auto bg-green-500 hover:bg-green-600">
                    <Upload className="h-4 w-4 mr-2" />
                    Bulk Upload
                  </Button>
                </DialogTrigger>
                <DialogContent className="p-4 sm:max-w-md w-full">
                  <DialogHeader>
                    <DialogTitle>Bulk Upload Contacts</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleBulkUpload}>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="file">Upload Excel File</Label>
                        <Input
                          id="file"
                          type="file"
                          accept=".xlsx,.xls"
                          onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                              bulkUploadForm.setData("file", file);
                            }
                          }}
                        />
                        {bulkUploadForm.errors.file && (
                          <p className="text-red-500 text-sm">{bulkUploadForm.errors.file}</p>
                        )}
                      </div>
                    </div>
                    <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                      <Button type="submit" disabled={bulkUploadForm.processing || !bulkUploadForm.data.file}>
                        Upload
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
              <Button
                variant="destructive"
                onClick={handleBulkDelete}
                disabled={selectedIds.length === 0}
                className="w-full sm:w-auto"
              >
                <Trash className="h-4 w-4 mr-2" />
                Delete Selected
              </Button>
            </div>
          </div>

          {contacts.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Your Employee Contacts</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null
                    ? `Showing ${searchCount} of ${totalRecords} records matching your search`
                    : `Total Records: ${totalRecords}`}
                </div>
              </CardHeader>
              <CardContent>
                {/* Mobile View */}
                <div className="block md:hidden space-y-4">
                  {contacts.data.map((contact) => (
                    <div key={contact.staffid} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-2">
                          <Checkbox
                            checked={selectedIds.includes(contact.staffid)}
                            onCheckedChange={(checked) => handleSelect(contact.staffid, checked as boolean)}
                          />
                          <div className="font-semibold">{contact.name}</div>
                        </div>
                        <div className="text-sm text-muted-foreground">Staff ID: {contact.staffid}</div>
                        <div className="text-sm text-muted-foreground">Shop: {contact.shop || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Phone: {contact.phone || "N/A"}</div>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={() => handleEdit(contact)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDelete(contact.staffid)}>
                            <Trash className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Desktop Table View */}
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>
                          <Checkbox
                            checked={selectedIds.length === contacts.data.length && contacts.data.length > 0}
                            onCheckedChange={handleSelectAll}
                          />
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSort("staffid")} className="flex items-center gap-1">
                            Staff ID
                            <ArrowUpDown className="h-4 w-4" />
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSort("name")} className="flex items-center gap-1">
                            Name
                            <ArrowUpDown className="h-4 w-4" />
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSort("shop")} className="flex items-center gap-1">
                            Shop
                            <ArrowUpDown className="h-4 w-4" />
                          </Button>
                        </TableHead>
                        <TableHead>
                          <Button variant="ghost" onClick={() => handleSort("phone")} className="flex items-center gap-1">
                            Phone
                            <ArrowUpDown className="h-4 w-4" />
                          </Button>
                        </TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {contacts.data.map((contact) => (
                        <TableRow key={contact.staffid}>
                          <TableCell>
                            <Checkbox
                              checked={selectedIds.includes(contact.staffid)}
                              onCheckedChange={(checked) => handleSelect(contact.staffid, checked as boolean)}
                            />
                          </TableCell>
                          <TableCell>{contact.staffid}</TableCell>
                          <TableCell>{contact.name}</TableCell>
                          <TableCell>{contact.shop || "N/A"}</TableCell>
                          <TableCell>{contact.phone || "N/A"}</TableCell>
                          <TableCell className="text-right">
                            <div className="flex gap-2 justify-end">
                              <Button variant="outline" size="sm" onClick={() => handleEdit(contact)}>
                                <Pencil className="h-4 w-4" />
                              </Button>
                              <Button variant="destructive" size="sm" onClick={() => handleDelete(contact.staffid)}>
                                <Trash className="h-4 w-4" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>

                {/* Pagination */}
                <div className="flex justify-end mt-4">
                  <div className="flex gap-2">
                    {contacts.links.map((link, index) => (
                      <Button
                        key={index}
                        variant={link.active ? "default" : "outline"}
                        disabled={!link.url}
                        asChild={!!link.url}
                      >
                        {link.url ? (
                          <a href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : (
                          <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        )}
                      </Button>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent className="pt-6">
                <p className="text-center text-gray-500">No contacts found. Create a new contact to get started!</p>
              </CardContent>
            </Card>
          )}

          {/* Single Delete Confirmation */}
          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this contact? This action cannot be undone.</p>
              <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
                  Cancel
                </Button>
                <Button variant="destructive" onClick={confirmDelete} disabled={processing}>
                  Delete
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          {/* Bulk Delete Confirmation */}
          <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Bulk Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete {selectedIds.length} contact(s)? This action cannot be undone.</p>
              <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                <Button variant="outline" onClick={() => setIsBulkDeleteOpen(false)}>
                  Cancel
                </Button>
                <Button variant="destructive" onClick={confirmBulkDelete} disabled={bulkDeleteForm.processing}>
                  Delete Selected
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
