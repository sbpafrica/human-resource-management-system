import { Head, Link, useForm, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Archive,
  RotateCcw,
  Trash2,
  Download,
  Eye,
  Search,
  Filter,
  Users,
  Building2,
  CheckCircle,
  AlertCircle,
  X,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useState, useEffect, useCallback } from "react";
import debounce from "lodash/debounce";

interface ArchivedEmployee {
  staffid: string;
  employeename: string;
  company: string;
  shop: string | null;
  position: string | null;
  qualification: string | null;
  startdate: string | null;
  dateofbirth: string | null;
  ssnit: string | null;
  ghcard: string | null;
  bank: string | null;
  account: string | null;
  branch: string | null;
  momonumber: string | null;
  momoname: string | null;
  momaname?: string | null;
  phone1: string | null;
  phone2: string | null;
  gender: string | null;
  nationality: string | null;
  email: string | null;
  nextofkin: string | null;
  relation: string | null;
  contact: string | null;
  guarantor: string | null;
  contactgua: string | null;
  gpsaddress: string | null;
  archived_at: string | null;
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface Props {
  employees: {
    data: ArchivedEmployee[];
    links: PaginationLink[];
    from: number;
    to: number;
    total: number;
  };
  filters: {
    search: string;
    company: string;
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

const breadcrumbs = [
  { title: "Database", href: "/employeeportal" },
  { title: "Archived Staff", href: "/employeearchived" },
];

export default function ArchivedIndex({
  employees,
  filters,
  totalRecords,
  searchCount,
  clients,
  flash,
}: Props) {
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [viewingEmployee, setViewingEmployee] = useState<ArchivedEmployee | null>(null);
  const [isViewOpen, setIsViewOpen] = useState(false);
  const [isRestoreOpen, setIsRestoreOpen] = useState(false);
  const [restoreEmployee, setRestoreEmployee] = useState<ArchivedEmployee | null>(null);
  const [isBulkRestoreOpen, setIsBulkRestoreOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteEmployee, setDeleteEmployee] = useState<ArchivedEmployee | null>(null);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);

  const [search, setSearch] = useState(filters.search || "");
  const [company, setCompany] = useState(filters.company || "all");
  const [sort, setSort] = useState(filters.sort || "employeename");
  const [order, setOrder] = useState(filters.order || "asc");

  const [toastMessage, setToastMessage] = useState<string | null>(null);
  const [toastType, setToastType] = useState<"success" | "error">("success");

  // Show flash messages as toasts
  useEffect(() => {
    if (flash?.success) {
      setToastMessage(flash.success);
      setToastType("success");
    } else if (flash?.error) {
      setToastMessage(flash.error);
      setToastType("error");
    }
  }, [flash]);

  useEffect(() => {
    if (toastMessage) {
      const timer = setTimeout(() => setToastMessage(null), 8000);
      return () => clearTimeout(timer);
    }
  }, [toastMessage]);

  // Debounced search trigger
  const debouncedSearch = useCallback(
    debounce((newSearch: string, newCompany: string, newSort: string, newOrder: string) => {
      router.get(
        route("employeearchived.index"),
        {
          search: newSearch,
          company: newCompany === "all" ? "" : newCompany,
          sort: newSort,
          order: newOrder,
        },
        { preserveState: true, preserveScroll: true }
      );
    }, 350),
    []
  );

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;
    setSearch(val);
    debouncedSearch(val, company, sort, order);
  };

  const handleCompanyChange = (val: string) => {
    setCompany(val);
    debouncedSearch(search, val, sort, order);
  };

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelectedIds(employees.data.map((e) => e.staffid));
    } else {
      setSelectedIds([]);
    }
  };

  const handleSelectRow = (staffid: string, checked: boolean) => {
    if (checked) {
      setSelectedIds((prev) => [...prev, staffid]);
    } else {
      setSelectedIds((prev) => prev.filter((id) => id !== staffid));
    }
  };

  const handleRestoreConfirm = () => {
    if (!restoreEmployee) return;
    router.post(
      route("employeearchived.restore", restoreEmployee.staffid),
      {},
      {
        onSuccess: () => {
          setIsRestoreOpen(false);
          setRestoreEmployee(null);
        },
      }
    );
  };

  const handleBulkRestoreConfirm = () => {
    if (selectedIds.length === 0) return;
    router.post(
      route("employeearchived.bulk-restore"),
      { ids: selectedIds },
      {
        onSuccess: () => {
          setIsBulkRestoreOpen(false);
          setSelectedIds([]);
        },
      }
    );
  };

  const handleDeleteConfirm = () => {
    if (!deleteEmployee) return;
    router.delete(route("employeearchived.destroy", deleteEmployee.staffid), {
      onSuccess: () => {
        setIsDeleteOpen(false);
        setDeleteEmployee(null);
      },
    });
  };

  const handleBulkDeleteConfirm = () => {
    if (selectedIds.length === 0) return;
    router.post(
      route("employeearchived.bulk-destroy"),
      { ids: selectedIds },
      {
        onSuccess: () => {
          setIsBulkDeleteOpen(false);
          setSelectedIds([]);
        },
      }
    );
  };

  const handleExport = () => {
    const params = new URLSearchParams();
    if (search) params.append("search", search);
    if (company && company !== "all") params.append("company", company);
    if (sort) params.append("sort", sort);
    if (order) params.append("order", order);

    window.location.href = route("employeearchived.export") + "?" + params.toString();
  };

  const isAllSelected =
    employees.data.length > 0 && selectedIds.length === employees.data.length;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Archived Staff Records" />

      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        {/* Toast Alert */}
        {toastMessage && (
          <div
            className={`flex items-center justify-between p-4 rounded-lg shadow-md border text-sm transition-all ${
              toastType === "success"
                ? "bg-green-50 border-green-200 text-green-800"
                : "bg-red-50 border-red-200 text-red-800"
            }`}
          >
            <div className="flex items-center gap-2">
              {toastType === "success" ? (
                <CheckCircle className="h-5 w-5 text-green-600 flex-shrink-0" />
              ) : (
                <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0" />
              )}
              <span>{toastMessage}</span>
            </div>
            <button
              onClick={() => setToastMessage(null)}
              className="text-gray-500 hover:text-gray-700 ml-4"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        {/* Top Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card className="border-l-4 border-l-amber-500">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">
                Total Archived Records
              </CardTitle>
              <Archive className="h-5 w-5 text-amber-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{totalRecords.toLocaleString()}</div>
              <p className="text-xs text-gray-500 mt-1">Inactive/Archived staff stored in system</p>
            </CardContent>
          </Card>

          <Card className="border-l-4 border-l-blue-500">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">
                Current Filtered Results
              </CardTitle>
              <Users className="h-5 w-5 text-blue-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {(searchCount !== null ? searchCount : totalRecords).toLocaleString()}
              </div>
              <p className="text-xs text-gray-500 mt-1">Matching current search & client filter</p>
            </CardContent>
          </Card>

          <Card className="border-l-4 border-l-emerald-500">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">
                Active Client Scope
              </CardTitle>
              <Building2 className="h-5 w-5 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{clients.length}</div>
              <p className="text-xs text-gray-500 mt-1">Companies accessible in archive</p>
            </CardContent>
          </Card>
        </div>

        {/* Search & Actions Bar */}
        <Card>
          <CardHeader className="pb-3">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div>
                <CardTitle className="text-xl flex items-center gap-2">
                  <Archive className="h-5 w-5 text-amber-600" />
                  Archived Staff Records
                </CardTitle>
                <p className="text-xs text-gray-500 mt-1">
                  View, search, restore to active portal, or export historical staff records.
                </p>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                {selectedIds.length > 0 && (
                  <>
                    <Button
                      variant="outline"
                      size="sm"
                      className="bg-green-50 text-green-700 border-green-300 hover:bg-green-100"
                      onClick={() => setIsBulkRestoreOpen(true)}
                    >
                      <RotateCcw className="h-4 w-4 mr-1.5" />
                      Restore Selected ({selectedIds.length})
                    </Button>
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => setIsBulkDeleteOpen(true)}
                    >
                      <Trash2 className="h-4 w-4 mr-1.5" />
                      Delete Selected ({selectedIds.length})
                    </Button>
                  </>
                )}

                <Button
                  variant="outline"
                  size="sm"
                  className="border-emerald-600 text-emerald-700 hover:bg-emerald-50"
                  onClick={handleExport}
                >
                  <Download className="h-4 w-4 mr-1.5" />
                  Export to Excel
                </Button>
              </div>
            </div>
          </CardHeader>

          <CardContent className="space-y-4">
            {/* Filters */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div className="relative sm:col-span-2">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <Input
                  placeholder="Search by Staff ID, Name, Company, Shop, Position, Ghana Card, Phone..."
                  className="pl-9"
                  value={search}
                  onChange={handleSearchChange}
                />
              </div>

              <div>
                <Select value={company} onValueChange={handleCompanyChange}>
                  <SelectTrigger>
                    <SelectValue placeholder="Filter by Client/Company" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Companies</SelectItem>
                    {clients.map((c) => (
                      <SelectItem key={c} value={c}>
                        {c}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* Table */}
            <div className="rounded-md border overflow-x-auto">
              <Table>
                <TableHeader className="bg-gray-50/80">
                  <TableRow>
                    <TableHead className="w-10">
                      <Checkbox
                        checked={isAllSelected}
                        onCheckedChange={(checked) => handleSelectAll(!!checked)}
                        aria-label="Select all"
                      />
                    </TableHead>
                    <TableHead className="font-semibold">Staff ID</TableHead>
                    <TableHead className="font-semibold">Employee Name</TableHead>
                    <TableHead className="font-semibold">Company</TableHead>
                    <TableHead className="font-semibold">Shop / Dept</TableHead>
                    <TableHead className="font-semibold">Position</TableHead>
                    <TableHead className="font-semibold">Phone</TableHead>
                    <TableHead className="font-semibold">Start Date</TableHead>
                    <TableHead className="font-semibold">Archived Date</TableHead>
                    <TableHead className="text-right font-semibold">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {employees.data.length > 0 ? (
                    employees.data.map((emp) => {
                      const isSelected = selectedIds.includes(emp.staffid);
                      return (
                        <TableRow key={emp.staffid} className={isSelected ? "bg-amber-50/50" : ""}>
                          <TableCell>
                            <Checkbox
                              checked={isSelected}
                              onCheckedChange={(checked) =>
                                handleSelectRow(emp.staffid, !!checked)
                              }
                              aria-label={`Select ${emp.staffid}`}
                            />
                          </TableCell>
                          <TableCell className="font-mono text-xs font-medium text-gray-700">
                            {emp.staffid}
                          </TableCell>
                          <TableCell className="font-medium text-gray-900">
                            {emp.employeename || "N/A"}
                          </TableCell>
                          <TableCell>{emp.company || "N/A"}</TableCell>
                          <TableCell>{emp.shop || "N/A"}</TableCell>
                          <TableCell>{emp.position || "N/A"}</TableCell>
                          <TableCell>{emp.phone1 || emp.phone2 || "N/A"}</TableCell>
                          <TableCell className="text-xs text-gray-500">
                            {emp.startdate || "N/A"}
                          </TableCell>
                          <TableCell className="text-xs text-gray-500">
                            {emp.archived_at || "N/A"}
                          </TableCell>
                          <TableCell className="text-right">
                            <div className="flex items-center justify-end gap-1.5">
                              <Button
                                variant="outline"
                                size="sm"
                                className="h-8 w-8 p-0"
                                title="View Details"
                                onClick={() => {
                                  setViewingEmployee(emp);
                                  setIsViewOpen(true);
                                }}
                              >
                                <Eye className="h-4 w-4 text-blue-600" />
                              </Button>
                              <Button
                                variant="outline"
                                size="sm"
                                className="h-8 w-8 p-0 border-green-300 hover:bg-green-50"
                                title="Restore to Active"
                                onClick={() => {
                                  setRestoreEmployee(emp);
                                  setIsRestoreOpen(true);
                                }}
                              >
                                <RotateCcw className="h-4 w-4 text-green-600" />
                              </Button>
                              <Button
                                variant="outline"
                                size="sm"
                                className="h-8 w-8 p-0 border-red-300 hover:bg-red-50"
                                title="Permanently Delete"
                                onClick={() => {
                                  setDeleteEmployee(emp);
                                  setIsDeleteOpen(true);
                                }}
                              >
                                <Trash2 className="h-4 w-4 text-red-600" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      );
                    })
                  ) : (
                    <TableRow>
                      <TableCell colSpan={10} className="h-32 text-center text-gray-500">
                        No archived staff records found matching your filters.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>

            {/* Pagination */}
            {employees.links && employees.links.length > 3 && (
              <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                <div className="text-xs text-gray-500">
                  Showing {employees.from || 0} to {employees.to || 0} of {employees.total} records
                </div>
                <div className="flex flex-wrap gap-1">
                  {employees.links.map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? "default" : "outline"}
                      size="sm"
                      disabled={!link.url}
                      asChild={!!link.url}
                    >
                      {link.url ? (
                        <Link
                          href={link.url}
                          preserveState
                          preserveScroll
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      ) : (
                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                      )}
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* View Employee Modal */}
        <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
          <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <Users className="h-5 w-5 text-blue-600" />
                Archived Staff Profile: {viewingEmployee?.employeename}
              </DialogTitle>
            </DialogHeader>

            {viewingEmployee && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 py-3 text-sm">
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Staff ID</div>
                  <div className="font-mono font-bold text-gray-900">{viewingEmployee.staffid}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Full Name</div>
                  <div className="font-bold text-gray-900">{viewingEmployee.employeename}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Company / Client</div>
                  <div>{viewingEmployee.company || "N/A"}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Shop / Department</div>
                  <div>{viewingEmployee.shop || "N/A"}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Position</div>
                  <div>{viewingEmployee.position || "N/A"}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Qualification</div>
                  <div>{viewingEmployee.qualification || "N/A"}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Ghana Card ID</div>
                  <div>{viewingEmployee.ghcard || "N/A"}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">SSNIT Number</div>
                  <div>{viewingEmployee.ssnit || "N/A"}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Phone 1 / Phone 2</div>
                  <div>
                    {viewingEmployee.phone1 || "N/A"} {viewingEmployee.phone2 ? ` / ${viewingEmployee.phone2}` : ""}
                  </div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Email</div>
                  <div>{viewingEmployee.email || "N/A"}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Bank / Account / Branch</div>
                  <div>
                    {viewingEmployee.bank || "N/A"} {viewingEmployee.account ? `- ${viewingEmployee.account}` : ""}{" "}
                    {viewingEmployee.branch ? `(${viewingEmployee.branch})` : ""}
                  </div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Mobile Money</div>
                  <div>
                    {viewingEmployee.momonumber || "N/A"}{" "}
                    {viewingEmployee.momoname || viewingEmployee.momaname
                      ? `(${viewingEmployee.momoname || viewingEmployee.momaname})`
                      : ""}
                  </div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Next of Kin / Contact</div>
                  <div>
                    {viewingEmployee.nextofkin || "N/A"}{" "}
                    {viewingEmployee.relation ? `(${viewingEmployee.relation})` : ""}{" "}
                    {viewingEmployee.contact ? `- ${viewingEmployee.contact}` : ""}
                  </div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Guarantor / Contact</div>
                  <div>
                    {viewingEmployee.guarantor || "N/A"}{" "}
                    {viewingEmployee.contactgua ? `- ${viewingEmployee.contactgua}` : ""}
                  </div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">GPS Address</div>
                  <div>{viewingEmployee.gpsaddress || "N/A"}</div>
                </div>
                <div className="space-y-1.5 p-3 rounded-lg bg-gray-50">
                  <div className="text-xs font-semibold text-gray-500">Archived Date</div>
                  <div>{viewingEmployee.archived_at || "N/A"}</div>
                </div>
              </div>
            )}

            <DialogFooter className="gap-2">
              <Button
                variant="outline"
                className="text-green-700 border-green-300 hover:bg-green-50"
                onClick={() => {
                  setIsViewOpen(false);
                  setRestoreEmployee(viewingEmployee);
                  setIsRestoreOpen(true);
                }}
              >
                <RotateCcw className="h-4 w-4 mr-1.5" />
                Restore This Employee
              </Button>
              <Button variant="default" onClick={() => setIsViewOpen(false)}>
                Close
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Single Restore Modal */}
        <Dialog open={isRestoreOpen} onOpenChange={setIsRestoreOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <RotateCcw className="h-5 w-5 text-green-600" />
                Confirm Employee Restore
              </DialogTitle>
            </DialogHeader>
            <p className="text-sm text-gray-600">
              Are you sure you want to restore{" "}
              <strong>{restoreEmployee?.employeename}</strong> (Staff ID:{" "}
              <code>{restoreEmployee?.staffid}</code>) back to active Employee Portal records?
            </p>
            <DialogFooter className="gap-2">
              <Button variant="outline" onClick={() => setIsRestoreOpen(false)}>
                Cancel
              </Button>
              <Button
                className="bg-green-600 hover:bg-green-700 text-white"
                onClick={handleRestoreConfirm}
              >
                Yes, Restore Employee
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Bulk Restore Modal */}
        <Dialog open={isBulkRestoreOpen} onOpenChange={setIsBulkRestoreOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <RotateCcw className="h-5 w-5 text-green-600" />
                Confirm Bulk Restore
              </DialogTitle>
            </DialogHeader>
            <p className="text-sm text-gray-600">
              Are you sure you want to restore <strong>{selectedIds.length}</strong> selected
              employee(s) back to active Employee Portal records?
            </p>
            <DialogFooter className="gap-2">
              <Button variant="outline" onClick={() => setIsBulkRestoreOpen(false)}>
                Cancel
              </Button>
              <Button
                className="bg-green-600 hover:bg-green-700 text-white"
                onClick={handleBulkRestoreConfirm}
              >
                Restore {selectedIds.length} Employee(s)
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Single Delete Modal */}
        <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-red-600">
                <Trash2 className="h-5 w-5 text-red-600" />
                Permanently Delete Record
              </DialogTitle>
            </DialogHeader>
            <p className="text-sm text-gray-600">
              Are you sure you want to permanently delete the archived record for{" "}
              <strong>{deleteEmployee?.employeename}</strong> (Staff ID:{" "}
              <code>{deleteEmployee?.staffid}</code>)? This action cannot be undone.
            </p>
            <DialogFooter className="gap-2">
              <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
                Cancel
              </Button>
              <Button variant="destructive" onClick={handleDeleteConfirm}>
                Permanently Delete
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Bulk Delete Modal */}
        <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-red-600">
                <Trash2 className="h-5 w-5 text-red-600" />
                Permanently Delete Selected Records
              </DialogTitle>
            </DialogHeader>
            <p className="text-sm text-gray-600">
              Are you sure you want to permanently delete <strong>{selectedIds.length}</strong>{" "}
              selected archived record(s)? This action cannot be undone.
            </p>
            <DialogFooter className="gap-2">
              <Button variant="outline" onClick={() => setIsBulkDeleteOpen(false)}>
                Cancel
              </Button>
              <Button variant="destructive" onClick={handleBulkDeleteConfirm}>
                Permanently Delete {selectedIds.length} Record(s)
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  );
}
