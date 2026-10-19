import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, Send, MessageSquare, CheckCircle, XCircle } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";

const styles = `
  .payslip-modal {
    max-height: 90vh;
    overflow-y: auto;
    padding: 8px;
  }
  .payslip-table {
    width: 100%;
    max-width: 100%;
    font-size: 12px;
    font-family: Arial, sans-serif;
    border-collapse: collapse;
    border: 1px solid #ccc;
  }
  .payslip-table th, .payslip-table td {
    padding: 8px;
    border: 1px solid #ccc;
    text-align: left;
  }
  .payslip-table th {
    background-color: #4b5563;
    color: white;
    font-size: 12px;
    font-weight: bold;
    text-align: center;
  }
  .payslip-table img {
    width: 150px;
    height: auto;
  }
  .payslip-table .text-3xl {
    font-size: 16px;
    font-weight: bold;
  }
  .payslip-table .total, .payslip-table .net-pay {
    font-weight: bold;
    color: #000000;
  }
  .payslip-table-container {
    overflow-x: auto;
  }
  @media (max-width: 640px) {
    .payslip-modal { padding: 4px; }
    .payslip-table { font-size: 10px; }
    .payslip-table th, .payslip-table td { padding: 4px; }
    .payslip-table img { width: 100px; }
    .payslip-table .text-3xl { font-size: 12px; }
  }
`;

interface Employee {
    staff_id: string;
    name: string;
}

interface Props {
    clientSlug: string;
    client: {
        short_name: string;
        client_dbname: string;
    };
    payslip?: any;
    month: string;
    formattedMonth: string;
    employees: Employee[];
    flash?: { success?: string; error?: string };
    errors?: { [key: string]: string };
    auth: {
        user: {
            name: string;
            email: string;
            is_super_admin: boolean;
        };
    };
}

const breadcrumbs = (clientSlug: string, shortName: string) => [
    { title: "Payslips", href: `/payslips/${clientSlug}` },
    { title: shortName, href: `/payslips/${clientSlug}` },
];

