import { Head, useForm, router, InertiaFormProps } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Plus,
  Trash,
  CheckCircle,
  XCircle,
  Download,
  ArrowUpDown,
  ReceiptText,
  Printer,
  Eye,
  Edit,
  DollarSign,
  FileCheck,
  Clock,
  ChevronLeft,
  ChevronRight,
  Filter,
  X,
  Search,
  CreditCard,
} from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect, useCallback } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";
import debounce from "lodash/debounce";

interface ReceiptItem {
  id: number;
  receipt_number: string;
  applicant_name: string;
  address: string;
  contact: string;
  position: string;
  client: string;
  descriptions: string;
  amount_paid: number | string;
  receipt_date: string;
  mode_of_payment: "Momo" | "Cash";
  receiving_officer: string;
  staffid: string;
  status: "Pending" | "Paid" | "Archived";
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface Props {
  receipts: {
    data: ReceiptItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
  };
  filters: {
    search: string;
    status: string;
    client: string;
    from_date: string;
    to_date: string;
    sort: string;
    order: string;
  };
  stats: {
    total_records: number;
    total_revenue: number;
    paid_count: number;
    pending_count: number;
  };
  totalRecords: number;
  searchCount: number | null;
  clients: string[];
  currentOfficer: string;
  flash?: {
    success?: string;
    error?: string;
  };
}

interface ExtendedFormProps<T> extends InertiaFormProps<T> {}

const breadcrumbs = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Receipts", href: "/receipts" },
];

const DEFAULT_DESCRIPTIONS = ["Forms", "Medicals", "Police reports"];

