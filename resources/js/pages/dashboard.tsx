import { Head } from "@inertiajs/react";
import { AlertCircle, Users, FileText, CreditCard, Shield, Calendar, UserX, ArrowUp, ArrowDown, Briefcase, Cake, Phone, } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useState, useEffect, useMemo } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";
import * as Dialog from "@radix-ui/react-dialog";
import { Cross2Icon } from "@radix-ui/react-icons";
import { ColumnDef, createColumnHelper, getCoreRowModel, useReactTable } from "@tanstack/react-table";
import Papa from "papaparse";
import pdfMake from "pdfmake/build/pdfmake";
import pdfFonts from "pdfmake/build/vfs_fonts";
import { Line } from "react-chartjs-2";
import { Chart, registerables } from "chart.js";

Chart.register(...registerables);
pdfMake.vfs = pdfFonts;

interface EmployeeItem {
    staffid: string;
    employeename: string;
    company: string;
    startdate?: string;
    enddate?: string;
}

interface ClientHeadcountItem {
    client_name: string;
    count: number;
    employees: EmployeeItem[];
}

interface ClientSummaryItem {
    client_name: string;
    count: number;
    table: string;
    employees?: EmployeeItem[];
}

interface ComplianceData {
    totalEmployees?: { count: number; change: number; list: EmployeeItem[] };
    staffOnPayroll?: { count: number; change: number; list: EmployeeItem[] };
    newHires?: { count: number; change: number; list: EmployeeItem[] };
    resignedTerminated?: { count: number; change: number; list: EmployeeItem[] };
    withoutBank: { count: number; list: EmployeeItem[] };
    withoutSsnit: { count: number; list: EmployeeItem[] };
    withoutPetra: { count: number; list: EmployeeItem[] };
    withoutGhanaCard: { count: number; list: EmployeeItem[] };
    incompleteProfiles: { count: number; list: EmployeeItem[] };
    pendingDocuments: { count: number; list: EmployeeItem[] };
    upcomingBirthdays: { count: number; list: EmployeeItem[] };
    employeesOnLeave?: { count: number; list: EmployeeItem[] };
    pendingLeaveRequests?: { count: number; list: EmployeeItem[] };
    expiringContracts?: { count: number; list: EmployeeItem[] };
    renewalExpiringContracts?: { count: number; list: EmployeeItem[] };
     renewalExpiredContracts?: { count: number; list: EmployeeItem[] };
      renewalUnsignedContracts?: { count: number; list: EmployeeItem[] };
expiredContracts?: { count: number; list: EmployeeItem[] };
unsignedContracts?: { count: number; list: EmployeeItem[] };
noContactDetails?: { count: number; list: EmployeeItem[] };
    trend?: {
        labels: string[];
        totalEmployees: number[];
        newHires: number[];
        resigned: number[];
    };
}

interface Props {
    complianceData?: Partial<ComplianceData>;
    clientSummary?: ClientSummaryItem[];
    allClientHeadcount?: ClientHeadcountItem[];
    auth: { user: { name: string; is_super_admin?: boolean } };
    flash?: { success?: string; error?: string };
}

const breadcrumbs = [{ title: "Dashboard", href: "/dashboard" }];
const columnHelper = createColumnHelper<EmployeeItem>();

