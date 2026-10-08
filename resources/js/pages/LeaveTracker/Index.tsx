import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { CheckCircle2, XCircle, Download, Check, X } from "lucide-react";
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
  type: string | null;
  date_start: string | null;
  date_end: string | null;
  reason: string | null;
  status: number;
  managername: string | null;
  phone: string | null;
}

interface Props {
  leaveApplications: {
    data: LeaveApplication[];
    links: any[];
  };
  filters: {
    search: string;
    sort: string;
    order: string;
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

export default function LeaveTrackerIndex({ leaveApplications, filters, totalRecords, searchCount, summary, flash }: Props) {
  console.log('Props:', { leaveApplications, filters, totalRecords, searchCount, summary, flash });
  console.log('Leave application IDs:', leaveApplications.data.map((app) => ({ id: app.id, status: app.status })));

  const [isBulkActionOpen, setIsBulkActionOpen] = useState(false);
  const [bulkAction, setBulkAction] = useState<"approve" | "deny" | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState(filters.search || "");
  const [sort, setSort] = useState(filters.sort || "date_start");
  const [order, setOrder] = useState(filters.order || "desc");
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

  const handleSelect = (id: number) => {
    console.log('handleSelect called:', { id, currentSelectedIds: selectedIds });
    setSelectedIds((prev) => {
      const newSelection = prev.includes(id)
        ? prev.filter((selectedId) => selectedId !== id)
        : [...prev, id];
      console.log('Updated selectedIds:', newSelection);
      setData('leave_ids', newSelection);
      return newSelection;
    });
  };

  const handleSelectAll = () => {
    console.log('handleSelectAll:', { currentSelectedIds: selectedIds });
    if (selectedIds.length === leaveApplications.data.filter((app) => app.status === 0 || app.status === 1).length) {
      setSelectedIds([]);
      setData('leave_ids', []);
    } else {
      const newSelection = leaveApplications.data
        .filter((app) => app.status === 0 || app.status === 1)
        .map((app) => app.id);
      setSelectedIds(newSelection);
      setData('leave_ids', newSelection);
      console.log('Updated selectedIds:', newSelection);
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
    console.log('handleDownloadMultiple called:', { selectedIds, leaveApplications: leaveApplications.data });

    if (selectedIds.length === 0) {
      console.log('No IDs selected, showing toast and aborting request.');
      setToastMessage("Please select at least one approved leave application.");
      setToastType("error");
      setShowToast(true);
      return;
    }

    const approvedIds = selectedIds.filter((id) => {
      const app = leaveApplications.data.find((app) => app.id === id && app.status === 1);
      console.log('Checking ID:', { id, found: !!app, status: app?.status });
      return !!app;
    });
    console.log('Approved IDs:', approvedIds);

    if (approvedIds.length === 0) {
      console.log('No approved IDs selected, showing toast and aborting request.');
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
      console.error("Download multiple error:", error);
      setToastMessage(error.message || "Failed to download leave PDFs.");
      setToastType("error");
      setShowToast(true);
    } finally {
      setIsDownloadingMultiple(false);
    }
  };

  const handleSearchSort = () => {
    post(route("leave-applications.index", { search, sort, order }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleExport = () => {
    const url = route("leave-applications.export", { search, sort, order });
    const link = document.createElement("a");
    link.href = url;
    link.download = "leave_applications.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const getStatusBadge = (status: number) => {
    switch (status) {
      case 0:
        return <span className="badge bg-yellow-500 text-black">Pending</span>;
      case 1:
        return <span className="badge bg-green-500 text-white">Approved</span>;
      case 2:
        return <span className="badge bg-red-500 text-white">Denied</span>;
      case 3:
        return <span className="badge bg-gray-500 text-white">Cancelled</span>;
      default:
        return <span className="badge bg-gray-500 text-white">Unknown</span>;
    }
  };

  const formatDate = (date: string | null) => {
    if (!date) return "N/A";
    return new Date(date).toLocaleDateString("en-GB", {
      day: "numeric",
      month: "long",
      year: "numeric",
    }).replace(/(\d+)(st|nd|rd|th)/, "$1$2");
  };

  const hasApprovedSelections = selectedIds.some((id) =>
    leaveApplications.data.find((app) => app.id === id && app.status === 1)
  );
  console.log('Button disabled state:', {
    selectedIds,
    hasApprovedSelections,
    processing,
    isDownloadingMultiple,
    disabled: processing || isDownloadingMultiple || selectedIds.length === 0 || !hasApprovedSelections,
  });

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
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <h1 className="text-2xl font-bold">Leave Tracker</h1>
              <div className="flex flex-col sm:flex-row gap-4">
                <ShadcnInput
                  placeholder="Search by employee ID, name, or leave type..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyUp={handleSearchSort}
                  className="w-full sm:w-64"
                />
                <Select
                  value={`${sort}:${order}`}
                  onValueChange={(value) => {
                    setSort(value.split(":")[0]);
                    setOrder(value.split(":")[1]);
                    handleSearchSort();
                  }}
                >
                  <SelectTrigger className="w-full sm:w-48">
                    <SelectValue placeholder="Sort by" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="ida:asc">Employee ID (A-Z)</SelectItem>
                    <SelectItem value="ida:desc">Employee ID (Z-A)</SelectItem>
                    <SelectItem value="name:asc">Name (A-Z)</SelectItem>
                    <SelectItem value="name:desc">Name (Z-A)</SelectItem>
                    <SelectItem value="type:asc">Leave Type (A-Z)</SelectItem>
                    <SelectItem value="type:desc">Leave Type (Z-A)</SelectItem>
                    <SelectItem value="date_start:asc">Start Date (Oldest)</SelectItem>
                    <SelectItem value="date_start:desc">Start Date (Newest)</SelectItem>
                    <SelectItem value="status:asc">Status (A-Z)</SelectItem>
                    <SelectItem value="status:desc">Status (Z-A)</SelectItem>
                  </SelectContent>
                </Select>
                <Button onClick={handleExport} className="w-full sm:w-auto">
                  <Download className="h-4 w-4 mr-2" />
                  Export to Excel
                </Button>
                <Button
                  onClick={() => {
                    if (processing || isDownloadingMultiple || selectedIds.length === 0 || !hasApprovedSelections) {
                      console.log('Button click prevented:', { processing, isDownloadingMultiple, selectedIds, hasApprovedSelections });
                      return;
                    }
                    handleDownloadMultiple();
                  }}
                  disabled={processing || isDownloadingMultiple || selectedIds.length === 0 || !hasApprovedSelections}
                  className={`w-full sm:w-auto bg-blue-600 hover:bg-blue-700 ${
                    processing || isDownloadingMultiple || selectedIds.length === 0 || !hasApprovedSelections
                      ? "opacity-50 cursor-not-allowed"
                      : ""
                  }`}
                >
                  <Download className="h-4 w-4 mr-2" />
                  {isDownloadingMultiple ? "Downloading..." : "Download Selected as ZIP"}
                </Button>
              </div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card className="bg-blue-600 text-white">
                <CardHeader>
                  <CardTitle>Total Leave Requests</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-4xl font-bold">{summary.total}</p>
                </CardContent>
              </Card>
              <Card className="bg-yellow-500 text-black">
                <CardHeader>
                  <CardTitle>Pending Requests</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-4xl font-bold">{summary.pending}</p>
                </CardContent>
              </Card>
              <Card className="bg-green-600 text-white">
                <CardHeader>
                  <CardTitle>Approved Requests</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-4xl font-bold">{summary.approved}</p>
                </CardContent>
              </Card>
              <Card className="bg-red-600 text-white">
                <CardHeader>
                  <CardTitle>Denied Requests</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-4xl font-bold">{summary.denied}</p>
                </CardContent>
              </Card>
            </div>
            <div className="flex flex-col sm:flex-row justify-end gap-4">
              <Button
                variant="default"
                onClick={() => handleBulkAction("approve")}
                disabled={selectedIds.length === 0}
                className="w-full sm:w-auto bg-green-600 hover:bg-green-700"
              >
                <Check className="h-4 w-4 mr-2" />
                Approve Selected
              </Button>
              <Button
                variant="destructive"
                onClick={() => handleBulkAction("deny")}
                disabled={selectedIds.length === 0}
                className="w-full sm:w-auto bg-red-600 hover:bg-red-700"
              >
                <X className="h-4 w-4 mr-2" />
                Deny Selected
              </Button>
            </div>
          </div>

          {leaveApplications.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>All Leave Applications</CardTitle>
                <div className="text-sm text-muted-foreground">
                  {searchCount !== null
                    ? `Showing ${searchCount} of ${totalRecords} records matching your search`
                    : `Total Records: ${totalRecords}`}
                </div>
              </CardHeader>
              <CardContent>
                <div className="block md:hidden space-y-4">
                  {leaveApplications.data.map((application) => (
                    <div key={application.id} className="border rounded-lg p-4 bg-background">
                      <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-2">
                          <Input
                            type="checkbox"
                            checked={selectedIds.includes(application.id)}
                            onChange={() => {
                              console.log('Checkbox mobile:', {
                                id: application.id,
                                checked: !selectedIds.includes(application.id),
                                status: application.status,
                                disabled: application.status !== 0 && application.status !== 1,
                              });
                              handleSelect(application.id);
                            }}
                            disabled={application.status !== 0 && application.status !== 1}
                          />
                          <div className="font-semibold">{application.ida}</div>
                        </div>
                        <div className="text-sm text-muted-foreground">Name: {application.name || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Leave Type: {application.type || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Start Date: {formatDate(application.date_start)}</div>
                        <div className="text-sm text-muted-foreground">End Date: {formatDate(application.date_end)}</div>
                        <div className="text-sm text-muted-foreground">Reason: {application.reason || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Status: {getStatusBadge(application.status)}</div>
                        <div className="text-sm text-muted-foreground">Manager: {application.managername || "N/A"}</div>
                        <div className="text-sm text-muted-foreground">Phone: {application.phone || "N/A"}</div>
                        <div className="flex gap-2">
                          {application.status === 0 && (
                            <>
                              <Button
                                variant="default"
                                size="sm"
                                onClick={() => handleUpdateStatus(application.id, "approve")}
                                disabled={processing}
                              >
                                <Check className="h-4 w-4" />
                              </Button>
                              <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => handleUpdateStatus(application.id, "deny")}
                                disabled={processing}
                              >
                                <X className="h-4 w-4" />
                              </Button>
                            </>
                          )}
                          {application.status === 1 && (
                            <Button
                              variant="ghost"
                              size="sm"
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
                              <Download className="h-4 w-4" />
                            </Button>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
                <div className="hidden md:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>
                          <Input
                            type="checkbox"
                            checked={selectedIds.length === leaveApplications.data.filter((app) => app.status === 0 || app.status === 1).length && leaveApplications.data.length > 0}
                            onChange={handleSelectAll}
                          />
                        </TableHead>
                        <TableHead>Employee ID</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Leave Type</TableHead>
                        <TableHead>Start Date</TableHead>
                        <TableHead>End Date</TableHead>
                        <TableHead>Reason</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Manager</TableHead>
                        <TableHead>Phone</TableHead>
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
                              onChange={() => {
                                console.log('Checkbox desktop:', {
                                  id: application.id,
                                  checked: !selectedIds.includes(application.id),
                                  status: application.status,
                                  disabled: application.status !== 0 && application.status !== 1,
                                });
                                handleSelect(application.id);
                              }}
                              disabled={application.status !== 0 && application.status !== 1}
                            />
                          </TableCell>
                          <TableCell>{application.ida}</TableCell>
                          <TableCell>{application.name || "N/A"}</TableCell>
                          <TableCell>{application.type || "N/A"}</TableCell>
                          <TableCell>{formatDate(application.date_start)}</TableCell>
                          <TableCell>{formatDate(application.date_end)}</TableCell>
                          <TableCell>{application.reason || "N/A"}</TableCell>
                          <TableCell>{getStatusBadge(application.status)}</TableCell>
                          <TableCell>{application.managername || "N/A"}</TableCell>
                          <TableCell>{application.phone || "N/A"}</TableCell>
                          <TableCell className="text-right">
                            {application.status === 0 && (
                              <div className="flex gap-2 justify-end">
                                <Button
                                  variant="default"
                                  size="sm"
                                  onClick={() => handleUpdateStatus(application.id, "approve")}
                                  disabled={processing}
                                >
                                  <Check className="h-4 w-4" />
                                </Button>
                                <Button
                                  variant="destructive"
                                  size="sm"
                                  onClick={() => handleUpdateStatus(application.id, "deny")}
                                  disabled={processing}
                                >
                                  <X className="h-4 w-4" />
                                </Button>
                              </div>
                            )}
                            {application.status === 1 && (
                              <Button
                                variant="ghost"
                                size="sm"
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
                                <Download className="h-4 w-4" />
                              </Button>
                            )}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
                <div className="flex justify-end mt-4">
                  <div className="flex gap-2">
                    {leaveApplications.links.map((link, index) => (
                      <Button
                        key={index}
                        variant={link.active ? "default" : "outline"}
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
              <CardContent className="pt-6">
                <p className="text-center text-gray-500">No leave application records found.</p>
              </CardContent>
            </Card>
          )}

          <Dialog open={isBulkActionOpen} onOpenChange={setIsBulkActionOpen}>
            <DialogContent className="p-4 sm:max-w-md w-full">
              <DialogHeader>
                <DialogTitle>Confirm Bulk {bulkAction === "approve" ? "Approval" : "Denial"}</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to {bulkAction} {selectedIds.length} leave application(s)? This action cannot be undone.</p>
              <DialogFooter className="flex flex-col sm:flex-row sm:justify-end gap-2">
                <Button variant="outline" onClick={() => setIsBulkActionOpen(false)}>
                  Cancel
                </Button>
                <Button
                  variant={bulkAction === "approve" ? "default" : "destructive"}
                  onClick={confirmBulkAction}
                  disabled={processing}
                  className={bulkAction === "approve" ? "bg-green-600 hover:bg-green-700" : "bg-red-600 hover:bg-red-700"}
                >
                  {bulkAction === "approve" ? "Approve" : "Deny"} Selected
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
