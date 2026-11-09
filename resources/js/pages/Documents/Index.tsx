import React, { useState, useEffect, useCallback, useRef } from "react";
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
  FileText,
  Upload,
  Send,
  Search,
  Mail,
  CheckCircle2,
  AlertCircle,
  Clock,
  Download,
  RefreshCw,
  Users,
  Building2,
  FolderArchive,
  Layers,
  FileUp,
  X,
  ExternalLink,
} from "lucide-react";
import debounce from "lodash/debounce";

interface EmployeeDoc {
  id: number;
  staff_id: string;
  employee_name: string | null;
  client_name: string | null;
  document_type: string;
  document_title: string;
  file_name: string;
  file_size: number | null;
  file_mime: string | null;
  recipient_email: string;
  sender_email: string | null;
  status: "sent" | "failed" | "pending";
  error_message: string | null;
  notes: string | null;
  sent_at: string | null;
  created_at: string;
  sender?: {
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
  documents: {
    data: EmployeeDoc[];
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
    type?: string;
    status?: string;
  };
  clients: ClientOption[];
  metrics: {
    total_sent: number;
    sent_this_month: number;
    unique_employees: number;
    failed: number;
  };
  currentUser: {
    name: string;
    email: string;
  };
}

const DOCUMENT_TYPES = [
  "Employment Contract",
  "Contract Renewal",
  "Appointment Letter",
  "Confirmation Letter",
  "Warning Letter",
  "Payslip Document",
  "Appraisal & Review",
  "Policy Document",
  "Exit / Clearance Letter",
  "Medical & Insurance",
  "General Document",
];

export default function DocumentsIndex({
  documents,
  filters,
  clients,
  metrics,
  currentUser,
}: Props) {
  const { flash } = usePage().props as any;

  // Filter states
  const [search, setSearch] = useState(filters.search || "");
  const [selectedClient, setSelectedClient] = useState(filters.client || "");
  const [selectedType, setSelectedType] = useState(filters.type || "");
  const [selectedStatus, setSelectedStatus] = useState(filters.status || "");

  // Modals state
  const [isSingleModalOpen, setIsSingleModalOpen] = useState(false);
  const [isBulkModalOpen, setIsBulkModalOpen] = useState(false);
  const [selectedDocDetails, setSelectedDocDetails] = useState<EmployeeDoc | null>(null);

  // Single Upload Form State
  const [singleStaffId, setSingleStaffId] = useState("");
  const [singleEmpName, setSingleEmpName] = useState("");
  const [singleClient, setSingleClient] = useState("");
  const [singleEmail, setSingleEmail] = useState("");
  const [singleDocType, setSingleDocType] = useState(DOCUMENT_TYPES[0]);
  const [singleDocTitle, setSingleDocTitle] = useState("");
  const [singleNotes, setSingleNotes] = useState("");
  const [singleCcHr, setSingleCcHr] = useState(true);
  const [singleFile, setSingleFile] = useState<File | null>(null);
  const [singleSubmitting, setSingleSubmitting] = useState(false);

  // Autocomplete Suggestions
  const [empSuggestions, setEmpSuggestions] = useState<any[]>([]);
  const [searchingEmp, setSearchingEmp] = useState(false);
  const [showSuggestions, setShowSuggestions] = useState(false);

  // Bulk Upload Form State
  const [bulkMode, setBulkMode] = useState<"files" | "archive">("files");
  const [bulkDocType, setBulkDocType] = useState(DOCUMENT_TYPES[0]);
  const [bulkDocTitle, setBulkDocTitle] = useState("");
  const [bulkClient, setBulkClient] = useState("");
  const [bulkNotes, setBulkNotes] = useState("");
  const [bulkCcHr, setBulkCcHr] = useState(true);
  const [bulkFiles, setBulkFiles] = useState<FileList | null>(null);
  const [bulkArchive, setBulkArchive] = useState<File | null>(null);
  const [bulkSubmitting, setBulkSubmitting] = useState(false);

  // Resend state
  const [resendingId, setResendingId] = useState<number | null>(null);

  // Debounced live search
  const debouncedSearch = useCallback(
    debounce((query: string, client: string, type: string, status: string) => {
      router.get(
        "/documents",
        {
          search: query || undefined,
          client: client || undefined,
          type: type || undefined,
          status: status || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
      );
    }, 400),
    []
  );

  const handleSearchChange = (val: string) => {
    setSearch(val);
    debouncedSearch(val, selectedClient, selectedType, selectedStatus);
  };

  const handleClientChange = (val: string) => {
    setSelectedClient(val);
    debouncedSearch(search, val, selectedType, selectedStatus);
  };

  const handleTypeChange = (val: string) => {
    setSelectedType(val);
    debouncedSearch(search, selectedClient, val, selectedStatus);
  };

  const handleStatusChange = (val: string) => {
    setSelectedStatus(val);
    debouncedSearch(search, selectedClient, selectedType, val);
  };

  const handleResetFilters = () => {
    setSearch("");
    setSelectedClient("");
    setSelectedType("");
    setSelectedStatus("");
    router.get("/documents", {}, { preserveState: true, replace: true });
  };

  // Employee Autocomplete Fetch
  const fetchSuggestions = useCallback(
    debounce(async (query: string) => {
      if (query.trim().length < 2) {
        setEmpSuggestions([]);
        return;
      }
      setSearchingEmp(true);
      try {
        const res = await fetch(`/documents/search-employees?q=${encodeURIComponent(query)}`);
        if (res.ok) {
          const data = await res.json();
          setEmpSuggestions(data);
          setShowSuggestions(true);
        }
      } catch (err) {
        console.error("Autocomplete error:", err);
      } finally {
        setSearchingEmp(false);
      }
    }, 300),
    []
  );

  const handleStaffIdInput = (val: string) => {
    setSingleStaffId(val);
    fetchSuggestions(val);
  };

  const selectEmployee = (emp: any) => {
    setSingleStaffId(emp.staff_id || "");
    setSingleEmpName(emp.employee_name || "");
    setSingleClient(emp.client_name || "");
    setSingleEmail(emp.email || "");
    if (!singleDocTitle) {
      setSingleDocTitle(`${singleDocType} - ${emp.employee_name || emp.staff_id}`);
    }
    setShowSuggestions(false);
  };

  // Submit Single Document
  const handleSingleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!singleFile) {
      alert("Please select a document file to upload.");
      return;
    }
    if (!singleStaffId.trim()) {
      alert("Please enter or select a Staff ID.");
      return;
    }
    if (!singleEmail.trim()) {
      alert("Please enter the recipient employee email.");
      return;
    }

    const formData = new FormData();
    formData.append("staff_id", singleStaffId);
    formData.append("employee_name", singleEmpName);
    formData.append("client_name", singleClient);
    formData.append("document_type", singleDocType);
    formData.append("document_title", singleDocTitle || `${singleDocType} - ${singleStaffId}`);
    formData.append("recipient_email", singleEmail);
    formData.append("notes", singleNotes);
    formData.append("cc_hr", singleCcHr ? "1" : "0");
    formData.append("file", singleFile);

    setSingleSubmitting(true);
    router.post("/documents", formData, {
      onSuccess: () => {
        setIsSingleModalOpen(false);
        resetSingleForm();
      },
      onFinish: () => setSingleSubmitting(false),
    });
  };

