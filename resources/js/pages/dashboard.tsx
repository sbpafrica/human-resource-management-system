import { Head, Link } from "@inertiajs/react";
import {
    AlertCircle,
    Users,
    FileText,
    CreditCard,
    Shield,
    Calendar,
    UserX,
    ArrowUp,
    ArrowDown,
    Briefcase,
    Cake,
    Phone,
    TrendingUp,
    ChevronRight,
    BarChart3,
    Layers,
    Building2,
    ArrowUpRight,
    ArrowDownRight,
    ExternalLink,
    Lock,
    DollarSign,
    Receipt,
    Sparkles,
    Download,
} from "lucide-react";
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

interface PayrollMonthClient {
    client_id: number;
    client_name: string;
    short_name: string;
    count: number;
    table: string;
}

interface PayrollMonthItem {
    year: number;
    month: number;
    month_name: string;
    month_short: string;
    month_key: string;
    total_count: number;
    clients_count: number;
    change_count: number;
    change_percent: number;
    has_data: boolean;
    clients: PayrollMonthClient[];
}

interface PayrollYearSummary {
    year: number;
    total_volume: number;
    months_count: number;
    avg_monthly: number;
    max_monthly: number;
    peak_month: string;
    months: PayrollMonthItem[];
}

interface PayrollHeadcountData {
    all_years: number[];
    current_year: number;
    selected_year_data?: PayrollYearSummary;
    yearly_summary: PayrollYearSummary[];
    monthly_trend: {
        labels: string[];
        counts: number[];
        clients: number[];
    };
    yearly_comparison: {
        labels: string[];
        datasets: {
            year: number;
            label: string;
            data: number[];
            borderColor: string;
            backgroundColor: string;
        }[];
    };
}

interface SuperadminFinancialClient {
    client_id: number;
    client_name: string;
    short_name: string;
    table: string;
    count: number;
    admin_fee: number;
    getfund: number;
    nhil: number;
    net_salary: number;
    ssnit_ee: number;
    ssnit_er: number;
    ssnit_total: number;
    petra: number;
    tier_3: number;
    total_cost: number;
}

interface SuperadminFinancialTotals {
    count: number;
    admin_fee: number;
    getfund: number;
    nhil: number;
    net_salary: number;
    ssnit_ee: number;
    ssnit_er: number;
    ssnit_total: number;
    petra: number;
    tier_3: number;
    total_cost: number;
    clients_count: number;
}

interface SuperadminFinancialsData {
    month: string;
    month_label: string;
    available_months: { key: string; label: string; year: number; month: number }[];
    grand_total: SuperadminFinancialTotals;
    by_client: SuperadminFinancialClient[];
}

interface Props {
    complianceData?: Partial<ComplianceData>;
    clientSummary?: ClientSummaryItem[];
    allClientHeadcount?: ClientHeadcountItem[];
    payrollHeadcountData?: PayrollHeadcountData;
    superadminFinancials?: SuperadminFinancialsData | null;
    auth: { user: { name: string; is_super_admin?: boolean } };
    flash?: { success?: string; error?: string };
}

const breadcrumbs = [{ title: "Dashboard", href: "/dashboard" }];
const columnHelper = createColumnHelper<EmployeeItem>();