export default function Index({ clientSlug, client, payslip, month, formattedMonth, employees = [], flash, errors, auth }: Props) {
    const [isViewDialogOpen, setIsViewDialogOpen] = useState(!!payslip);
    const [staffId, setStaffId] = useState("");
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedEmployees, setSelectedEmployees] = useState<string[]>([]);
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, setToastType] = useState<"success" | "error">("success");
    const [isDownloading, setIsDownloading] = useState(false);
    const [isDownloadingMultiple, setIsDownloadingMultiple] = useState(false);
    const [isSendingEmail, setIsSendingEmail] = useState(false);
    const [isSendingSms, setIsSendingSms] = useState(false);

    const { data, setData, processing, reset } = useForm({
        month,
        staff_ids: [] as string[],
    });

    const isMelcom = client.client_dbname === 'melcom';
    const isHonda = client.client_dbname === 'honda';
    const isJaykay = client.client_dbname === 'jkay';
    const isPz = client.client_dbname === 'pz';
    const isTwellium = client.client_dbname === 'twellium';
    const isTwelliumAccra = client.client_dbname === 'twelliumaccra';
    const isShefer = client.client_dbname === 'shefer';
    const isInhouse = client.client_dbname === 'inhouse';
    const isAvara = client.client_dbname === 'avara' || client.client_dbname.includes('avara');
    const isOfiExternal = client.client_dbname === 'ofiexternal' || client.client_dbname === 'hygieneexternal' || clientSlug === 'ofi-hygiene-external' || client.client_dbname.includes('external');
    const isOfiHygiene = client.client_dbname === 'ofihygiene' || client.client_dbname === 'olamhygiene' || client.client_dbname === 'hygiene' || clientSlug === 'ofi-hygiene';
    const isOfiTempering = client.client_dbname === 'ofitemp' || client.client_dbname === 'olamtem' || clientSlug === 'ofi-tempering' || client.client_dbname.includes('tempering');
    const isOfiBwh = client.client_dbname === 'ofibwh' || client.client_dbname === 'olambwh' || clientSlug === 'ofi-bean-warehouse' || client.client_dbname.includes('bwh') || client.client_dbname.includes('bean');

    useEffect(() => {
        if (flash?.success) {
            setToastMessage(flash.success);
            setToastType("success");
            setShowToast(true);
        } else if (flash?.error) {
            setToastMessage(flash.error);
            setToastType("error");
            setShowToast(true);
        } else if (errors && Object.keys(errors).length > 0) {
            setToastMessage(Object.values(errors).join(" | "));
            setToastType("error");
            setShowToast(true);
        }
    }, [flash, errors]);

    useEffect(() => {
        if (showToast) {
            const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 10000 : 5000);
            return () => clearTimeout(timer);
        }
    }, [showToast, toastType]);

    const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newMonth = e.target.value;
        setData("month", newMonth);
        setStaffId("");
        setSelectedEmployees([]);
        router.get(route("payslips.index", clientSlug), { month: newMonth }, { preserveState: true, preserveScroll: true });
    };

    const handleStaffIdChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setStaffId(e.target.value);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.month || !staffId.trim()) {
            setToastMessage("Please select month and enter Staff ID");
            setToastType("error");
            setShowToast(true);
            return;
        }

        router.get(
            route("payslips.view", { client: clientSlug, month: data.month, staffId: staffId.trim() }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => setIsViewDialogOpen(true),
            }
        );
    };

    const handleSelectEmployee = (id: string | number) => {
        const idStr = String(id);
        setSelectedEmployees(prev => {
            const newSel = prev.includes(idStr) ? prev.filter(x => x !== idStr) : [...prev, idStr];
            setData("staff_ids", newSel);
            return newSel;
        });
    };

    const handleSelectAll = () => {
        if (selectedEmployees.length === filteredEmployees.length) {
            setSelectedEmployees([]);
            setData("staff_ids", []);
        } else {
            const all = filteredEmployees.map(e => String(e.staff_id));
            setSelectedEmployees(all);
            setData("staff_ids", all);
        }
    };

    const filteredEmployees = (employees || []).filter(emp =>
        String(emp?.staff_id ?? '').toLowerCase().includes(searchQuery.toLowerCase()) ||
        String(emp?.name ?? '').toLowerCase().includes(searchQuery.toLowerCase())
    );

    // Send emails in batches of 10
    const handleSendEmails = async () => {
        if (selectedEmployees.length === 0) {
            setToastMessage("Please select at least one employee");
            setToastType("error");
            setShowToast(true);
            return;
        }

        setIsSendingEmail(true);

        const batchSize = 10;
        let totalSent = 0;
        let totalFailed = 0;
        let totalNoEmail = 0;

        for (let i = 0; i < selectedEmployees.length; i += batchSize) {
            const batch = selectedEmployees.slice(i, i + batchSize);

            try {
                const response = await fetch(route("payslips.email", clientSlug, false), {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
                    },
                    body: JSON.stringify({
                        month: data.month,
                        staff_ids: batch,
                    }),
                });

                if (response.ok) {
                    const result = await response.json();
                    totalSent += result.sent || 0;
                    totalFailed += result.failed || 0;
                    totalNoEmail += result.no_email || 0;
                } else {
                    totalFailed += batch.length;
                }
            } catch (error) {
                totalFailed += batch.length;
            }
        }

        setIsSendingEmail(false);
        setSelectedEmployees([]);
        setSearchQuery("");
        reset();

        if (totalFailed === 0 && totalNoEmail === 0) {
            setToastMessage(`Successfully sent ${totalSent} payslip(s) via email!`);
            setToastType("success");
        } else {
            setToastMessage(`Email Result: ${totalSent} sent, ${totalNoEmail} skipped (no email), ${totalFailed} failed.`);
            setToastType("error");
        }
        setShowToast(true);
    };

    // Send SMS in batches of 10
    const handleSendSms = async () => {
        if (selectedEmployees.length === 0) {
            setToastMessage("Please select at least one employee");
            setToastType("error");
            setShowToast(true);
            return;
        }

        setIsSendingSms(true);

        const batchSize = 10;
        let totalSent = 0;
        let totalFailed = 0;
        let totalNoPhone = 0;

        for (let i = 0; i < selectedEmployees.length; i += batchSize) {
            const batch = selectedEmployees.slice(i, i + batchSize);

            try {
                const response = await fetch(route("payslips.sms", clientSlug, false), {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
                    },
                    body: JSON.stringify({
                        month: data.month,
                        staff_ids: batch,
                    }),
                });

                if (response.ok) {
                    const result = await response.json();
                    totalSent += result.sent || 0;
                    totalFailed += result.failed || 0;
                    totalNoPhone += result.no_phone || 0;
                } else {
                    totalFailed += batch.length;
                }
            } catch (error) {
                totalFailed += batch.length;
            }
        }

        setIsSendingSms(false);
        setSelectedEmployees([]);
        setSearchQuery("");
        reset();

        if (totalFailed === 0 && totalNoPhone === 0) {
            setToastMessage(`Successfully sent SMS to ${totalSent} employee(s)!`);
            setToastType("success");
        } else {
            setToastMessage(`SMS Result: ${totalSent} sent, ${totalNoPhone} skipped (no phone), ${totalFailed} failed.`);
            setToastType("error");
        }
        setShowToast(true);
    };

    const handleDownloadMultiplePDFs = () => {
        if (selectedEmployees.length === 0) {
            setToastMessage("Please select at least one employee");
            setToastType("error");
            setShowToast(true);
            return;
        }

        setIsDownloadingMultiple(true);
        const total = selectedEmployees.length;
        const targetMonth = data.month || month;

        setToastMessage(`Generating ZIP for ${total} payslip(s)... Your download will begin shortly.`);
        setToastType("success");
        setShowToast(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

            // Native form submission with Content-Disposition: attachment streams the ZIP directly
            // into the browser's download manager without JavaScript fetch timeouts, connection aborts,
            // or memory limits.
            const form = document.createElement("form");
            form.method = "POST";
            form.action = route("payslips.download-multiple", { client: clientSlug }, false);
            form.style.display = "none";

            const csrfInput = document.createElement("input");
            csrfInput.type = "hidden";
            csrfInput.name = "_token";
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            const monthInput = document.createElement("input");
            monthInput.type = "hidden";
            monthInput.name = "month";
            monthInput.value = targetMonth;
            form.appendChild(monthInput);

            const modeInput = document.createElement("input");
            modeInput.type = "hidden";
            modeInput.name = "mode";
            modeInput.value = "direct";
            form.appendChild(modeInput);

            // Pass selected staff IDs as JSON array string
            const staffIdsInput = document.createElement("input");
            staffIdsInput.type = "hidden";
            staffIdsInput.name = "staff_ids_json";
            staffIdsInput.value = JSON.stringify(selectedEmployees);
            form.appendChild(staffIdsInput);

            // If all employees are selected, signal all=1
            if (selectedEmployees.length === employees.length) {
                const allInput = document.createElement("input");
                allInput.type = "hidden";
                allInput.name = "all";
                allInput.value = "1";
                form.appendChild(allInput);
            }

            // Also pass individual staff_ids[] for standard PHP form parser
            selectedEmployees.forEach((id) => {
                const idInput = document.createElement("input");
                idInput.type = "hidden";
                idInput.name = "staff_ids[]";
                idInput.value = id;
                form.appendChild(idInput);
            });

            // Ensure hidden iframe exists so download form does not navigate or reload the page
            let iframe = document.getElementById("hidden_download_iframe") as HTMLIFrameElement;
            if (!iframe) {
                iframe = document.createElement("iframe");
                iframe.id = "hidden_download_iframe";
                iframe.name = "hidden_download_iframe";
                iframe.style.display = "none";
                document.body.appendChild(iframe);
            }
            form.target = "hidden_download_iframe";

            document.body.appendChild(form);
            form.submit();
            form.remove();

            setTimeout(() => {
                setIsDownloadingMultiple(false);
            }, 5000);

        } catch (error) {
            console.error("Bulk Payslip Download Error:", error);
            setToastMessage(error instanceof Error ? error.message : "Download failed");
            setToastType("error");
            setShowToast(true);
            setIsDownloadingMultiple(false);
        }
    };

    const handleDownloadPDF = async () => {
        if (!payslip) return;

        setIsDownloading(true);

        try {
            const employeeId =
                payslip.staff_id ||
                payslip.staffid ||
                payslip.staffida ||
                payslip.employ ||
                payslip.id;

            if (!employeeId) {
                throw new Error("Employee ID is missing.");
            }

            const targetMonth = data.month || month;
            const downloadUrl = route("payslips.download", {
                client: clientSlug,
                month: targetMonth,
                staffId: employeeId,
                download: 1,
            }, false);

            // Use direct browser anchor trigger for instant, reliable file download
            const a = document.createElement("a");
            a.href = downloadUrl;
            a.setAttribute("download", `${client.short_name}_Payslip_${employeeId}_${targetMonth}.pdf`);
            document.body.appendChild(a);
            a.click();
            a.remove();

            setToastMessage(`Downloading payslip for ${employeeId}...`);
            setToastType("success");
            setShowToast(true);

        } catch (error) {
            console.error("PDF Download Error:", error);

            setToastMessage(
                error instanceof Error
                    ? error.message
                    : "Download failed"
            );

            setToastType("error");
            setShowToast(true);

        } finally {
            setIsDownloading(false);
        }
    };

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs(clientSlug, client.short_name)}>
                <Head title={`${client.short_name} Payslips`} />
                <style>{styles}</style>

                {showToast && (
                    <div className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg text-white max-w-md break-words ${toastType === "success" ? "bg-green-500" : "bg-red-500"}`}>
                        {toastType === "success" ? <CheckCircle className="h-5 w-5 flex-shrink-0" /> : <XCircle className="h-5 w-5 flex-shrink-0" />}
                        <span className="text-sm">{toastMessage}</span>
                        <Button variant="ghost" size="sm" onClick={() => setShowToast(false)}>
                            <XCircle className="h-4 w-4" />
                        </Button>
                    </div>
                )}

                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h1 className="text-2xl font-bold">{client.short_name} Payslips</h1>
                        <form onSubmit={handleSubmit} className="flex flex-col sm:flex-row gap-4">
                            <div className="flex items-center gap-2">
                                <Label htmlFor="month">Select Month:</Label>
                                <Input
                                    id="month"
                                    type="month"
                                    value={data.month}
                                    onChange={handleMonthChange}
                                    min="2020-01"
                                    max={new Date().toISOString().slice(0, 7)}
                                    className="w-full sm:w-48"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="staffId">Staff ID:</Label>
                                <Input
                                    id="staffId"
                                    type="text"
                                    value={staffId}
                                    onChange={handleStaffIdChange}
                                    placeholder="Enter Staff ID"
                                    className="w-full sm:w-48"
                                />
                                <Button type="submit" disabled={processing || !data.month || !staffId}>
                                    View Payslip
                                </Button>
                            </div>
                        </form>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Send Email / Send SMS / Download Payslips</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4">
                                <div className="flex flex-col sm:flex-row items-center gap-4">
                                    <div className="flex items-center gap-2">
                                        <Label>Send Via:</Label>
                                        <div className="px-4 py-2 bg-gray-200 rounded-md text-sm font-medium">
                                            Email / SMS
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 w-full">
                                        <Label htmlFor="search">Search Employees:</Label>
                                        <Input
                                            id="search"
                                            value={searchQuery}
                                            onChange={e => setSearchQuery(e.target.value)}
                                            placeholder="Search by ID or Name"
                                            className="w-full"
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button type="button" variant="outline" onClick={handleSelectAll}>
                                        {selectedEmployees.length === filteredEmployees.length ? "Deselect All" : "Select All"}
                                    </Button>
                                    <div className="text-sm text-gray-600">{selectedEmployees.length} selected</div>
                                </div>

                                <div className="max-h-60 overflow-y-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Select</TableHead>
                                                <TableHead>Staff ID</TableHead>
                                                <TableHead>Name</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {filteredEmployees.map(emp => {
                                                const idStr = String(emp.staff_id);
                                                return (
                                                    <TableRow key={idStr}>
                                                        <TableCell>
                                                            <Input
                                                                type="checkbox"
                                                                checked={selectedEmployees.includes(idStr)}
                                                                onChange={() => handleSelectEmployee(idStr)}
                                                            />
                                                        </TableCell>
                                                        <TableCell>{idStr}</TableCell>
                                                        <TableCell>{emp.name}</TableCell>
                                                    </TableRow>
                                                );
                                            })}
                                            {filteredEmployees.length === 0 && (
                                                <TableRow>
                                                    <TableCell colSpan={3} className="text-center text-gray-500">
                                                        No employees found
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>

                                <div className="flex justify-end gap-2 flex-wrap">
                                    <Button
                                        type="button"
                                        onClick={handleSendEmails}
                                        disabled={isSendingEmail || isSendingSms || selectedEmployees.length === 0}
                                        className="bg-green-500 hover:bg-green-600 text-white"
                                    >
                                        <Send className="h-4 w-4 mr-2" />
                                        {isSendingEmail ? "Sending Emails..." : "Send Emails"}
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={handleSendSms}
                                        disabled={isSendingSms || isSendingEmail || selectedEmployees.length === 0}
                                        className="bg-purple-600 hover:bg-purple-700 text-white"
                                    >
                                        <MessageSquare className="h-4 w-4 mr-2" />
                                        {isSendingSms ? "Sending SMS..." : "Send SMS"}
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={handleDownloadMultiplePDFs}
                                        disabled={isDownloadingMultiple || selectedEmployees.length === 0}
                                        className="bg-blue-500 hover:bg-blue-600 text-white"
                                    >
                                        <Download className="h-4 w-4 mr-2" />
                                        {isDownloadingMultiple ? "Downloading..." : "Download PDFs"}
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Dialog open={isViewDialogOpen} onOpenChange={setIsViewDialogOpen}>
    <DialogContent className="payslip-modal sm:max-w-2xl w-[95vw]">
        <DialogHeader>
            <DialogTitle>
    {payslip
        ? `Payslip for ${
            isHonda ? payslip.NAME :
            isShefer ? payslip.name1 :
            isMelcom ? payslip.empoyeename :
            isAvara ? (payslip.name || payslip.empoyeename || payslip.NAME) :
            isJaykay || isTwellium || isInhouse||isTwelliumAccra ? payslip.name :
            isPz ? payslip.name :
            payslip.name
          } - ${formattedMonth}`
        : "No Payslip"}
</DialogTitle>
        </DialogHeader>

        {payslip && (
            <div className="payslip-table-container">
                <table className="payslip-table">
                    <thead>
                        <tr>
                            <td colSpan={2} className="p-2">
                                <img src="/images/sbpafricalogo.png" alt="Logo" />
                            </td>
                            <td className="text-center p-2">
                                <span className="text-3xl font-bold text-orange-500">SALARY SLIP</span>
                            </td>
                            <td colSpan={2} className="text-center p-2">
                                <span className="text-3xl font-bold text-black">CONFIDENTIAL</span>
                            </td>
                        </tr>
                        <tr>
                            <td colSpan={2} className="p-2"></td>
                            <td className="text-center p-2 font-bold">{formattedMonth}</td>
                            <td colSpan={2} className="p-2"></td>
                        </tr>
                    </thead>
                    <tbody>
                        {/* ==================== HONDA PAYSLIP ==================== */}
                        {isHonda && (
                            <>
                                <tr>
                                    <td className="p-2">Name</td>
                                    <td className="p-2">{payslip.NAME}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Title</td>
                                    <td className="p-2">{payslip.POSITION}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Employee ID</td>
                                    <td className="p-2">{payslip.STAFFID}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Client Company</td>
                                    <td className="p-2">HONDA</td>
                                </tr>
                                <tr>
                                    <td className="p-2">SSNIT #</td>
                                    <td className="p-2"></td>
                                    <td colSpan={3} className="p-2"></td>
                                </tr>
                                <tr>
                                    <td className="p-2">Basic Salary</td>
                                    <td className="p-2">{(payslip.BASICSALARY_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2"></td>
                                    <td className="p-2"></td>
                                </tr>
                                <tr>
                                    <th colSpan={2} className="text-white text-center">Item</th>
                                    <th className="text-white text-center">Description/Days</th>
                                    <th className="text-white text-center">Earnings</th>
                                    <th className="text-white text-center">Deductions</th>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Basic Salary Earned</td>
                                    <td className="p-2 text-right">{payslip.NUMBEROFDAYSWORKED || 0}</td>
                                    <td className="p-2 text-center">{(payslip.BASICSALARYEARNED_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Performance Bonus</td>
                                    <td className="p-2 text-right">{payslip.COEFFICIENT || 0}</td>
                                    <td className="p-2 text-center">{(payslip.PERFORMANCEBONUS ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Transport Allowance</td>
                                    <td className="p-2 text-right">{payslip.NUMBEROFDAYSWORKED || 0}</td>
                                    <td className="p-2 text-center">{(payslip.TRANSPORTALLOWANCE ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Food Allowance</td>
                                    <td className="p-2"></td>
                                    <td className="p-2 text-center">{(payslip.foodallowance ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
                                    <td></td>
                                    <td className="p-2 text-center font-bold">{(payslip.GROSSSALARY_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">SSNIT</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.SSNITEE_0_5 ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">PETRA</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.PETRA_5 ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">PAYE</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.PAYE_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={3} className="p-2 total">Total</td>
                                    <td className="p-2 text-center total">{(payslip.GROSSSALARY_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className="p-2 text-center total">{(payslip.TOTALDEDUCTIONS_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Name</td>
                                    <td className="p-2">{ payslip.bankname }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 net-pay">NET PAY</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account Name</td>
                                    <td className="p-2">{ payslip.name }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 text-center net-pay">
                                        {(payslip.TAKEHOMEPAY_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account #</td>
                                    <td className="p-2">{ payslip.alcnum }</td>
                                    <td colSpan={3}></td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Branch</td>
                                    <td className="p-2">{ payslip.branch }</td>
                                    <td colSpan={3}></td>
                                </tr>
                            </>
                        )}

                        {/* ==================== MELCOM PAYSLIP ==================== */}
                        {isMelcom && (
                            <>
                                <tr>
                                    <td className="p-2">Name</td>
                                    <td className="p-2">{payslip.empoyeename}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Title</td>
                                    <td className="p-2">{payslip.designation}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Employee ID</td>
                                    <td className="p-2">{payslip.employ}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Client Company</td>
                                    <td className="p-2">{payslip.shopname + " Melcom"}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">SSNIT #</td>
                                    <td className="p-2"></td>
                                    <td colSpan={3} className="p-2"></td>
                                </tr>
                                <tr>
                                    <th colSpan={2} className="text-white text-center">Item</th>
                                    <th className="text-white text-center">Description/Days/Hours</th>
                                    <th className="text-white text-center">Earnings</th>
                                    <th className="text-white text-center">Deductions</th>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Basic Salary</td>
                                    <td className="p-2 text-right">{payslip.actualdays ?? 0}</td>
                                    <td className="p-2 text-center">{(payslip.basicentitlement ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Absent Days</td>
                                    <td className="p-2 text-right">{payslip.absentdays ?? 0}</td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.absentwithoutpay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Normal Overtime</td>
                                    <td className="p-2 text-right">{payslip.weekdaysOT ?? 0}</td>
                                    <td className="p-2 text-center">{(payslip.normalovertime ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Sat & Sun Overtime</td>
                                    <td className="p-2 text-right">{payslip.weekendOT ?? 0}</td>
                                    <td className="p-2 text-center">{(payslip.saturdayovertime ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Holiday Overtime</td>
                                    <td className="p-2 text-right">{payslip.holidayot ?? 0}</td>
                                    <td className="p-2 text-center">{(payslip.holidayotamt ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Transport Allowance</td>
                                    <td className="p-2 text-right">{payslip.actualdays ?? 0}</td>
                                    <td className="p-2 text-center">{(payslip.transport ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
                                    <td></td>
                                    <td className="p-2 text-center font-bold">{(payslip.gross ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Recovery To Shop</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.trust ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">SSNIT</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.ssnit ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Normal Tax-Regular</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.paye ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Overtime Tax</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.overtimetax ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={3} className="p-2 total">Total</td>
                                    <td className="p-2 text-center total">{(payslip.gross ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className="p-2 text-center total">{(payslip.totaldeduction ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Name</td>
                                    <td className="p-2">{ payslip.bankname }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 net-pay">NET PAY</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account Name</td>
                                    <td className="p-2">{ payslip.name }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 text-center net-pay">
                                        {(payslip.banktransfer ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account #</td>
                                    <td className="p-2">{ payslip.alcnum }</td>
                                    <td colSpan={3}></td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Branch</td>
                                    <td className="p-2">{ payslip.branch }</td>
                                    <td colSpan={3}></td>
                                </tr>
                            </>
                        )}

                        {/* ==================== AVARA PAYSLIP ==================== */}
                        {isAvara && (
                            <>
                                <tr>
                                    <td className="p-2">Name</td>
                                    <td className="p-2">{payslip.empoyeename || payslip.name || payslip.NAME}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Title</td>
                                    <td className="p-2">{payslip.position || payslip.POSITION}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Employee ID</td>
                                    <td className="p-2">{payslip.employ || payslip.staff_id || payslip.STAFFID}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Client Company</td>
                                    <td className="p-2">{(payslip.brandname || payslip.SHOP || "Avara RC Limited")}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">SSNIT #</td>
                                    <td className="p-2">{payslip.ssnit_number || payslip.ssnit || payslip.SSNIT}</td>
                                    <td colSpan={3} className="p-2"></td>
                                </tr>
                                <tr>
                                    <th colSpan={2} className="text-white text-center">Item</th>
                                    <th className="text-white text-center">Description/Days/Hours</th>
                                    <th className="text-white text-center">Earnings</th>
                                    <th className="text-white text-center">Deductions</th>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Basic Salary</td>
                                    <td className="p-2 text-right">{Math.round(Number(payslip.workdays ?? payslip.NUMBER_OF_DAYS_WORKED ?? 0))}</td>
                                    <td className="p-2 text-center">{Number(payslip.basic || payslip.BASIC_SALARY_EARNED_GHC || payslip.BASIC_SALARY_GHC || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                {(Number(payslip.absentdays ?? payslip.ABSENT_DAYS ?? 0) > 0 || Number(payslip.absentwithoutpay ?? payslip.ABSENT_DAYS_DEDUCTIONS_GHC ?? 0) > 0) && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Absent Days</td>
                                        <td className="p-2 text-right">{Math.round(Number(payslip.absentdays ?? payslip.ABSENT_DAYS ?? 0))}</td>
                                        <td></td>
                                        <td className="p-2 text-center">{Number(payslip.absentwithoutpay || payslip.ABSENT_DAYS_DEDUCTIONS_GHC || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    </tr>
                                )}
                                <tr>
                                    <td colSpan={2} className="p-2">Normal Overtime</td>
                                    <td className="p-2 text-right">{parseFloat(Number(payslip.weekdaysOT ?? payslip.WEEKDAY_OVERTIME_HRS ?? 0).toFixed(2))}</td>
                                    <td className="p-2 text-center">{Number(payslip.normalovertime || payslip.WEEKDAY_OVERTIME_AMOUNT_GHC || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Sat & Sun Overtime</td>
                                    <td className="p-2 text-right">
                                        {(() => {
                                            const basicSal = Number(payslip.BASIC_SALARY_GHC || payslip.basic || 0);
                                            const legalDays = Number(payslip.LEGAL_WORK_DAYS || 26);
                                            const dailyRate = (basicSal > 0 && legalDays > 0) ? (basicSal / legalDays) : 0;
                                            const weekendOtAmt = Number(payslip.saturdayovertime || payslip.WEEKEND_OVERTIME_AMOUNT_GHC || 0);
                                            if (weekendOtAmt > 0 && dailyRate > 0) {
                                                const rawDays = weekendOtAmt / dailyRate;
                                                return (Math.abs(rawDays - Math.round(rawDays)) < 0.15) ? Math.round(rawDays) : Number(rawDays.toFixed(2));
                                            }
                                            return 0;
                                        })()}
                                    </td>
                                    <td className="p-2 text-center">{Number(payslip.saturdayovertime || payslip.WEEKEND_OVERTIME_AMOUNT_GHC || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Holiday Overtime</td>
                                    <td className="p-2 text-right">{parseFloat(Number(payslip.holiday ?? payslip.HOLIDAY_OVERTIME_HRS ?? 0).toFixed(2))}</td>
                                    <td className="p-2 text-center">{Number(payslip.holidayotamt || payslip.HOLIDAY_OVERTIME_AMOUNT_GHC || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Transport Allowance</td>
                                    <td className="p-2 text-right">
                                        {Number(payslip.allowance || payslip.TRANSPORT_GHC || 0) > 0 
                                            ? Math.round(Number(payslip.workdays ?? payslip.NUMBER_OF_DAYS_WORKED ?? 0)) 
                                            : 0}
                                    </td>
                                    <td className="p-2 text-center">{Number(payslip.allowance || payslip.TRANSPORT_GHC || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
                                    <td></td>
                                    <td className="p-2 text-center font-bold">{Number(payslip.grosspay ?? payslip.GROSS_SALARY_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">SSNIT EE (0.5%)</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{Number(payslip.ssnitee ?? payslip.SSNIT_EE_0_5_PERCENT ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Petra (5%)</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{Number(payslip.petra ?? payslip.PETRA_5_PERCENT ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Normal Tax (PAYE)</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{Number(payslip.paye ?? payslip.PAYE_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                {Number(payslip.overtimetax ?? payslip.OVERTIME_TAX_GHC ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Overtime Tax</td>
                                        <td></td>
                                        <td></td>
                                        <td className="p-2 text-center">{Number(payslip.overtimetax ?? payslip.OVERTIME_TAX_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    </tr>
                                )}
                                {Number(payslip.otherdeduction ?? payslip.DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Other Deductions / Student Loan</td>
                                        <td></td>
                                        <td></td>
                                        <td className="p-2 text-center">{Number(payslip.otherdeduction ?? payslip.DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    </tr>
                                )}
                                <tr>
                                    <td colSpan={3} className="p-2 total">Total</td>
                                    <td className="p-2 text-center total">{Number(payslip.grosspay ?? payslip.GROSS_SALARY_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className="p-2 text-center total">
                                        {(
                                            Number(payslip.ssnitee ?? payslip.SSNIT_EE_0_5_PERCENT ?? 0) +
                                            Number(payslip.petra ?? payslip.PETRA_5_PERCENT ?? 0) +
                                            Number(payslip.paye ?? payslip.PAYE_GHC ?? 0) +
                                            Number(payslip.overtimetax ?? payslip.OVERTIME_TAX_GHC ?? 0) +
                                            Number(payslip.otherdeduction ?? payslip.DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC ?? 0)
                                        ).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Name</td>
                                    <td className="p-2">{ payslip.bankname || payslip.BANK || '-' }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 net-pay">NET PAY</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account Name</td>
                                    <td className="p-2">{ payslip.name || payslip.empoyeename || payslip.NAME }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 text-center net-pay">
                                        {Number(payslip.netpay ?? payslip.TAKE_HOME_PAY_GHC ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account #</td>
                                    <td className="p-2">{ payslip.alcnum || payslip.ACCOUNT_NUMBER || '-' }</td>
                                    <td colSpan={3}></td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Branch</td>
                                    <td className="p-2">{ payslip.branch || payslip.BRANCH || '-' }</td>
                                    <td colSpan={3}></td>
                                </tr>
                            </>
                        )}
                        {/* ==================== OFI PAYSLIP (HYGIENE / TEMPERING / BEAN WAREHOUSE) ==================== */}
                        {(isOfiExternal || isOfiHygiene || isOfiTempering || isOfiBwh) && (
                            <>
                                <tr>
                                    <td className="p-2">Name</td>
                                    <td className="p-2">{payslip.name || payslip.employeename || payslip.names || '-'}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Title</td>
                                    <td className="p-2">{payslip.position || payslip.workarea || payslip.dept || '-'}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Employee ID</td>
                                    <td className="p-2">{payslip.staffid || payslip.staffida || payslip.id || '-'}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Client Company</td>
                                    <td className="p-2">{payslip.brandname || (isOfiExternal ? "OFI Hygiene External" : isOfiTempering ? "OFI Tempering" : isOfiBwh ? "OFI Bean Warehouse" : "OFI Hygiene")}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">SSNIT #</td>
                                    <td className="p-2">{payslip.ssnit_number || payslip.ssnit || payslip.SSNIT || '-'}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Department / Work Area</td>
                                    <td className="p-2">{payslip.department || payslip.workarea || payslip.dept || '-'}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">
                                        {(payslip.has_basic_salary === true || (Number(payslip.raw_basic ?? 0) > 0) || (Number(payslip.BASIC_SALARY_GHC ?? 0) > 0 && !payslip.is_casual && Number(payslip.weekdayhr ?? 0) === 0 && Number(payslip.weekdaytonage ?? 0) === 0 && Number(payslip.amt ?? 0) === 0))
                                            ? "Basic Salary"
                                            : "Daily Rate"}
                                    </td>
                                    <td className="p-2">
                                        {(payslip.has_basic_salary === true || (Number(payslip.raw_basic ?? 0) > 0) || (Number(payslip.BASIC_SALARY_GHC ?? 0) > 0 && !payslip.is_casual && Number(payslip.weekdayhr ?? 0) === 0 && Number(payslip.weekdaytonage ?? 0) === 0 && Number(payslip.amt ?? 0) === 0))
                                            ? Number(payslip.raw_basic || payslip.BASIC_SALARY_GHC || payslip.basic || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                            : (Number(payslip.daily_rate || payslip.rate || payslip.weekdayrate || 0) > 0
                                                ? `GHS ${Number(payslip.daily_rate || payslip.rate || payslip.weekdayrate).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                : "Daily Rate")}
                                    </td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Days Worked</td>
                                    <td className="p-2">{payslip.workdays ?? payslip.noofdayspresent ?? payslip.legalworkdays ?? 0}</td>
                                </tr>
                                <tr>
                                    <th colSpan={2} className="text-white text-center">Item</th>
                                    <th className="text-white text-center">Description/Days/Hours</th>
                                    <th className="text-white text-center">Earnings (GHS)</th>
                                    <th className="text-white text-center">Deductions (GHS)</th>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">
                                        {Number(payslip.weekdaytonage ?? 0) > 0 
                                            ? "Weekday Tonnage Pay" 
                                            : Number(payslip.weekdayhr ?? 0) > 0 
                                            ? "Regular Hours Pay" 
                                            : (payslip.has_basic_salary === true || (Number(payslip.raw_basic ?? 0) > 0) || (Number(payslip.BASIC_SALARY_GHC ?? 0) > 0 && !payslip.is_casual && Number(payslip.weekdayhr ?? 0) === 0 && Number(payslip.weekdaytonage ?? 0) === 0 && Number(payslip.amt ?? 0) === 0))
                                                ? "Basic Salary Earned"
                                                : "Daily Rate Earned"}
                                    </td>
                                    <td className="p-2 text-right">
                                        {Number(payslip.weekdaytonage ?? 0) > 0 
                                            ? `${Number(payslip.weekdaytonage)} tons` 
                                            : Number(payslip.weekdayhr ?? 0) > 0 
                                            ? `${Number(payslip.weekdayhr)} hrs` 
                                            : `${Math.round(Number(payslip.workdays ?? payslip.noofdayspresent ?? 0))} days`}
                                    </td>
                                    <td className="p-2 text-center">{Number(payslip.BASIC_SALARY_EARNED_GHC || payslip.basic_earned || payslip.basic || payslip.amt || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                {(Number(payslip.ftamount ?? 0) > 0 || (isOfiBwh && Number(payslip.fuel_and_rent_allowance ?? 0) > 0)) && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Feeding & Transport Allowance</td>
                                        <td className="p-2 text-right">{Math.round(Number(payslip.workdays ?? 0))} days</td>
                                        <td className="p-2 text-center">{Number(payslip.ftamount || payslip.fuel_and_rent_allowance || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                <tr>
                                    <td colSpan={2} className="p-2">Normal Overtime (Weekday)</td>
                                    <td className="p-2 text-right">{parseFloat(Number(payslip.weekdaysOT ?? 0).toFixed(2))}</td>
                                    <td className="p-2 text-center">{Number(payslip.normalovertime ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">
                                        {Number(payslip.weekendtonage ?? 0) > 0 ? "Weekend Tonnage Pay" : "Weekend Overtime"}
                                    </td>
                                    <td className="p-2 text-right">
                                        {Number(payslip.weekendtonage ?? 0) > 0 
                                            ? `${Number(payslip.weekendtonage)} tons` 
                                            : `${parseFloat(Number(payslip.weekendOT ?? 0).toFixed(2))} hrs`}
                                    </td>
                                    <td className="p-2 text-center">{Number(payslip.saturdayovertime ?? payslip.amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                {(Number(payslip.holiday ?? 0) > 0 || Number(payslip.holidayotamt ?? 0) > 0) && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Holiday Overtime</td>
                                        <td className="p-2 text-right">{parseFloat(Number(payslip.holiday ?? 0).toFixed(2))}</td>
                                        <td className="p-2 text-center">{Number(payslip.holidayotamt ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.transport ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Transport Allowance</td>
                                        <td className="p-2 text-right">
                                            {Math.round(Number(payslip.workdays ?? payslip.noofdayspresent ?? 0))} days
                                        </td>
                                        <td className="p-2 text-center">{Number(payslip.transport).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.nightallowance ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Night Allowance</td>
                                        <td className="p-2 text-right">{Math.round(Number(payslip.night ?? 0))}</td>
                                        <td className="p-2 text-center">{Number(payslip.nightallowance ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.medical ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Medical Allowance</td>
                                        <td></td>
                                        <td className="p-2 text-center">{Number(payslip.medical ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                <tr>
                                    <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
                                    <td></td>
                                    <td className="p-2 text-center font-bold">{Number(payslip.grosspay ?? payslip.totalamount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">SSNIT EE (0.5%)</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{Number(payslip.ssnitee ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Petra (5%)</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{Number(payslip.petra ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Normal Tax (PAYE)</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{Number(payslip.paye ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                {Number(payslip.overtimetax ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Overtime Tax</td>
                                        <td></td>
                                        <td></td>
                                        <td className="p-2 text-center">{Number(payslip.overtimetax ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    </tr>
                                )}
                                {Number(payslip.otherdeduction ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Other Deductions</td>
                                        <td></td>
                                        <td></td>
                                        <td className="p-2 text-center">{Number(payslip.otherdeduction ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    </tr>
                                )}
                                <tr>
                                    <td colSpan={3} className="p-2 total">Total</td>
                                    <td className="p-2 text-center total">{Number(payslip.grosspay ?? payslip.totalamount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className="p-2 text-center total">
                                        {(
                                            Number(payslip.ssnitee ?? 0) +
                                            Number(payslip.petra ?? 0) +
                                            Number(payslip.paye ?? 0) +
                                            Number(payslip.overtimetax ?? 0) +
                                            Number(payslip.otherdeduction ?? 0)
                                        ).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Name</td>
                                    <td className="p-2">{ payslip.bank ?? payslip.bankname ?? payslip.BANK ?? '-' }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 net-pay">NET PAY</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account Name</td>
                                    <td className="p-2">{ payslip.name ?? payslip.employeename ?? payslip.names ?? '-' }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 text-center net-pay">
                                        {Number(payslip.netpay ?? payslip.takehomepay ?? payslip.takehome ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account #</td>
                                    <td className="p-2">{ payslip.accountnumber ?? payslip.account ?? payslip.alcnum ?? payslip.ACCOUNT_NUMBER ?? '-' }</td>
                                    <td colSpan={3}></td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Branch</td>
                                    <td className="p-2">{ payslip.branch ?? payslip.BRANCH ?? '-' }</td>
                                    <td colSpan={3}></td>
                                </tr>
                            </>
                        )}
                        {/* ==================== JAYKAY PAYSLIP ==================== */}
{isJaykay && (
    <>
        <tr>
            <td className="p-2">Name</td>
            <td className="p-2">{payslip.name}</td>
            <td className="p-2"></td>
            <td className="p-2">Title</td>
            <td className="p-2">{payslip.position}</td>
        </tr>
        <tr>
            <td className="p-2">Employee ID</td>
            <td className="p-2">{payslip.staff_id}</td>
            <td className="p-2"></td>
            <td className="p-2">Client Company</td>
            <td className="p-2">JAY KAY</td>
        </tr>
        <tr>
            <td className="p-2">SSNIT #</td>
            <td className="p-2">{payslip.ssnit_number ?? payslip.SSNIT ?? payslip.ssnit ?? '-'}</td>
            <td colSpan={3} className="p-2"></td>
        </tr>
        <tr>
            <td className="p-2">Basic Salary</td>
            <td className="p-2">{(payslip.basic_salary ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td colSpan={3} className="p-2"></td>
        </tr>
        <tr>
            <th colSpan={2} className="text-white text-center">Item</th>
            <th className="text-white text-center">Description/Days</th>
            <th className="text-white text-center">Earnings</th>
            <th className="text-white text-center">Deductions</th>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Basic Salary</td>
            <td className="p-2 text-right">{payslip.days_worked ?? 0}</td>
            <td className="p-2 text-center">{(payslip.earned_basic_salary ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Absent Days</td>
            <td className="p-2 text-right">{payslip.absentee_days ?? 0}</td>
            <td></td>
            <td className="p-2 text-center">{(payslip.absent_deductions ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Night Allowance</td>
            <td className="p-2"></td>
            <td className="p-2 text-center">{(payslip.night_allowance ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Saturday Amount</td>
            <td className="p-2 text-right">{payslip.saturdays_worked ?? 0}</td>
            <td className="p-2 text-center">{(payslip.saturday_amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Overtime</td>
            <td className="p-2 text-right">{payslip.overtime_hours ?? 0}</td>
            <td className="p-2 text-center">{(payslip.overtime_amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
            <td></td>
            <td className="p-2 text-center font-bold">{(payslip.gross_salary ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">SSNIT</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.ssnit_ee ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        {Number(payslip.petra ?? payslip.petra_5_percent ?? 0) > 0 && (
            <tr>
                <td colSpan={2} className="p-2">Petra (5%)</td>
                <td></td>
                <td></td>
                <td className="p-2 text-center">{(payslip.petra ?? payslip.petra_5_percent ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>
        )}
        <tr>
            <td colSpan={2} className="p-2">Normal Tax-Regular</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.paye ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Overtime Tax</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.overtime_tax ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Other Deduction</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.surcharge_or_student_loan ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        <tr style={{ backgroundColor: '#3498db', color: 'white' }}>
            <td colSpan={2} className="p-2 font-bold">Total</td>
            <td></td>
            <td className="p-2 text-center font-bold">{(payslip.gross_salary ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td className="p-2 text-center font-bold">{(payslip.total_deductions ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Name</td>
            <td className="p-2">{ payslip.bankname ?? payslip.bank ?? '-' }</td>
            <td></td>
            <td colSpan={2} className="p-2 text-center" style={{ backgroundColor: '#95a5a6', color: 'white', fontWeight: 'bold' }}>NET PAY</td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Account Name</td>
            <td className="p-2">{ payslip.name }</td>
            <td></td>
            <td colSpan={2} className="p-2 text-right" style={{ backgroundColor: '#e74c3c', color: 'white', fontSize: '16px', fontWeight: 'bold' }}>
                {(payslip.take_home_pay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Account #</td>
            <td className="p-2">{ payslip.alcnum ?? payslip.accountnumber ?? '-' }</td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Branch</td>
            <td className="p-2">{ payslip.branch ?? '-' }</td>
            <td colSpan={3}></td>
        </tr>
    </>
)}

{/* ==================== PZ CUSSONS PAYSLIP ==================== */}
{isPz && (
    <>
        <tr>
            <td className="p-2 header">Name</td>
            <td className="p-2">{payslip.name}</td>
            <td className="p-2"></td>
            <td className="p-2 header">Department</td>
            <td className="p-2">{payslip.dept || '-'}</td>
        </tr>
        <tr>
            <td className="p-2 header">Employee ID</td>
            <td className="p-2">{payslip.staffid}</td>
            <td className="p-2"></td>
            <td className="p-2 header">Client Company</td>
            <td className="p-2">PZ CUSSONS</td>
        </tr>
        <tr>
            <td className="p-2 header">SSNIT #</td>
            <td className="p-2">{payslip.ssnit_number ?? payslip.SSNIT ?? payslip.ssnit ?? '-'}</td>
            <td colSpan={3} className="p-2"></td>
        </tr>
        <tr>
            <th colSpan={2} className="p-2 header" style={{ textAlign: 'center' }}>Item</th>
            <th className="p-2 header" style={{ textAlign: 'center' }}>Description/Days</th>
            <th className="p-2 header" style={{ textAlign: 'center' }}>Earnings</th>
            <th className="p-2 header" style={{ textAlign: 'center' }}>Deductions</th>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Basic Salary</td>
            <td className="p-2 text-center">-</td>
            <td className="p-2 text-center">{(payslip.basic ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Incentives</td>
            <td className="p-2 text-right"></td>
            <td className="p-2 text-center">{(payslip.incentive ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Bonus/Transport</td>
            <td className="p-2 text-right"></td>
            <td className="p-2 text-center">{(payslip.bonus ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Overtime / Incentives</td>
            <td className="p-2 text-right"></td>
            <td className="p-2 text-center">{(payslip.overtime ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Other</td>
            <td className="p-2 text-right"></td>
            <td className="p-2 text-center">{(payslip.other ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2 total">Gross Salary</td>
            <td></td>
            <td className="p-2 text-center total">{(payslip.totalearn ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Annual Bonus</td>
            <td></td>
            <td className="p-2 text-center">{(payslip['13month'] ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">SSNIT</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.ssnitee ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        {Number(payslip.petra ?? payslip.petra_5_percent ?? 0) > 0 && (
            <tr>
                <td colSpan={2} className="p-2">Petra (5%)</td>
                <td></td>
                <td></td>
                <td className="p-2 text-center">{(payslip.petra ?? payslip.petra_5_percent ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>
        )}
        <tr>
            <td colSpan={2} className="p-2">Annual Bonus Tax</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip['13tax'] ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Normal Tax-Regular</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.totaltaxdeduct ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2 total">Total</td>
            <td></td>
            <td className="p-2 text-center total">{((payslip.totalearn ?? 0) + (payslip['13month'] ?? 0)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td className="p-2 text-center total">{(payslip.totaldeduct ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td className="p-2 header">Bank Name</td>
            <td className="p-2">{payslip.bankname ?? payslip.bank ?? '-'}</td>
            <td></td>
            <td className="p-2 header">Account Name</td>
            <td className="p-2">{payslip.name ?? '-'}</td>
        </tr>
        <tr>
            <td className="p-2 header">Bank Account #</td>
            <td className="p-2">{payslip.alcnum ?? payslip.accountnumber ?? '-'}</td>
            <td></td>
            <td className="p-2 header">Branch</td>
            <td className="p-2">{payslip.branch ?? '-'}</td>
        </tr>
        <tr>
            <td colSpan={3}></td>
            <td className="p-2 net-pay" style={{ textAlign: 'center' }}>NET PAY</td>
            <td className="p-2 net-pay" style={{ textAlign: 'center' }}>{(payslip.netpay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>
    </>
)}
{/* ==================== TWELLIUM PAYSLIP ==================== */}
{isTwellium && (
    <>
        <tr>
            <td className="p-2">Name</td>
            <td className="p-2">{payslip.name}</td>
            <td className="p-2"></td>
            <td className="p-2">Title</td>
            <td className="p-2">{payslip.position ?? '-'}</td>
        </tr>
        <tr>
            <td className="p-2">Employee ID</td>
            <td className="p-2">{payslip.employ}</td>
            <td className="p-2"></td>
            <td className="p-2">Client Company</td>
            <td className="p-2">TWELLIUM</td>
        </tr>
        <tr>
            <td className="p-2">SSNIT #</td>
            <td className="p-2">{/* SSNIT from separate table */}</td>
            <td colSpan={3} className="p-2"></td>
        </tr>
        <tr>
            <td className="p-2">Basic Salary</td>
            <td className="p-2">{(payslip.basic ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <th colSpan={2} className="text-white text-center">Item</th>
            <th className="text-white text-center">Description/Days/Hours</th>
            <th className="text-white text-center">Earnings</th>
            <th className="text-white text-center">Deductions</th>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Basic Earned</td>
            <td className="p-2 text-right">{payslip.total_hours_worked ?? 0}</td>
            <td className="p-2 text-center">{(payslip.basic_earned ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Weekday Overtime</td>
            <td className="p-2 text-right">{payslip.weekday_overtime_hours ?? 0}</td>
            <td className="p-2 text-center">{(payslip.weekday_overtime_amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Offday/Holiday Overtime</td>
            <td className="p-2 text-right">{payslip.offday_holiday_overtime_hours ?? 0}</td>
            <td className="p-2 text-center">{(payslip.offday_holiday_overtime_total ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Special Day Overtime</td>
            <td className="p-2 text-right">{payslip.special_day_overtime_hours ?? 0}</td>
            <td className="p-2 text-center">{(payslip.special_day_overtime_amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Allowance</td>
            <td></td>
            <td className="p-2 text-center">{(payslip.allowance ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Risk Allowance</td>
            <td></td>
            <td className="p-2 text-center">{(payslip.risk_allowance ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
    <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
    <td></td>
    <td className="p-2 text-center font-bold">
        {(
            Number(payslip.basic_earned ?? 0) +
            Number(payslip.weekday_overtime_amount ?? 0) +
            Number(payslip.offday_holiday_overtime_total ?? 0) +
            Number(payslip.special_day_overtime_amount ?? 0) +
            Number(payslip.allowance ?? 0) +
            Number(payslip.risk_allowance ?? 0)
        ).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
    </td>
    <td></td>
</tr>
        <tr>
            <td colSpan={2} className="p-2">SSNIT</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.ssnit_ee ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        {Number(payslip.petra ?? payslip.petra_5_percent ?? 0) > 0 && (
            <tr>
                <td colSpan={2} className="p-2">Petra (5%)</td>
                <td></td>
                <td></td>
                <td className="p-2 text-center">{(payslip.petra ?? payslip.petra_5_percent ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            </tr>
        )}
        <tr>
            <td colSpan={2} className="p-2">Normal Tax-Regular</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.paye ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Overtime Tax</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.overtime_tax ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Other Deduction</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{(payslip.deduction ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr style={{ backgroundColor: '#3498db', color: 'white' }}>
    <td colSpan={2} className="p-2 font-bold">Total</td>
    <td></td>
    <td className="p-2 text-center font-bold">
        {(
            Number(payslip.basic_earned ?? 0) +
            Number(payslip.weekday_overtime_amount ?? 0) +
            Number(payslip.offday_holiday_overtime_total ?? 0) +
            Number(payslip.special_day_overtime_amount ?? 0) +
            Number(payslip.allowance ?? 0) +
            Number(payslip.risk_allowance ?? 0)
        ).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
    </td>
    <td className="p-2 text-center font-bold">
        {(payslip.total_deductions ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
    </td>
</tr>
        <tr>
            <td className="p-2 font-bold">Bank Name</td>
            <td className="p-2">{payslip.bankname ?? '-'}</td>
            <td></td>
            <td colSpan={2} className="p-2 text-center" style={{ backgroundColor: '#343434', color: 'white', fontWeight: 'bold' }}>NET PAY</td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Account Name</td>
            <td className="p-2">{payslip.name}</td>
            <td></td>
            <td colSpan={2} className="p-2 text-right" style={{ backgroundColor: '#e74c3c', color: 'white', fontSize: '16px', fontWeight: 'bold' }}>
                {(payslip.netpay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}
            </td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Account #</td>
            <td className="p-2">{payslip.alcnum ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Branch</td>
            <td className="p-2">{payslip.branch ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
    </>
)}

{/* ==================== TWELLIUM ACCRA PAYSLIP ==================== */}
{isTwelliumAccra && (
    <>
        <tr>
            <td className="p-2">Name</td>
            <td className="p-2">{payslip.name}</td>
            <td className="p-2"></td>
            <td className="p-2">Title</td>
            <td className="p-2">{payslip.position ?? '-'}</td>
        </tr>
        <tr>
            <td className="p-2">Employee ID</td>
            <td className="p-2">{payslip.staff_id}</td>
            <td className="p-2"></td>
            <td className="p-2">Client Company</td>
            <td className="p-2">TWELLIUM</td>
        </tr>
        <tr>
            <td className="p-2">Basic Salary</td>
            <td className="p-2">
                {Number(payslip.basic_salary ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <th colSpan={2} className="text-white text-center">Item</th>
            <th className="text-white text-center">Description/Days</th>
            <th className="text-white text-center">Earnings</th>
            <th className="text-white text-center">Deductions</th>
        </tr>

        {/* Basic Earned */}
        <tr>
            <td colSpan={2} className="p-2">Basic Earned</td>
            <td className="p-2 text-right">{payslip.days_worked ?? 0}</td>
            <td className="p-2 text-center">
                {Number(payslip.basic_earned ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td></td>
        </tr>

        {/* Absent Deduction */}
        <tr>
            <td colSpan={2} className="p-2">Absent Deduction</td>
            <td className="p-2 text-right">{payslip.absent_days ?? 0}</td>
            <td></td>
            <td className="p-2 text-center">
                {Number(payslip.absent_deduction ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
        </tr>

        {/* Gross Salary (shows only basic_earned) */}
        <tr>
            <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
            <td></td>
            <td className="p-2 text-center font-bold">
                {Number(payslip.basic_earned ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td></td>
        </tr>

        {/* Deductions */}
        <tr>
            <td colSpan={2} className="p-2">SSNIT</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">
                {Number(payslip.ssnit_ee ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
        </tr>
        {Number(payslip.petra ?? payslip.petra_5_percent ?? 0) > 0 && (
            <tr>
                <td colSpan={2} className="p-2">Petra (5%)</td>
                <td></td>
                <td></td>
                <td className="p-2 text-center">
                    {Number(payslip.petra ?? payslip.petra_5_percent ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </td>
            </tr>
        )}
        <tr>
            <td colSpan={2} className="p-2">Loan</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">
                {Number(payslip.deductions ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Normal Tax-Regular</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">
                {Number(payslip.paye ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
        </tr>

        {/* Overtime Items - AFTER deductions */}
        <tr>
            <td colSpan={2} className="p-2">Weekday Overtime</td>
            <td className="p-2 text-right">{payslip.weekday_hours ?? 0}</td>
            <td className="p-2 text-center">
                {Number(payslip.weekday_overtime_amt ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Saturday Overtime</td>
            <td className="p-2 text-right">{payslip.saturday_hours ?? 0}</td>
            <td className="p-2 text-center">
                {Number(payslip.saturday_overtime_amt ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Sunday/Holiday Overtime</td>
            <td className="p-2 text-right">{payslip.num_sundays_holidays ?? 0}</td>
            <td className="p-2 text-center">
                {Number(payslip.sunday_holiday_overtime_amt ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Overtime Refund</td>
            <td></td>
            <td className="p-2 text-center">
                {Number(payslip.overtimerefund ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td></td>
        </tr>

        {/* Total Row */}
        <tr style={{ backgroundColor: '#3498db', color: 'white' }}>
            <td colSpan={2} className="p-2 font-bold">Total</td>
            <td></td>
            <td className="p-2 text-center font-bold">
                {(
                    Number(payslip.basic_earned ?? 0) +
                    Number(payslip.total_overtime_amt ?? 0)
                ).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td className="p-2 text-center font-bold">
                {Number(payslip.total_deductions ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
        </tr>

        {/* Refund after Total */}
        <tr>
            <td colSpan={2} className="p-2">Refund</td>
            <td></td>
            <td className="p-2 text-center">
                {Number(payslip.refund ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td></td>
        </tr>

        {/* Net Pay Section - matches your layout */}
        <tr>
            <td colSpan={3}></td>
            <td colSpan={2} className="p-2 text-center" style={{ backgroundColor: '#343434', color: 'white', fontWeight: 'bold' }}>
                NET PAY
            </td>
        </tr>
        <tr>
            <td colSpan={3}></td>
            <td colSpan={2} className="p-2 text-center" style={{ backgroundColor: '#e74c3c', color: 'white', fontSize: '16px', fontWeight: 'bold' }}>
                {Number(payslip.take_home_pay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
        </tr>

        {/* Bank Details */}
        <tr>
            <td className="p-2 font-bold">Bank Name</td>
            <td className="p-2">{payslip.bankname ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Account Name</td>
            <td className="p-2">{payslip.name}</td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Account #</td>
            <td className="p-2">{payslip.alcnum ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Branch</td>
            <td className="p-2">{payslip.branch ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
    </>
)}

{/* ==================== SHEFER PAYSLIP ==================== */}
{isShefer && (
    <>
        <tr>
            <td className="p-2">Name</td>
            <td className="p-2">{payslip.name1}</td>
            <td className="p-2"></td>
            <td className="p-2">Title</td>
            <td className="p-2">{payslip.position ?? '-'}</td>
        </tr>
        <tr>
            <td className="p-2">Employee ID</td>
            <td className="p-2">{payslip.staff_id}</td>
            <td className="p-2"></td>
            <td className="p-2">Client Company</td>
            <td className="p-2">SHEFER</td>
        </tr>
        <tr>
            <td className="p-2">SSNIT #</td>
            <td className="p-2">{payslip.ssnit_number ?? payslip.SSNIT ?? payslip.ssnit ?? '-'}</td>
            <td className="p-2"></td>
            <td className="p-2">Department</td>
            <td className="p-2">{payslip.department ?? '-'}</td>
        </tr>
        <tr>
            <td className="p-2">Basic Salary</td>
            <td className="p-2">
                {Number(payslip.basic ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}
            </td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <th colSpan={2} className="text-white text-center">Item</th>
            <th className="text-white text-center">Description/Days/Hours</th>
            <th className="text-white text-center">Earnings</th>
            <th className="text-white text-center">Deductions</th>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Basic Entitled</td>
            <td className="p-2 text-right">{payslip.no_of_days_worked ?? 0}</td>
            <td className="p-2 text-center">{Number(payslip.basic_earned ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Absent Days</td>
            <td className="p-2 text-right">{payslip.absent_days ?? 0}</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.absent_without_pay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Weekday Overtime</td>
            <td className="p-2 text-right">{payslip.weekdayOT_hours ?? 0}</td>
            <td className="p-2 text-center">{Number(payslip.weekdayOT_amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Saturday Overtime</td>
            <td className="p-2 text-right">{payslip.weekendOT_hours ?? 0}</td>
            <td className="p-2 text-center">{Number(payslip.weekendOT_amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Sunday Overtime</td>
            <td className="p-2 text-right">{payslip.sunOT_hours ?? 0}</td>
            <td className="p-2 text-center">{Number(payslip.sunOT_amount ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Fixed Overtime</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.fixedot ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Food Allowance</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.food ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Airtime Allowance</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.airtime ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Medical Allowance</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.medical ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Responsibility Allowance</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.responseallo ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Transport Allowance</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.transport ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
            <td></td>
            <td className="p-2 text-center font-bold">{Number(payslip.gross ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">SSNIT</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.ssnit_5_5_percent ?? payslip.ssnitee ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        {Number(payslip.petra ?? payslip.petra_5_percent ?? 0) > 0 && (
            <tr>
                <td colSpan={2} className="p-2">Petra (5%)</td>
                <td></td>
                <td></td>
                <td className="p-2 text-center">{Number(payslip.petra ?? payslip.petra_5_percent ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            </tr>
        )}
        <tr>
            <td colSpan={2} className="p-2">Normal Tax-Regular</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.paye ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Overtime Tax</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.ot_tax ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Salary Loan</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.salaryloan ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr style={{ backgroundColor: '#3498db', color: 'white' }}>
            <td colSpan={2} className="p-2 font-bold">Total</td>
            <td></td>
            <td className="p-2 text-center font-bold">{Number(payslip.gross ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td className="p-2 text-center font-bold">{Number(payslip.total_deductions ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Name</td>
            <td className="p-2">{payslip.bankname ?? '-'}</td>
            <td></td>
            <td colSpan={2} className="p-2 text-center" style={{ backgroundColor: '#343434', color: 'white', fontWeight: 'bold' }}>NET PAY</td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Account Name</td>
            <td className="p-2">{payslip.name ?? '-'}</td>
            <td></td>
            <td colSpan={2} className="p-2 text-center" style={{ backgroundColor: '#e74c3c', color: 'white', fontSize: '16px', fontWeight: 'bold' }}>
                {Number(payslip.net_pay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}
            </td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Account #</td>
            <td className="p-2">{payslip.alcnum ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <td className="p-2 font-bold">Bank Branch</td>
            <td className="p-2">{payslip.branch ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
    </>
)}

{/* ==================== INHOUSE PAYSLIP ==================== */}
{isInhouse && (
    <>
        <tr>
            <td className="p-2">Name</td>
            <td className="p-2">{payslip.name}</td>
            <td className="p-2"></td>
            <td className="p-2">Title</td>
            <td className="p-2">{payslip.position ?? '-'}</td>
        </tr>
        <tr>
            <td className="p-2">Employee ID</td>
            <td className="p-2">{payslip.id}</td>
            <td className="p-2"></td>
            <td className="p-2">Client Company</td>
            <td className="p-2">SBP AFRICA LTD</td>
        </tr>
        <tr>
            <td className="p-2">SSNIT #</td>
            <td className="p-2">{payslip.ssnit ?? '-'}</td>
            <td className="p-2"></td>
            <td className="p-2">Department</td>
            <td className="p-2">-</td>
        </tr>
        <tr>
            <th colSpan={2} className="text-white text-center">Item</th>
            <th className="text-white text-center">Description/Days</th>
            <th className="text-white text-center">Earnings</th>
            <th className="text-white text-center">Deductions</th>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Basic Salary</td>
            <td className="p-2 text-right">{payslip.actualwork ?? 0}</td>
            <td className="p-2 text-center">{Number(payslip.actualbasic ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Monthly KPI</td>
            <td className="p-2 text-right">{payslip.coefficient ?? 0}</td>
            <td className="p-2 text-center">{Number(payslip.monthlykpi ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Commission</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.commission ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Overtime</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.overtime ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Transport Subsidy</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.transport ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
            <td></td>
            <td className="p-2 text-center font-bold">
                {(
                    Number(payslip.actualbasic ?? 0) +
                    Number(payslip.monthlykpi ?? 0) +
                    Number(payslip.commission ?? 0) +
                    Number(payslip.overtime ?? 0) +
                    Number(payslip.transport ?? 0)
                ).toLocaleString('en-GH', { minimumFractionDigits: 2 })}
            </td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">SSNIT</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.ssnitpay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">PAYE</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.totaltax ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Salary Loan</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip.loans ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Annual Bonus</td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip['13thmonth'] ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
            <td></td>
        </tr>
        <tr>
            <td colSpan={2} className="p-2">Annual Tax</td>
            <td></td>
            <td></td>
            <td className="p-2 text-center">{Number(payslip['13thtax'] ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}</td>
        </tr>
        <tr style={{ backgroundColor: '#3498db', color: 'white' }}>
            <td colSpan={3} className="p-2 font-bold">Total</td>
            <td className="p-2 text-center font-bold">
                {(
                    Number(payslip.actualbasic ?? 0) +
                    Number(payslip.monthlykpi ?? 0) +
                    Number(payslip.commission ?? 0) +
                    Number(payslip.overtime ?? 0) +
                    Number(payslip.transport ?? 0) +
                    Number(payslip['13thmonth'] ?? 0)
                ).toLocaleString('en-GH', { minimumFractionDigits: 2 })}
            </td>
            <td className="p-2 text-center font-bold">
                {Number(payslip.totaldeduction ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}
            </td>
        </tr>
        <tr>
            <td className="p-2">Bank Name</td>
            <td className="p-2">{payslip.bankname ?? '-'}</td>
            <td></td>
            <td colSpan={2} className="p-2 text-center" style={{ backgroundColor: '#343434', color: 'white', fontWeight: 'bold' }}>NET PAY</td>
        </tr>
        <tr>
            <td className="p-2">Bank Account Name</td>
            <td className="p-2">{payslip.name}</td>
            <td></td>
            <td colSpan={2} className="p-2 text-center" style={{ backgroundColor: '#e74c3c', color: 'white', fontSize: '16px', fontWeight: 'bold' }}>
                {Number(payslip.netpay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2 })}
            </td>
        </tr>
        <tr>
            <td className="p-2">Bank Account #</td>
            <td className="p-2">{payslip.alcnum ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
        <tr>
            <td className="p-2">Bank Branch</td>
            <td className="p-2">{payslip.branch ?? '-'}</td>
            <td colSpan={3}></td>
        </tr>
    </>
)}


                        {/* ==================== TRANSSION PAYSLIP ==================== */}
                        {!isMelcom && !isAvara && !isHonda && !isJaykay && !isPz && !isTwellium && !isTwelliumAccra && !isShefer && !isInhouse && !isOfiExternal && !isOfiHygiene && !isOfiTempering && !isOfiBwh && (
                            <>
                                <tr>
                                    <td className="p-2">Name</td>
                                    <td className="p-2">{payslip.name}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Title</td>
                                    <td className="p-2">{payslip.position}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Employee ID</td>
                                    <td className="p-2">{payslip.staffida}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Client Company</td>
                                    <td className="p-2">{payslip.brandname}</td>
                                </tr>
                                <tr>
                                    <td className="p-2">SSNIT #</td>
                                    <td className="p-2">{payslip.ssnit_number || payslip.SSNIT || payslip.ssnit12 || payslip.ssnit || '-'}</td>
                                    <td className="p-2"></td>
                                    <td className="p-2">Department</td>
                                    <td className="p-2">{payslip.department || payslip.dept || payslip.workarea || payslip.work_location || '-'}</td>
                                </tr>
                                <tr>
                                    <th colSpan={2} className="text-white text-center">Item</th>
                                    <th className="text-white text-center">Description/Days/Hours</th>
                                    <th className="text-white text-center">Earnings</th>
                                    <th className="text-white text-center">Deductions</th>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Basic Salary</td>
                                    <td className="p-2 text-right">{payslip.workdays ?? '-'}</td>
                                    <td className="p-2 text-center">{(payslip.basic_earned ?? payslip.basic ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                {Number(payslip.fuel_and_rent_allowance ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Fuel & Rent Allowance</td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.fuel_and_rent_allowance)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.transport ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Transport Allowance</td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.transport)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.nightallowance ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Night Allowance</td>
                                        <td className="p-2 text-right">{payslip.night ?? '-'}</td>
                                        <td className="p-2 text-center">{(Number(payslip.nightallowance)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.medical ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Medical Allowance</td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.medical)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.leave1 ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Leave Allowance</td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.leave1)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.general_allowance ?? 0) > 0 && Number(payslip.fuel_and_rent_allowance ?? 0) === 0 && Number(payslip.transport ?? 0) === 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Allowance</td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.general_allowance)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.allowance ?? 0) > 0 && Number(payslip.fuel_and_rent_allowance ?? 0) === 0 && Number(payslip.transport ?? 0) === 0 && Number(payslip.nightallowance ?? 0) === 0 && Number(payslip.medical ?? 0) === 0 && Number(payslip.leave1 ?? 0) === 0 && Number(payslip.general_allowance ?? 0) === 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Allowance</td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.allowance)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                <tr>
                                    <td colSpan={2} className="p-2">Performance Bonus</td>
                                    <td className="p-2 text-right">{payslip.coefficient ?? '-'}</td>
                                    <td className="p-2 text-center">{(payslip.performstand ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Overtime Weekday</td>
                                    <td className="p-2 text-right">{payslip.weekdaysOT ?? payslip.normalOThrs ?? payslip.otworkdays ?? '-'}</td>
                                    <td className="p-2 text-center">{(payslip.normalovertime ?? payslip.otworkingday ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Overtime Weekend/Holiday</td>
                                    <td className="p-2 text-right">{payslip.weekendOT ?? payslip.weekendOThrs ?? payslip.otweekend ?? '-'}</td>
                                    <td className="p-2 text-center">{(payslip.saturdayovertime ?? payslip.otholiday ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Other Income</td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.other ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Commission</td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.commission ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                {Number(payslip.quarterlybonus ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Quarterly Bonus</td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.quarterlybonus)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                {Number(payslip.endcontractrights ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">End of Contract Rights</td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.endcontractrights)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td></td>
                                    </tr>
                                )}
                                <tr>
                                    <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
                                    <td></td>
                                    <td className="p-2 text-center font-bold">{(payslip.grosspay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Annual Bonus</td>
                                    <td></td>
                                    <td className="p-2 text-center font-bold">{(payslip.annaulbonus ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">SSNIT</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.ssnitee ?? (payslip.basic ?? 0) * 0.055).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Normal Tax-Regular</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.paye ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                {Number(payslip.petra ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Petra</td>
                                        <td></td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.petra)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    </tr>
                                )}
                                {Number(payslip.employeepf ?? payslip.provident_fund ?? 0) > 0 && (
                                    <tr>
                                        <td colSpan={2} className="p-2">Provident Fund</td>
                                        <td></td>
                                        <td></td>
                                        <td className="p-2 text-center">{(Number(payslip.employeepf ?? payslip.provident_fund)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    </tr>
                                )}
                                <tr>
                                    <td colSpan={2} className="p-2">Leave</td>
                                    <td className="p-2 text-center">{payslip.leavedays ?? '-'}</td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.leavededuction ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Other Deduction</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.otherdeduction ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Loan</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.loan ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={2} className="p-2">Annual Tax</td>
                                    <td></td>
                                    <td></td>
                                    <td className="p-2 text-center">{(payslip.annaultax ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                </tr>
                                <tr>
                                    <td colSpan={3} className="p-2 total">Total</td>
                                    <td className="p-2 text-center total">{((payslip.grosspay ?? 0) + (payslip.annaulbonus ?? 0)).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className="p-2 text-center total">
                                        {(Number(payslip.totaldeductions ?? (
                                            (payslip.ssnitee ?? (payslip.basic ?? 0) * 0.055) +
                                            (payslip.paye ?? 0) +
                                            (payslip.petra ?? 0) +
                                            (payslip.employeepf ?? payslip.provident_fund ?? 0) +
                                            (payslip.leavededuction ?? 0) +
                                            (payslip.otherdeduction ?? 0) +
                                            (payslip.loan ?? 0) +
                                            (payslip.annaultax ?? 0)
                                        ))).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Name</td>
                                    <td className="p-2">{ payslip.bankname || payslip.bank || payslip.bank1 || '-' }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 net-pay">NET PAY</td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account Name</td>
                                    <td className="p-2">{ payslip.name }</td>
                                    <td></td>
                                    <td colSpan={2} className="p-2 text-center net-pay">
                                        {(payslip.netpay ?? 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Account #</td>
                                    <td className="p-2">{ payslip.alcnum || payslip.accountnumber || payslip.account || payslip.account1 || '-' }</td>
                                    <td colSpan={3}></td>
                                </tr>
                                <tr>
                                    <td className="p-2">Bank Branch</td>
                                    <td className="p-2">{ payslip.branch || payslip.branch1 || '-' }</td>
                                    <td colSpan={3}></td>
                                </tr>
                            </>
                        )}


                    </tbody>
                </table>

                <div className="mt-4 flex justify-end">
                    <Button onClick={handleDownloadPDF} disabled={isDownloading}>
                        <Download className="h-4 w-4 mr-2" />
                        {isDownloading ? "Downloading..." : "Download PDF"}
                    </Button>
                </div>
            </div>
        )}
</DialogContent>
</Dialog>
                </div>
            </AppLayout>
        </ErrorBoundary>
    );
}