export default function Dashboard({
    complianceData: partialComplianceData = {},
    clientSummary = [],
    allClientHeadcount = [],
    auth,
    flash,
}: Props) {
    const complianceData: ComplianceData = {
        totalEmployees: { count: 0, change: 0, list: [] },
        staffOnPayroll: { count: 0, change: 0, list: [] },
        newHires: { count: 0, change: 0, list: [] },
        resignedTerminated: { count: 0, change: 0, list: [] },
        withoutBank: { count: 0, list: [] },
        withoutSsnit: { count: 0, list: [] },
        withoutPetra: { count: 0, list: [] },
        withoutGhanaCard: { count: 0, list: [] },
        incompleteProfiles: { count: 0, list: [] },
        pendingDocuments: { count: 0, list: [] },
renewalExpiringContracts: { count: 0, list: [] },
renewalExpiredContracts: { count: 0, list: [] },
renewalUnsignedContracts: { count: 0, list: [] },
        upcomingBirthdays: { count: 0, list: [] },
        employeesOnLeave: { count: 0, list: [] },
        expiringContracts: { count: 0, list: [] },
expiredContracts: { count: 0, list: [] },
unsignedContracts: { count: 0, list: [] },
noContactDetails: { count: 0, list: [] },
        pendingLeaveRequests: { count: 0, list: [] },
        trend: { labels: [], totalEmployees: [], newHires: [], resigned: [] },
        ...partialComplianceData,
    };

    const [showToast, setShowToast] = useState(false);
    const [toastMessage, setToastMessage] = useState("");
    const [toastType, setToastType] = useState<"success" | "error">("success");
    const [modalData, setModalData] = useState<{
        title: string;
        items: EmployeeItem[];
        open: boolean;
    }>({ title: "", items: [], open: false });

    useEffect(() => {
        if (typeof window !== "undefined") {
            (window as any).pdfMake = pdfMake;
            (window as any).pdfMake.vfs = pdfFonts;
        }
    }, []);

    useEffect(() => {
        if (flash?.success || flash?.error) {
            setToastMessage(flash.success || flash.error || "");
            setToastType(flash.success ? "success" : "error");
            setShowToast(true);
        }
    }, [flash]);

    useEffect(() => {
        if (showToast) {
            const timer = setTimeout(() => setShowToast(false), toastType === "error" ? 8000 : 4000);
            return () => clearTimeout(timer);
        }
    }, [showToast, toastType]);

    {/* DYNAMIC COLUMNS FOR MODAL TABLE */}
const columns: ColumnDef<EmployeeItem>[] = useMemo(() => {
    const baseColumns = [
        columnHelper.accessor("staffid", {
            header: "StaffID",
            cell: info => <span className="font-mono text-sm">{info.getValue()}</span>,
        }),
        columnHelper.accessor("employeename", {
            header: "Employee",
            cell: info => (
                <a
                    href={`/employees/${info.row.original.staffid}/edit`}
                    className="text-blue-600 hover:underline font-medium"
                >
                    {info.getValue() || "Unknown"}
                </a>
            ),
        }),
        columnHelper.accessor("company", {
            header: "Company",
            cell: info => info.getValue() || "N/A",
        }),
    ];

    const isExpiringOrExpired = modalData.title.includes("Contracts Expiring Soon") ||
                                modalData.title.includes("Expired Contracts");

    const isUnsigned = modalData.title.includes("Unsigned Contracts");

    if (isExpiringOrExpired) {
        baseColumns.push(
            columnHelper.accessor("enddate", {
                header: "End Date",
                cell: info => info.getValue() ? new Date(info.getValue()).toLocaleDateString() : "N/A",
            })
        );
    }

    if (isUnsigned) {
        baseColumns.push(
            columnHelper.accessor("startdate", {
                header: "Start Date",
                cell: info => info.getValue() ? new Date(info.getValue()).toLocaleDateString() : "N/A",
            })
        );
    }

    return baseColumns;
}, [modalData.title]);

    const table = useReactTable({
        data: modalData.items,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    const openModal = (title: string, items: EmployeeItem[]) => {
        setModalData({ title, items, open: true });
    };

    const closeModal = () => {
        setModalData({ title: "", items: [], open: false });
    };

    const handleExportCSV = () => {
        const headers = ["StaffID", "Employee", "Company"];
        const rows = modalData.items.map(item => [
            item.staffid,
            item.employeename || "Unknown",
            item.company || "N/A"
        ]);

        const csv = Papa.unparse([headers, ...rows]);
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `${modalData.title.replace(/\s+/g, "_").toLowerCase()}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    const handleExportPDF = () => {
        const docDefinition: any = {
            pageSize: "A4",
            pageOrientation: "landscape",
            content: [
                { text: modalData.title, style: "header", margin: [0, 0, 0, 20] },
                {
                    table: {
                        headerRows: 1,
                        widths: ["auto", "*", "*"],
                        body: [
                            ["StaffID", "Employee", "Company"],
                            ...modalData.items.map(item => [
                                item.staffid,
                                item.employeename || "Unknown",
                                item.company || "N/A"
                            ])
                        ]
                    }
                }
            ],
            styles: {
                header: { fontSize: 18, bold: true }
            }
        };

        pdfMake.createPdf(docDefinition).download(`${modalData.title.replace(/\s+/g, "_").toLowerCase()}.pdf`);
    };

    // AUTOMATIC LAST 3 MONTHS CHART
    const chartData = {
        labels: complianceData.trend?.labels?.length ? complianceData.trend.labels : ["No Data"],
        datasets: [
            {
                label: "Total Employees",
                data: complianceData.trend?.totalEmployees ?? [0],
                borderColor: "rgb(59, 130, 246)",
                backgroundColor: "rgba(59, 130, 246, 0.1)",
                tension: 0.4,
            },
            {
                label: "New Hires",
                data: complianceData.trend?.newHires ?? [0],
                borderColor: "rgb(34, 197, 94)",
                backgroundColor: "rgba(34, 197, 94, 0.1)",
                tension: 0.4,
            },
            {
                label: "Resigned/Terminated",
                data: complianceData.trend?.resigned ?? [0],
                borderColor: "rgb(239, 68, 68)",
                backgroundColor: "rgba(239, 68, 68, 0.1)",
                tension: 0.4,
            },
        ],
    };

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: "top" as const } },
        scales: { y: { beginAtZero: true } },
    };

    const StatCard = ({ icon: Icon, title, count, change, list, textColor }: any) => (
        <Card className="shadow-lg hover:shadow-xl transition-shadow">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-sm font-semibold flex items-center gap-2">
                        <Icon className="h-5 w-5 text-blue-600" />
                        {title}
                    </CardTitle>
                </div>
            </CardHeader>
            <CardContent>
                <div className="flex items-center justify-between">
                    <span className={`text-3xl font-bold ${textColor}`}>{count}</span>
                    <div className="flex items-center gap-2">
                        {change !== 0 && (
                            <div className="flex items-center gap-1">
                                {change > 0 ? <ArrowUp className="h-5 w-5 text-green-600" /> : <ArrowDown className="h-5 w-5 text-red-600" />}
                                <span className={`text-sm font-medium ${change > 0 ? "text-green-600" : "text-red-600"}`}>
                                    {Math.abs(change)}%
                                </span>
                            </div>
                        )}
                        {list.length > 0 && (
                            <Button variant="ghost" size="icon" onClick={() => openModal(title, list)}>
                                <Users className="h-5 w-5 text-blue-600" />
                            </Button>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );

    return (
        <ErrorBoundary>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Compliance Dashboard" />

                <div className="p-6 space-y-8">
                    {showToast && (
                        <div className={`fixed top-4 right-4 z-50 rounded-lg p-4 shadow-lg text-white flex items-center gap-3 ${toastType === "success" ? "bg-green-600" : "bg-red-600"}`}>
                            <span>{toastMessage}</span>
                        </div>
                    )}

                    <div>
                        <h1 className="text-3xl font-bold">
                            Welcome back, <span className="text-blue-600">{auth.user.name}</span>!
                        </h1>
                        <p className="text-gray-600 mt-2">Here's your HR compliance overview</p>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            icon={Users}
                            title="Total Staff"
                            count={complianceData.totalEmployees?.count ?? 0}
                            change={complianceData.totalEmployees?.change ?? 0}
                            list={complianceData.totalEmployees?.list ?? []}
                            textColor="text-blue-600"
                        />
                        <StatCard
                            icon={Briefcase}
                            title="Staff on Payroll"
                            count={complianceData.staffOnPayroll?.count ?? 0}
                            change={complianceData.staffOnPayroll?.change ?? 0}
                            list={complianceData.staffOnPayroll?.list ?? []}
                            textColor="text-emerald-600"
                        />
                        <StatCard
                            icon={Users}
                            title="New Hires"
                            count={complianceData.newHires?.count ?? 0}
                            change={complianceData.newHires?.change ?? 0}
                            list={complianceData.newHires?.list ?? []}
                            textColor="text-green-600"
                        />
                        <StatCard
                            icon={UserX}
                            title="Resigned/Terminated"
                            count={complianceData.resignedTerminated?.count ?? 0}
                            change={complianceData.resignedTerminated?.change ?? 0}
                            list={complianceData.resignedTerminated?.list ?? []}
                            textColor="text-red-600"
                        />
                    </div>

                    {/* HEADCOUNT & PAYROLL BREAKDOWN BY CLIENT */}
                    {(allClientHeadcount.length > 0 || clientSummary.length > 0) && (
                        <div className="grid gap-6 lg:grid-cols-2">
                            {/* TOTAL STAFF HEADCOUNT BY CLIENT */}
                            <Card className="shadow-lg hover:shadow-xl transition-shadow">
                                <CardHeader className="pb-3 border-b border-gray-100 dark:border-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Users className="h-5 w-5 text-blue-600" />
                                            <CardTitle className="text-lg font-bold">Total Staff by Client</CardTitle>
                                        </div>
                                        <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                            {complianceData.totalEmployees?.count ?? 0} Staff Total
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-4">
                                    <div className="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800 pr-1">
                                        {allClientHeadcount.length > 0 ? (
                                            allClientHeadcount.map((item, idx) => (
                                                <div
                                                    key={idx}
                                                    className="flex items-center justify-between py-2.5 px-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded-lg transition-colors"
                                                >
                                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                        {item.client_name}
                                                    </span>
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-sm font-bold text-blue-600 font-mono">
                                                            {item.count}
                                                        </span>
                                                        {item.employees && item.employees.length > 0 && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 px-2 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                                onClick={() =>
                                                                    openModal(
                                                                        `${item.client_name} - Total Staff (${item.count})`,
                                                                        item.employees
                                                                    )
                                                                }
                                                            >
                                                                <Users className="h-4 w-4 mr-1" /> View
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))
                                        ) : (
                                            <p className="text-sm text-gray-500 text-center py-6">No client headcount available</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* STAFF ON PAYROLL BY CLIENT */}
                            <Card className="shadow-lg hover:shadow-xl transition-shadow">
                                <CardHeader className="pb-3 border-b border-gray-100 dark:border-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Briefcase className="h-5 w-5 text-emerald-600" />
                                            <CardTitle className="text-lg font-bold">Staff on Payroll by Client</CardTitle>
                                        </div>
                                        <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            {complianceData.staffOnPayroll?.count ?? 0} On Payroll
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-4">
                                    <div className="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800 pr-1">
                                        {clientSummary.length > 0 ? (
                                            clientSummary.map((item, idx) => (
                                                <div
                                                    key={idx}
                                                    className="flex items-center justify-between py-2.5 px-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded-lg transition-colors"
                                                >
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                                            {item.client_name}
                                                        </span>
                                                        {item.table && (
                                                            <span className="text-xs text-gray-400 font-mono">
                                                                {item.table}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-sm font-bold text-emerald-600 font-mono">
                                                            {item.count}
                                                        </span>
                                                        {item.employees && item.employees.length > 0 && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-8 px-2 text-xs text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50"
                                                                onClick={() =>
                                                                    openModal(
                                                                        `${item.client_name} - Payroll Staff (${item.count})`,
                                                                        item.employees || []
                                                                    )
                                                                }
                                                            >
                                                                <Users className="h-4 w-4 mr-1" /> View
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))
                                        ) : (
                                            <p className="text-sm text-gray-500 text-center py-6">No payroll data for current cycle</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* ONLY THESE TWO CARDS ARE CHANGED */}
                    <div className="grid gap-6 lg:grid-cols-2 mb-7">
                        {/* BEAUTIFUL BLUE GRADIENT CARD */}
                        <Card className="overflow-hidden border-0 shadow-2xl bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 p-8">
                            <div className="">
                                <CardHeader>
                                    <CardTitle className="text-2xl font-bold text-white mb-5">Compliance Overview</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
            {[
                { icon: CreditCard, title: "Without Bank Details", data: complianceData.withoutBank },
                { icon: Shield, title: "Without SSNIT Details", data: complianceData.withoutSsnit },
                { icon: Shield, title: "Without Petra Details", data: complianceData.withoutPetra },
                { icon: Shield, title: "Without Ghana Card Details", data: complianceData.withoutGhanaCard },
                { icon: UserX, title: "Incomplete Profiles", data: complianceData.incompleteProfiles },
                { icon: Phone, title: "No Contact Details", data: complianceData.noContactDetails },
            ].map(({ icon: Icon, title, data }) => (
                <div key={title} className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="p-3 bg-white/10 rounded-xl backdrop-blur-sm">
                            <Icon className="h-6 w-6 text-white" />
                        </div>
                        <span className="text-lg font-medium text-white">{title}</span>
                    </div>
                    <div className="flex items-center gap-4">
                        <span className="text-3xl font-bold text-white">{data?.count ?? 0}</span>
                        {(data?.list ?? []).length > 0 && (
                            <Button variant="ghost" size="icon" onClick={() => openModal(title, data?.list ?? [])}>
                                <Users className="h-5 w-5 text-white" />
                            </Button>
                        )}
                    </div>
                </div>
            ))}
        </CardContent>
                            </div>
                        </Card>

                        {/* DYNAMIC CHART */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Employee Trends</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="h-80">
                                    <Line data={chartData} options={chartOptions} />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2 mt-8">
    {/* ORIGINAL CONTRACT REPORTS - WHITE BACKGROUND */}
    <Card className="overflow-hidden border shadow-xl bg-white p-8">
        <CardHeader>
            <CardTitle className="text-2xl font-bold mb-5">Contract Reports</CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
            {[
                { icon: Calendar, title: "Contracts Expiring Soon (2 Months)", data: complianceData.expiringContracts },
                { icon: AlertCircle, title: "Expired Contracts Pending Renewal", data: complianceData.expiredContracts },
                { icon: FileText, title: "Unsigned Contracts", data: complianceData.unsignedContracts },
            ].map(({ icon: Icon, title, data }) => (
                <div key={title} className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="p-3 bg-gray-100 rounded-xl">
                            <Icon className="h-6 w-6 text-gray-700" />
                        </div>
                        <span className="text-lg font-medium">{title}</span>
                    </div>
                    <div className="flex items-center gap-4">
                        <span className="text-3xl font-bold">{data?.count ?? 0}</span>
                        {(data?.list ?? []).length > 0 && (
                            <Button variant="ghost" size="icon" onClick={() => openModal(title, data?.list ?? [])}>
                                <Users className="h-5 w-5 text-gray-700" />
                            </Button>
                        )}
                    </div>
                </div>
            ))}
        </CardContent>
    </Card>

    {/* RENEWAL CONTRACT REPORTS - BLUE GRADIENT LIKE COMPLIANCE OVERVIEW */}
    <Card className="overflow-hidden border-0 shadow-2xl bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 p-8">
        <div className="">
            <CardHeader>
                <CardTitle className="text-2xl font-bold text-white mb-5">Contract Renewal Reports</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {[
                    { icon: Calendar, title: "Contracts Expiring Soon (2 Months)", data: complianceData.renewalExpiringContracts },
                    { icon: AlertCircle, title: "Expired Contracts Pending Renewal", data: complianceData.renewalExpiredContracts },
                    { icon: FileText, title: "Unsigned Contracts", data: complianceData.renewalUnsignedContracts },
                ].map(({ icon: Icon, title, data }) => (
                    <div key={title} className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-white/10 rounded-xl backdrop-blur-sm">
                                <Icon className="h-6 w-6 text-white" />
                            </div>
                            <span className="text-lg font-medium text-white">{title}</span>
                        </div>
                        <div className="flex items-center gap-4">
                            <span className="text-3xl font-bold text-white">{data?.count ?? 0}</span>
                            {(data?.list ?? []).length > 0 && (
                                <Button variant="ghost" size="icon" onClick={() => openModal(title, data?.list ?? [])}>
                                    <Users className="h-5 w-5 text-white" />
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
            </CardContent>
        </div>
    </Card>
</div>

                    {/* MODAL - UNCHANGED */}
                    <Dialog.Root open={modalData.open} onOpenChange={closeModal}>
                        <Dialog.Portal>
                            <Dialog.Overlay className="fixed inset-0 bg-black/50 z-40" />
                            <Dialog.Content className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-5xl w-full max-h-[85vh] overflow-hidden z-50">
                                <div className="flex items-center justify-between p-6 border-b">
                                    <Dialog.Title className="text-2xl font-bold">{modalData.title}</Dialog.Title>
                                    <Dialog.Close>
                                        <Cross2Icon className="h-6 w-6" />
                                    </Dialog.Close>
                                </div>
                                <div className="p-6 overflow-y-auto max-h-[60vh]">
                                    {modalData.items.length > 0 ? (
                                        <table className="w-full">
                                            <thead className="bg-gray-100 dark:bg-gray-700">
                                                <tr>
                                                    <th className="px-6 py-4 text-left font-medium">StaffID</th>
                                                    <th className="px-6 py-4 text-left font-medium">Employee</th>
                                                    <th className="px-6 py-4 text-left font-medium">Company</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {modalData.items.map((item, i) => (
                                                    <tr key={i} className="border-b hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td className="px-6 py-4 font-mono">{item.staffid}</td>
                                                        <td className="px-6 py-4">
                                                            <a href={`/employees/${item.staffid}/edit`} className="text-blue-600 hover:underline">
                                                                {item.employeename}
                                                            </a>
                                                        </td>
                                                        <td className="px-6 py-4">{item.company}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <p className="text-center py-10 text-gray-500">No records found</p>
                                    )}
                                </div>
                                <div className="flex justify-end gap-3 p-6 border-t">
                                    <Button variant="outline" onClick={closeModal}>Close</Button>
                                    <Button onClick={handleExportCSV} className="bg-green-600 hover:bg-green-700">
                                        Export CSV
                                    </Button>
                                    <Button onClick={handleExportPDF} className="bg-blue-600 hover:bg-blue-700">
                                        Export PDF
                                    </Button>
                                </div>
                            </Dialog.Content>
                        </Dialog.Portal>
                    </Dialog.Root>
                </div>
            </AppLayout>
        </ErrorBoundary>
    );
}