  const resetSingleForm = () => {
    setSingleStaffId("");
    setSingleEmpName("");
    setSingleClient("");
    setSingleEmail("");
    setSingleDocType(DOCUMENT_TYPES[0]);
    setSingleDocTitle("");
    setSingleNotes("");
    setSingleFile(null);
    setSingleCcHr(true);
    setEmpSuggestions([]);
  };

  // Submit Bulk Upload
  const handleBulkSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (bulkMode === "files" && (!bulkFiles || bulkFiles.length === 0)) {
      alert("Please select one or more document files.");
      return;
    }
    if (bulkMode === "archive" && !bulkArchive) {
      alert("Please select a ZIP archive.");
      return;
    }

    const formData = new FormData();
    formData.append("document_type", bulkDocType);
    formData.append("document_title", bulkDocTitle);
    formData.append("client_name", bulkClient);
    formData.append("notes", bulkNotes);
    formData.append("cc_hr", bulkCcHr ? "1" : "0");

    if (bulkMode === "archive" && bulkArchive) {
      formData.append("archive", bulkArchive);
    } else if (bulkFiles) {
      Array.from(bulkFiles).forEach((f) => {
        formData.append("files[]", f);
      });
    }

    setBulkSubmitting(true);
    router.post("/documents/bulk-upload", formData, {
      onSuccess: () => {
        setIsBulkModalOpen(false);
        setBulkFiles(null);
        setBulkArchive(null);
        setBulkDocTitle("");
        setBulkNotes("");
      },
      onFinish: () => setBulkSubmitting(false),
    });
  };

  // Resend Email
  const handleResend = (id: number) => {
    if (!confirm("Are you sure you want to resend this document to the recipient email?")) {
      return;
    }
    setResendingId(id);
    router.post(
      `/documents/${id}/resend`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setResendingId(null),
      }
    );
  };

  const formatFileSize = (bytes: number | null) => {
    if (!bytes) return "—";
    const units = ["B", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
  };

  return (
    <AppLayout
      breadcrumbs={[
        { title: "Dashboard", href: "/dashboard" },
        { title: "Employee Documents", href: "/documents" },
      ]}
    >
      <Head title="Employee Documents Dispatcher" />

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
        {flash?.warning && (
          <div className="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg flex items-center space-x-2 text-sm shadow-sm">
            <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0" />
            <span>{flash.warning}</span>
          </div>
        )}
        {flash?.error && (
          <div className="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-lg flex items-center space-x-2 text-sm shadow-sm">
            <AlertCircle className="w-5 h-5 text-rose-600 flex-shrink-0" />
            <span>{flash.error}</span>
          </div>
        )}

        {/* Top Header & Actions */}
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
              <FileText className="w-7 h-7 text-blue-600" />
              Employee Documents Dispatcher
            </h1>
            <p className="text-sm text-gray-500 mt-1">
              Upload official HR documents (Contracts, Letters, Payslips, Reviews) and automatically dispatch them to employees' emails with HR tracking.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2.5">
            <Button
              onClick={() => setIsSingleModalOpen(true)}
              className="bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-sm transition-all flex items-center gap-2"
            >
              <Send className="w-4 h-4" />
              Upload &amp; Send Document
            </Button>

            <Button
              onClick={() => setIsBulkModalOpen(true)}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-medium shadow-sm transition-all flex items-center gap-2"
            >
              <FileUp className="w-4 h-4" />
              Bulk Upload Documents
            </Button>
          </div>
        </div>

        {/* Metric Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="border border-gray-100 shadow-sm bg-gradient-to-br from-blue-50/60 to-white">
            <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-xs font-semibold text-blue-700 uppercase tracking-wider">
                Total Documents Sent
              </CardTitle>
              <Send className="w-4 h-4 text-blue-600" />
            </CardHeader>
            <CardContent className="p-4 pt-0">
              <div className="text-2xl font-bold text-gray-900">
                {metrics.total_sent.toLocaleString()}
              </div>
              <p className="text-xs text-gray-500 mt-1">Successfully dispatched to staff</p>
            </CardContent>
          </Card>

          <Card className="border border-gray-100 shadow-sm bg-gradient-to-br from-emerald-50/60 to-white">
            <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-xs font-semibold text-emerald-700 uppercase tracking-wider">
                Sent This Month
              </CardTitle>
              <Clock className="w-4 h-4 text-emerald-600" />
            </CardHeader>
            <CardContent className="p-4 pt-0">
              <div className="text-2xl font-bold text-gray-900">
                {metrics.sent_this_month.toLocaleString()}
              </div>
              <p className="text-xs text-gray-500 mt-1">Documents sent in {new Date().toLocaleString('default', { month: 'long' })}</p>
            </CardContent>
          </Card>

          <Card className="border border-gray-100 shadow-sm bg-gradient-to-br from-purple-50/60 to-white">
            <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-xs font-semibold text-purple-700 uppercase tracking-wider">
                Employees Reached
              </CardTitle>
              <Users className="w-4 h-4 text-purple-600" />
            </CardHeader>
            <CardContent className="p-4 pt-0">
              <div className="text-2xl font-bold text-gray-900">
                {metrics.unique_employees.toLocaleString()}
              </div>
              <p className="text-xs text-gray-500 mt-1">Distinct staff members contacted</p>
            </CardContent>
          </Card>

          <Card className="border border-gray-100 shadow-sm bg-gradient-to-br from-rose-50/60 to-white">
            <CardHeader className="p-4 pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-xs font-semibold text-rose-700 uppercase tracking-wider">
                Delivery Issues
              </CardTitle>
              <AlertCircle className="w-4 h-4 text-rose-600" />
            </CardHeader>
            <CardContent className="p-4 pt-0">
              <div className="text-2xl font-bold text-gray-900">
                {metrics.failed.toLocaleString()}
              </div>
              <p className="text-xs text-gray-500 mt-1">Failed delivery attempts</p>
            </CardContent>
          </Card>
        </div>

        {/* Filters Toolbar */}
        <Card className="border-gray-200 shadow-sm">
          <CardContent className="p-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
              {/* Search */}
              <div className="lg:col-span-2 relative">
                <Search className="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                <Input
                  type="text"
                  placeholder="Search by staff ID, name, email, or title..."
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

              {/* Document Type Filter */}
              <div>
                <select
                  value={selectedType}
                  onChange={(e) => handleTypeChange(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-gray-50/50 border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">All Document Types</option>
                  {DOCUMENT_TYPES.map((t) => (
                    <option key={t} value={t}>
                      {t}
                    </option>
                  ))}
                </select>
              </div>

              {/* Status & Reset */}
              <div className="flex items-center gap-2">
                <select
                  value={selectedStatus}
                  onChange={(e) => handleStatusChange(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm bg-gray-50/50 border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">All Statuses</option>
                  <option value="sent">Delivered</option>
                  <option value="failed">Failed</option>
                  <option value="pending">Pending</option>
                </select>

                {(search || selectedClient || selectedType || selectedStatus) && (
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

        {/* Documents Table */}
        <Card className="border-gray-200 shadow-sm overflow-hidden">
          <CardHeader className="p-4 bg-gray-50 border-b flex flex-row items-center justify-between">
            <CardTitle className="text-base font-semibold text-gray-800 flex items-center gap-2">
              <Layers className="w-4 h-4 text-gray-500" />
              Dispatched Document Records ({documents.total.toLocaleString()})
            </CardTitle>
          </CardHeader>
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow className="bg-gray-100/70 hover:bg-gray-100/70">
                  <TableHead className="w-12 text-center">#</TableHead>
                  <TableHead>Staff ID</TableHead>
                  <TableHead>Employee Name</TableHead>
                  <TableHead>Client</TableHead>
                  <TableHead>Document Details</TableHead>
                  <TableHead>Recipient Email</TableHead>
                  <TableHead>Dispatched By</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {documents.data.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={9} className="text-center py-12 text-gray-500">
                      <FileText className="w-10 h-10 mx-auto text-gray-300 mb-2" />
                      <p className="font-medium text-gray-600">No dispatched documents found</p>
                      <p className="text-xs text-gray-400 mt-1">
                        Use "Upload &amp; Send Document" or "Bulk Upload" to send documents to employees.
                      </p>
                    </TableCell>
                  </TableRow>
                ) : (
                  documents.data.map((doc, idx) => {
                    const rowNumber = (documents.current_page - 1) * documents.per_page + idx + 1;
                    return (
                      <TableRow key={doc.id} className="hover:bg-blue-50/30 transition-colors">
                        <TableCell className="text-center text-xs text-gray-400">
                          {rowNumber}
                        </TableCell>
                        <TableCell className="font-semibold text-blue-700">
                          {doc.staff_id}
                        </TableCell>
                        <TableCell className="font-medium text-gray-900">
                          {doc.employee_name || "—"}
                        </TableCell>
                        <TableCell className="text-gray-600 text-sm">
                          {doc.client_name ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                              {doc.client_name}
                            </span>
                          ) : (
                            "—"
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="space-y-0.5">
                            <div className="font-medium text-gray-900 text-sm flex items-center gap-1.5">
                              {doc.document_title}
                            </div>
                            <div className="text-xs text-gray-500 flex items-center gap-2">
                              <span className="text-blue-600 font-medium">{doc.document_type}</span>
                              <span>&bull;</span>
                              <span className="truncate max-w-[180px]">{doc.file_name}</span>
                              <span>({formatFileSize(doc.file_size)})</span>
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1 text-sm text-gray-700">
                            <Mail className="w-3.5 h-3.5 text-gray-400" />
                            <span className="font-mono text-xs">{doc.recipient_email}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="text-xs text-gray-600">
                            <div>{doc.sender?.name || doc.sender_email || "System"}</div>
                            <div className="text-[11px] text-gray-400">
                              {doc.sent_at
                                ? new Date(doc.sent_at).toLocaleDateString()
                                : new Date(doc.created_at).toLocaleDateString()}
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          {doc.status === "sent" ? (
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                              <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                              Delivered
                            </span>
                          ) : doc.status === "failed" ? (
                            <span
                              className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 cursor-pointer"
                              title={doc.error_message || "Delivery failed"}
                              onClick={() => setSelectedDocDetails(doc)}
                            >
                              <AlertCircle className="w-3 h-3 text-rose-600" />
                              Failed
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                              <Clock className="w-3 h-3 text-amber-600" />
                              Pending
                            </span>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <a
                              href={`/documents/${doc.id}/download`}
                              target="_blank"
                              rel="noreferrer"
                              className="inline-flex items-center p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                              title="Download stored document"
                            >
                              <Download className="w-4 h-4" />
                            </a>

                            <button
                              type="button"
                              onClick={() => handleResend(doc.id)}
                              disabled={resendingId === doc.id}
                              className="inline-flex items-center p-1.5 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors disabled:opacity-50"
                              title="Resend email to recipient"
                            >
                              <RefreshCw
                                className={`w-4 h-4 ${resendingId === doc.id ? "animate-spin" : ""}`}
                              />
                            </button>

                            <button
                              type="button"
                              onClick={() => setSelectedDocDetails(doc)}
                              className="text-xs text-blue-600 hover:text-blue-800 font-medium px-1.5 py-1"
                            >
                              Details
                            </button>
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
          {documents.last_page > 1 && (
            <div className="p-4 bg-gray-50 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-gray-600">
              <div>
                Showing {documents.from || 0} to {documents.to || 0} of {documents.total} records
              </div>
              <div className="flex items-center gap-1">
                {documents.links.map((link, idx) => (
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

        {/* ─── SINGLE DOCUMENT UPLOAD MODAL ─────────────────────────────────────── */}
        <Dialog open={isSingleModalOpen} onOpenChange={setIsSingleModalOpen}>
          <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-lg font-bold text-gray-900 flex items-center gap-2">
                <Send className="w-5 h-5 text-blue-600" />
                Upload &amp; Send Document to Employee
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSingleSubmit} className="space-y-4 pt-2">
              {/* Employee Search Autocomplete */}
              <div className="space-y-1.5 relative">
                <Label className="text-xs font-semibold text-gray-700">
                  Employee Staff ID / Search <span className="text-rose-500">*</span>
                </Label>
                <div className="relative">
                  <Input
                    type="text"
                    placeholder="Type Staff ID or Name (e.g. SBP1001)..."
                    value={singleStaffId}
                    onChange={(e) => handleStaffIdInput(e.target.value)}
                    required
                  />
                  {searchingEmp && (
                    <RefreshCw className="w-4 h-4 text-blue-500 animate-spin absolute right-3 top-3" />
                  )}
                </div>

                {/* Suggestions drop */}
                {showSuggestions && empSuggestions.length > 0 && (
                  <div className="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-52 overflow-y-auto">
                    {empSuggestions.map((emp) => (
                      <div
                        key={emp.staff_id}
                        onClick={() => selectEmployee(emp)}
                        className="p-2.5 hover:bg-blue-50 cursor-pointer border-b last:border-0 text-sm transition-colors"
                      >
                        <div className="font-semibold text-gray-900 flex items-center justify-between">
                          <span>{emp.staff_id} — {emp.employee_name}</span>
                          <span className="text-xs text-blue-600 font-normal">{emp.client_name}</span>
                        </div>
                        <div className="text-xs text-gray-500 font-mono mt-0.5">
                          {emp.email || "No email on record"}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Employee Name & Client */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Employee Name</Label>
                  <Input
                    type="text"
                    value={singleEmpName}
                    onChange={(e) => setSingleEmpName(e.target.value)}
                    placeholder="Auto-filled or manual"
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Client / Company</Label>
                  <select
                    value={singleClient}
                    onChange={(e) => setSingleClient(e.target.value)}
                    className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">Select Client (Optional)</option>
                    {clients.map((c) => (
                      <option key={c.id} value={c.client_name}>
                        {c.client_name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Recipient Email */}
              <div className="space-y-1">
                <Label className="text-xs font-semibold text-gray-700">
                  Recipient Email <span className="text-rose-500">*</span>
                </Label>
                <div className="relative">
                  <Mail className="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                  <Input
                    type="email"
                    value={singleEmail}
                    onChange={(e) => setSingleEmail(e.target.value)}
                    placeholder="employee@domain.com"
                    className="pl-9 font-mono text-sm"
                    required
                  />
                </div>
                <p className="text-[11px] text-gray-500">
                  The document file will be emailed directly to this address.
                </p>
              </div>

              {/* Document Category & Title */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">
                    Document Category <span className="text-rose-500">*</span>
                  </Label>
                  <select
                    value={singleDocType}
                    onChange={(e) => setSingleDocType(e.target.value)}
                    className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    {DOCUMENT_TYPES.map((t) => (
                      <option key={t} value={t}>
                        {t}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">
                    Document Title <span className="text-rose-500">*</span>
                  </Label>
                  <Input
                    type="text"
                    value={singleDocTitle}
                    onChange={(e) => setSingleDocTitle(e.target.value)}
                    placeholder="e.g. Employment Contract 2026"
                    required
                  />
                </div>
              </div>

              {/* File Upload */}
              <div className="space-y-1">
                <Label className="text-xs font-semibold text-gray-700">
                  Select Document File <span className="text-rose-500">*</span>
                </Label>
                <div className="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 transition-colors bg-gray-50/50">
                  <input
                    type="file"
                    id="single-file-upload"
                    className="hidden"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip"
                    onChange={(e) => {
                      if (e.target.files && e.target.files[0]) {
                        setSingleFile(e.target.files[0]);
                      }
                    }}
                  />
                  <label
                    htmlFor="single-file-upload"
                    className="cursor-pointer flex flex-col items-center justify-center space-y-1"
                  >
                    <Upload className="w-8 h-8 text-blue-500" />
                    <span className="text-sm font-medium text-blue-600 hover:underline">
                      {singleFile ? singleFile.name : "Click to browse or drag document file here"}
                    </span>
                    <span className="text-xs text-gray-500">
                      PDF, Word (DOCX), Excel, Images, or ZIP up to 25MB
                    </span>
                    {singleFile && (
                      <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">
                        Selected: {singleFile.name} ({formatFileSize(singleFile.size)})
                      </span>
                    )}
                  </label>
                </div>
              </div>

              {/* Optional Message / Notes */}
              <div className="space-y-1">
                <Label className="text-xs font-semibold text-gray-700">
                  Custom Message to Employee (Optional)
                </Label>
                <textarea
                  rows={2}
                  value={singleNotes}
                  onChange={(e) => setSingleNotes(e.target.value)}
                  placeholder="e.g. Please find attached your official appointment letter. Kindly review and acknowledge receipt."
                  className="w-full text-sm p-2.5 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              {/* CC HR Manager */}
              <div className="flex items-center space-x-2 bg-blue-50/70 p-3 rounded-lg border border-blue-100">
                <input
                  type="checkbox"
                  id="singleCcHr"
                  checked={singleCcHr}
                  onChange={(e) => setSingleCcHr(e.target.checked)}
                  className="rounded text-blue-600 focus:ring-blue-500 h-4 w-4"
                />
                <label htmlFor="singleCcHr" className="text-xs text-blue-900 leading-tight">
                  Send a CC copy of this email to my HR address (
                  <span className="font-semibold">{currentUser.email}</span>)
                </label>
              </div>

              <DialogFooter className="pt-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsSingleModalOpen(false)}
                  disabled={singleSubmitting}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={singleSubmitting}
                  className="bg-blue-600 hover:bg-blue-700 text-white font-medium flex items-center gap-2"
                >
                  {singleSubmitting ? (
                    <>
                      <RefreshCw className="w-4 h-4 animate-spin" />
                      Uploading &amp; Sending...
                    </>
                  ) : (
                    <>
                      <Send className="w-4 h-4" />
                      Send to Employee
                    </>
                  )}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* ─── BULK DOCUMENT UPLOAD MODAL ───────────────────────────────────────── */}
        <Dialog open={isBulkModalOpen} onOpenChange={setIsBulkModalOpen}>
          <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-lg font-bold text-gray-900 flex items-center gap-2">
                <FileUp className="w-5 h-5 text-emerald-600" />
                Bulk Upload &amp; Auto-Dispatch Documents
              </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleBulkSubmit} className="space-y-4 pt-2">
              <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-xs text-emerald-900 space-y-1">
                <p className="font-semibold flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  How Auto-Matching Works:
                </p>
                <p>
                  Name each file starting with the employee's <strong>Staff ID</strong> (e.g.{" "}
                  <code className="bg-emerald-100 px-1 py-0.5 rounded font-mono">
                    SBP1001_contract.pdf
                  </code>{" "}
                  or{" "}
                  <code className="bg-emerald-100 px-1 py-0.5 rounded font-mono">
                    SBP1002.docx
                  </code>
                  ).
                </p>
                <p>
                  The system will automatically identify each staff member, look up their email from the employee registry, attach the file, and email them.
                </p>
              </div>

              {/* Mode Selection */}
              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => setBulkMode("files")}
                  className={`p-3 rounded-lg border text-left text-xs font-semibold transition-all ${
                    bulkMode === "files"
                      ? "bg-emerald-50 border-emerald-500 text-emerald-900 shadow-sm"
                      : "bg-white border-gray-200 text-gray-700 hover:bg-gray-50"
                  }`}
                >
                  <div className="flex items-center gap-1.5 mb-1">
                    <FileText className="w-4 h-4 text-emerald-600" />
                    Multiple Files
                  </div>
                  <span className="text-[11px] font-normal text-gray-500">
                    Select 5, 20, 50+ individual PDF/doc files
                  </span>
                </button>

                <button
                  type="button"
                  onClick={() => setBulkMode("archive")}
                  className={`p-3 rounded-lg border text-left text-xs font-semibold transition-all ${
                    bulkMode === "archive"
                      ? "bg-emerald-50 border-emerald-500 text-emerald-900 shadow-sm"
                      : "bg-white border-gray-200 text-gray-700 hover:bg-gray-50"
                  }`}
                >
                  <div className="flex items-center gap-1.5 mb-1">
                    <FolderArchive className="w-4 h-4 text-emerald-600" />
                    ZIP Archive
                  </div>
                  <span className="text-[11px] font-normal text-gray-500">
                    Upload a single .zip folder of documents
                  </span>
                </button>
              </div>

              {/* Document Type & Client */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">
                    Document Category <span className="text-rose-500">*</span>
                  </Label>
                  <select
                    value={bulkDocType}
                    onChange={(e) => setBulkDocType(e.target.value)}
                    className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  >
                    {DOCUMENT_TYPES.map((t) => (
                      <option key={t} value={t}>
                        {t}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">Client (Optional)</Label>
                  <select
                    value={bulkClient}
                    onChange={(e) => setBulkClient(e.target.value)}
                    className="w-full h-10 px-3 py-2 text-sm bg-white border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  >
                    <option value="">Auto-detect from Employee Record</option>
                    {clients.map((c) => (
                      <option key={c.id} value={c.client_name}>
                        {c.client_name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Document General Title */}
              <div className="space-y-1">
                <Label className="text-xs font-semibold text-gray-700">
                  Document Title Override (Optional)
                </Label>
                <Input
                  type="text"
                  value={bulkDocTitle}
                  onChange={(e) => setBulkDocTitle(e.target.value)}
                  placeholder="e.g. Contract Renewal 2026 (Leave empty to use file name)"
                />
              </div>

              {/* Bulk File Input */}
              {bulkMode === "files" ? (
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">
                    Select Multiple Document Files <span className="text-rose-500">*</span>
                  </Label>
                  <div className="border-2 border-dashed border-gray-300 rounded-lg p-5 text-center hover:border-emerald-500 transition-colors bg-gray-50/50">
                    <input
                      type="file"
                      id="bulk-files-input"
                      multiple
                      className="hidden"
                      accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                      onChange={(e) => setBulkFiles(e.target.files)}
                    />
                    <label
                      htmlFor="bulk-files-input"
                      className="cursor-pointer flex flex-col items-center justify-center space-y-1"
                    >
                      <Upload className="w-8 h-8 text-emerald-500" />
                      <span className="text-sm font-medium text-emerald-600 hover:underline">
                        {bulkFiles && bulkFiles.length > 0
                          ? `${bulkFiles.length} files selected`
                          : "Choose multiple files to upload"}
                      </span>
                      <span className="text-xs text-gray-500">
                        Hold Ctrl/Cmd or Shift to select multiple files
                      </span>
                    </label>
                  </div>
                </div>
              ) : (
                <div className="space-y-1">
                  <Label className="text-xs font-semibold text-gray-700">
                    Upload ZIP Archive (.zip) <span className="text-rose-500">*</span>
                  </Label>
                  <div className="border-2 border-dashed border-gray-300 rounded-lg p-5 text-center hover:border-emerald-500 transition-colors bg-gray-50/50">
                    <input
                      type="file"
                      id="bulk-zip-input"
                      className="hidden"
                      accept=".zip"
                      onChange={(e) => {
                        if (e.target.files && e.target.files[0]) {
                          setBulkArchive(e.target.files[0]);
                        }
                      }}
                    />
                    <label
                      htmlFor="bulk-zip-input"
                      className="cursor-pointer flex flex-col items-center justify-center space-y-1"
                    >
                      <FolderArchive className="w-8 h-8 text-emerald-500" />
                      <span className="text-sm font-medium text-emerald-600 hover:underline">
                        {bulkArchive ? bulkArchive.name : "Choose a ZIP archive containing documents"}
                      </span>
                      <span className="text-xs text-gray-500">
                        Max archive size: 50MB
                      </span>
                      {bulkArchive && (
                        <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">
                          Selected: {bulkArchive.name} ({formatFileSize(bulkArchive.size)})
                        </span>
                      )}
                    </label>
                  </div>
                </div>
              )}

              {/* Message to all */}
              <div className="space-y-1">
                <Label className="text-xs font-semibold text-gray-700">
                  Custom Message in Email (Optional)
                </Label>
                <textarea
                  rows={2}
                  value={bulkNotes}
                  onChange={(e) => setBulkNotes(e.target.value)}
                  placeholder="e.g. Please find attached your official document. Kindly keep for your records."
                  className="w-full text-sm p-2.5 border rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              {/* CC HR Manager */}
              <div className="flex items-center space-x-2 bg-emerald-50/70 p-3 rounded-lg border border-emerald-100">
                <input
                  type="checkbox"
                  id="bulkCcHr"
                  checked={bulkCcHr}
                  onChange={(e) => setBulkCcHr(e.target.checked)}
                  className="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                />
                <label htmlFor="bulkCcHr" className="text-xs text-emerald-900 leading-tight">
                  Send CC copy of dispatched emails to my address (
                  <span className="font-semibold">{currentUser.email}</span>)
                </label>
              </div>

              <DialogFooter className="pt-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsBulkModalOpen(false)}
                  disabled={bulkSubmitting}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={bulkSubmitting}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white font-medium flex items-center gap-2"
                >
                  {bulkSubmitting ? (
                    <>
                      <RefreshCw className="w-4 h-4 animate-spin" />
                      Processing &amp; Dispatching...
                    </>
                  ) : (
                    <>
                      <Send className="w-4 h-4" />
                      Process &amp; Send All
                    </>
                  )}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        {/* ─── DETAILS MODAL ────────────────────────────────────────────────────── */}
        <Dialog
          open={!!selectedDocDetails}
          onOpenChange={(open) => !open && setSelectedDocDetails(null)}
        >
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle className="text-base font-bold text-gray-900 flex items-center gap-2">
                <FileText className="w-5 h-5 text-blue-600" />
                Dispatched Document Details
              </DialogTitle>
            </DialogHeader>

            {selectedDocDetails && (
              <div className="space-y-3 pt-2 text-sm">
                <div className="p-3 bg-gray-50 rounded-lg space-y-2 border">
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Title</span>
                    <span className="font-medium text-gray-900">{selectedDocDetails.document_title}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Category</span>
                    <span className="text-blue-600 font-medium">{selectedDocDetails.document_type}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Staff ID</span>
                    <span className="font-semibold text-gray-900">{selectedDocDetails.staff_id}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Employee Name</span>
                    <span className="text-gray-900">{selectedDocDetails.employee_name || "—"}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Client / Company</span>
                    <span className="text-gray-900">{selectedDocDetails.client_name || "—"}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Recipient Email</span>
                    <span className="font-mono text-xs text-gray-900">{selectedDocDetails.recipient_email}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Original File</span>
                    <span className="text-gray-800 text-xs truncate max-w-[200px]">
                      {selectedDocDetails.file_name} ({formatFileSize(selectedDocDetails.file_size)})
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Dispatched By</span>
                    <span className="text-gray-800 text-xs">
                      {selectedDocDetails.sender?.name || selectedDocDetails.sender_email || "System"}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-xs text-gray-500 font-semibold uppercase">Dispatched At</span>
                    <span className="text-gray-800 text-xs">
                      {selectedDocDetails.sent_at
                        ? new Date(selectedDocDetails.sent_at).toLocaleString()
                        : new Date(selectedDocDetails.created_at).toLocaleString()}
                    </span>
                  </div>
                </div>

                {selectedDocDetails.notes && (
                  <div className="p-3 bg-blue-50/60 rounded-lg border border-blue-100 text-xs text-blue-900">
                    <span className="font-semibold block mb-0.5">HR Message:</span>
                    <p className="whitespace-pre-line">{selectedDocDetails.notes}</p>
                  </div>
                )}

                {selectedDocDetails.error_message && (
                  <div className="p-3 bg-rose-50 rounded-lg border border-rose-200 text-xs text-rose-900">
                    <span className="font-semibold block mb-0.5 flex items-center gap-1 text-rose-700">
                      <AlertCircle className="w-3.5 h-3.5" />
                      Delivery Error:
                    </span>
                    <p className="font-mono text-[11px]">{selectedDocDetails.error_message}</p>
                  </div>
                )}

                <DialogFooter className="pt-2 flex justify-between sm:justify-between w-full">
                  <a
                    href={`/documents/${selectedDocDetails.id}/download`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-700 bg-white border rounded-md hover:bg-gray-50"
                  >
                    <Download className="w-4 h-4 text-gray-500" />
                    Download File
                  </a>

                  <Button
                    type="button"
                    onClick={() => {
                      const id = selectedDocDetails.id;
                      setSelectedDocDetails(null);
                      handleResend(id);
                    }}
                    className="bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium flex items-center gap-1.5"
                  >
                    <RefreshCw className="w-3.5 h-3.5" />
                    Resend Email Now
                  </Button>
                </DialogFooter>
              </div>
            )}
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  );
}