export default function Dashboard({
    complianceData: partialComplianceData = {},
    clientSummary = [],
    allClientHeadcount = [],
    payrollHeadcountData,
    superadminFinancials,
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

    // Filter out any entries related to Benin, Togo, or CIV
    const filteredClientSummary = useMemo(() => {
        return (clientSummary || []).filter(item => {
            const name = (item.client_name || "").toLowerCase();
            const table = (item.table || "").toLowerCase();
            return !name.includes("benin") && !name.includes("togo") && !name.includes("civ") &&
                   !table.includes("benin") && !table.includes("togo") && !table.includes("civ");
        });
    }, [clientSummary]);

    const filteredAllClientHeadcount = useMemo(() => {
        return (allClientHeadcount || []).filter(item => {
            const name = (item.client_name || "").toLowerCase();
            return !name.includes("benin") && !name.includes("togo") && !name.includes("civ");
        });
    }, [allClientHeadcount]);

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
    const [activePayrollYear, setActivePayrollYear] = useState<number>(
        payrollHeadcountData?.current_year || new Date().getFullYear()
    );
    const [expandedPayrollMonth, setExpandedPayrollMonth] = useState<string | null>(null);
    const [headcountChartMode, setHeadcountChartMode] = useState<"year_months" | "timeline" | "comparison">("year_months");

    // Super Admin Financials State
    const [financialsData, setFinancialsData] = useState<SuperadminFinancialsData | null>(
        superadminFinancials || null
    );
    const [financialMonth, setFinancialMonth] = useState<string>(
        superadminFinancials?.month || "2026-08"
    );
    const [financialClientFilter, setFinancialClientFilter] = useState<string>("all");
    const [financialsLoading, setFinancialsLoading] = useState<boolean>(false);
    const [showFinancialClientTable, setShowFinancialClientTable] = useState<boolean>(false);

    const handleMonthChange = async (newMonth: string) => {
        setFinancialMonth(newMonth);
        setFinancialsLoading(true);
        try {
            const res = await fetch(`/payroll-financials?month=${encodeURIComponent(newMonth)}`);
            if (res.ok) {
                const data = await res.json();
                setFinancialsData(data);
                setFinancialClientFilter("all");
            }
        } catch (e) {
            console.error("Failed to fetch financials for month", e);
        } finally {
            setFinancialsLoading(false);
        }
    };

    const activeFinancialMetrics = useMemo(() => {
        if (!financialsData) return null;
        if (financialClientFilter === "all") {
            return {
                title: `All Clients (${financialsData.grand_total.clients_count} Active)`,
                is_all: true,
                client_name: "All Clients Combined",
                ...financialsData.grand_total,
            };
        }
        const found = financialsData.by_client.find(
            (c) => c.client_name === financialClientFilter || String(c.client_id) === financialClientFilter
        );
        if (found) {
            return {
                title: found.client_name,
                is_all: false,
                client_name: found.client_name,
                ...found,
                clients_count: 1,
            };
        }
        return {
            title: `All Clients (${financialsData.grand_total.clients_count} Active)`,
            is_all: true,
            client_name: "All Clients Combined",
            ...financialsData.grand_total,
        };
    }, [financialsData, financialClientFilter]);

    const handleExportFinancialsCSV = () => {
        if (!financialsData) return;
        const rows = financialsData.by_client.map((c) => ({
            "Client Name": c.client_name,
            "Payroll Table": c.table,
            "Employees Count": c.count,
            "Net Salary (GHS)": c.net_salary,
            "Principal / Admin Fee (GHS)": c.admin_fee,
            "GETFund 2.5% (GHS)": c.getfund,
            "NHIL 2.5% (GHS)": c.nhil,
            "SSNIT EE 5.5% (GHS)": c.ssnit_ee,
            "SSNIT ER 13% (GHS)": c.ssnit_er,
            "SSNIT Tier 1 Total (GHS)": c.ssnit_total,
            "Petra Tier 2 (GHS)": c.petra,
            "Tier 3 / PF (GHS)": c.tier_3,
            "Total Payroll & Fee Cost (GHS)": c.total_cost,
        }));

        rows.push({
            "Client Name": "GRAND TOTAL",
            "Payroll Table": "-",
            "Employees Count": financialsData.grand_total.count,
            "Net Salary (GHS)": financialsData.grand_total.net_salary,
            "Principal / Admin Fee (GHS)": financialsData.grand_total.admin_fee,
            "GETFund 2.5% (GHS)": financialsData.grand_total.getfund,
            "NHIL 2.5% (GHS)": financialsData.grand_total.nhil,
            "SSNIT EE 5.5% (GHS)": financialsData.grand_total.ssnit_ee,
            "SSNIT ER 13% (GHS)": financialsData.grand_total.ssnit_er,
            "SSNIT Tier 1 Total (GHS)": financialsData.grand_total.ssnit_total,
            "Petra Tier 2 (GHS)": financialsData.grand_total.petra,
            "Tier 3 / PF (GHS)": financialsData.grand_total.tier_3,
            "Total Payroll & Fee Cost (GHS)": financialsData.grand_total.total_cost,
        });

        const csv = Papa.unparse(rows);
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `payroll_financials_${financialsData.month}.csv`;
        link.click();
    };

    const activeYearSummary = useMemo(() => {
        return (
            payrollHeadcountData?.yearly_summary?.find((y) => y.year === activePayrollYear) ||
            payrollHeadcountData?.selected_year_data
        );
    }, [payrollHeadcountData, activePayrollYear]);

    const headcountChartData = useMemo(() => {
        if (headcountChartMode === "timeline" && payrollHeadcountData?.monthly_trend) {
            return {
                labels: payrollHeadcountData.monthly_trend.labels,
                datasets: [
                    {
                        label: "Staff on Payroll",
                        data: payrollHeadcountData.monthly_trend.counts,
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37, 99, 235, 0.1)",
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                    },
                ],
            };
        }

        if (headcountChartMode === "comparison" && payrollHeadcountData?.yearly_comparison) {
            return {
                labels: payrollHeadcountData.yearly_comparison.labels,
                datasets: payrollHeadcountData.yearly_comparison.datasets.map((ds) => ({
                    label: `Year ${ds.label}`,
                    data: ds.data,
                    borderColor: ds.borderColor,
                    backgroundColor: ds.backgroundColor,
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 3,
                })),
            };
        }

        const months = activeYearSummary?.months || [];
        return {
            labels: months.map((m) => m.month_short),
            datasets: [
                {
                    label: `Staff on Payroll (${activePayrollYear})`,
                    data: months.map((m) => (m.has_data ? m.total_count : null)),
                    borderColor: "#10b981",
                    backgroundColor: "rgba(16, 185, 129, 0.15)",
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 5,
                },
            ],
        };
    }, [headcountChartMode, payrollHeadcountData, activeYearSummary, activePayrollYear]);

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
                    {(filteredAllClientHeadcount.length > 0 || filteredClientSummary.length > 0) && (
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
                                        {filteredAllClientHeadcount.length > 0 ? (
                                            filteredAllClientHeadcount.map((item, idx) => (
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
                                        {filteredClientSummary.length > 0 ? (
                                            filteredClientSummary.map((item, idx) => (
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

                    {/* PAYROLL HEADCOUNT BY MONTH & YEAR */}
                    {payrollHeadcountData && payrollHeadcountData.all_years?.length > 0 && (
                        <div className="space-y-4 mb-8">
                            <Card className="shadow-lg hover:shadow-xl transition-shadow border border-gray-100 dark:border-gray-800">
                                <CardHeader className="pb-3 border-b border-gray-100 dark:border-gray-800">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                        <div className="flex items-center gap-2.5">
                                            <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                                                <Users className="h-5 w-5" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg font-bold flex items-center gap-2">
                                                    Employees on Payroll (Monthly & Yearly)
                                                </CardTitle>
                                                <p className="text-xs text-gray-500">
                                                    Headcount tracking for every month and across all years
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2 flex-wrap">
                                            {/* Year Selector Tabs */}
                                            <div className="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg">
                                                {payrollHeadcountData.all_years.map((yr) => (
                                                    <button
                                                        key={yr}
                                                        onClick={() => setActivePayrollYear(yr)}
                                                        className={`px-2.5 py-1 text-xs font-bold rounded-md transition-all ${
                                                            activePayrollYear === yr
                                                                ? "bg-white dark:bg-gray-700 text-emerald-600 dark:text-emerald-400 shadow-sm"
                                                                : "text-gray-500 hover:text-gray-900 dark:hover:text-white"
                                                        }`}
                                                    >
                                                        {yr}
                                                    </button>
                                                ))}
                                            </div>

                                            <Link
                                                href="/payroll-headcount"
                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300 transition-colors"
                                            >
                                                <span>Full Analytics & Export</span>
                                                <ExternalLink className="h-3.5 w-3.5" />
                                            </Link>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent className="p-4 sm:p-6 space-y-6">
                                    {/* Year Metrics Row */}
                                    {activeYearSummary && (
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <div className="p-3.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-900/20">
                                                <div className="flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-400 font-medium">
                                                    <span>{activePayrollYear} Monthly Average</span>
                                                    <Users className="h-4 w-4" />
                                                </div>
                                                <div className="mt-2 text-2xl font-extrabold text-emerald-700 dark:text-emerald-300 font-mono">
                                                    {activeYearSummary.avg_monthly.toLocaleString()}
                                                    <span className="text-xs font-normal text-emerald-600/70 ml-1">staff/mo</span>
                                                </div>
                                            </div>

                                            <div className="p-3.5 rounded-xl bg-blue-50/60 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/20">
                                                <div className="flex items-center justify-between text-xs text-blue-700 dark:text-blue-400 font-medium">
                                                    <span>{activePayrollYear} Peak Headcount</span>
                                                    <TrendingUp className="h-4 w-4" />
                                                </div>
                                                <div className="mt-2 text-2xl font-extrabold text-blue-700 dark:text-blue-300 font-mono">
                                                    {activeYearSummary.max_monthly.toLocaleString()}
                                                    <span className="text-xs font-normal text-blue-600/70 ml-1">({activeYearSummary.peak_month})</span>
                                                </div>
                                            </div>

                                            <div className="p-3.5 rounded-xl bg-purple-50/60 dark:bg-purple-900/10 border border-purple-100 dark:border-purple-900/20">
                                                <div className="flex items-center justify-between text-xs text-purple-700 dark:text-purple-400 font-medium">
                                                    <span>Active Months Recorded</span>
                                                    <Calendar className="h-4 w-4" />
                                                </div>
                                                <div className="mt-2 text-2xl font-extrabold text-purple-700 dark:text-purple-300 font-mono">
                                                    {activeYearSummary.months_count}
                                                    <span className="text-xs font-normal text-purple-600/70 ml-1">/ 12 months</span>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* 12-Month Calendar Grid */}
                                    <div>
                                        <div className="flex items-center justify-between mb-3">
                                            <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500">
                                                {activePayrollYear} Monthly Headcount Breakdown
                                            </h4>
                                            <span className="text-xs text-gray-400">Click any month to view client details</span>
                                        </div>

                                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
                                            {activeYearSummary?.months.map((m) => {
                                                const isExpanded = expandedPayrollMonth === m.month_key;
                                                return (
                                                    <div
                                                        key={m.month_key}
                                                        onClick={() => m.has_data && setExpandedPayrollMonth(isExpanded ? null : m.month_key)}
                                                        className={`p-3 rounded-xl border transition-all text-left flex flex-col justify-between ${
                                                            !m.has_data
                                                                ? "border-dashed border-gray-200 dark:border-gray-800 opacity-40 bg-gray-50/30"
                                                                : isExpanded
                                                                ? "border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/40 dark:bg-emerald-950/20 cursor-pointer shadow-sm"
                                                                : "border-gray-200 dark:border-gray-700 hover:border-emerald-400 hover:shadow-sm cursor-pointer bg-white dark:bg-gray-800"
                                                        }`}
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <span className="text-xs font-bold text-gray-700 dark:text-gray-300">
                                                                {m.month_name}
                                                            </span>
                                                            {m.has_data && m.change_count !== 0 && (
                                                                <span
                                                                    className={`text-[10px] font-bold px-1 rounded ${
                                                                        m.change_count > 0
                                                                            ? "text-emerald-700 bg-emerald-100"
                                                                            : "text-rose-700 bg-rose-100"
                                                                    }`}
                                                                >
                                                                    {m.change_count > 0 ? `+${m.change_percent}%` : `${m.change_percent}%`}
                                                                </span>
                                                            )}
                                                        </div>

                                                        <div className="mt-2">
                                                            {m.has_data ? (
                                                                <>
                                                                    <div className="text-lg font-extrabold font-mono text-gray-900 dark:text-white">
                                                                        {m.total_count.toLocaleString()}
                                                                    </div>
                                                                    <div className="text-[11px] text-gray-400 mt-0.5">
                                                                        {m.clients_count} clients
                                                                    </div>
                                                                </>
                                                            ) : (
                                                                <div className="text-xs text-gray-400 italic py-1">No data</div>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {/* EXPANDED MONTH CLIENT BREAKDOWN */}
                                    {expandedPayrollMonth && (
                                        <div className="p-4 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/30 dark:bg-emerald-950/10 animate-in fade-in">
                                            {(() => {
                                                const m = activeYearSummary?.months.find((x) => x.month_key === expandedPayrollMonth);
                                                if (!m) return null;
                                                return (
                                                    <div>
                                                        <div className="flex items-center justify-between pb-3 border-b border-emerald-200/60 dark:border-emerald-800/60">
                                                            <div className="flex items-center gap-2">
                                                                <Building2 className="h-4 w-4 text-emerald-600" />
                                                                <span className="text-sm font-bold text-gray-900 dark:text-white">
                                                                    Client Headcount for {m.month_name} {activePayrollYear} ({m.total_count.toLocaleString()} Total)
                                                                </span>
                                                            </div>
                                                            <button
                                                                onClick={() => setExpandedPayrollMonth(null)}
                                                                className="text-xs text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                                            >
                                                                Close
                                                            </button>
                                                        </div>
                                                        <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5 max-h-60 overflow-y-auto pr-1">
                                                            {m.clients.map((c, cIdx) => (
                                                                <div
                                                                    key={cIdx}
                                                                    className="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-2xs"
                                                                >
                                                                    <div className="min-w-0 pr-2">
                                                                        <div className="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                                                                            {c.client_name}
                                                                        </div>
                                                                        <div className="text-[10px] text-gray-400 font-mono truncate">
                                                                            {c.table}
                                                                        </div>
                                                                    </div>
                                                                    <span className="text-xs font-extrabold font-mono text-emerald-600 flex-shrink-0">
                                                                        {c.count.toLocaleString()}
                                                                    </span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                );
                                            })()}
                                        </div>
                                    )}

                                    {/* Headcount Chart with Mode Toggles */}
                                    <div className="pt-2">
                                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                                            <div className="text-xs font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                                                <BarChart3 className="h-4 w-4 text-emerald-600" />
                                                Payroll Headcount Progression
                                            </div>
                                            <div className="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 p-0.5 rounded-lg self-start">
                                                <button
                                                    onClick={() => setHeadcountChartMode("year_months")}
                                                    className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-all ${
                                                        headcountChartMode === "year_months"
                                                            ? "bg-white dark:bg-gray-700 text-emerald-600 shadow-sm"
                                                            : "text-gray-500"
                                                    }`}
                                                >
                                                    {activePayrollYear} (12 Months)
                                                </button>
                                                <button
                                                    onClick={() => setHeadcountChartMode("timeline")}
                                                    className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-all ${
                                                        headcountChartMode === "timeline"
                                                            ? "bg-white dark:bg-gray-700 text-emerald-600 shadow-sm"
                                                            : "text-gray-500"
                                                    }`}
                                                >
                                                    24-Month Timeline
                                                </button>
                                                <button
                                                    onClick={() => setHeadcountChartMode("comparison")}
                                                    className={`px-2.5 py-1 text-xs font-semibold rounded-md transition-all ${
                                                        headcountChartMode === "comparison"
                                                            ? "bg-white dark:bg-gray-700 text-emerald-600 shadow-sm"
                                                            : "text-gray-500"
                                                    }`}
                                                >
                                                    Year-over-Year
                                                </button>
                                            </div>
                                        </div>
                                        <div className="h-64 w-full">
                                            <Line data={headcountChartData} options={chartOptions} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* SUPER ADMIN PAYROLL & STATUTORY / ADMINISTRATIVE FINANCIALS */}
                    {auth.user.is_super_admin && activeFinancialMetrics && financialsData && (
                        <div className="space-y-4 mb-8">
                            <Card className="shadow-xl border-2 border-amber-300/80 dark:border-amber-700/50 bg-gradient-to-b from-amber-50/40 via-white to-white dark:from-amber-950/20 dark:via-gray-900 dark:to-gray-900 overflow-hidden">
                                <CardHeader className="pb-3 border-b border-amber-200/60 dark:border-amber-800/40 bg-amber-50/60 dark:bg-amber-950/30">
                                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                        <div className="flex items-center gap-3">
                                            <div className="p-2.5 rounded-xl bg-amber-500 text-white shadow-md shadow-amber-500/20">
                                                <Lock className="h-5 w-5" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <CardTitle className="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                        Payroll & Statutory Fee Financials
                                                    </CardTitle>
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-200/80 text-amber-900 dark:bg-amber-900/60 dark:text-amber-200">
                                                        <Sparkles className="h-3 w-3" /> Super Admin Only
                                                    </span>
                                                </div>
                                                <p className="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                                    Principal administrative fees, GETFund, NHIL, net salary & Tier 1/2/3 statutory levies
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2.5 flex-wrap">
                                            {/* Payroll Month / Cycle Dropdown */}
                                            <div className="flex items-center gap-1.5 bg-white dark:bg-gray-800 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                                                <span className="text-xs text-gray-500 font-medium">Cycle:</span>
                                                <select
                                                    value={financialMonth}
                                                    onChange={(e) => handleMonthChange(e.target.value)}
                                                    disabled={financialsLoading}
                                                    className="text-xs font-bold text-gray-800 dark:text-gray-200 bg-transparent border-0 focus:outline-none focus:ring-0 cursor-pointer"
                                                >
                                                    {financialsData.available_months?.map((m) => (
                                                        <option key={m.key} value={m.key}>
                                                            {m.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            {/* Client Selector ("when the super admin select") */}
                                            <div className="flex items-center gap-1.5 bg-white dark:bg-gray-800 px-2.5 py-1.5 rounded-lg border border-amber-300 dark:border-amber-700 shadow-sm">
                                                <span className="text-xs text-amber-800 dark:text-amber-300 font-semibold">Client:</span>
                                                <select
                                                    value={financialClientFilter}
                                                    onChange={(e) => setFinancialClientFilter(e.target.value)}
                                                    className="text-xs font-bold text-gray-800 dark:text-gray-200 bg-transparent border-0 focus:outline-none focus:ring-0 cursor-pointer max-w-[200px] truncate"
                                                >
                                                    <option value="all">★ All Clients Combined ({financialsData.grand_total.clients_count})</option>
                                                    {financialsData.by_client?.map((c) => (
                                                        <option key={c.client_id} value={c.client_name}>
                                                            {c.client_name} ({c.count} staff)
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            {/* CSV Export Button */}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleExportFinancialsCSV}
                                                className="h-8 px-2.5 text-xs border-gray-300 hover:bg-amber-100/50 dark:hover:bg-amber-900/40 text-gray-700 dark:text-gray-300 flex items-center gap-1.5"
                                            >
                                                <Download className="h-3.5 w-3.5 text-amber-600" />
                                                <span>Export CSV</span>
                                            </Button>

                                            {/* Toggle Client Breakdown Table */}
                                            <Button
                                                variant={showFinancialClientTable ? "default" : "outline"}
                                                size="sm"
                                                onClick={() => setShowFinancialClientTable(!showFinancialClientTable)}
                                                className={`h-8 px-2.5 text-xs flex items-center gap-1.5 ${
                                                    showFinancialClientTable
                                                        ? "bg-amber-600 hover:bg-amber-700 text-white"
                                                        : "border-amber-300 text-amber-800 dark:text-amber-300 hover:bg-amber-50"
                                                }`}
                                            >
                                                <Receipt className="h-3.5 w-3.5" />
                                                <span>{showFinancialClientTable ? "Hide Table" : "Client Table"}</span>
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent className="p-4 sm:p-6 space-y-6">
                                    {/* Subheader info showing currently selected scope */}
                                    <div className="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800 text-xs">
                                        <div className="flex items-center gap-2">
                                            <span className="text-gray-500">Active View:</span>
                                            <span className="font-extrabold text-amber-700 dark:text-amber-400">
                                                {activeFinancialMetrics.client_name}
                                            </span>
                                            <span className="text-gray-400">|</span>
                                            <span className="text-gray-500">Period:</span>
                                            <span className="font-bold text-gray-700 dark:text-gray-300">{financialsData.month_label}</span>
                                            <span className="text-gray-400">|</span>
                                            <span className="text-gray-500">Staff Count:</span>
                                            <span className="font-bold text-emerald-600 font-mono">
                                                {Number(activeFinancialMetrics.count).toLocaleString()}
                                            </span>
                                        </div>
                                        {financialsLoading && (
                                            <span className="text-amber-600 font-semibold animate-pulse">Updating metrics...</span>
                                        )}
                                    </div>

                                    {/* 8 Metric Cards Grid */}
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                                        {/* 1. Principal / Admin Fee */}
                                        <div className="p-3.5 rounded-xl bg-amber-50/80 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-900/30">
                                            <div className="text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-400">
                                                Principal / Admin Fee
                                            </div>
                                            <div className="mt-2 text-xl font-black text-amber-950 dark:text-amber-100 font-mono">
                                                GHS {Number(activeFinancialMetrics.admin_fee || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                            <p className="mt-1 text-[10px] text-amber-700/80 dark:text-amber-400/80">
                                                Management fee charge
                                            </p>
                                        </div>

                                        {/* 2. GETFund */}
                                        <div className="p-3.5 rounded-xl bg-orange-50/80 dark:bg-orange-950/20 border border-orange-200/80 dark:border-orange-900/30">
                                            <div className="text-[11px] font-bold uppercase tracking-wider text-orange-800 dark:text-orange-400">
                                                GETFund (2.5%)
                                            </div>
                                            <div className="mt-2 text-xl font-black text-orange-950 dark:text-orange-100 font-mono">
                                                GHS {Number(activeFinancialMetrics.getfund || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                            <p className="mt-1 text-[10px] text-orange-700/80 dark:text-orange-400/80">
                                                Statutory education levy
                                            </p>
                                        </div>

                                        {/* 3. NHIL */}
                                        <div className="p-3.5 rounded-xl bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200/80 dark:border-rose-900/30">
                                            <div className="text-[11px] font-bold uppercase tracking-wider text-rose-800 dark:text-rose-400">
                                                NHIL (2.5%)
                                            </div>
                                            <div className="mt-2 text-xl font-black text-rose-950 dark:text-rose-100 font-mono">
                                                GHS {Number(activeFinancialMetrics.nhil || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                            <p className="mt-1 text-[10px] text-rose-700/80 dark:text-rose-400/80">
                                                National health insurance levy
                                            </p>
                                        </div>

                                        {/* 4. Total Net Salary */}
                                        <div className="p-3.5 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-900/30">
                                            <div className="text-[11px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-400">
                                                Total Net Salary
                                            </div>
                                            <div className="mt-2 text-xl font-black text-emerald-950 dark:text-emerald-100 font-mono">
                                                GHS {Number(activeFinancialMetrics.net_salary || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                            <p className="mt-1 text-[10px] text-emerald-700/80 dark:text-emerald-400/80">
                                                Take-home bank disbursements
                                            </p>
                                        </div>

                                        {/* 5. SSNIT Tier 1 (EE + ER) */}
                                        <div className="p-3.5 rounded-xl bg-blue-50/80 dark:bg-blue-950/20 border border-blue-200/80 dark:border-blue-900/30">
                                            <div className="flex items-center justify-between">
                                                <div className="text-[11px] font-bold uppercase tracking-wider text-blue-800 dark:text-blue-400">
                                                    SSNIT (Tier 1 Total)
                                                </div>
                                                <span className="text-[10px] font-bold text-blue-600 bg-blue-100 dark:bg-blue-900/50 px-1.5 py-0.5 rounded">
                                                    13.5%
                                                </span>
                                            </div>
                                            <div className="mt-2 text-xl font-black text-blue-950 dark:text-blue-100 font-mono">
                                                GHS {Number(activeFinancialMetrics.ssnit_total || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                            <p className="mt-1 text-[10px] text-blue-700/80 dark:text-blue-400/80 truncate">
                                                EE 5.5%: GHS {Number(activeFinancialMetrics.ssnit_ee || 0).toLocaleString(undefined, { maximumFractionDigits: 0 })} | ER 13%: GHS {Number(activeFinancialMetrics.ssnit_er || 0).toLocaleString(undefined, { maximumFractionDigits: 0 })}
                                            </p>
                                        </div>

                                        {/* 6. Petra (Tier 2) */}
                                        <div className="p-3.5 rounded-xl bg-indigo-50/80 dark:bg-indigo-950/20 border border-indigo-200/80 dark:border-indigo-900/30">
                                            <div className="flex items-center justify-between">
                                                <div className="text-[11px] font-bold uppercase tracking-wider text-indigo-800 dark:text-indigo-400">
                                                    Petra (Tier 2)
                                                </div>
                                                <span className="text-[10px] font-bold text-indigo-600 bg-indigo-100 dark:bg-indigo-900/50 px-1.5 py-0.5 rounded">
                                                    5%
                                                </span>
                                            </div>
                                            <div className="mt-2 text-xl font-black text-indigo-950 dark:text-indigo-100 font-mono">
                                                GHS {Number(activeFinancialMetrics.petra || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                            <p className="mt-1 text-[10px] text-indigo-700/80 dark:text-indigo-400/80">
                                                Mandatory occupational pension
                                            </p>
                                        </div>

                                        {/* 7. Tier 3 / Provident Fund */}
                                        <div className="p-3.5 rounded-xl bg-purple-50/80 dark:bg-purple-950/20 border border-purple-200/80 dark:border-purple-900/30">
                                            <div className="text-[11px] font-bold uppercase tracking-wider text-purple-800 dark:text-purple-400">
                                                Tier 3 (Provident Fund)
                                            </div>
                                            <div className="mt-2 text-xl font-black text-purple-950 dark:text-purple-100 font-mono">
                                                GHS {Number(activeFinancialMetrics.tier_3 || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                            <p className="mt-1 text-[10px] text-purple-700/80 dark:text-purple-400/80">
                                                Voluntary pension / provident fund
                                            </p>
                                        </div>

                                        {/* 8. Total Payroll & Fee Cost */}
                                        <div className="p-3.5 rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 text-white shadow-md shadow-amber-600/20">
                                            <div className="text-[11px] font-bold uppercase tracking-wider text-amber-100">
                                                Total Payroll & Cost
                                            </div>
                                            <div className="mt-2 text-xl font-black text-white font-mono">
                                                GHS {Number(activeFinancialMetrics.total_cost || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                            <p className="mt-1 text-[10px] text-amber-200">
                                                Net salaries + admin fees + levies
                                            </p>
                                        </div>
                                    </div>

                                    {/* Collapsible Client Breakdown Table */}
                                    {showFinancialClientTable && financialsData.by_client?.length > 0 && (
                                        <div className="pt-2">
                                            <div className="flex items-center justify-between mb-2">
                                                <h4 className="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                                    Client-by-Client Financial Breakdown ({financialsData.by_client.length} Active Clients)
                                                </h4>
                                                <span className="text-[11px] text-gray-500">Amounts in GHS</span>
                                            </div>
                                            <div className="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                                                <table className="w-full text-xs text-left">
                                                    <thead className="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                                                        <tr>
                                                            <th className="py-2.5 px-3">Client</th>
                                                            <th className="py-2.5 px-2 text-right">Staff</th>
                                                            <th className="py-2.5 px-2 text-right">Net Salary</th>
                                                            <th className="py-2.5 px-2 text-right">Admin Fee</th>
                                                            <th className="py-2.5 px-2 text-right">GETFund</th>
                                                            <th className="py-2.5 px-2 text-right">NHIL</th>
                                                            <th className="py-2.5 px-2 text-right">SSNIT (Tier 1)</th>
                                                            <th className="py-2.5 px-2 text-right">Petra (Tier 2)</th>
                                                            <th className="py-2.5 px-2 text-right">Tier 3</th>
                                                            <th className="py-2.5 px-3 text-right">Total Cost</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                                        {financialsData.by_client.map((c) => (
                                                            <tr
                                                                key={c.client_id}
                                                                onClick={() => setFinancialClientFilter(c.client_name)}
                                                                className={`hover:bg-amber-50/50 dark:hover:bg-amber-950/20 cursor-pointer transition-colors ${
                                                                    financialClientFilter === c.client_name ? "bg-amber-100/50 dark:bg-amber-950/40 font-semibold" : ""
                                                                }`}
                                                            >
                                                                <td className="py-2 px-3">
                                                                    <div className="font-medium text-gray-900 dark:text-gray-100">{c.client_name}</div>
                                                                    <div className="text-[10px] text-gray-400 font-mono">{c.table}</div>
                                                                </td>
                                                                <td className="py-2 px-2 text-right font-mono font-bold text-emerald-600">{c.count}</td>
                                                                <td className="py-2 px-2 text-right font-mono">{Number(c.net_salary).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                                <td className="py-2 px-2 text-right font-mono text-amber-700 dark:text-amber-400">{Number(c.admin_fee).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                                <td className="py-2 px-2 text-right font-mono">{Number(c.getfund).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                                <td className="py-2 px-2 text-right font-mono">{Number(c.nhil).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                                <td className="py-2 px-2 text-right font-mono text-blue-600">{Number(c.ssnit_total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                                <td className="py-2 px-2 text-right font-mono text-indigo-600">{Number(c.petra).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                                <td className="py-2 px-2 text-right font-mono text-purple-600">{Number(c.tier_3).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                                <td className="py-2 px-3 text-right font-mono font-bold text-gray-900 dark:text-gray-100">{Number(c.total_cost).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                    <tfoot className="bg-amber-50 dark:bg-amber-950/40 font-bold border-t-2 border-amber-300 dark:border-amber-700">
                                                        <tr>
                                                            <td className="py-2.5 px-3 uppercase text-amber-900 dark:text-amber-200">GRAND TOTAL</td>
                                                            <td className="py-2.5 px-2 text-right font-mono text-emerald-700">{financialsData.grand_total.count}</td>
                                                            <td className="py-2.5 px-2 text-right font-mono">{Number(financialsData.grand_total.net_salary).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                            <td className="py-2.5 px-2 text-right font-mono text-amber-800 dark:text-amber-300">{Number(financialsData.grand_total.admin_fee).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                            <td className="py-2.5 px-2 text-right font-mono">{Number(financialsData.grand_total.getfund).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                            <td className="py-2.5 px-2 text-right font-mono">{Number(financialsData.grand_total.nhil).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                            <td className="py-2.5 px-2 text-right font-mono text-blue-700">{Number(financialsData.grand_total.ssnit_total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                            <td className="py-2.5 px-2 text-right font-mono text-indigo-700">{Number(financialsData.grand_total.petra).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                            <td className="py-2.5 px-2 text-right font-mono text-purple-700">{Number(financialsData.grand_total.tier_3).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                            <td className="py-2.5 px-3 text-right font-mono font-black text-amber-950 dark:text-amber-100">{Number(financialsData.grand_total.total_cost).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    )}
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
