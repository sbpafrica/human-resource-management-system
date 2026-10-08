import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Download, Send, CheckCircle, XCircle } from "lucide-react";
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import ErrorBoundary from "@/components/ErrorBoundary";

// CSS (unchanged)
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
  .payslip-table .text-xl {
    font-size: 16px;
    font-weight: bold;
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
    .payslip-modal {
      padding: 4px;
    }
    .payslip-table {
      font-size: 10px;
    }
    .payslip-table th, .payslip-table td {
      padding: 4px;
    }
    .payslip-table img {
      width: 100px;
    }
    .payslip-table .text-xl, .payslip-table .text-3xl {
      font-size: 12px;
    }
  }
`;

interface Payslip {
    staffida: string;
    name: string;
    department: string;
    position: string;
    brandname: string;
    ssnit: string;
    workdays: number;
    basic: number;
    coefficient: number;
    performstand: number;
    allowance: number;
    otworkingday: number;
    otholiday: number;
    normalovertime?: number;
    saturdayovertime?: number;
    normalOThrs?: number;
    weekdaysOT?: number;
    weekendOThrs?: number;
    weekendOT?: number;
    other: number;
    commission: number;
    grosspay: number;
    annaulbonus: number;
    leavedays: number;
    leavededuction: number;
    otherdeduction: number;
    loan: number;
    paye: number;
    annaultax: number;
    netpay: number;
    bankname: string;
    alcnum: string;
    branch: string;
    email: string;
    phone: string;
    ssnit_contribution: number;
    total_earnings: number;
    total_deductions: number;
}

interface Employee {
    staffida: string;
    name: string;
}

interface Props {
    payslip?: Payslip;
    month: string;
    formattedMonth: string;
    employees: Employee[];
    flash?: {
        success?: string;
        error?: string;
    };
    errors?: {
        [key: string]: string;
    };
}

const breadcrumbs = [
    { title: "Payslips", href: "/payslips/transsion" },
    { title: "Good Ease", href: "/payslips/transsion" },
];

export default function Transsion({ payslip, month, formattedMonth, employees, flash, errors }: Props) {
    const [isViewDialogOpen, setIsViewDialogOpen] = useState(!!payslip);
    const [sendType, setSendType] = useState<"sms" | "email">("sms");
    const [staffId, setStaffId] = useState("");
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedEmployees, setSelectedEmployees] = useState<string[]>([]);
    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, setToastType] = useState<"success" | "error">("success");
    const [isDownloading, setIsDownloading] = useState(false);
    const [isDownloadingMultiple, setIsDownloadingMultiple] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        month,
        staff_id: "",
        staff_ids: [] as string[],
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
        } else if (errors && Object.keys(errors).length > 0) {
            const errorMessage = Object.values(errors).join(" | ");
            setToastMessage(errorMessage);
            setToastType("error");
            setShowToast(true);
        }
    }, [flash, errors]);

    useEffect(() => {
        if (showToast) {
            const timeout = toastType === "error" ? 60000 : 3000;
            const timer = setTimeout(() => setShowToast(false), timeout);
            return () => clearTimeout(timer);
        }
    }, [showToast, toastType]);

    const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData("month", e.target.value);
        setStaffId("");
        setData("staff_id", "");
        setSelectedEmployees([]);
        setData("staff_ids", []);
        router.get(
            route("payslips.transsion.index"),
            { month: e.target.value },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleStaffIdChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setStaffId(e.target.value);
        setData("staff_id", e.target.value);
    };

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!data.month) {
            setToastMessage("Please select a month.");
            setToastType("error");
            setShowToast(true);
            return;
        }
        if (!staffId.trim()) {
            setToastMessage("Please enter a Staff ID.");
            setToastType("error");
            setShowToast(true);
            return;
        }
        router.get(
            route("payslips.transsion.view", { month: data.month, staffida: staffId.trim() }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setIsViewDialogOpen(true);
                },
                onError: (errors) => {
                    console.error("View Payslip errors:", errors);
                    setToastMessage(
                        errors.month ||
                        errors.staffida ||
                        "Failed to load payslip. Please check the Staff ID and try again."
                    );
                    setToastType("error");
                    setShowToast(true);
                },
            }
        );
    };

    const handleSendPayslips = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        e.stopPropagation();

        if (selectedEmployees.length === 0) {
            setToastMessage("Please select at least one employee.");
            setToastType("error");
            setShowToast(true);
            return;
        }

        const payload = {
            month: data.month,
            staff_ids: selectedEmployees,
        };

        post(route(`payslips.transsion.send-${sendType}`), {
            data: payload,
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setToastMessage(`Payslips sent successfully via ${sendType}.`);
                setToastType("success");
                setShowToast(true);
                setSelectedEmployees([]);
                setSearchQuery("");
                setData("staff_ids", []);
                reset();
            },
            onError: (errors) => {
                console.error("Send Payslip errors:", errors);
                setToastMessage(
                    errors.staff_ids ||
                    errors.month ||
                    `Failed to send payslips via ${sendType}. Please try again.`
                );
                setToastType("error");
                setShowToast(true);
            },
        });
    };

    const handleSelectEmployee = (staffId: string) => {
        setSelectedEmployees((prev) => {
            const newSelection = prev.includes(staffId)
                ? prev.filter((id) => id !== staffId)
                : [...prev, staffId];
            setData("staff_ids", newSelection);
            return newSelection;
        });
    };

    const handleSelectAll = () => {
        if (selectedEmployees.length === filteredEmployees.length) {
            setSelectedEmployees([]);
            setData("staff_ids", []);
        } else {
            const newSelection = filteredEmployees.map((emp) => emp.staffida);
            setSelectedEmployees(newSelection);
            setData("staff_ids", newSelection);
        }
    };

    const handleDownloadPDF = async () => {
        if (!payslip || !data.month) {
            setToastMessage("No payslip or month selected.");
            setToastType("error");
            setShowToast(true);
            return;
        }
        setIsDownloading(true);
        try {
            const response = await fetch(
                route("payslips.transsion.download", { month: data.month, staffida: payslip.staffida }),
                { credentials: "include" }
            );
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || "Failed to download PDF");
            }
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `payslip_${payslip.staffida}_${data.month}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch (error: any) {
            console.error("PDF download error:", error);
            setToastMessage(error.message || "Failed to download PDF");
            setToastType("error");
            setShowToast(true);
        } finally {
            setIsDownloading(false);
        }
    };

    const handleDownloadMultiplePDFs = async () => {
        if (!data.month || selectedEmployees.length === 0) {
            setToastMessage("Please select a month and at least one employee.");
            setToastType("error");
            setShowToast(true);
            return;
        }
        setIsDownloadingMultiple(true);
        try {
            const fetchWithRetry = async (url: string, options: RequestInit, maxRetries = 3, delayMs = 1200): Promise<Response> => {
                let lastError: any = null;
                for (let attempt = 1; attempt <= maxRetries; attempt++) {
                    try {
                        const res = await fetch(url, options);
                        if (res.ok) return res;
                        if ([502, 503, 504, 429].includes(res.status) && attempt < maxRetries) {
                            await new Promise(r => setTimeout(r, delayMs));
                            continue;
                        }
                        return res;
                    } catch (err: any) {
                        lastError = err;
                        if (attempt < maxRetries) {
                            await new Promise(r => setTimeout(r, delayMs));
                        }
                    }
                }
                throw lastError || new Error("Network request failed after multiple retries");
            };

            const response = await fetchWithRetry(route("payslips.transsion.download-multiple"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
                },
                credentials: "include",
                body: JSON.stringify({
                    month: data.month,
                    staff_ids: selectedEmployees,
                }),
            });
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || "Failed to download payslips");
            }
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `payslips_${data.month}.zip`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch (error: any) {
            console.error("Multiple PDF download error:", error);
            setToastMessage(error.message || "Failed to download payslips");
            setToastType("error");
            setShowToast(true);
        } finally {
            setIsDownloadingMultiple(false);
        }
    };

    const filteredEmployees = employees.filter(
        (emp) =>
            emp.staffida.toLowerCase().includes(searchQuery.toLowerCase()) ||
            emp.name.toLowerCase().includes(searchQuery.toLowerCase())
    );

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Good Ease Payslips" />
                <style>{styles}</style>
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
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h1 className="text-2xl font-bold">Good Ease Payslips</h1>
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
                    {/* Send Payslips Form */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Send Payslips</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSendPayslips} className="grid gap-4">
                                <div className="flex flex-col sm:flex-row items-center gap-4">
                                    <div className="flex items-center gap-2">
                                        <Label>Send Via:</Label>
                                        <Select
                                            value={sendType}
                                            onValueChange={(value: "sms" | "email") => setSendType(value)}
                                        >
                                            <SelectTrigger className="w-32">
                                                <SelectValue placeholder="Select Method" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="sms">SMS</SelectItem>
                                                <SelectItem value="email">Email</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex items-center gap-2 w-full">
                                        <Label htmlFor="search">Search Employees:</Label>
                                        <Input
                                            id="search"
                                            type="text"
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            placeholder='Search by Staff ID or Name'
                                            className="w-full"
                                        />
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleSelectAll}
                                        disabled={filteredEmployees.length === 0}
                                    >
                                        {selectedEmployees.length === filteredEmployees.length
                                            ? "Deselect All"
                                            : "Select All"}
                                    </Button>
                                    <div className="text-sm text-gray-600">
                                        {selectedEmployees.length} employee(s) selected
                                    </div>
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
                                            {filteredEmployees.length > 0 ? (
                                                filteredEmployees.map((emp) => (
                                                    <TableRow key={emp.staffida}>
                                                        <TableCell>
                                                            <Input
                                                                type="checkbox"
                                                                checked={selectedEmployees.includes(emp.staffida)}
                                                                onChange={() => handleSelectEmployee(emp.staffida)}
                                                            />
                                                        </TableCell>
                                                        <TableCell>{emp.staffida}</TableCell>
                                                        <TableCell>{emp.name}</TableCell>
                                                    </TableRow>
                                                ))
                                            ) : (
                                                <TableRow>
                                                    <TableCell colSpan={3} className="text-center">
                                                        No employees found.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="submit"
                                        disabled={processing || selectedEmployees.length === 0}
                                        className="bg-green-500 hover:bg-green-600"
                                    >
                                        <Send className="h-4 w-4 mr-2" />
                                        Send
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={handleDownloadMultiplePDFs}
                                        disabled={isDownloadingMultiple || processing || selectedEmployees.length === 0}
                                        className={`bg-blue-500 hover:bg-blue-600 text-white flex items-center ${
                                            isDownloadingMultiple || processing || selectedEmployees.length === 0
                                                ? "opacity-50 cursor-not-allowed"
                                                : ""
                                        }`}
                                    >
                                        <Download className="h-4 w-4 mr-2" />
                                        {isDownloadingMultiple ? "Downloading..." : "Download PDFs"}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                    {/* View Payslip Modal (unchanged) */}
                    <Dialog open={isViewDialogOpen} onOpenChange={setIsViewDialogOpen}>
                        <DialogContent className="payslip-modal sm:max-w-2xl w-[95vw]">
                            <DialogHeader>
                                <DialogTitle>
                                    {payslip ? `Payslip for ${payslip.name} - ${formattedMonth}` : "No Payslip Selected"}
                                </DialogTitle>
                            </DialogHeader>
                            {payslip ? (
                                <div className="payslip-table-container">
                                    <table className="payslip-table">
                                        <thead>
                                            <tr>
                                                <td colSpan={2} className="p-2">
                                                    <img src="/images/sbpafricalogo.png" alt="Company Logo" />
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
                                                <td className="text-center p-2">{formattedMonth}</td>
                                                <td colSpan={2} className="p-2"></td>
                                            </tr>
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
                                                <td className="p-2">{payslip.ssnit}</td>
                                                <td className="p-2"></td>
                                                <td className="p-2">Department</td>
                                                <td className="p-2">{payslip.department}</td>
                                            </tr>
                                            <tr>
                                                <th colSpan={2} className="text-white text-center">Item</th>
                                                <th className="text-white text-center">Description/Days/Hours</th>
                                                <th className="text-white text-center">Earnings</th>
                                                <th className="text-white text-center">Deductions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colSpan={2} className="p-2">Basic Salary</td>
                                                <td className="p-2 text-right">{payslip.workdays ?? 0}</td>
                                                <td className="p-2 text-center">{payslip.basic?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Performance Bonus</td>
                                                <td className="p-2 text-right">{payslip.coefficient ?? 0}</td>
                                                <td className="p-2 text-center">{payslip.performstand?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Allowance</td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.allowance?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Overtime Weekday</td>
                                                <td className="p-2 text-right">{payslip.otworkdays ?? payslip.normalOThrs ?? payslip.weekdaysOT ?? 0}</td>
                                                <td className="p-2 text-center">{(payslip.otworkingday ?? payslip.normalovertime)?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Overtime Weekend/Holiday</td>
                                                <td className="p-2 text-right">{payslip.otweekend ?? payslip.weekendOThrs ?? payslip.weekendOT ?? 0}</td>
                                                <td className="p-2 text-center">{(payslip.otholiday ?? payslip.saturdayovertime)?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Other Income</td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.other?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Commission</td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.commission?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2 font-bold">Gross Salary</td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center font-bold">{payslip.grosspay?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Annual Bonus</td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center font-bold">{payslip.annaulbonus?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">SSNIT</td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.ssnit_contribution?.toFixed(2) ?? "0.00"}</td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Normal Tax-Regular</td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.paye?.toFixed(2) ?? "0.00"}</td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Leave</td>
                                                <td className="p-2 text-center">{payslip.leavedays ?? 0}</td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.leavededuction?.toFixed(2) ?? "0.00"}</td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Other Deduction</td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.otherdeduction?.toFixed(2) ?? "0.00"}</td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Loan</td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.loan?.toFixed(2) ?? "0.00"}</td>
                                            </tr>
                                            <tr>
                                                <td colSpan={2} className="p-2">Annual Tax</td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                                <td className="p-2 text-center">{payslip.annaultax?.toFixed(2) ?? "0.00"}</td>
                                            </tr>
                                            <tr>
                                                <td colSpan={3} className="p-2 total">Total</td>
                                                <td className="p-2 text-center total">{payslip.total_earnings?.toFixed(2) ?? "0.00"}</td>
                                                <td className="p-2 text-center total">{payslip.total_deductions?.toFixed(2) ?? "0.00"}</td>
                                            </tr>
                                            <tr>
                                                <td className="p-2">Bank Name</td>
                                                <td className="p-2">{payslip.bankname}</td>
                                                <td className="p-2"></td>
                                                <td colSpan={2} className="p-2 net-pay">NET PAY</td>
                                            </tr>
                                            <tr>
                                                <td className="p-2">Bank Account Name</td>
                                                <td className="p-2">{payslip.name}</td>
                                                <td className="p-2"></td>
                                                <td colSpan={2} className="p-2 text-center net-pay">{payslip.netpay?.toFixed(2) ?? "0.00"}</td>
                                            </tr>
                                            <tr>
                                                <td className="p-2">Bank Account #</td>
                                                <td className="p-2">{payslip.alcnum}</td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                            </tr>
                                            <tr>
                                                <td className="p-2">Bank Branch</td>
                                                <td className="p-2">{payslip.branch}</td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                                <td className="p-2"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div className="mt-4 flex justify-end">
                                        <Button
                                            onClick={handleDownloadPDF}
                                            disabled={isDownloading || !payslip}
                                            className={`bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded flex items-center ${
                                                isDownloading ? "opacity-50 cursor-not-allowed" : ""
                                            }`}
                                        >
                                            <Download className="h-4 w-4 mr-2" />
                                            {isDownloading ? "Downloading..." : "Download PDF"}
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-center text-gray-500">No payslip data available.</p>
                            )}
                        </DialogContent>
                    </Dialog>
                </div>
            </AppLayout>
        </ErrorBoundary>
    );
}
