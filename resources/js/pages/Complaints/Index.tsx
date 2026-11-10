import React, { useState, useEffect, useCallback } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
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
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  LifeBuoy,
  Search,
  CheckCircle2,
  Clock,
  AlertCircle,
  FileSpreadsheet,
  Plus,
  X,
  ExternalLink,
  RefreshCw,
  Trash2,
  Check,
  Eye,
  Building2,
  User,
  Layers,
  Copy,
} from "lucide-react";
import debounce from "lodash/debounce";

interface ComplaintItem {
  id: number;
  ticket_number: string;
  client_name: string;
  staff_id: string;
  employee_name: string;
  shop_name: string | null;
  phone: string | null;
  email: string | null;
  month_period: string | null;
  category: string;
  complaint_text: string;
  attachment_path: string | null;
  status: "pending" | "under_review" | "resolved" | "rejected";
  priority: "low" | "medium" | "high" | "urgent";
  resolved_by: number | null;
  resolution_notes: string | null;
  resolved_at: string | null;
  submission_source: string;
  created_at: string;
  resolver?: {
    id: number;
    name: string;
    email: string;
  };
}

interface ClientOption {
  id: number;
  client_name: string;
  short_name: string;
}

interface Props {
  complaints: {
    data: ComplaintItem[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
  };
  filters: {
    search?: string;
    client?: string;
    month?: string;
    category?: string;
    status?: string;
    priority?: string;
  };
  clients: ClientOption[];
  metrics: {
    total: number;
    pending: number;
    under_review: number;
    resolved: number;
  };
  availableMonths: string[];
  categories: string[];
  canManage: boolean;
}

export default function ComplaintsIndex({
  complaints,
  filters,
  clients,
  metrics,
  availableMonths,
  categories,
  canManage,
}: Props) {
  const { flash, auth } = usePage().props as any;

  // Filter states
  const [search, setSearch] = useState(filters.search || "");
  const [selectedClient, setSelectedClient] = useState(filters.client || "");
  const [selectedMonth, setSelectedMonth] = useState(filters.month || "");
  const [selectedCategory, setSelectedCategory] = useState(filters.category || "");
  const [selectedStatus, setSelectedStatus] = useState(filters.status || "");
  const [selectedPriority, setSelectedPriority] = useState(filters.priority || "");

  // Review / Resolve Modal State
  const [activeComplaint, setActiveComplaint] = useState<ComplaintItem | null>(null);
  const [modalStatus, setModalStatus] = useState<"pending" | "under_review" | "resolved" | "rejected">("pending");
  const [modalPriority, setModalPriority] = useState<"low" | "medium" | "high" | "urgent">("medium");
  const [modalNotes, setModalNotes] = useState("");
  const [updating, setUpdating] = useState(false);

  // Manual Intake Modal State
  const [isManualModalOpen, setIsManualModalOpen] = useState(false);
  const [manStaffId, setManStaffId] = useState("");
  const [manName, setManName] = useState("");
  const [manClient, setManClient] = useState(clients[0]?.client_name || "Melcom");
  const [manShop, setManShop] = useState("");
  const [manPhone, setManPhone] = useState("");
  const [manEmail, setManEmail] = useState("");
  const [manMonth, setManMonth] = useState(availableMonths[0] || "November 2026");
  const [manCategory, setManCategory] = useState(categories[0]);
  const [manPriority, setManPriority] = useState<"low" | "medium" | "high" | "urgent">("medium");
  const [manText, setManText] = useState("");
  const [creatingManual, setCreatingManual] = useState(false);
  const [copiedLink, setCopiedLink] = useState(false);

  const handleCopyLink = () => {
    const baseUrl = window.location.origin + "/complaints/submit";
    const shareUrl = selectedClient ? `${baseUrl}?client=${encodeURIComponent(selectedClient)}` : baseUrl;
    navigator.clipboard.writeText(shareUrl).then(() => {
      setCopiedLink(true);
      setTimeout(() => setCopiedLink(false), 3000);
    });
  };

  // Debounced search
  const debouncedSearch = useCallback(
    debounce((q: string, cl: string, mo: string, cat: string, st: string, pr: string) => {
      router.get(
        "/complaints",
        {
          search: q || undefined,
          client: cl || undefined,
          month: mo || undefined,
          category: cat || undefined,
          status: st || undefined,
          priority: pr || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
      );
    }, 400),
    []
  );

  const handleSearchChange = (val: string) => {
    setSearch(val);
    debouncedSearch(val, selectedClient, selectedMonth, selectedCategory, selectedStatus, selectedPriority);
  };

  const handleClientChange = (val: string) => {
    setSelectedClient(val);
    debouncedSearch(search, val, selectedMonth, selectedCategory, selectedStatus, selectedPriority);
  };

  const handleMonthChange = (val: string) => {
    setSelectedMonth(val);
    debouncedSearch(search, selectedClient, val, selectedCategory, selectedStatus, selectedPriority);
  };

  const handleCategoryChange = (val: string) => {
    setSelectedCategory(val);
    debouncedSearch(search, selectedClient, selectedMonth, val, selectedStatus, selectedPriority);
  };

  const handleStatusChange = (val: string) => {
    setSelectedStatus(val);
    debouncedSearch(search, selectedClient, selectedMonth, selectedCategory, val, selectedPriority);
  };

  const handleResetFilters = () => {
    setSearch("");
    setSelectedClient("");
    setSelectedMonth("");
    setSelectedCategory("");
    setSelectedStatus("");
    setSelectedPriority("");
    router.get("/complaints", {}, { preserveState: true, replace: true });
  };

  const openReviewModal = (item: ComplaintItem) => {
    setActiveComplaint(item);
    setModalStatus(item.status);
    setModalPriority(item.priority);
    setModalNotes(item.resolution_notes || "");
  };

  const handleUpdateComplaint = (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeComplaint) return;

    setUpdating(true);
    router.put(
      `/complaints/${activeComplaint.id}`,
      {
        status: modalStatus,
        priority: modalPriority,
        resolution_notes: modalNotes,
      },
      {
        preserveScroll: true,
        onSuccess: () => setActiveComplaint(null),
        onFinish: () => setUpdating(false),
      }
    );
  };

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setCreatingManual(true);
    router.post(
      "/complaints",
      {
        staff_id: manStaffId,
        employee_name: manName,
        client_name: manClient,
        shop_name: manShop,
        phone: manPhone,
        email: manEmail,
        month_period: manMonth,
        category: manCategory,
        priority: manPriority,
        complaint_text: manText,
      },
      {
        onSuccess: () => {
          setIsManualModalOpen(false);
          setManStaffId("");
          setManName("");
          setManShop("");
          setManPhone("");
          setManEmail("");
          setManText("");
        },
        onFinish: () => setCreatingManual(false),
      }
    );
  };