export default function ReceiptIndex({
  receipts,
  filters,
  stats,
  totalRecords,
  searchCount,
  clients,
  currentOfficer,
  flash,
}: Props) {
  const [isAddOpen, setIsAddOpen] = useState(false);
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [isViewOpen, setIsViewOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);
  const [showMobileFilters, setShowMobileFilters] = useState(false);
  const [initiatingId, setInitiatingId] = useState<number | null>(null);

  const [selectedReceipt, setSelectedReceipt] = useState<ReceiptItem | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");

  // Filters State
  const [search, setSearch] = useState(filters.search || "");
  const [statusFilter, setStatusFilter] = useState(filters.status || "all");
  const [clientFilter, setClientFilter] = useState(filters.client || "all");
  const [fromDate, setFromDate] = useState(filters.from_date || "");
  const [toDate, setToDate] = useState(filters.to_date || "");
  const [sort, setSort] = useState(filters.sort || "id");
  const [order, setOrder] = useState(filters.order || "desc");

  const todayStr = new Date().toISOString().split("T")[0];

  // Forms
  const addForm = useForm({
    applicant_name: "",
    address: "",
    contact: "",
    position: "",
    client: clients.length > 0 ? clients[0] : "Twellium",
    descriptions: ["Forms", "Medicals", "Police reports"],
    amount_paid: "150.00",
    receipt_date: todayStr,
    receiving_officer: currentOfficer || "",
    mode_of_payment: "Momo" as "Momo" | "Cash",
    status: "Pending" as "Pending" | "Paid" | "Archived",
    initiate_paystack: false,
  }) as ExtendedFormProps<any>;

  const editForm = useForm({
    id: 0,
    receipt_number: "",
    applicant_name: "",
    address: "",
    contact: "",
    position: "",
    client: "",
    descriptions: [] as string[],
    amount_paid: "",
    receipt_date: "",
    receiving_officer: "",
    mode_of_payment: "Momo" as "Momo" | "Cash",
    status: "Paid" as "Pending" | "Paid" | "Archived",
  }) as ExtendedFormProps<any>;

  const bulkDeleteForm = useForm({
    ids: [] as number[],
  });

  // Debounced search / filter update using Inertia router
  const applyFilters = useCallback(
    (newParams: Record<string, string>) => {
      const query = {
        search,
        status: statusFilter,
        client: clientFilter,
        from_date: fromDate,
        to_date: toDate,
        sort,
        order,
        ...newParams,
      };

      router.get(route("receipts.index"), query, {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [search, statusFilter, clientFilter, fromDate, toDate, sort, order]
  );

  const debouncedSearch = useCallback(
    debounce((val: string) => {
      applyFilters({ search: val });
    }, 300),
    [applyFilters]
  );

  useEffect(() => {
    bulkDeleteForm.setData("ids", selectedIds);
  }, [selectedIds]);

  useEffect(() => {
    if (flash?.success) {
      setToastMessage(flash.success);
      setToastType("success");
      setShowToast(true);
      const timer = setTimeout(() => setShowToast(false), 5000);
      return () => clearTimeout(timer);
    }
    if (flash?.error) {
      setToastMessage(flash.error);
      setToastType("error");
      setShowToast(true);
      const timer = setTimeout(() => setShowToast(false), 5000);
      return () => clearTimeout(timer);
    }
  }, [flash]);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearch(value);
    debouncedSearch(value);
  };

  const handleSort = (column: string) => {
    const newOrder = sort === column && order === "asc" ? "desc" : "asc";
    setSort(column);
    setOrder(newOrder);
    applyFilters({ sort: column, order: newOrder });
  };

  const handlePageClick = (url: string | null) => {
    if (!url) return;
    router.visit(url, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const resetFilters = () => {
    setSearch("");
    setStatusFilter("all");
    setClientFilter("all");
    setFromDate("");
    setToDate("");
    setSort("id");
    setOrder("desc");
    router.get(
      route("receipts.index"),
      {},
      {
        preserveState: true,
        preserveScroll: true,
      }
    );
  };

  const handleAddSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    addForm.post(route("receipts.store"), {
      onSuccess: () => {
        setIsAddOpen(false);
        addForm.reset();
      },
    });
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    editForm.put(route("receipts.update", editForm.data.id), {
      onSuccess: () => {
        setIsEditOpen(false);
      },
    });
  };

  const handlePaystackCheckout = (id: number) => {
    setInitiatingId(id);
    router.post(
      route("receipts.initiate-payment", id),
      {},
      {
        onFinish: () => setInitiatingId(null),
      }
    );
  };

  const handleDeleteConfirm = () => {
    if (selectedReceipt) {
      addForm.delete(route("receipts.destroy", selectedReceipt.id), {
        onSuccess: () => {
          setIsDeleteOpen(false);
          setSelectedReceipt(null);
        },
      });
    }
  };

  const handleBulkDeleteConfirm = () => {
    bulkDeleteForm.post(route("receipts.bulk-destroy"), {
      onSuccess: () => {
        setIsBulkDeleteOpen(false);
        setSelectedIds([]);
      },
    });
  };

  const handleExport = () => {
    const query = new URLSearchParams({
      search,
      status: statusFilter,
      client: clientFilter,
      from_date: fromDate,
      to_date: toDate,
    }).toString();
    window.location.href = `${route("receipts.export")}?${query}`;
  };

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelectedIds(receipts.data.map((item) => item.id));
    } else {
      setSelectedIds([]);
    }
  };

  const handleSelectOne = (id: number, checked: boolean) => {
    if (checked) {
      setSelectedIds((prev) => [...prev, id]);
    } else {
      setSelectedIds((prev) => prev.filter((item) => item !== id));
    }
  };

  const openEditModal = (item: ReceiptItem) => {
    setSelectedReceipt(item);
    const descArray = item.descriptions ? item.descriptions.split(",").map((d) => d.trim()) : [];
    editForm.setData({
      id: item.id,
      receipt_number: item.receipt_number,
      applicant_name: item.applicant_name,
      address: item.address,
      contact: item.contact,
      position: item.position,
      client: item.client,
      descriptions: descArray,
      amount_paid: item.amount_paid.toString(),
      receipt_date: item.receipt_date,
      receiving_officer: item.receiving_officer,
      mode_of_payment: item.mode_of_payment,
      status: item.status,
    });
    setIsEditOpen(true);
  };

  const openViewModal = (item: ReceiptItem) => {
    setSelectedReceipt(item);
    setIsViewOpen(true);
  };

  const openDeleteModal = (item: ReceiptItem) => {
    setSelectedReceipt(item);
    setIsDeleteOpen(true);
  };

  const toggleAddDescription = (desc: string) => {
    const current = [...addForm.data.descriptions];
    if (current.includes(desc)) {
      addForm.setData(
        "descriptions",
        current.filter((d) => d !== desc)
      );
    } else {
      addForm.setData("descriptions", [...current, desc]);
    }
  };

  const toggleEditDescription = (desc: string) => {
    const current = [...editForm.data.descriptions];
    if (current.includes(desc)) {
      editForm.setData(
        "descriptions",
        current.filter((d) => d !== desc)
      );
    } else {
      editForm.setData("descriptions", [...current, desc]);
    }
  };

  const handlePrint = () => {
    if (!selectedReceipt) return;
    const printWindow = window.open("", "_blank");
    if (!printWindow) return;

    const receiptHtml = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Receipt #${selectedReceipt.receipt_number}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; color: #333; max-width: 650px; margin: 0 auto; border: 2px solid #0284c7; border-radius: 8px; }
          .header { text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 20px; }
          .header h1 { margin: 0; color: #0284c7; font-size: 24px; text-transform: uppercase; }
          .header p { margin: 4px 0 0; font-size: 13px; color: #666; }
          .receipt-title { display: flex; justify-content: space-between; margin-bottom: 20px; }
          .receipt-title div { font-size: 14px; }
          .receipt-title .no { font-size: 18px; font-weight: bold; color: #0284c7; }
          .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
          .details-table td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
          .details-table td.label { font-weight: bold; width: 35%; background: #f8fafc; }
          .amount-box { background: #f0f9ff; border: 1px solid #bae6fd; padding: 15px; text-align: center; border-radius: 6px; margin-bottom: 20px; }
          .amount-box .amount { font-size: 26px; font-weight: bold; color: #0369a1; }
          .footer { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 15px; border-top: 1px dashed #ccc; font-size: 12px; }
          .signature-box { text-align: center; width: 45%; }
          .signature-line { border-top: 1px solid #000; margin-top: 30px; padding-top: 5px; }
          @media print {
            body { border: none; }
          }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>SBP AFRICA</h1>
          <p>Official Payment Receipt</p>
        </div>
        <div class="receipt-title">
          <div><strong>Date:</strong> ${selectedReceipt.receipt_date}</div>
          <div class="no">RECEIPT #${selectedReceipt.receipt_number}</div>
        </div>
        <table class="details-table">
          <tr><td class="label">Received From:</td><td>${selectedReceipt.applicant_name}</td></tr>
          <tr><td class="label">Contact / Phone:</td><td>${selectedReceipt.contact}</td></tr>
          <tr><td class="label">Address:</td><td>${selectedReceipt.address}</td></tr>
          <tr><td class="label">Position:</td><td>${selectedReceipt.position}</td></tr>
          <tr><td class="label">Client / Company:</td><td>${selectedReceipt.client}</td></tr>
          <tr><td class="label">Payment For:</td><td>${selectedReceipt.descriptions}</td></tr>
          <tr><td class="label">Payment Mode:</td><td>${selectedReceipt.mode_of_payment}</td></tr>
          <tr><td class="label">Status:</td><td>${selectedReceipt.status}</td></tr>
        </table>
        <div class="amount-box">
          <div>TOTAL AMOUNT PAID</div>
          <div class="amount">GHS ${Number(selectedReceipt.amount_paid).toFixed(2)}</div>
        </div>
        <div class="footer">
          <div class="signature-box">
            <div class="signature-line">Applicant Signature</div>
          </div>
          <div class="signature-box">
            <div><strong>${selectedReceipt.receiving_officer}</strong></div>
            <div class="signature-line">Receiving Officer Signature</div>
          </div>
        </div>
      </body>
      </html>
    `;

    printWindow.document.write(receiptHtml);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
      printWindow.print();
    }, 250);
  };

  const hasActiveFilters =
    search !== "" || statusFilter !== "all" || clientFilter !== "all" || fromDate !== "" || toDate !== "";

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Receipts Management" />

        <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 md:gap-6 md:p-6 max-w-7xl mx-auto w-full">
          {/* Toast Notification */}
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 text-white shadow-lg transition-all ${
                toastType === "success" ? "bg-emerald-600" : "bg-red-600"
              }`}
            >
              {toastType === "success" ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
            </div>
          )}

          {/* Page Header */}
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <ReceiptText className="h-6 w-6 sm:h-7 sm:w-7 text-sky-600 shrink-0" />
                <span>Receipts Management</span>
              </h1>
              <p className="text-xs sm:text-sm text-muted-foreground mt-0.5">
                Issue, track, edit and manage payment receipts for job applicants and clients.
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2 pt-1 sm:pt-0">
              <Button onClick={() => setIsAddOpen(true)} className="bg-sky-600 hover:bg-sky-700 text-white text-xs sm:text-sm h-9">
                <Plus className="mr-1.5 h-4 w-4" /> Issue Receipt
              </Button>
              <Button variant="outline" onClick={handleExport} className="text-xs sm:text-sm h-9">
                <Download className="mr-1.5 h-4 w-4" /> Export
              </Button>
              {selectedIds.length > 0 && (
                <Button variant="destructive" onClick={() => setIsBulkDeleteOpen(true)} className="text-xs sm:text-sm h-9">
                  <Trash className="mr-1.5 h-4 w-4" /> Delete ({selectedIds.length})
                </Button>
              )}
            </div>
          </div>

          {/* Responsive Stats Section */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <Card className="p-3 sm:p-4">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Total Revenue</span>
                <div className="p-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950">
                  <DollarSign className="h-4 w-4 text-emerald-600" />
                </div>
              </div>
              <div className="text-lg sm:text-2xl font-bold text-emerald-600 mt-1">
                GHS {stats.total_revenue.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
              </div>
              <p className="text-[10px] sm:text-xs text-muted-foreground mt-0.5">Non-archived receipts</p>
            </Card>

            <Card className="p-3 sm:p-4">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Total Receipts</span>
                <div className="p-1.5 rounded-full bg-sky-50 dark:bg-sky-950">
                  <ReceiptText className="h-4 w-4 text-sky-600" />
                </div>
              </div>
              <div className="text-lg sm:text-2xl font-bold mt-1">{stats.total_records}</div>
              <p className="text-[10px] sm:text-xs text-muted-foreground mt-0.5">All issued receipts</p>
            </Card>

            <Card className="p-3 sm:p-4">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Paid Receipts</span>
                <div className="p-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950">
                  <FileCheck className="h-4 w-4 text-emerald-600" />
                </div>
              </div>
              <div className="text-lg sm:text-2xl font-bold text-emerald-700 mt-1">{stats.paid_count}</div>
              <p className="text-[10px] sm:text-xs text-muted-foreground mt-0.5">Fully paid</p>
            </Card>

            <Card className="p-3 sm:p-4">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Pending</span>
                <div className="p-1.5 rounded-full bg-amber-50 dark:bg-amber-950">
                  <Clock className="h-4 w-4 text-amber-600" />
                </div>
              </div>
              <div className="text-lg sm:text-2xl font-bold text-amber-600 mt-1">{stats.pending_count}</div>
              <p className="text-[10px] sm:text-xs text-muted-foreground mt-0.5">Awaiting payment</p>
            </Card>
          </div>

          {/* Filters Card */}
          <Card>
            <CardContent className="p-3 sm:p-4">
              <div className="flex flex-col gap-3">
                {/* Search & Toggle row */}
                <div className="flex gap-2">
                  <div className="relative flex-1">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      placeholder="Search applicant, receipt #, officer..."
                      className="pl-8 text-xs sm:text-sm h-9"
                      value={search}
                      onChange={handleSearchChange}
                    />
                    {search && (
                      <button
                        onClick={() => {
                          setSearch("");
                          applyFilters({ search: "" });
                        }}
                        className="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                      >
                        <X className="h-4 w-4" />
                      </button>
                    )}
                  </div>

                  <Button
                    variant="outline"
                    size="sm"
                    className="sm:hidden h-9 px-3 text-xs"
                    onClick={() => setShowMobileFilters(!showMobileFilters)}
                  >
                    <Filter className="h-3.5 w-3.5 mr-1" />
                    Filters
                    {hasActiveFilters && <span className="ml-1 rounded-full bg-sky-600 w-2 h-2" />}
                  </Button>

                  {hasActiveFilters && (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="hidden sm:flex h-9 text-xs text-muted-foreground hover:text-foreground"
                      onClick={resetFilters}
                    >
                      Clear Filters
                    </Button>
                  )}
                </div>

                {/* Filter dropdowns */}
                <div
                  className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1 ${
                    showMobileFilters ? "block" : "hidden sm:grid"
                  }`}
                >
                  <div>
                    <Label className="text-[11px] text-muted-foreground mb-1 block">Status</Label>
                    <Select
                      value={statusFilter}
                      onValueChange={(val) => {
                        setStatusFilter(val);
                        applyFilters({ status: val });
                      }}
                    >
                      <SelectTrigger className="h-8 text-xs">
                        <SelectValue placeholder="All Statuses" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem value="Paid">Paid</SelectItem>
                        <SelectItem value="Pending">Pending</SelectItem>
                        <SelectItem value="Archived">Archived</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div>
                    <Label className="text-[11px] text-muted-foreground mb-1 block">Client</Label>
                    <Select
                      value={clientFilter}
                      onValueChange={(val) => {
                        setClientFilter(val);
                        applyFilters({ client: val });
                      }}
                    >
                      <SelectTrigger className="h-8 text-xs">
                        <SelectValue placeholder="All Clients" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All Clients</SelectItem>
                        {clients.map((c) => (
                          <SelectItem key={c} value={c}>
                            {c}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div>
                    <Label className="text-[11px] text-muted-foreground mb-1 block">From Date</Label>
                    <Input
                      type="date"
                      className="h-8 text-xs"
                      value={fromDate}
                      onChange={(e) => {
                        setFromDate(e.target.value);
                        applyFilters({ from_date: e.target.value });
                      }}
                    />
                  </div>

                  <div>
                    <Label className="text-[11px] text-muted-foreground mb-1 block">To Date</Label>
                    <Input
                      type="date"
                      className="h-8 text-xs"
                      value={toDate}
                      onChange={(e) => {
                        setToDate(e.target.value);
                        applyFilters({ to_date: e.target.value });
                      }}
                    />
                  </div>
                </div>

                {hasActiveFilters && (
                  <div className="sm:hidden pt-1">
                    <Button variant="outline" size="sm" className="w-full text-xs h-8" onClick={resetFilters}>
                      Reset Filters
                    </Button>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Receipts Records */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between py-3 px-4">
              <CardTitle className="text-sm sm:text-base font-semibold">
                Receipts Records
                {searchCount !== null && (
                  <span className="ml-2 text-xs font-normal text-muted-foreground">
                    ({searchCount} found)
                  </span>
                )}
              </CardTitle>
              <div className="text-xs text-muted-foreground">
                Total: {totalRecords}
              </div>
            </CardHeader>
            <CardContent className="p-0">
              {/* Desktop & Tablet Table View */}
              <div className="hidden md:block overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-10">
                        <Checkbox
                          checked={receipts.data.length > 0 && selectedIds.length === receipts.data.length}
                          onCheckedChange={handleSelectAll}
                        />
                      </TableHead>
                      <TableHead className="cursor-pointer" onClick={() => handleSort("receipt_number")}>
                        <div className="flex items-center gap-1">
                          Receipt # <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead className="cursor-pointer" onClick={() => handleSort("applicant_name")}>
                        <div className="flex items-center gap-1">
                          Applicant <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead className="cursor-pointer" onClick={() => handleSort("client")}>
                        <div className="flex items-center gap-1">
                          Client <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead>Descriptions</TableHead>
                      <TableHead className="cursor-pointer" onClick={() => handleSort("amount_paid")}>
                        <div className="flex items-center gap-1">
                          Amount (GHS) <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead className="cursor-pointer" onClick={() => handleSort("receipt_date")}>
                        <div className="flex items-center gap-1">
                          Date <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead>Mode</TableHead>
                      <TableHead>Officer</TableHead>
                      <TableHead className="cursor-pointer" onClick={() => handleSort("status")}>
                        <div className="flex items-center gap-1">
                          Status <ArrowUpDown className="h-3 w-3" />
                        </div>
                      </TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {receipts.data.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={11} className="text-center py-8 text-muted-foreground text-sm">
                          No receipts found matching your criteria.
                        </TableCell>
                      </TableRow>
                    ) : (
                      receipts.data.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell>
                            <Checkbox
                              checked={selectedIds.includes(item.id)}
                              onCheckedChange={(checked) => handleSelectOne(item.id, !!checked)}
                            />
                          </TableCell>
                          <TableCell className="font-mono font-semibold text-xs text-sky-700">
                            {item.receipt_number}
                          </TableCell>
                          <TableCell>
                            <div className="font-medium text-xs sm:text-sm">{item.applicant_name}</div>
                            <div className="text-[11px] text-muted-foreground">{item.contact}</div>
                          </TableCell>
                          <TableCell className="text-xs">{item.client}</TableCell>
                          <TableCell className="max-w-[180px] truncate" title={item.descriptions}>
                            <span className="text-[11px] bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">
                              {item.descriptions}
                            </span>
                          </TableCell>
                          <TableCell className="font-semibold text-xs sm:text-sm text-emerald-700">
                            {Number(item.amount_paid).toFixed(2)}
                          </TableCell>
                          <TableCell className="text-xs whitespace-nowrap">{item.receipt_date}</TableCell>
                          <TableCell className="text-xs font-medium">{item.mode_of_payment}</TableCell>
                          <TableCell className="text-xs">{item.receiving_officer}</TableCell>
                          <TableCell>
                            <span
                              className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ${
                                item.status === "Paid"
                                  ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                  : item.status === "Pending"
                                  ? "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                  : "bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300"
                              }`}
                            >
                              {item.status}
                            </span>
                          </TableCell>
                          <TableCell className="text-right">
                            <div className="flex items-center justify-end gap-1">
                              {item.status === "Pending" && (
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  disabled={initiatingId === item.id}
                                  className="h-7 w-7 p-0 text-emerald-600 hover:text-emerald-800"
                                  title="Pay with Paystack"
                                  onClick={() => handlePaystackCheckout(item.id)}
                                >
                                  <CreditCard className="h-3.5 w-3.5" />
                                </Button>
                              )}
                              <Button
                                size="sm"
                                variant="ghost"
                                className="h-7 w-7 p-0 text-sky-600 hover:text-sky-800"
                                title="View & Print"
                                onClick={() => openViewModal(item)}
                              >
                                <Eye className="h-3.5 w-3.5" />
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                className="h-7 w-7 p-0 text-amber-600 hover:text-amber-800"
                                title="Edit"
                                onClick={() => openEditModal(item)}
                              >
                                <Edit className="h-3.5 w-3.5" />
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                className="h-7 w-7 p-0 text-red-600 hover:text-red-800"
                                title="Delete"
                                onClick={() => openDeleteModal(item)}
                              >
                                <Trash className="h-3.5 w-3.5" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </div>

              {/* Mobile Card List View (< md screens) */}
              <div className="block md:hidden divide-y">
                {receipts.data.length === 0 ? (
                  <div className="p-6 text-center text-xs text-muted-foreground">
                    No receipts found matching your criteria.
                  </div>
                ) : (
                  receipts.data.map((item) => (
                    <div key={item.id} className="p-3.5 space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Checkbox
                            checked={selectedIds.includes(item.id)}
                            onCheckedChange={(checked) => handleSelectOne(item.id, !!checked)}
                          />
                          <span className="font-mono font-bold text-xs text-sky-700">
                            #{item.receipt_number}
                          </span>
                        </div>
                        <span
                          className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium ${
                            item.status === "Paid"
                              ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                              : item.status === "Pending"
                              ? "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                              : "bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300"
                          }`}
                        >
                          {item.status}
                        </span>
                      </div>

                      <div className="flex justify-between items-start text-xs pt-1">
                        <div>
                          <div className="font-semibold text-sm">{item.applicant_name}</div>
                          <div className="text-muted-foreground text-[11px]">{item.contact}</div>
                        </div>
                        <div className="text-right">
                          <div className="font-bold text-emerald-600 text-sm">
                            GHS {Number(item.amount_paid).toFixed(2)}
                          </div>
                          <div className="text-[11px] text-muted-foreground">{item.mode_of_payment}</div>
                        </div>
                      </div>

                      <div className="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted-foreground bg-slate-50 dark:bg-slate-900 p-2 rounded">
                        <div><strong className="text-foreground">Client:</strong> {item.client}</div>
                        <div><strong className="text-foreground">Date:</strong> {item.receipt_date}</div>
                        <div><strong className="text-foreground">For:</strong> {item.descriptions}</div>
                        <div><strong className="text-foreground">Officer:</strong> {item.receiving_officer}</div>
                      </div>

                      <div className="flex items-center justify-end gap-2 pt-1 border-t">
                        {item.status === "Pending" && (
                          <Button
                            size="sm"
                            disabled={initiatingId === item.id}
                            className="h-7 text-xs px-2.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                            onClick={() => handlePaystackCheckout(item.id)}
                          >
                            <CreditCard className="h-3 w-3 mr-1" /> Pay Now
                          </Button>
                        )}
                        <Button
                          size="sm"
                          variant="outline"
                          className="h-7 text-xs px-2.5 text-sky-600"
                          onClick={() => openViewModal(item)}
                        >
                          <Eye className="h-3 w-3 mr-1" /> View / Print
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          className="h-7 text-xs px-2.5 text-amber-600"
                          onClick={() => openEditModal(item)}
                        >
                          <Edit className="h-3 w-3 mr-1" /> Edit
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          className="h-7 text-xs px-2 text-red-600"
                          onClick={() => openDeleteModal(item)}
                        >
                          <Trash className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>
                  ))
                )}
              </div>

              {/* Robust Pagination Section */}
              {receipts.links && receipts.links.length > 0 && (
                <div className="flex flex-col sm:flex-row items-center justify-between p-3 sm:p-4 border-t gap-3">
                  <div className="text-xs text-muted-foreground text-center sm:text-left">
                    Showing{" "}
                    <span className="font-medium text-foreground">
                      {receipts.from || 0}
                    </span>{" "}
                    to{" "}
                    <span className="font-medium text-foreground">
                      {receipts.to || 0}
                    </span>{" "}
                    of{" "}
                    <span className="font-medium text-foreground">
                      {receipts.total || totalRecords}
                    </span>{" "}
                    entries
                  </div>

                  <div className="flex flex-wrap items-center justify-center gap-1 max-w-full overflow-x-auto py-1">
                    {receipts.links.map((link, idx) => {
                      const isPrevious = link.label.includes("&laquo;") || link.label.toLowerCase().includes("previous");
                      const isNext = link.label.includes("&raquo;") || link.label.toLowerCase().includes("next");

                      return (
                        <Button
                          key={idx}
                          size="sm"
                          variant={link.active ? "default" : "outline"}
                          disabled={!link.url}
                          className={`h-8 text-xs transition-colors ${
                            link.active ? "bg-sky-600 hover:bg-sky-700 text-white" : ""
                          } ${!link.url ? "opacity-50 cursor-not-allowed" : "cursor-pointer"}`}
                          onClick={() => handlePageClick(link.url)}
                        >
                          {isPrevious ? (
                            <span className="flex items-center gap-1">
                              <ChevronLeft className="h-3.5 w-3.5" /> Prev
                            </span>
                          ) : isNext ? (
                            <span className="flex items-center gap-1">
                              Next <ChevronRight className="h-3.5 w-3.5" />
                            </span>
                          ) : (
                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                          )}
                        </Button>
                      );
                    })}
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Issue / Add Receipt Dialog */}
        <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
          <DialogContent className="max-w-[95vw] sm:max-w-2xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-sky-700 text-lg">
                <ReceiptText className="h-5 w-5" /> Issue New Receipt
              </DialogTitle>
            </DialogHeader>
            <form onSubmit={handleAddSubmit} className="space-y-3 sm:space-y-4 py-2">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="applicant_name" className="text-xs">Applicant Name *</Label>
                  <Input
                    id="applicant_name"
                    required
                    placeholder="Enter full name"
                    className="h-9 text-xs sm:text-sm"
                    value={addForm.data.applicant_name}
                    onChange={(e) => addForm.setData("applicant_name", e.target.value)}
                  />
                  {addForm.errors.applicant_name && (
                    <p className="text-[11px] text-red-500">{addForm.errors.applicant_name}</p>
                  )}
                </div>

                <div className="space-y-1">
                  <Label htmlFor="contact" className="text-xs">Contact / Phone *</Label>
                  <Input
                    id="contact"
                    required
                    placeholder="e.g. 0244123456"
                    className="h-9 text-xs sm:text-sm"
                    value={addForm.data.contact}
                    onChange={(e) => addForm.setData("contact", e.target.value)}
                  />
                  {addForm.errors.contact && (
                    <p className="text-[11px] text-red-500">{addForm.errors.contact}</p>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="address" className="text-xs">Address *</Label>
                  <Input
                    id="address"
                    required
                    placeholder="Enter location"
                    className="h-9 text-xs sm:text-sm"
                    value={addForm.data.address}
                    onChange={(e) => addForm.setData("address", e.target.value)}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="position" className="text-xs">Position *</Label>
                  <Input
                    id="position"
                    required
                    placeholder="e.g. Factory Worker"
                    className="h-9 text-xs sm:text-sm"
                    value={addForm.data.position}
                    onChange={(e) => addForm.setData("position", e.target.value)}
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="client" className="text-xs">Client / Company *</Label>
                  <Select
                    value={addForm.data.client}
                    onValueChange={(val) => addForm.setData("client", val)}
                  >
                    <SelectTrigger id="client" className="h-9 text-xs sm:text-sm">
                      <SelectValue placeholder="Select Client" />
                    </SelectTrigger>
                    <SelectContent>
                      {clients.map((c) => (
                        <SelectItem key={c} value={c}>
                          {c}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1">
                  <Label htmlFor="mode_of_payment" className="text-xs">Mode of Payment *</Label>
                  <Select
                    value={addForm.data.mode_of_payment}
                    onValueChange={(val: "Momo" | "Cash") => addForm.setData("mode_of_payment", val)}
                  >
                    <SelectTrigger id="mode_of_payment" className="h-9 text-xs sm:text-sm">
                      <SelectValue placeholder="Payment Mode" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Momo">Mobile Money (Momo)</SelectItem>
                      <SelectItem value="Cash">Cash</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {/* Descriptions Checkboxes */}
              <div className="space-y-2 border p-3 rounded-md bg-slate-50 dark:bg-slate-900">
                <Label className="font-semibold text-xs sm:text-sm">Payment Descriptions *</Label>
                <div className="flex flex-wrap gap-3 sm:gap-4 pt-1">
                  {DEFAULT_DESCRIPTIONS.map((desc) => (
                    <div key={desc} className="flex items-center space-x-2">
                      <Checkbox
                        id={`add_desc_${desc}`}
                        checked={addForm.data.descriptions.includes(desc)}
                        onCheckedChange={() => toggleAddDescription(desc)}
                      />
                      <label htmlFor={`add_desc_${desc}`} className="text-xs font-medium leading-none cursor-pointer">
                        {desc}
                      </label>
                    </div>
                  ))}
                </div>
                <div className="pt-1">
                  <Label className="text-[11px] text-muted-foreground">Selected: {addForm.data.descriptions.join(", ") || "None"}</Label>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="amount_paid" className="text-xs">Amount Paid (GHS) *</Label>
                  <Input
                    id="amount_paid"
                    type="number"
                    step="0.01"
                    required
                    placeholder="150.00"
                    className="h-9 text-xs sm:text-sm"
                    value={addForm.data.amount_paid}
                    onChange={(e) => addForm.setData("amount_paid", e.target.value)}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="receipt_date" className="text-xs">Receipt Date *</Label>
                  <Input
                    id="receipt_date"
                    type="date"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={addForm.data.receipt_date}
                    onChange={(e) => addForm.setData("receipt_date", e.target.value)}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="status" className="text-xs">Status</Label>
                  <Input
                    id="status"
                    value="Pending"
                    disabled
                    className="h-9 text-xs sm:text-sm bg-muted cursor-not-allowed"
                  />
                  <p className="text-[10px] text-muted-foreground">Status is set automatically after payment verification.</p>
                </div>
              </div>

              <div className="space-y-1">
                <Label htmlFor="receiving_officer" className="text-xs">Receiving Officer *</Label>
                <Input
                  id="receiving_officer"
                  required
                  placeholder="Officer name"
                  className="h-9 text-xs sm:text-sm"
                  value={addForm.data.receiving_officer}
                  onChange={(e) => addForm.setData("receiving_officer", e.target.value)}
                />
              </div>

              {/* Paystack Online Payment Checkbox */}
              <div className="flex items-center space-x-2 border p-3 rounded-md bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200">
                <Checkbox
                  id="initiate_paystack"
                  checked={addForm.data.initiate_paystack}
                  onCheckedChange={(checked) => addForm.setData("initiate_paystack", !!checked)}
                />
                <label htmlFor="initiate_paystack" className="text-xs font-semibold text-emerald-900 dark:text-emerald-300 cursor-pointer flex items-center gap-1.5">
                  <CreditCard className="h-4 w-4 text-emerald-600" />
                  Proceed directly to Paystack Online Checkout after saving
                </label>
              </div>

              <DialogFooter className="pt-3 flex flex-col-reverse sm:flex-row gap-2">
                <Button type="button" variant="outline" className="w-full sm:w-auto h-9 text-xs" onClick={() => setIsAddOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={addForm.processing} className="w-full sm:w-auto h-9 text-xs bg-sky-600 hover:bg-sky-700 text-white">
                  {addForm.processing ? "Processing..." : addForm.data.initiate_paystack ? "Save & Pay with Paystack" : "Create & Issue Receipt"}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* Edit Receipt Dialog */}
        <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
          <DialogContent className="max-w-[95vw] sm:max-w-2xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-amber-600 text-lg">
                <Edit className="h-5 w-5" /> Edit Receipt #{editForm.data.receipt_number}
              </DialogTitle>
            </DialogHeader>
            <form onSubmit={handleEditSubmit} className="space-y-3 sm:space-y-4 py-2">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="edit_receipt_number" className="text-xs">Receipt Number *</Label>
                  <Input
                    id="edit_receipt_number"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={editForm.data.receipt_number}
                    onChange={(e) => editForm.setData("receipt_number", e.target.value)}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="edit_applicant_name" className="text-xs">Applicant Name *</Label>
                  <Input
                    id="edit_applicant_name"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={editForm.data.applicant_name}
                    onChange={(e) => editForm.setData("applicant_name", e.target.value)}
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="edit_contact" className="text-xs">Contact / Phone *</Label>
                  <Input
                    id="edit_contact"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={editForm.data.contact}
                    onChange={(e) => editForm.setData("contact", e.target.value)}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="edit_address" className="text-xs">Address *</Label>
                  <Input
                    id="edit_address"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={editForm.data.address}
                    onChange={(e) => editForm.setData("address", e.target.value)}
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="edit_position" className="text-xs">Position *</Label>
                  <Input
                    id="edit_position"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={editForm.data.position}
                    onChange={(e) => editForm.setData("position", e.target.value)}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="edit_client" className="text-xs">Client / Company *</Label>
                  <Select
                    value={editForm.data.client}
                    onValueChange={(val) => editForm.setData("client", val)}
                  >
                    <SelectTrigger id="edit_client" className="h-9 text-xs sm:text-sm">
                      <SelectValue placeholder="Select Client" />
                    </SelectTrigger>
                    <SelectContent>
                      {clients.map((c) => (
                        <SelectItem key={c} value={c}>
                          {c}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {/* Edit Descriptions */}
              <div className="space-y-2 border p-3 rounded-md bg-slate-50 dark:bg-slate-900">
                <Label className="font-semibold text-xs sm:text-sm">Payment Descriptions *</Label>
                <div className="flex flex-wrap gap-3 sm:gap-4 pt-1">
                  {DEFAULT_DESCRIPTIONS.map((desc) => (
                    <div key={desc} className="flex items-center space-x-2">
                      <Checkbox
                        id={`edit_desc_${desc}`}
                        checked={editForm.data.descriptions.includes(desc)}
                        onCheckedChange={() => toggleEditDescription(desc)}
                      />
                      <label htmlFor={`edit_desc_${desc}`} className="text-xs font-medium leading-none cursor-pointer">
                        {desc}
                      </label>
                    </div>
                  ))}
                </div>
                <div className="pt-1">
                  <Label className="text-[11px] text-muted-foreground">Selected: {editForm.data.descriptions.join(", ") || "None"}</Label>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="edit_amount_paid" className="text-xs">Amount Paid (GHS) *</Label>
                  <Input
                    id="edit_amount_paid"
                    type="number"
                    step="0.01"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={editForm.data.amount_paid}
                    onChange={(e) => editForm.setData("amount_paid", e.target.value)}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="edit_receipt_date" className="text-xs">Receipt Date *</Label>
                  <Input
                    id="edit_receipt_date"
                    type="date"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={editForm.data.receipt_date}
                    onChange={(e) => editForm.setData("receipt_date", e.target.value)}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor="edit_status" className="text-xs">Status</Label>
                  <Input
                    id="edit_status"
                    value={editForm.data.status}
                    disabled
                    className="h-9 text-xs sm:text-sm bg-muted cursor-not-allowed"
                  />
                  <p className="text-[10px] text-muted-foreground">Status is updated automatically via payment verification.</p>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1">
                  <Label htmlFor="edit_mode" className="text-xs">Mode of Payment *</Label>
                  <Select
                    value={editForm.data.mode_of_payment}
                    onValueChange={(val: "Momo" | "Cash") => editForm.setData("mode_of_payment", val)}
                  >
                    <SelectTrigger id="edit_mode" className="h-9 text-xs sm:text-sm">
                      <SelectValue placeholder="Mode" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Momo">Mobile Money (Momo)</SelectItem>
                      <SelectItem value="Cash">Cash</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1">
                  <Label htmlFor="edit_receiving_officer" className="text-xs">Receiving Officer *</Label>
                  <Input
                    id="edit_receiving_officer"
                    required
                    className="h-9 text-xs sm:text-sm"
                    value={editForm.data.receiving_officer}
                    onChange={(e) => editForm.setData("receiving_officer", e.target.value)}
                  />
                </div>
              </div>

              <DialogFooter className="pt-3 flex flex-col-reverse sm:flex-row gap-2">
                <Button type="button" variant="outline" className="w-full sm:w-auto h-9 text-xs" onClick={() => setIsEditOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={editForm.processing} className="w-full sm:w-auto h-9 text-xs bg-amber-600 hover:bg-amber-700 text-white">
                  {editForm.processing ? "Updating..." : "Update Receipt"}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* View / Print Receipt Dialog */}
        <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
          <DialogContent className="max-w-[95vw] sm:max-w-lg p-4 sm:p-6">
            <DialogHeader>
              <DialogTitle className="flex items-center justify-between text-base sm:text-lg">
                <span>Receipt Details</span>
                {selectedReceipt && (
                  <span className="text-xs px-2.5 py-1 rounded-full font-mono bg-sky-100 text-sky-800">
                    #{selectedReceipt.receipt_number}
                  </span>
                )}
              </DialogTitle>
            </DialogHeader>

            {selectedReceipt && (
              <div className="space-y-3 sm:space-y-4 border rounded-lg p-3 sm:p-4 bg-white text-slate-900">
                <div className="text-center border-b pb-2 sm:pb-3">
                  <h3 className="text-lg sm:text-xl font-bold text-sky-700 tracking-wide">SBP AFRICA</h3>
                  <p className="text-[10px] sm:text-xs text-slate-500 uppercase tracking-widest">Official Receipt</p>
                </div>

                <div className="grid grid-cols-2 text-xs gap-y-2 pt-1">
                  <div>
                    <span className="text-slate-500 block text-[10px] sm:text-xs">Applicant Name</span>
                    <span className="font-semibold text-xs sm:text-sm">{selectedReceipt.applicant_name}</span>
                  </div>
                  <div>
                    <span className="text-slate-500 block text-[10px] sm:text-xs">Contact</span>
                    <span className="font-semibold text-xs sm:text-sm">{selectedReceipt.contact}</span>
                  </div>
                  <div>
                    <span className="text-slate-500 block text-[10px] sm:text-xs">Address</span>
                    <span>{selectedReceipt.address}</span>
                  </div>
                  <div>
                    <span className="text-slate-500 block text-[10px] sm:text-xs">Position</span>
                    <span>{selectedReceipt.position}</span>
                  </div>
                  <div>
                    <span className="text-slate-500 block text-[10px] sm:text-xs">Client</span>
                    <span className="font-medium">{selectedReceipt.client}</span>
                  </div>
                  <div>
                    <span className="text-slate-500 block text-[10px] sm:text-xs">Date</span>
                    <span>{selectedReceipt.receipt_date}</span>
                  </div>
                  <div className="col-span-2">
                    <span className="text-slate-500 block text-[10px] sm:text-xs">Payment Descriptions</span>
                    <span className="font-medium text-sky-900 bg-sky-50 px-2 py-0.5 rounded border border-sky-100 text-xs">
                      {selectedReceipt.descriptions}
                    </span>
                  </div>
                </div>

                <div className="bg-sky-50 border border-sky-200 rounded-lg p-3 text-center my-2">
                  <div className="text-[11px] text-sky-800 font-medium">TOTAL AMOUNT PAID</div>
                  <div className="text-xl sm:text-2xl font-extrabold text-sky-900">
                    GHS {Number(selectedReceipt.amount_paid).toFixed(2)}
                  </div>
                  <div className="text-[11px] text-slate-500 mt-0.5">Mode: {selectedReceipt.mode_of_payment}</div>
                </div>

                <div className="flex justify-between items-end text-[11px] border-t pt-2.5 text-slate-500">
                  <div>
                    <span>Staff ID: </span>
                    <span className="font-mono">{selectedReceipt.staffid}</span>
                  </div>
                  <div>
                    <span>Officer: </span>
                    <span className="font-medium text-slate-800">{selectedReceipt.receiving_officer}</span>
                  </div>
                </div>
              </div>
            )}

            <DialogFooter className="flex flex-col-reverse sm:flex-row gap-2 sm:justify-between">
              <Button type="button" variant="outline" className="w-full sm:w-auto h-9 text-xs" onClick={() => setIsViewOpen(false)}>
                Close
              </Button>
              <Button type="button" onClick={handlePrint} className="w-full sm:w-auto h-9 text-xs bg-sky-600 hover:bg-sky-700 text-white">
                <Printer className="mr-2 h-4 w-4" /> Print Receipt
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Delete Confirmation Dialog */}
        <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
          <DialogContent className="max-w-[95vw] sm:max-w-md p-4 sm:p-6">
            <DialogHeader>
              <DialogTitle className="text-red-600 flex items-center gap-2 text-base sm:text-lg">
                <Trash className="h-5 w-5" /> Confirm Receipt Deletion
              </DialogTitle>
            </DialogHeader>
            <p className="text-xs sm:text-sm text-muted-foreground">
              Are you sure you want to delete receipt{" "}
              <strong className="text-foreground">#{selectedReceipt?.receipt_number}</strong> for{" "}
              <strong>{selectedReceipt?.applicant_name}</strong>? This action cannot be undone.
            </p>
            <DialogFooter className="flex flex-col-reverse sm:flex-row gap-2">
              <Button variant="outline" className="w-full sm:w-auto h-9 text-xs" onClick={() => setIsDeleteOpen(false)}>
                Cancel
              </Button>
              <Button variant="destructive" className="w-full sm:w-auto h-9 text-xs" onClick={handleDeleteConfirm} disabled={addForm.processing}>
                Delete Receipt
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Bulk Delete Dialog */}
        <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
          <DialogContent className="max-w-[95vw] sm:max-w-md p-4 sm:p-6">
            <DialogHeader>
              <DialogTitle className="text-red-600 flex items-center gap-2 text-base sm:text-lg">
                <Trash className="h-5 w-5" /> Confirm Bulk Deletion
              </DialogTitle>
            </DialogHeader>
            <p className="text-xs sm:text-sm text-muted-foreground">
              Are you sure you want to delete <strong className="text-foreground">{selectedIds.length}</strong> selected receipt record(s)? This action cannot be undone.
            </p>
            <DialogFooter className="flex flex-col-reverse sm:flex-row gap-2">
              <Button variant="outline" className="w-full sm:w-auto h-9 text-xs" onClick={() => setIsBulkDeleteOpen(false)}>
                Cancel
              </Button>
              <Button variant="destructive" className="w-full sm:w-auto h-9 text-xs" onClick={handleBulkDeleteConfirm} disabled={bulkDeleteForm.processing}>
                Delete Selected
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </AppLayout>
    </ErrorBoundary>
  );
}
