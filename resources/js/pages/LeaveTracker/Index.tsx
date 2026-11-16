import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { CheckCircle2, XCircle, Download, Check, X, Building2 } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Input } from "@/components/ui/input"; // Use native Input for checkboxes
import { useState, useEffect } from "react";
import { Input as ShadcnInput } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import ErrorBoundary from "@/components/ErrorBoundary";

interface LeaveApplication {
  id: number;
  ida: string;
  name: string | null;
  company?: string | null;
  type: string | null;
  date_start: string | null;
  date_end: string | null;
  reason: string | null;
  status: number | string;
  managername: string | null;
  phone: string | null;
}

interface ClientOption {
  id: number;
  client_name: string;
  short_name: string;
  client_dbname?: string;
}

interface Props {
  leaveApplications: {
    data: LeaveApplication[];
    links: any[];
  };
  clients?: ClientOption[];
  filters: {
    search: string;
    sort: string;
    order: string;
    client?: string;
  };
  totalRecords: number;
  searchCount: number | null;
  summary: {
    total: number;
    pending: number;
    approved: number;
    denied: number;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

interface FormData {
  ids: number[];
  action: "approve" | "deny" | null;
  leave_ids: number[];
  [key: string]: any;
}

const breadcrumbs = [
  { title: "Leave Tracker", href: "/leave-applications" },
];

export default function LeaveTrackerIndex({ leaveApplications, clients = [], filters, totalRecords, searchCount, summary, flash }: Props) {
  const [isBulkActionOpen, setIsBulkActionOpen] = useState(false);
  const [bulkAction, setBulkAction] = useState<"approve" | "deny" | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "date_start");
  const [order, setOrder] = useState(filters.order || "desc");
  const [selectedClient, setSelectedClient] = useState(filters.client || "all");
  const [isDownloadingMultiple, setIsDownloadingMultiple] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm<FormData>({
    ids: [],
    action: null,
    leave_ids: [],
  });

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
    if (Object.keys(errors).length > 0) {
      const errorMessage = Object.values(errors).join(' | ');
      setToastMessage(errorMessage);
      setToastType("error");
      setShowToast(true);
    }
  }, [errors]);

  useEffect(() => {
    if (showToast) {
      const duration = toastType === "error" ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), duration);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const isPending = (status: number | string) => {
    const s = String(status).toLowerCase();
    return s === "0" || s === "pending";
  };

  const isApproved = (status: number | string) => {
    const s = String(status).toLowerCase();
    return s === "1" || s === "approved";
  };

  const handleSelect = (id: number) => {
    setSelectedIds((prev) => {
      const newSelection = prev.includes(id)
        ? prev.filter((selectedId) => selectedId !== id)
        : [...prev, id];
      setData('leave_ids', newSelection);
      return newSelection;
    });
  };

  const handleSelectAll = () => {
    const selectableApps = leaveApplications.data.filter((app) => isPending(app.status) || isApproved(app.status));
    if (selectedIds.length === selectableApps.length && selectableApps.length > 0) {
      setSelectedIds([]);
      setData('leave_ids', []);
    } else {
      const newSelection = selectableApps.map((app) => app.id);
      setSelectedIds(newSelection);
      setData('leave_ids', newSelection);
    }
  };

  const handleBulkAction = (action: "approve" | "deny") => {
    if (selectedIds.length > 0) {
      setBulkAction(action);
      setIsBulkActionOpen(true);
    } else {
      setToastMessage("Please select at least one leave application.");
      setToastType("error");
      setShowToast(true);
    }
  };

  const confirmBulkAction = () => {
    if (bulkAction && selectedIds.length > 0) {
      setData({
        ids: selectedIds,
        action: bulkAction,
        leave_ids: data.leave_ids,
      });
      post(route("leave-applications.bulk-update"), {
        onSuccess: () => {
          setIsBulkActionOpen(false);
          setSelectedIds([]);
          setBulkAction(null);
          reset();
        },
        preserveState: true,
        preserveScroll: true,
      });
    }
  };

  const handleUpdateStatus = (id: number, action: "approve" | "deny") => {
    setData({
      ids: [id],
      action,
      leave_ids: data.leave_ids,
    });
    post(route("leave-applications.update-status", id), {
      onSuccess: () => {
        setSelectedIds((prev) => prev.filter((selectedId) => selectedId !== id));
        reset();
      },
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleDownloadMultiple = async () => {
    if (selectedIds.length === 0) {
      setToastMessage("Please select at least one approved leave application.");
      setToastType("error");
      setShowToast(true);
      return;
    }

    const approvedIds = selectedIds.filter((id) => {
      const app = leaveApplications.data.find((app) => app.id === id && isApproved(app.status));
      return !!app;
    });

    if (approvedIds.length === 0) {
      setToastMessage("Please select at least one approved leave application.");
      setToastType("error");
      setShowToast(true);
      return;
    }

    setIsDownloadingMultiple(true);
    try {
      const response = await fetch(route("leave-applications.download-multiple"), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
        },
        credentials: "include",
        body: JSON.stringify({
          leave_ids: approvedIds,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || "Failed to download leave PDFs");
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `leaves.zip`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);

      setSelectedIds([]);
      setData('leave_ids', []);
      setToastMessage("Leave PDFs downloaded successfully.");
      setToastType("success");
      setShowToast(true);
    } catch (error: any) {
      setToastMessage(error.message || "Failed to download leave PDFs.");
      setToastType("error");
      setShowToast(true);
    } finally {
      setIsDownloadingMultiple(false);
    }
  };

  const handleSearchSort = (customParams: Record<string, any> = {}) => {
    post(route("leave-applications.index", {
      search,
      sort,
      order,
      client: selectedClient,
      ...customParams,
    }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleExport = () => {
    const url = route("leave-applications.export", {
      search,
      sort,
      order,
      client: selectedClient,
    });
    const link = document.createElement("a");
    link.href = url;
    link.download = "leave_applications.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const getStatusBadge = (status: number | string) => {
    const s = String(status).toLowerCase();
    switch (s) {
      case "0":
      case "pending":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Pending</span>;
      case "1":
      case "approved":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Approved</span>;
      case "2":
      case "denied":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">Denied</span>;
      case "3":
      case "cancelled":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">Cancelled</span>;
      default:
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800">Unknown</span>;
    }
  };

  const formatDate = (date: string | null) => {
    if (!date) return "N/A";
    return new Date(date).toLocaleDateString("en-GB", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  };

  const hasApprovedSelections = selectedIds.some((id) =>
    leaveApplications.data.find((app) => app.id === id && isApproved(app.status))
  );

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Leave Tracker" />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? <CheckCircle2 className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
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
            <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
              <div>
                <h1 className="text-2xl font-bold tracking-tight">Leave Tracker</h1>
                <p className="text-sm text-muted-foreground">Manage and track leave applications across all assigned clients & companies.</p>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                {/* Search */}
                <ShadcnInput
                  placeholder="Search ID, Name, Type, Company..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyUp={(e) => e.key === "Enter" && handleSearchSort()}
                  className="w-full sm:w-56"
                />

                {/* Client Filter */}
                <Select
                  value={selectedClient}
                  onValueChange={(val) => {
                    setSelectedClient(val);
                    handleSearchSort({ client: val });
                  }}
                >
                  <SelectTrigger className="w-full sm:w-52">
                    <Building2 className="h-4 w-4 mr-1.5 opacity-60" />
                    <SelectValue placeholder="All Clients / Companies" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Clients / Companies</SelectItem>
                    {clients.map((c) => (
                      <SelectItem key={c.id} value={c.client_name}>
                        {c.short_name || c.client_name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                {/* Sort */}
                <Select
                  value={`${sort}:${order}`}
                  onValueChange={(value) => {
                    const [s, o] = value.split(":");
                    setSort(s);
                    setOrder(o);
                    handleSearchSort({ sort: s, order: o });
                  }}
                >
                  <SelectTrigger className="w-full sm:w-44">
                    <SelectValue placeholder="Sort by" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="date_start:desc">Start Date (Newest)</SelectItem>
                    <SelectItem value="date_start:asc">Start Date (Oldest)</SelectItem>
                    <SelectItem value="ida:asc">Employee ID (A-Z)</SelectItem>
                    <SelectItem value="name:asc">Name (A-Z)</SelectItem>
                    <SelectItem value="company:asc">Company (A-Z)</SelectItem>
                    <SelectItem value="type:asc">Leave Type (A-Z)</SelectItem>
                    <SelectItem value="status:asc">Status</SelectItem>
                  </SelectContent>
                </Select>

                {/* Export */}
                <Button onClick={handleExport} variant="outline" className="w-full sm:w-auto">
                  <Download className="h-4 w-4 mr-1.5" />
                  Export
                </Button>

                {/* Download Zip */}
                <Button
                  onClick={handleDownloadMultiple}
                  disabled={processing || isDownloadingMultiple || selectedIds.length === 0 || !hasApprovedSelections}
                  className={`w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white ${
                    processing || isDownloadingMultiple || selectedIds.length === 0 || !hasApprovedSelections
                      ? "opacity-50 cursor-not-allowed"
                      : ""
                  }`}
                >
                  <Download className="h-4 w-4 mr-1.5" />
                  {isDownloadingMultiple ? "Downloading..." : "Download Selected (ZIP)"}
                </Button>
              </div>
            </div>

            {/* Metric Summary Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card className="bg-blue-600 text-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-blue-100">Total Leave Requests</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-bold">{summary.total}</p>
                </CardContent>
              </Card>
              <Card className="bg-amber-500 text-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-amber-100">Pending Requests</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-bold">{summary.pending}</p>
                </CardContent>
              </Card>
              <Card className="bg-emerald-600 text-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-emerald-100">Approved Requests</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-bold">{summary.approved}</p>
                </CardContent>
              </Card>
              <Card className="bg-rose-600 text-white">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-rose-100">Denied Requests</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-3xl font-bold">{summary.denied}</p>
                </CardContent>
              </Card>
            </div>

            {/* Bulk Action Buttons */}
            <div className="flex flex-col sm:flex-row justify-end gap-2">
              <Button
                variant="default"
                onClick={() => handleBulkAction("approve")}
                disabled={selectedIds.length === 0}
                className="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white"
              >
                <Check className="h-4 w-4 mr-1.5" />
                Approve Selected
              </Button>
              <Button
                variant="destructive"
                onClick={() => handleBulkAction("deny")}
                disabled={selectedIds.length === 0}
                className="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white"
              >
                <X className="h-4 w-4 mr-1.5" />
                Deny Selected
              </Button>
            </div>
          </div>

          {leaveApplications.data.length > 0 ? (
            <Card>
              <CardHeader className="flex flex-row items-center justify-between py-3">
                <CardTitle className="text-base font-semibold">Leave Applications</CardTitle>
                <div className="text-xs text-muted-foreground">
                  {searchCount !== null
                    ? `Showing ${searchCount} of ${totalRecords} matching records`
                    : `Total: ${totalRecords} records`}
                </div>
              </CardHeader>
              <CardContent className="p-0 sm:p-4">
                {/* Mobile View */}
                <div className="block md:hidden space-y-3 p-3">
                  {leaveApplications.data.map((application) => (
                    <div key={application.id} className="border rounded-lg p-3 bg-card space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Input
                            type="checkbox"
                            checked={selectedIds.includes(application.id)}
                            onChange={() => handleSelect(application.id)}
                            disabled={!isPending(application.status) && !isApproved(application.status)}
                            className="h-4 w-4"
                          />
                          <span className="font-semibold">{application.ida}</span>
                        </div>
                        <div>{getStatusBadge(application.status)}</div>
                      </div>

                      <div className="text-sm"><span className="text-muted-foreground">Name:</span> <span className="font-medium">{application.name || "N/A"}</span></div>
                      <div className="text-sm"><span className="text-muted-foreground">Company:</span> <span className="font-semibold text-primary">{application.company || "N/A"}</span></div>
                      <div className="text-sm"><span className="text-muted-foreground">Type:</span> {application.type || "N/A"}</div>
                      <div className="text-xs text-muted-foreground">Period: {formatDate(application.date_start)} — {formatDate(application.date_end)}</div>
                      <div className="text-xs text-muted-foreground">Reason: {application.reason || "N/A"}</div>
                      <div className="text-xs text-muted-foreground">Manager: {application.managername || "N/A"} ({application.phone || "N/A"})</div>

                      <div className="flex gap-2 pt-2 border-t">
                        {isPending(application.status) && (
                          <>
                            <Button
                              variant="default"
                              size="sm"
                              className="bg-green-600 hover:bg-green-700 text-white flex-1"
                              onClick={() => handleUpdateStatus(application.id, "approve")}
                              disabled={processing}
                            >
                              <Check className="h-4 w-4 mr-1" /> Approve
                            </Button>
                            <Button
                              variant="destructive"
                              size="sm"
                              className="bg-red-600 hover:bg-red-700 text-white flex-1"
                              onClick={() => handleUpdateStatus(application.id, "deny")}
                              disabled={processing}
                            >
                              <X className="h-4 w-4 mr-1" /> Deny
                            </Button>
                          </>
                        )}
                        {isApproved(application.status) && (
                          <Button
                            variant="outline"
                            size="sm"
                            className="w-full"
                            onClick={() => {
                              const url = route("leave-applications.download", application.id);
                              const link = document.createElement("a");
                              link.href = url;
                              link.download = `leave_${application.ida}_${application.id}.pdf`;
                              document.body.appendChild(link);
                              link.click();
                              document.body.removeChild(link);
                            }}
                          >
                            <Download className="h-4 w-4 mr-1.5" /> Download PDF
                          </Button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>

                {/* Desktop View */}
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-10">
                          <Input
                            type="checkbox"
                            checked={
                              leaveApplications.data.filter((app) => isPending(app.status) || isApproved(app.status)).length > 0 &&
                              selectedIds.length === leaveApplications.data.filter((app) => isPending(app.status) || isApproved(app.status)).length
                            }
                            onChange={handleSelectAll}
                            className="h-4 w-4"
                          />
                        </TableHead>
                        <TableHead>Staff ID</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Company / Client</TableHead>
                        <TableHead>Leave Type</TableHead>
                        <TableHead>Start Date</TableHead>
                        <TableHead>End Date</TableHead>
                        <TableHead>Reason</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Manager</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {leaveApplications.data.map((application) => (
                        <TableRow key={application.id}>
                          <TableCell>
                            <Input
                              type="checkbox"
                              checked={selectedIds.includes(application.id)}
                              onChange={() => handleSelect(application.id)}
                              disabled={!isPending(application.status) && !isApproved(application.status)}
                              className="h-4 w-4"
                            />
                          </TableCell>
                          <TableCell className="font-medium">{application.ida}</TableCell>
                          <TableCell>{application.name || "N/A"}</TableCell>
                          <TableCell>
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-foreground border">
                              {application.company || "N/A"}
                            </span>
                          </TableCell>
                          <TableCell>{application.type || "N/A"}</TableCell>
                          <TableCell>{formatDate(application.date_start)}</TableCell>
                          <TableCell>{formatDate(application.date_end)}</TableCell>
                          <TableCell className="max-w-[200px] truncate" title={application.reason || ""}>
                            {application.reason || "N/A"}
                          </TableCell>
                          <TableCell>{getStatusBadge(application.status)}</TableCell>
                          <TableCell>
                            <div className="text-xs">
                              <div>{application.managername || "N/A"}</div>
                              <div className="text-muted-foreground">{application.phone || ""}</div>
                            </div>
                          </TableCell>
                          <TableCell className="text-right">
                            {isPending(application.status) && (
                              <div className="flex gap-1.5 justify-end">
                                <Button
                                  variant="default"
                                  size="sm"
                                  className="h-8 px-2.5 bg-green-600 hover:bg-green-700 text-white"
                                  onClick={() => handleUpdateStatus(application.id, "approve")}
                                  disabled={processing}
                                  title="Approve"
                                >
                                  <Check className="h-4 w-4" />
                                </Button>
                                <Button
                                  variant="destructive"
                                  size="sm"
                                  className="h-8 px-2.5 bg-red-600 hover:bg-red-700 text-white"
                                  onClick={() => handleUpdateStatus(application.id, "deny")}
                                  disabled={processing}
                                  title="Deny"
                                >
                                  <X className="h-4 w-4" />
                                </Button>
                              </div>
                            )}
                            {isApproved(application.status) && (
                              <Button
                                variant="outline"
                                size="sm"
                                className="h-8 px-2.5"
                                onClick={() => {
                                  const url = route("leave-applications.download", application.id);
                                  const link = document.createElement("a");
                                  link.href = url;
                                  link.download = `leave_${application.ida}_${application.id}.pdf`;
                                  document.body.appendChild(link);
                                  link.click();
                                  document.body.removeChild(link);
                                }}
                                title="Download PDF"
                              >
                                <Download className="h-4 w-4" />
                              </Button>
                            )}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>

                {/* Pagination */}
                <div className="flex justify-end p-4 border-t">
                  <div className="flex gap-1.5">
                    {leaveApplications.links.map((link, index) => (
                      <Button
                        key={index}
                        variant={link.active ? "default" : "outline"}
                        size="sm"
                        disabled={!link.url}
                        asChild={!!link.url}
                      >
                        {link.url ? (
                          <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} preserveState preserveScroll />
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
              <CardContent className="py-12 text-center text-muted-foreground">
                <p>No leave application records found.</p>
              </CardContent>
            </Card>
          )}

          {/* Bulk Action Dialog */}
          <Dialog open={isBulkActionOpen} onOpenChange={setIsBulkActionOpen}>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Confirm Bulk Action</DialogTitle>
              </DialogHeader>
              <p>
                Are you sure you want to <strong>{bulkAction}</strong> {selectedIds.length} leave application(s)?
              </p>
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsBulkActionOpen(false)}>
                  Cancel
                </Button>
                <Button
                  variant={bulkAction === "approve" ? "default" : "destructive"}
                  onClick={confirmBulkAction}
                  disabled={processing}
                  className={bulkAction === "approve" ? "bg-green-600 hover:bg-green-700 text-white" : ""}
                >
                  Confirm {bulkAction === "approve" ? "Approval" : "Denial"}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