  const handleDelete = (id: number, ticket: string) => {
    if (!confirm(`Are you sure you want to permanently delete complaint ${ticket}?`)) return;
    router.delete(`/complaints/${id}`, { preserveScroll: true });
  };

  const exportUrl = `/complaints-export?${new URLSearchParams({
    search: search || "",
    client: selectedClient || "",
    month: selectedMonth || "",
    category: selectedCategory || "",
    status: selectedStatus || "",
  }).toString()}`;

  return (
    <AppLayout
      breadcrumbs={[
        { title: "Dashboard", href: "/dashboard" },
        { title: "Employee Complaints", href: "/complaints" },
      ]}
    >
      <Head title="Employee Complaints & Queries Management" />

      <div className="p-4 md:p-6 space-y-6">
        {/* Flash alerts */}
        {flash?.success && (
          <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg flex items-center justify-between text-sm shadow-sm">
            <div className="flex items-center space-x-2">
              <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
              <span>{flash.success}</span>
            </div>
          </div>
        )}

        {/* Top Header & Actions */}
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
              <LifeBuoy className="w-7 h-7 text-blue-600" />
              Employee Complaints &amp; Queries
            </h1>
            <p className="text-sm text-gray-500 mt-1">
              Unified management portal for employee salary, attendance, deduction, and benefits queries across all clients.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2.5">
            <Button
              type="button"
              variant="outline"
              onClick={handleCopyLink}
              className={`inline-flex items-center gap-1.5 text-xs h-9 font-semibold transition-all shadow-sm ${
                copiedLink
                  ? "bg-emerald-600 text-white border-emerald-600 hover:bg-emerald-700"
                  : "bg-white text-blue-700 border-blue-300 hover:bg-blue-50"
              }`}
              title="Copy public link to share with employees via WhatsApp or Email"
            >
              {copiedLink ? (
                <>
                  <Check className="w-3.5 h-3.5 text-white" />
                  Link Copied!
                </>
              ) : (
                <>
                  <Copy className="w-3.5 h-3.5 text-blue-600" />
                  Copy Link for Employees
                </>
              )}
            </Button>

            <a
              href="/complaints/submit"
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 transition-colors"
            >
              <ExternalLink className="w-3.5 h-3.5" />
              Open Portal
            </a>

            <a
              href={exportUrl}
              className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md hover:bg-emerald-100 transition-colors shadow-sm"
            >
              <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
              Export to Excel
            </a>

            <Button
              onClick={() => setIsManualModalOpen(true)}
              className="bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-sm flex items-center gap-1.5 text-xs h-9"
            >
              <Plus className="w-4 h-4" />
              Log Walk-in Complaint
            </Button>
          </div>
        </div>

        {/* Public Share Info Banner */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200/80 rounded-xl text-xs text-blue-900 gap-3 shadow-sm">
          <div className="flex items-center gap-2.5">
            <div className="p-1.5 bg-blue-600 text-white rounded-lg shadow-sm">
              <LifeBuoy className="w-4 h-4" />
            </div>
            <div>
              <p className="font-semibold text-blue-950">
                Employees do NOT need to log in to raise a complaint!
              </p>
              <p className="text-blue-700 mt-0.5">
                Click <strong>"Copy Link for Employees"</strong> to copy the public link and share it on WhatsApp, SMS, or Email. Staff IDs are automatically verified against the database.
              </p>
            </div>
          </div>
          <Button
            type="button"
            size="sm"
            onClick={handleCopyLink}
            className="bg-blue-600 hover:bg-blue-700 text-white text-xs whitespace-nowrap h-8 font-medium shadow-sm flex items-center gap-1.5 self-start sm:self-auto"
          >
            {copiedLink ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
            {copiedLink ? "Link Copied!" : "Copy Link"}
          </Button>
        </div>

        {/* KPI Metrics */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="border border-gray-100 shadow-sm bg-gradient-to-br from-slate-50/70 to-white">
            <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-xs font-semibold text-slate-700 uppercase tracking-wider">
                Total Complaints
              </CardTitle>
              <Layers className="w-4 h-4 text-slate-500" />
            </CardHeader>
            <CardContent className="p-4 pt-0">
              <div className="text-2xl font-bold text-gray-900">
                {metrics.total.toLocaleString()}
              </div>
              <p className="text-xs text-gray-500 mt-1">All time records across all clients</p>
            </CardContent>
          </Card>

          <Card className="border border-gray-100 shadow-sm bg-gradient-to-br from-amber-50/60 to-white">
            <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-xs font-semibold text-amber-700 uppercase tracking-wider">
                Pending Review
              </CardTitle>
              <Clock className="w-4 h-4 text-amber-600" />
            </CardHeader>
            <CardContent className="p-4 pt-0">
              <div className="text-2xl font-bold text-amber-900">
                {metrics.pending.toLocaleString()}
              </div>
              <p className="text-xs text-amber-700 mt-1">Awaiting HR assessment</p>
            </CardContent>
          </Card>

          <Card className="border border-gray-100 shadow-sm bg-gradient-to-br from-blue-50/60 to-white">
            <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-xs font-semibold text-blue-700 uppercase tracking-wider">
                Under Review
              </CardTitle>
              <AlertCircle className="w-4 h-4 text-blue-600" />
            </CardHeader>
            <CardContent className="p-4 pt-0">
              <div className="text-2xl font-bold text-blue-900">
                {metrics.under_review.toLocaleString()}
              </div>
              <p className="text-xs text-blue-700 mt-1">Currently being investigated</p>
            </CardContent>
          </Card>

          <Card className="border border-gray-100 shadow-sm bg-gradient-to-br from-emerald-50/60 to-white">
            <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-xs font-semibold text-emerald-700 uppercase tracking-wider">
                Resolved
              </CardTitle>
              <CheckCircle2 className="w-4 h-4 text-emerald-600" />
            </CardHeader>
            <CardContent className="p-4 pt-0">
              <div className="text-2xl font-bold text-emerald-900">
                {metrics.resolved.toLocaleString()}
              </div>
              <p className="text-xs text-emerald-700 mt-1">Successfully addressed</p>
            </CardContent>
          </Card>
        </div>

        {/* Filters Card */}
        <Card className="border-gray-200 shadow-sm">
          <CardContent className="p-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
              {/* Search */}
              <div className="lg:col-span-2 relative">
                <Search className="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                <Input
                  type="text"
                  placeholder="Search ticket, staff ID, name, shop..."
                  value={search}
                  onChange={(e) => handleSearchChange(e.target.value)}
                  className="pl-9 bg-gray-50/50"
                />
              </div>

              {/* Client Filter */}
              <div>
                <select
                  value={selectedClient}
                  onChange={(e) => handleClientChange(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-gray-50/50 border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">All Clients</option>
                  {clients.map((c) => (
                    <option key={c.id} value={c.client_name}>
                      {c.client_name}
                    </option>
                  ))}
                </select>
              </div>

              {/* Month Period Filter */}
              <div>
                <select
                  value={selectedMonth}
                  onChange={(e) => handleMonthChange(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-gray-50/50 border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">All Months</option>
                  {availableMonths.map((m) => (
                    <option key={m} value={m}>
                      {m}
                    </option>
                  ))}
                </select>
              </div>

              {/* Status Filter */}
              <div>
                <select
                  value={selectedStatus}
                  onChange={(e) => handleStatusChange(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-gray-50/50 border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">All Statuses</option>
                  <option value="pending">Pending</option>
                  <option value="under_review">Under Review</option>
                  <option value="resolved">Resolved</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>

              {/* Reset */}
              <div className="flex items-center gap-2">
                <select
                  value={selectedCategory}
                  onChange={(e) => handleCategoryChange(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-gray-50/50 border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">All Categories</option>
                  {categories.map((cat) => (
                    <option key={cat} value={cat}>
                      {cat}
                    </option>
                  ))}
                </select>

                {(search || selectedClient || selectedMonth || selectedCategory || selectedStatus) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleResetFilters}
                    className="text-gray-500 hover:text-gray-800"
                    title="Clear filters"
                  >
                    <X className="w-4 h-4" />
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Complaints Table */}
        <Card className="border-gray-200 shadow-sm overflow-hidden">
          <CardHeader className="p-4 bg-gray-50 border-b flex flex-row items-center justify-between">
            <CardTitle className="text-base font-semibold text-gray-800 flex items-center gap-2">
              <LifeBuoy className="w-4 h-4 text-blue-600" />
              Complaints List ({complaints.total.toLocaleString()})
            </CardTitle>
          </CardHeader>
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow className="bg-gray-100/70 hover:bg-gray-100/70">
                  <TableHead className="w-12 text-center">#</TableHead>
                  <TableHead>Ticket #</TableHead>
                  <TableHead>Staff Details</TableHead>
                  <TableHead>Client &amp; Shop</TableHead>
                  <TableHead>Month &amp; Category</TableHead>
                  <TableHead className="max-w-xs">Complaint Details</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Date Logged</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {complaints.data.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={9} className="text-center py-12 text-gray-500">
                      <LifeBuoy className="w-10 h-10 mx-auto text-gray-300 mb-2" />
                      <p className="font-medium text-gray-600">No complaints found</p>
                      <p className="text-xs text-gray-400 mt-1">Try adjusting your filters or search query.</p>
                    </TableCell>
                  </TableRow>
                ) : (
                  complaints.data.map((item, idx) => {
                    const rowNumber = (complaints.current_page - 1) * complaints.per_page + idx + 1;
                    return (
                      <TableRow key={item.id} className="hover:bg-blue-50/30 transition-colors">
                        <TableCell className="text-center text-xs text-gray-400">
                          {rowNumber}
                        </TableCell>
                        <TableCell>
                          <span className="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded">
                            {item.ticket_number}
                          </span>
                        </TableCell>
                        <TableCell>
                          <div>
                            <div className="font-semibold text-gray-900 text-sm">{item.employee_name}</div>
                            <div className="text-xs text-blue-600 font-mono font-medium">{item.staff_id}</div>
                            {item.phone && <div className="text-[11px] text-gray-400">{item.phone}</div>}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div>
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                              {item.client_name}
                            </span>
                            {item.shop_name && (
                              <div className="text-xs text-gray-500 mt-0.5">{item.shop_name}</div>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div>
                            <span className="text-xs font-semibold text-gray-800 block">
                              {item.month_period || "—"}
                            </span>
                            <span className="text-[11px] text-blue-600 font-medium">{item.category}</span>
                          </div>
                        </TableCell>
                        <TableCell className="max-w-xs">
                          <p className="text-xs text-gray-700 line-clamp-2" title={item.complaint_text}>
                            {item.complaint_text}
                          </p>
                        </TableCell>
                        <TableCell>
                          {item.status === "resolved" ? (
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                              <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                              Resolved
                            </span>
                          ) : item.status === "under_review" ? (
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                              <Clock className="w-3 h-3 text-blue-600" />
                              Under Review
                            </span>
                          ) : item.status === "rejected" ? (
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                              <AlertCircle className="w-3 h-3 text-rose-600" />
                              Rejected
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                              <Clock className="w-3 h-3 text-amber-600" />
                              Pending
                            </span>
                          )}
                        </TableCell>
                        <TableCell className="text-xs text-gray-500">
                          {new Date(item.created_at).toLocaleDateString()}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => openReviewModal(item)}
                              className="text-xs h-7 px-2.5 border-blue-200 text-blue-700 hover:bg-blue-50"
                            >
                              Review / Resolve
                            </Button>

                            {auth?.user?.is_super_admin && (
                              <button
                                type="button"
                                onClick={() => handleDelete(item.id, item.ticket_number)}
                                className="p-1 text-gray-400 hover:text-rose-600 rounded"
                                title="Delete record"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
                              </button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })
                )}
              </TableBody>
            </Table>
          </div>

          {/* Pagination */}
          {complaints.last_page > 1 && (
            <div className="p-4 bg-gray-50 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-gray-600">
              <div>
                Showing {complaints.from || 0} to {complaints.to || 0} of {complaints.total} records
              </div>
              <div className="flex items-center gap-1">
                {complaints.links.map((link, idx) => (
                  <button
                    key={idx}
                    disabled={!link.url}
                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                    className={`px-3 py-1.5 text-xs rounded border transition-colors ${
                      link.active
                        ? "bg-blue-600 text-white border-blue-600 font-semibold"
                        : link.url
                        ? "bg-white text-gray-700 hover:bg-gray-100 border-gray-300"
                        : "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
            </div>
          )}
        </Card>

        {/* ─── REVIEW & RESOLVE MODAL ───────────────────────────────────────────── */}
        <Dialog open={!!activeComplaint} onOpenChange={(open) => !open && setActiveComplaint(null)}>
          <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-base font-bold text-gray-900 flex items-center gap-2">
                <LifeBuoy className="w-5 h-5 text-blue-600" />
                Review &amp; Resolve Complaint ({activeComplaint?.ticket_number})
              </DialogTitle>
            </DialogHeader>

            {activeComplaint && (
              <form onSubmit={handleUpdateComplaint} className="space-y-4 pt-2">
                {/* Employee Info Box */}
                <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs grid grid-cols-2 gap-2">
                  <div>
                    <span className="text-gray-400 block font-medium">Employee Name:</span>
                    <span className="font-bold text-gray-900">{activeComplaint.employee_name}</span>
                  </div>
                  <div>
                    <span className="text-gray-400 block font-medium">Staff ID:</span>
                    <span className="font-mono font-bold text-blue-700">{activeComplaint.staff_id}</span>
                  </div>
                  <div>
                    <span className="text-gray-400 block font-medium">Company:</span>
                    <span className="font-semibold text-gray-900">{activeComplaint.client_name}</span>
                  </div>
                  <div>
                    <span className="text-gray-400 block font-medium">Shop / Branch:</span>
                    <span className="font-semibold text-gray-900">{activeComplaint.shop_name || "—"}</span>
                  </div>
                  <div>
                    <span className="text-gray-400 block font-medium">Phone / Email:</span>
                    <span className="text-gray-700">{activeComplaint.phone || activeComplaint.email || "—"}</span>
                  </div>
                  <div>
                    <span className="text-gray-400 block font-medium">Issue Period:</span>
                    <span className="font-semibold text-gray-900">{activeComplaint.month_period || "—"}</span>
                  </div>
                </div>

                {/* Complaint Text */}
                <div className="space-y-1">
                  <Label className="text-xs font-bold uppercase tracking-wider text-gray-700">
                    Category: <span className="text-blue-600">{activeComplaint.category}</span>
                  </Label>
                  <div className="p-3 bg-white border border-gray-200 rounded-lg text-sm text-gray-800 leading-relaxed whitespace-pre-line">
                    {activeComplaint.complaint_text}
                  </div>
                </div>

                {/* Status & Priority Selection */}
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1">
                    <Label className="text-xs font-bold uppercase tracking-wider text-gray-700">
                      Status <span className="text-rose-500">*</span>
                    </Label>
                    <select
                      value={modalStatus}
                      onChange={(e: any) => setModalStatus(e.target.value)}
                      disabled={!canManage}
                      className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500"
                    >
                      <option value="pending">Pending</option>
                      <option value="under_review">Under Review</option>
                      <option value="resolved">Resolved</option>
                      <option value="rejected">Rejected</option>
                    </select>
                  </div>

                  <div className="space-y-1">
                    <Label className="text-xs font-bold uppercase tracking-wider text-gray-700">
                      Priority <span className="text-rose-500">*</span>
                    </Label>
                    <select
                      value={modalPriority}
                      onChange={(e: any) => setModalPriority(e.target.value)}
                      disabled={!canManage}
                      className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500"
                    >
                      <option value="low">Low</option>
                      <option value="medium">Medium</option>
                      <option value="high">High</option>
                      <option value="urgent">Urgent</option>
                    </select>
                  </div>
                </div>

                {/* Resolution Notes */}
                <div className="space-y-1">
                  <Label className="text-xs font-bold uppercase tracking-wider text-gray-700">
                    HR Resolution Notes &amp; Actions Taken
                  </Label>
                  <textarea
                    rows={3}
                    value={modalNotes}
                    onChange={(e) => setModalNotes(e.target.value)}
                    disabled={!canManage}
                    placeholder="e.g. Attendance records checked with shop manager; discrepancy confirmed and 2 days salary adjusted in supplementary payroll."
                    className="w-full p-2.5 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500"
                  />
                  <p className="text-[11px] text-gray-400">
                    This note will be visible to HR and shown on the employee's tracking portal.
                  </p>
                </div>

                {!canManage && (
                  <p className="text-xs text-amber-600 bg-amber-50 p-2 rounded border border-amber-200">
                    View only: HR Managers and HR Officers have permission to update status and resolution notes.
                  </p>
                )}

                <DialogFooter className="pt-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setActiveComplaint(null)}
                    disabled={updating}
                  >
                    {canManage ? "Cancel" : "Close"}
                  </Button>
                  {canManage && (
                    <Button
                      type="submit"
                      disabled={updating}
                      className="bg-blue-600 hover:bg-blue-700 text-white font-medium flex items-center gap-2"
                    >
                      {updating ? (
                        <>
                          <RefreshCw className="w-4 h-4 animate-spin" />
                          Saving...
                        </>
                      ) : (
                        <>
                          <Check className="w-4 h-4" />
                          Save Updates
                        </>
                      )}
                    </Button>
                  )}
                </DialogFooter>
              </form>
            )}
          </DialogContent>
        </Dialog>

        {/* ─── MANUAL COMPLAINT INTAKE MODAL ─────────────────────────────────────── */}
        <Dialog open={isManualModalOpen} onOpenChange={setIsManualModalOpen}>
          <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-base font-bold text-gray-900 flex items-center gap-2">
                <Plus className="w-5 h-5 text-blue-600" />
                Log Walk-in / Phone Complaint
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleManualSubmit} className="space-y-4 pt-2">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Staff ID *</Label>
                  <Input
                    type="text"
                    placeholder="e.g. SBP1106"
                    value={manStaffId}
                    onChange={(e) => setManStaffId(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Employee Name *</Label>
                  <Input
                    type="text"
                    placeholder="Full name"
                    value={manName}
                    onChange={(e) => setManName(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Client *</Label>
                  <select
                    value={manClient}
                    onChange={(e) => setManClient(e.target.value)}
                    className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                  >
                    {clients.map((c) => (
                      <option key={c.id} value={c.client_name}>
                        {c.client_name}
                      </option>
                    ))}
                    <option value="Melcom">Melcom</option>
                  </select>
                </div>
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Shop / Branch</Label>
                  <Input
                    type="text"
                    placeholder="e.g. Accra Mall"
                    value={manShop}
                    onChange={(e) => setManShop(e.target.value)}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Phone</Label>
                  <Input
                    type="text"
                    placeholder="024XXXXXXX"
                    value={manPhone}
                    onChange={(e) => setManPhone(e.target.value)}
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Month Period *</Label>
                  <Input
                    type="text"
                    placeholder="e.g. November 2026"
                    value={manMonth}
                    onChange={(e) => setManMonth(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Category *</Label>
                  <select
                    value={manCategory}
                    onChange={(e) => setManCategory(e.target.value)}
                    className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    {categories.map((c) => (
                      <option key={c} value={c}>
                        {c}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Priority *</Label>
                  <select
                    value={manPriority}
                    onChange={(e: any) => setManPriority(e.target.value)}
                    className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>

              <div className="space-y-1">
                <Label className="text-xs font-semibold text-gray-700">Complaint Details *</Label>
                <textarea
                  rows={3}
                  value={manText}
                  onChange={(e) => setManText(e.target.value)}
                  placeholder="Record employee's explanation..."
                  className="w-full p-2.5 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <DialogFooter className="pt-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsManualModalOpen(false)}
                  disabled={creatingManual}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={creatingManual}
                  className="bg-blue-600 hover:bg-blue-700 text-white font-medium flex items-center gap-2"
                >
                  {creatingManual ? (
                    <>
                      <RefreshCw className="w-4 h-4 animate-spin" />
                      Saving...
                    </>
                  ) : (
                    <>
                      <Plus className="w-4 h-4" />
                      Register Complaint
                    </>
                  )}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  );
}
