import { Head, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { useState, useMemo } from "react";
import * as Dialog from "@radix-ui/react-dialog";
import { Cross2Icon } from "@radix-ui/react-icons";
import {
    Users,
    Calendar,
    TrendingUp,
    TrendingDown,
    Building2,
    Download,
    RefreshCw,
    Search,
    ChevronRight,
    ArrowUpRight,
    ArrowDownRight,
    BarChart3,
    Layers,
    FileSpreadsheet,
} from "lucide-react";
import { Line, Bar } from "react-chartjs-2";
import { Chart, registerables } from "chart.js";
import Papa from "papaparse";

Chart.register(...registerables);

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

interface Props {
    headcountData: PayrollHeadcountData;
    selectedYear: number;
}

interface DrilldownEmployee {
    staffid: string;
    name: string;
    department?: string;
    netpay?: number;
}

const breadcrumbs = [
    { title: "Dashboard", href: "/dashboard" },
    { title: "Payroll Headcount", href: "/payroll-headcount" },
];

export default function PayrollHeadcountIndex({ headcountData, selectedYear }: Props) {
    const [activeYear, setActiveYear] = useState<number>(selectedYear || headcountData.current_year || new Date().getFullYear());
    const [chartMode, setChartMode] = useState<"timeline" | "comparison" | "yearly">("timeline");
    const [expandedMonth, setExpandedMonth] = useState<string | null>(null);

    // Drilldown modal state
    const [drilldownOpen, setDrilldownOpen] = useState(false);
    const [drilldownLoading, setDrilldownLoading] = useState(false);
    const [drilldownTitle, setDrilldownTitle] = useState("");
    const [drilldownEmployees, setDrilldownEmployees] = useState<DrilldownEmployee[]>([]);
    const [drilldownSearch, setDrilldownSearch] = useState("");

    // Selected year's data
    const activeYearSummary = useMemo(() => {
        return headcountData.yearly_summary.find((y) => y.year === activeYear) || headcountData.selected_year_data;
    }, [headcountData, activeYear]);

    // Timeline Chart Data
    const timelineChartData = useMemo(() => {
        return {
            labels: headcountData.monthly_trend.labels,
            datasets: [
                {
                    label: "Employees on Payroll",
                    data: headcountData.monthly_trend.counts,
                    borderColor: "#2563eb",
                    backgroundColor: "rgba(37, 99, 235, 0.1)",
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                },
            ],
        };
    }, [headcountData]);

    // Comparison Chart Data (Year-over-Year overlay)
    const comparisonChartData = useMemo(() => {
        return {
            labels: headcountData.yearly_comparison.labels,
            datasets: headcountData.yearly_comparison.datasets.map((ds) => ({
                label: `Year ${ds.label}`,
                data: ds.data,
                borderColor: ds.borderColor,
                backgroundColor: ds.backgroundColor,
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5,
            })),
        };
    }, [headcountData]);

    // Yearly Bar Chart Data
    const yearlyBarChartData = useMemo(() => {
        const sorted = [...headcountData.yearly_summary].sort((a, b) => a.year - b.year);
        return {
            labels: sorted.map((y) => y.year.toString()),
            datasets: [
                {
                    label: "Average Monthly Headcount",
                    data: sorted.map((y) => y.avg_monthly),
                    backgroundColor: "rgba(37, 99, 235, 0.75)",
                    borderRadius: 6,
                },
                {
                    label: "Peak Month Headcount",
                    data: sorted.map((y) => y.max_monthly),
                    backgroundColor: "rgba(16, 185, 129, 0.75)",
                    borderRadius: 6,
                },
            ],
        };
    }, [headcountData]);

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "top" as const,
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                },
            },
            tooltip: {
                callbacks: {
                    label: (context: any) => {
                        const val = context.parsed.y || 0;
                        return ` ${context.dataset.label}: ${val.toLocaleString()} employees`;
                    },
                },
            },
        },
        scales: {
            y: {
                beginAtZero: false,
                grid: { color: "rgba(156, 163, 175, 0.15)" },
                ticks: {
                    callback: (value: any) => Number(value).toLocaleString(),
                },
            },
            x: {
                grid: { display: false },
            },
        },
    };

    // Open Drilldown for a specific client payroll table
    const handleOpenDrilldown = async (client: PayrollMonthClient, monthName: string, year: number) => {
        setDrilldownTitle(`${client.client_name} - ${monthName} ${year}`);
        setDrilldownOpen(true);
        setDrilldownLoading(true);
        setDrilldownSearch("");
        try {
            const res = await fetch(`/payroll-headcount/employees?table=${encodeURIComponent(client.table)}`);
            const data = await res.json();
            setDrilldownEmployees(data.employees || []);
        } catch (err) {
            console.error("Failed to load employees for table", err);
            setDrilldownEmployees([]);
        } finally {
            setDrilldownLoading(false);
        }
    };

    // Filter drilldown employees by search
    const filteredEmployees = useMemo(() => {
        if (!drilldownSearch.trim()) return drilldownEmployees;
        const q = drilldownSearch.toLowerCase();
        return drilldownEmployees.filter(
            (e) => (e.staffid && e.staffid.toLowerCase().includes(q)) || (e.name && e.name.toLowerCase().includes(q)) || (e.department && e.department.toLowerCase().includes(q))
        );
    }, [drilldownEmployees, drilldownSearch]);

    // Export entire history to CSV
    const exportHistoryCSV = () => {
        const rows: any[] = [];
        headcountData.yearly_summary.forEach((y) => {
            y.months.forEach((m) => {
                if (m.has_data) {
                    rows.push({
                        Year: y.year,
                        Month: m.month_name,
                        "Total Headcount": m.total_count,
                        "Active Clients": m.clients_count,
                        "MoM Change (Count)": m.change_count,
                        "MoM Change (%)": `${m.change_percent}%`,
                        "Client Breakdown": m.clients.map((c) => `${c.client_name}: ${c.count}`).join("; "),
                    });
                }
            });
        });

        const csv = Papa.unparse(rows);
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `payroll_headcount_history_all_years.csv`;
        link.click();
    };

    // Export current modal employee list
    const exportDrilldownCSV = () => {
        if (!filteredEmployees.length) return;
        const csv = Papa.unparse(
            filteredEmployees.map((e) => ({
                "Staff ID": e.staffid,
                Name: e.name,
                Department: e.department || "",
                "Net Pay": e.netpay || 0,
            }))
        );
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `${drilldownTitle.replace(/[^a-zA-Z0-9]/g, "_")}_employees.csv`;
        link.click();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Headcount - Monthly & Yearly" />

            <div className="flex-1 space-y-6 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
                {/* PAGE HEADER */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 dark:border-gray-800 pb-5">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white flex items-center gap-3">
                            <span className="p-2 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                <Users className="h-6 w-6" />
                            </span>
                            Payroll Headcount Analytics
                        </h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Total numbers of employees on payroll every month and across all years
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.get("/payroll-headcount", { refresh: 1 }, { preserveState: true })}
                            className="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300"
                        >
                            <RefreshCw className="h-3.5 w-3.5" /> Refresh
                        </Button>
                        <Button
                            size="sm"
                            onClick={exportHistoryCSV}
                            className="flex items-center gap-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
                        >
                            <Download className="h-3.5 w-3.5" /> Export All History (CSV)
                        </Button>
                    </div>
                </div>

                {/* YEAR TABS SELECTOR */}
                <div className="flex items-center justify-between gap-2 overflow-x-auto pb-1 scrollbar-none">
                    <div className="flex items-center gap-1.5 bg-gray-100 dark:bg-gray-800 p-1.5 rounded-xl">
                        {headcountData.all_years.map((year) => {
                            const ySum = headcountData.yearly_summary.find((y) => y.year === year);
                            return (
                                <button
                                    key={year}
                                    onClick={() => setActiveYear(year)}
                                    className={`px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all ${
                                        activeYear === year
                                            ? "bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 shadow-sm"
                                            : "text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                                    }`}
                                >
                                    {year}
                                    {ySum && (
                                        <span className="ml-1.5 text-xs font-normal opacity-75">
                                            ({ySum.avg_monthly.toLocaleString()}/mo)
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                    <span className="text-xs text-gray-500 hidden md:inline-flex items-center gap-1">
                        <Calendar className="h-3.5 w-3.5" /> Select year to view monthly breakdown
                    </span>
                </div>

                {/* YEARLY SUMMARY STAT CARDS */}
                {activeYearSummary && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <Card className="border-0 shadow-md bg-gradient-to-br from-blue-600 to-indigo-700 text-white">
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-blue-100 uppercase tracking-wider">
                                        Year {activeYear} Avg Headcount
                                    </span>
                                    <Users className="h-5 w-5 text-blue-200" />
                                </div>
                                <div className="mt-3 flex items-baseline gap-2">
                                    <span className="text-3xl font-extrabold tracking-tight">
                                        {activeYearSummary.avg_monthly.toLocaleString()}
                                    </span>
                                    <span className="text-xs text-blue-200">/ month</span>
                                </div>
                                <p className="mt-1 text-xs text-blue-100/80">Average active employees per payroll cycle</p>
                            </CardContent>
                        </Card>

                        <Card className="border shadow-sm bg-white dark:bg-gray-800">
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Peak Month ({activeYear})
                                    </span>
                                    <TrendingUp className="h-5 w-5 text-emerald-500" />
                                </div>
                                <div className="mt-3 flex items-baseline gap-2">
                                    <span className="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
                                        {activeYearSummary.max_monthly.toLocaleString()}
                                    </span>
                                    <span className="text-xs font-semibold text-gray-600 dark:text-gray-300">
                                        in {activeYearSummary.peak_month}
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-gray-500">Highest monthly payroll headcount in {activeYear}</p>
                            </CardContent>
                        </Card>

                        <Card className="border shadow-sm bg-white dark:bg-gray-800">
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Total Records Processed
                                    </span>
                                    <Layers className="h-5 w-5 text-indigo-500" />
                                </div>
                                <div className="mt-3 flex items-baseline gap-2">
                                    <span className="text-3xl font-extrabold text-gray-900 dark:text-white">
                                        {activeYearSummary.total_volume.toLocaleString()}
                                    </span>
                                    <span className="text-xs text-gray-500">records</span>
                                </div>
                                <p className="mt-1 text-xs text-gray-500">Cumulative payroll headcount across all months</p>
                            </CardContent>
                        </Card>

                        <Card className="border shadow-sm bg-white dark:bg-gray-800">
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Active Months Processed
                                    </span>
                                    <Calendar className="h-5 w-5 text-amber-500" />
                                </div>
                                <div className="mt-3 flex items-baseline gap-2">
                                    <span className="text-3xl font-extrabold text-amber-600 dark:text-amber-400">
                                        {activeYearSummary.months_count}
                                    </span>
                                    <span className="text-xs text-gray-500">/ 12 months</span>
                                </div>
                                <p className="mt-1 text-xs text-gray-500">Monthly payroll runs recorded in {activeYear}</p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* INTERACTIVE CHART SECTION */}
                <Card className="border shadow-sm bg-white dark:bg-gray-800">
                    <CardHeader className="pb-3 border-b border-gray-100 dark:border-gray-800">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <CardTitle className="text-lg font-bold flex items-center gap-2">
                                    <BarChart3 className="h-5 w-5 text-blue-600" />
                                    Payroll Headcount Growth & Trends
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    {chartMode === "timeline" && "Continuous monthly payroll progression over the last 24 months"}
                                    {chartMode === "comparison" && "Side-by-side month-by-month headcount comparison across years"}
                                    {chartMode === "yearly" && "Comparison of annual average and peak headcount across years"}
                                </CardDescription>
                            </div>

                            <div className="flex items-center gap-1 bg-gray-100 dark:bg-gray-700/50 p-1 rounded-lg">
                                <button
                                    onClick={() => setChartMode("timeline")}
                                    className={`px-3 py-1 text-xs font-semibold rounded-md transition-all ${
                                        chartMode === "timeline"
                                            ? "bg-white dark:bg-gray-800 text-blue-600 shadow-sm"
                                            : "text-gray-500 hover:text-gray-900"
                                    }`}
                                >
                                    Monthly Timeline
                                </button>
                                <button
                                    onClick={() => setChartMode("comparison")}
                                    className={`px-3 py-1 text-xs font-semibold rounded-md transition-all ${
                                        chartMode === "comparison"
                                            ? "bg-white dark:bg-gray-800 text-blue-600 shadow-sm"
                                            : "text-gray-500 hover:text-gray-900"
                                    }`}
                                >
                                    Year-over-Year
                                </button>
                                <button
                                    onClick={() => setChartMode("yearly")}
                                    className={`px-3 py-1 text-xs font-semibold rounded-md transition-all ${
                                        chartMode === "yearly"
                                            ? "bg-white dark:bg-gray-800 text-blue-600 shadow-sm"
                                            : "text-gray-500 hover:text-gray-900"
                                    }`}
                                >
                                    Yearly Comparison
                                </button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-5">
                        <div className="h-80 w-full">
                            {chartMode === "timeline" && <Line data={timelineChartData} options={chartOptions} />}
                            {chartMode === "comparison" && <Line data={comparisonChartData} options={chartOptions} />}
                            {chartMode === "yearly" && <Bar data={yearlyBarChartData} options={chartOptions} />}
                        </div>
                    </CardContent>
                </Card>

                {/* MONTHLY BREAKDOWN TABLE FOR SELECTED YEAR */}
                <Card className="border shadow-sm bg-white dark:bg-gray-800">
                    <CardHeader className="pb-3 border-b border-gray-100 dark:border-gray-800">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Calendar className="h-5 w-5 text-indigo-600" />
                                <CardTitle className="text-lg font-bold">
                                    Monthly Payroll Headcount Breakdown ({activeYear})
                                </CardTitle>
                            </div>
                            <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                12 Months
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-800/50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        <th className="py-3 px-4">Month</th>
                                        <th className="py-3 px-4">Employees on Payroll</th>
                                        <th className="py-3 px-4">MoM Growth</th>
                                        <th className="py-3 px-4">Active Clients</th>
                                        <th className="py-3 px-4">Top Clients</th>
                                        <th className="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                                    {activeYearSummary?.months.map((m) => {
                                        const isExpanded = expandedMonth === m.month_key;
                                        return (
                                            <>
                                                <tr
                                                    key={m.month_key}
                                                    className={`hover:bg-gray-50/80 dark:hover:bg-gray-800/50 transition-colors ${
                                                        !m.has_data ? "opacity-50" : ""
                                                    }`}
                                                >
                                                    <td className="py-3.5 px-4 font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                                        <span className="w-2 h-2 rounded-full bg-blue-500"></span>
                                                        {m.month_name} {activeYear}
                                                    </td>
                                                    <td className="py-3.5 px-4">
                                                        {m.has_data ? (
                                                            <div className="flex items-center gap-2.5">
                                                                <span className="text-base font-bold font-mono text-gray-900 dark:text-white">
                                                                    {m.total_count.toLocaleString()}
                                                                </span>
                                                                <span className="text-xs text-gray-400">staff</span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-gray-400 italic">No payroll data</span>
                                                        )}
                                                    </td>
                                                    <td className="py-3.5 px-4">
                                                        {m.has_data && m.change_count !== 0 ? (
                                                            <div
                                                                className={`inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full ${
                                                                    m.change_count > 0
                                                                        ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                                                                        : "bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400"
                                                                }`}
                                                            >
                                                                {m.change_count > 0 ? (
                                                                    <ArrowUpRight className="h-3 w-3" />
                                                                ) : (
                                                                    <ArrowDownRight className="h-3 w-3" />
                                                                )}
                                                                {m.change_count > 0 ? `+${m.change_count}` : m.change_count} ({m.change_percent}%)
                                                            </div>
                                                        ) : m.has_data ? (
                                                            <span className="text-xs text-gray-400">0%</span>
                                                        ) : (
                                                            <span className="text-xs text-gray-300">-</span>
                                                        )}
                                                    </td>
                                                    <td className="py-3.5 px-4">
                                                        {m.has_data ? (
                                                            <Badge variant="outline" className="text-xs font-normal">
                                                                {m.clients_count} companies
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-xs text-gray-300">-</span>
                                                        )}
                                                    </td>
                                                    <td className="py-3.5 px-4 text-xs text-gray-600 dark:text-gray-300">
                                                        {m.has_data ? (
                                                            <div className="flex flex-wrap gap-1 max-w-xs">
                                                                {m.clients.slice(0, 3).map((c, idx) => (
                                                                    <span
                                                                        key={idx}
                                                                        className="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[11px]"
                                                                    >
                                                                        {c.short_name}: <strong>{c.count}</strong>
                                                                    </span>
                                                                ))}
                                                                {m.clients.length > 3 && (
                                                                    <span className="text-gray-400 text-[11px]">
                                                                        +{m.clients.length - 3} more
                                                                    </span>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-gray-300">-</span>
                                                        )}
                                                    </td>
                                                    <td className="py-3.5 px-4 text-right">
                                                        {m.has_data && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => setExpandedMonth(isExpanded ? null : m.month_key)}
                                                                className="h-8 px-2.5 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                            >
                                                                {isExpanded ? "Hide Breakdown" : "View Breakdown"}
                                                                <ChevronRight
                                                                    className={`h-3.5 w-3.5 ml-1 transition-transform ${
                                                                        isExpanded ? "rotate-90" : ""
                                                                    }`}
                                                                />
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>

                                                {/* EXPANDABLE CLIENT BREAKDOWN ROW */}
                                                {isExpanded && m.has_data && (
                                                    <tr className="bg-blue-50/40 dark:bg-gray-800/80 border-b border-blue-100 dark:border-gray-700">
                                                        <td colSpan={6} className="p-4 sm:p-5">
                                                            <div className="rounded-xl border border-blue-200/60 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 shadow-sm">
                                                                <div className="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                                                                    <h4 className="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200 flex items-center gap-1.5">
                                                                        <Building2 className="h-4 w-4 text-blue-600" />
                                                                        Client Payroll Breakdown for {m.month_name} {activeYear} ({m.total_count.toLocaleString()} Total Staff)
                                                                    </h4>
                                                                    <span className="text-xs text-gray-500">
                                                                        {m.clients_count} active clients
                                                                    </span>
                                                                </div>

                                                                <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 max-h-72 overflow-y-auto pr-1">
                                                                    {m.clients.map((c, cIdx) => (
                                                                        <div
                                                                            key={cIdx}
                                                                            className="flex items-center justify-between p-2.5 rounded-lg border border-gray-100 dark:border-gray-800 hover:border-blue-300 dark:hover:border-blue-600 bg-gray-50/60 dark:bg-gray-800/40 transition-colors"
                                                                        >
                                                                            <div className="flex flex-col min-w-0 pr-2">
                                                                                <span className="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                                                                                    {c.client_name}
                                                                                </span>
                                                                                <span className="text-[10px] text-gray-400 font-mono truncate">
                                                                                    {c.table}
                                                                                </span>
                                                                            </div>
                                                                            <div className="flex items-center gap-2 flex-shrink-0">
                                                                                <span className="text-sm font-extrabold text-blue-600 font-mono">
                                                                                    {c.count.toLocaleString()}
                                                                                </span>
                                                                                <Button
                                                                                    size="sm"
                                                                                    variant="outline"
                                                                                    className="h-7 px-2 text-[11px] text-gray-600 hover:text-blue-600 hover:border-blue-400"
                                                                                    onClick={() =>
                                                                                        handleOpenDrilldown(
                                                                                            c,
                                                                                            m.month_name,
                                                                                            activeYear
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Staff
                                                                                </Button>
                                                                            </div>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )}
                                            </>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* ALL-YEARS HISTORICAL COMPARISON TABLE */}
                <Card className="border shadow-sm bg-white dark:bg-gray-800">
                    <CardHeader className="pb-3 border-b border-gray-100 dark:border-gray-800">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Layers className="h-5 w-5 text-emerald-600" />
                                <CardTitle className="text-lg font-bold">Annual Payroll Headcount Comparison (All Years)</CardTitle>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-gray-800 bg-gray-50/75 dark:bg-gray-800/50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        <th className="py-3 px-4">Year</th>
                                        <th className="py-3 px-4">Average Monthly Staff</th>
                                        <th className="py-3 px-4">Peak Month Headcount</th>
                                        <th className="py-3 px-4">Active Months</th>
                                        <th className="py-3 px-4">Total Records Processed</th>
                                        <th className="py-3 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                                    {headcountData.yearly_summary.map((y) => (
                                        <tr
                                            key={y.year}
                                            className={`hover:bg-gray-50/80 dark:hover:bg-gray-800/50 transition-colors ${
                                                activeYear === y.year ? "bg-blue-50/30 dark:bg-blue-900/10 font-medium" : ""
                                            }`}
                                        >
                                            <td className="py-3.5 px-4 font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                                <Calendar className="h-4 w-4 text-blue-600" />
                                                {y.year}
                                                {activeYear === y.year && (
                                                    <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700">
                                                        Selected
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3.5 px-4 text-blue-600 font-extrabold font-mono text-base">
                                                {y.avg_monthly.toLocaleString()}
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <span className="text-emerald-600 font-bold font-mono">
                                                    {y.max_monthly.toLocaleString()}
                                                </span>{" "}
                                                <span className="text-xs text-gray-500">({y.peak_month})</span>
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <Badge variant="outline" className="text-xs">
                                                    {y.months_count} / 12
                                                </Badge>
                                            </td>
                                            <td className="py-3.5 px-4 font-mono text-gray-700 dark:text-gray-300">
                                                {y.total_volume.toLocaleString()}
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => setActiveYear(y.year)}
                                                    className="h-8 px-2.5 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                >
                                                    View {y.year} Breakdown <ChevronRight className="h-3 w-3 ml-1" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* DRILLDOWN EMPLOYEES MODAL */}
            <Dialog.Root open={drilldownOpen} onOpenChange={setDrilldownOpen}>
                <Dialog.Portal>
                    <Dialog.Overlay className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 animate-in fade-in" />
                    <Dialog.Content className="fixed left-[50%] top-[50%] translate-x-[-50%] translate-y-[-50%] w-[95vw] max-w-3xl max-h-[85vh] bg-white dark:bg-gray-900 rounded-2xl p-6 shadow-2xl z-50 flex flex-col focus:outline-none animate-in zoom-in-95">
                        <div className="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-800">
                            <div>
                                <Dialog.Title className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Users className="h-5 w-5 text-blue-600" />
                                    {drilldownTitle}
                                </Dialog.Title>
                                <Dialog.Description className="text-xs text-gray-500 mt-0.5">
                                    Showing up to 200 staff on payroll for this period ({filteredEmployees.length} records matching)
                                </Dialog.Description>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={exportDrilldownCSV}
                                    disabled={!filteredEmployees.length}
                                    className="h-8 text-xs flex items-center gap-1"
                                >
                                    <Download className="h-3.5 w-3.5" /> CSV
                                </Button>
                                <Dialog.Close asChild>
                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-gray-400 hover:text-gray-700">
                                        <Cross2Icon className="h-4 w-4" />
                                    </Button>
                                </Dialog.Close>
                            </div>
                        </div>

                        {/* Search Bar */}
                        <div className="py-3">
                            <div className="relative">
                                <Search className="h-4 w-4 absolute left-3 top-2.5 text-gray-400" />
                                <Input
                                    value={drilldownSearch}
                                    onChange={(e) => setDrilldownSearch(e.target.value)}
                                    placeholder="Search by Staff ID, Name, or Department..."
                                    className="pl-9 h-9 text-xs"
                                />
                            </div>
                        </div>

                        {/* Table of employees */}
                        <div className="flex-1 overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-xl">
                            {drilldownLoading ? (
                                <div className="flex items-center justify-center py-16 text-gray-400 text-xs">
                                    <RefreshCw className="h-5 w-5 animate-spin mr-2" /> Loading staff records...
                                </div>
                            ) : filteredEmployees.length > 0 ? (
                                <table className="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr className="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 font-semibold text-gray-500 uppercase tracking-wider sticky top-0">
                                            <th className="py-2.5 px-3">Staff ID</th>
                                            <th className="py-2.5 px-3">Employee Name</th>
                                            <th className="py-2.5 px-3">Department / Location</th>
                                            <th className="py-2.5 px-3 text-right">Net Pay (GHS)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {filteredEmployees.map((emp, i) => (
                                            <tr key={i} className="hover:bg-gray-50/80 dark:hover:bg-gray-800/50">
                                                <td className="py-2 px-3 font-mono font-medium text-gray-900 dark:text-gray-200">
                                                    {emp.staffid}
                                                </td>
                                                <td className="py-2 px-3 font-medium text-gray-800 dark:text-gray-200">
                                                    {emp.name}
                                                </td>
                                                <td className="py-2 px-3 text-gray-500">
                                                    {emp.department || "N/A"}
                                                </td>
                                                <td className="py-2 px-3 text-right font-mono font-bold text-emerald-600">
                                                    {Number(emp.netpay || 0).toLocaleString(undefined, {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2,
                                                    })}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <div className="text-center py-12 text-gray-400 text-xs">
                                    No staff records found.
                                </div>
                            )}
                        </div>
                    </Dialog.Content>
                </Dialog.Portal>
            </Dialog.Root>
        </AppLayout>
    );
}
