import { Head, router } from "@inertiajs/react";
import { AlertCircle, Users, BarChart, Calendar, DollarSign, Clock, FileText } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useState, useEffect, useMemo, useRef } from "react";
import ErrorBoundary from "@/components/ErrorBoundary";
import * as Dialog from "@radix-ui/react-dialog";
import { Cross2Icon } from "@radix-ui/react-icons";
import { ColumnDef, createColumnHelper, flexRender, getCoreRowModel, useReactTable } from "@tanstack/react-table";
import Papa from "papaparse";
import pdfMake from "pdfmake/build/pdfmake";
import pdfFonts from "pdfmake/build/vfs_fonts";
import { Line, Bar, Pie } from "react-chartjs-2";
import { Chart, registerables } from "chart.js";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

// Register Chart.js components
Chart.register(...registerables);

// Set up pdfMake fonts
pdfMake.vfs = pdfFonts;
pdfMake.fonts = {
  Roboto: {
    normal: 'Roboto-Regular.ttf',
    bold: 'Roboto-Medium.ttf',
    italics: 'Roboto-Italic.ttf',
    bolditalics: 'Roboto-MediumItalic.ttf'
  }
};
console.log("VFS contents:", Object.keys(pdfMake.vfs));

interface EmployeePerformance {
  staffida: string;
  name: string;
  department: string;
  position: string;
  grade: string;
  workplace: string;
  performstand: number;
  performbonus: number;
  coefficient: number;
  commission: number;
  workdays: number;
  leavedays: number;
  netpay: number;
  leavededuction: number;
  otherdeduction: number;
  basic: number;
  allowance: number;
  otworkdays: number;
  otweekend: number;
}

interface ComparisonData {
  month: string;
  performstand: number;
  performbonus: number;
  commission: number;
  workdays: number;
  leavedays: number;
  netpay: number;
  otworkdays: number;
  otweekend: number;
  performstandChange?: number;
  performbonusChange?: number;
  commissionChange?: number;
  workdaysChange?: number;
  leavedaysChange?: number;
}

interface PerformanceData {
  employee: EmployeePerformance;
  comparisonData: ComparisonData[];
  availableEmployees: { staffida: string; name: string }[];
  availableMonths: string[];
}

interface Props {
  performanceData?: Partial<PerformanceData>;
  auth: { user: { name: string } };
  flash?: {
    success?: string;
    error?: string;
  };
}

// Define TDocumentDefinitions locally
interface TDocumentDefinitions {
  content: Array<any>;
  styles?: Record<string, any>;
  pageMargins?: [number, number, number, number];
  defaultStyle?: { font?: string; fontSize?: number };
  header?: any;
  footer?: (currentPage: number, pageCount: number) => any;
  pageSize?: string;
}

const breadcrumbs = [{ title: "Performance Tracker", href: "/performance" }];

const defaultPerformanceData: PerformanceData = {
  employee: {
    staffida: "",
    name: "",
    department: "",
    position: "",
    grade: "",
    workplace: "",
    performstand: 0,
    performbonus: 0,
    coefficient: 1,
    commission: 0,
    workdays: 0,
    leavedays: 0,
    netpay: 0,
    leavededuction: 0,
    otherdeduction: 0,
    basic: 0,
    allowance: 0,
    otworkdays: 0,
    otweekend: 0,
  },
  comparisonData: [],
  availableEmployees: [],
  availableMonths: [],
};

const columnHelper = createColumnHelper<ComparisonData>();

export default function PerformanceTracker({ performanceData: partialPerformanceData = {}, auth, flash }: Props) {
  const performanceData: PerformanceData = { ...defaultPerformanceData, ...partialPerformanceData };
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [selectedEmployee, setSelectedEmployee] = useState<string>(performanceData.employee.staffida || "");
  const [selectedMonth, setSelectedMonth] = useState<string>(performanceData.availableMonths[0] || "");
  const [comparisonPeriod, setComparisonPeriod] = useState<"3" | "6">("3");
  const [modalData, setModalData] = useState<{
    title: string;
    items: ComparisonData[];
    open: boolean;
  }>({ title: "", items: [], open: false });
  const lineChartRef = useRef<Chart<"line">>(null);
  const barChartRef = useRef<Chart<"bar">>(null);
  const pieChartRef = useRef<Chart<"pie">>(null);

  useEffect(() => {
    if (performanceData.employee.staffida && performanceData.employee.staffida !== selectedEmployee) {
      setSelectedEmployee(performanceData.employee.staffida);
    }
  }, [performanceData.employee.staffida]);

  useEffect(() => {
    if (performanceData.availableMonths.length > 0 && (!selectedMonth || !performanceData.availableMonths.includes(selectedMonth))) {
      setSelectedMonth(performanceData.availableMonths[0]);
    }
  }, [performanceData.availableMonths]);

  useEffect(() => {
    console.log("Performance Tracker props:", { performanceData, auth, flash });
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
    if (showToast) {
      const timeout = toastType === "error" ? 6000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  // Generate Summary Insights
  const summaryInsights = useMemo(() => {
    const current = performanceData.employee;
    const previous = performanceData.comparisonData.find((data) => data.month !== selectedMonth);
    let narrative = `${current.name} showed `;
    let achievements: string[] = [];
    let improvements: string[] = [];

    if (previous) {
      const performstandChange = current.performstand - previous.performstand;
      const performbonusChange = current.performbonus - previous.performbonus;
      const commissionChange = current.commission - previous.commission;
      const workdaysChange = current.workdays - previous.workdays;
      const leavedaysChange = current.leavedays - previous.leavedays;
      const overtimeTotal = current.otworkdays + current.otweekend;
      const prevOvertimeTotal = previous.otworkdays + previous.otweekend;

      narrative += performstandChange > 0
        ? `strong performance in ${selectedMonth}, with a ${((performstandChange / previous.performstand) * 100).toFixed(2)}% increase in performance score`
        : `performance in ${selectedMonth}, with a ${((performstandChange / previous.performstand) * 100).toFixed(2)}% decrease in performance score`;

      if (performbonusChange > 0) {
        achievements.push(`Increased performance bonus by ${((performbonusChange / previous.performbonus) * 100).toFixed(2)}%`);
      } else if (performbonusChange < 0) {
        improvements.push(`Performance bonus decreased by ${((performbonusChange / previous.performbonus) * 100).toFixed(2)}%`);
      }

      if (commissionChange > 0) {
        achievements.push(`Increased commission by ${((commissionChange / previous.commission) * 100).toFixed(2)}%`);
      } else if (commissionChange < 0) {
        improvements.push(`Commission decreased by ${((commissionChange / previous.commission) * 100).toFixed(2)}%`);
      }

      if (overtimeTotal > prevOvertimeTotal) {
        achievements.push(`Increased overtime by ${overtimeTotal - prevOvertimeTotal} hours`);
      } else if (overtimeTotal < prevOvertimeTotal) {
        improvements.push(`Reduced overtime by ${prevOvertimeTotal - overtimeTotal} hours`);
      }

      if (leavedaysChange < 0) {
        achievements.push(`Reduced leave days by ${Math.abs(leavedaysChange)}`);
      } else if (leavedaysChange > 0) {
        improvements.push(`Increased leave days by ${leavedaysChange}`);
      }

      narrative += `.`;
    } else {
      narrative += `performance in ${selectedMonth}.`;
    }

    return { narrative, achievements, improvements };
  }, [performanceData.employee, performanceData.comparisonData, selectedMonth]);

  const columns: ColumnDef<ComparisonData>[] = useMemo(
    () => [
      columnHelper.accessor("month", {
        header: "Month",
        cell: ({ row }) => row.original.month,
      }),
      columnHelper.accessor("performstand", {
        header: "Performance Score",
        cell: ({ row }) => row.original.performstand.toFixed(2),
      }),
      columnHelper.accessor("performstandChange", {
        header: "Score Change (%)",
        cell: ({ row }) => (
          <span
            className={
              row.original.performstandChange && row.original.performstandChange > 0
                ? "text-green-500"
                : row.original.performstandChange && row.original.performstandChange < 0
                ? "text-red-500"
                : ""
            }
          >
            {row.original.performstandChange ? `${row.original.performstandChange.toFixed(2)}%` : "N/A"}
          </span>
        ),
      }),
      columnHelper.accessor("performbonus", {
        header: "Performance Bonus",
        cell: ({ row }) => `${row.original.performbonus.toFixed(2)}`,
      }),
      columnHelper.accessor("performbonusChange", {
        header: "Bonus Change (%)",
        cell: ({ row }) => (
          <span
            className={
              row.original.performbonusChange && row.original.performbonusChange > 0
                ? "text-green-500"
                : row.original.performbonusChange && row.original.performbonusChange < 0
                ? "text-red-500"
                : ""
            }
          >
            {row.original.performbonusChange ? `${row.original.performbonusChange.toFixed(2)}%` : "N/A"}
          </span>
        ),
      }),
      columnHelper.accessor("commission", {
        header: "Commission",
        cell: ({ row }) => `${row.original.commission.toFixed(2)}`,
      }),
      columnHelper.accessor("commissionChange", {
        header: "Commission Change (%)",
        cell: ({ row }) => (
          <span
            className={
              row.original.commissionChange && row.original.commissionChange > 0
                ? "text-green-500"
                : row.original.commissionChange && row.original.commissionChange < 0
                ? "text-red-500"
                : ""
            }
          >
            {row.original.commissionChange ? `${row.original.commissionChange.toFixed(2)}%` : "N/A"}
          </span>
        ),
      }),
      columnHelper.accessor("workdays", {
        header: "Work Days",
        cell: ({ row }) => row.original.workdays.toFixed(2),
      }),
      columnHelper.accessor("workdaysChange", {
        header: "Work Days Change (%)",
        cell: ({ row }) => (
          <span
            className={
              row.original.workdaysChange && row.original.workdaysChange > 0
                ? "text-green-500"
                : row.original.workdaysChange && row.original.workdaysChange < 0
                ? "text-red-500"
                : ""
            }
          >
            {row.original.workdaysChange ? `${row.original.workdaysChange.toFixed(2)}%` : "N/A"}
          </span>
        ),
      }),
      columnHelper.accessor("leavedays", {
        header: "Leave Days",
        cell: ({ row }) => row.original.leavedays.toFixed(2),
      }),
      columnHelper.accessor("leavedaysChange", {
        header: "Leave Days Change (%)",
        cell: ({ row }) => (
          <span
            className={
              row.original.leavedaysChange && row.original.leavedaysChange > 0
                ? "text-red-500"
                : row.original.leavedaysChange && row.original.leavedaysChange < 0
                ? "text-green-500"
                : ""
            }
          >
            {row.original.leavedaysChange ? `${row.original.leavedaysChange.toFixed(2)}%` : "N/A"}
          </span>
        ),
      }),
    ],
    []
  );

  const table = useReactTable({
    data: modalData.items,
    columns,
    getCoreRowModel: getCoreRowModel(),
  });

  const openModal = (title: string, items: ComparisonData[]) => {
    console.log("Opening modal with title:", title, "items:", items);
    setModalData({ title, items, open: true });
  };

  const closeModal = () => {
    setModalData({ title: "", items: [], open: false });
  };

  const handleExportCSV = () => {
    const headers = [
      "Month",
      "Performance Score",
      "Score Change (%)",
      "Performance Bonus",
      "Bonus Change (%)",
      "Commission",
      "Commission Change (%)",
      "Work Days",
      "Work Days Change (%)",
      "Leave Days",
      "Leave Days Change (%)",
    ];
    const data = modalData.items.map((item) => ({
      Month: item.month,
      "Performance Score": item.performstand.toFixed(2),
      "Score Change (%)": item.performstandChange ? item.performstandChange.toFixed(2) : "N/A",
      "Performance Bonus": `${item.performbonus.toFixed(2)}`,
      "Bonus Change (%)": item.performbonusChange ? item.performbonusChange.toFixed(2) : "N/A",
      "Commission": `${item.commission.toFixed(2)}`,
      "Commission Change (%)": item.commissionChange ? item.commissionChange.toFixed(2) : "N/A",
      "Work Days": item.workdays.toFixed(2),
      "Work Days Change (%)": item.workdaysChange ? item.workdaysChange.toFixed(2) : "N/A",
      "Leave Days": item.leavedays.toFixed(2),
      "Leave Days Change (%)": item.leavedaysChange ? item.leavedaysChange.toFixed(2) : "N/A",
    }));
    const csv = Papa.unparse([headers, ...data.map(Object.values)]);
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${modalData.title.replace(/\s+/g, "_").toLowerCase()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const handleExportPDF = () => {
    console.log("Export PDF clicked");
    setTimeout(() => {
      const lineChartImage = lineChartRef.current?.toBase64Image();
      const barChartImage = barChartRef.current?.toBase64Image();
      const pieChartImage = pieChartRef.current?.toBase64Image();
      console.log("Chart images:", { lineChartImage, barChartImage, pieChartImage });

      const docDefinition: TDocumentDefinitions = {
        pageSize: 'A4',
        pageMargins: [40, 60, 40, 40],
        defaultStyle: {
          font: 'Roboto',
          fontSize: 11,
          color: '#333333',
        },
        header: {
          text: `Performance Report - ${selectedMonth}`,
          fontSize: 7,
          alignment: 'right',
          margin: [0, 20, 40, 0],
        },
        footer: (currentPage, pageCount) => ({
          text: `Page ${currentPage} of ${pageCount}`,
          alignment: 'center',
          fontSize: 8,
          margin: [0, 10, 0, 0],
        }),
        content: [
          // Cover Page with Employee Details
          {
            text: `Employee Performance Report\n${selectedMonth}`,
            style: 'coverTitle',
            alignment: 'center',
            margin: [0, 50, 0, 20],
          },
          {
            text: `Prepared for: ${performanceData.employee.name} (ID: ${performanceData.employee.staffida})`,
            style: 'coverSubtitle',
            alignment: 'center',
            margin: [0, 0, 0, 30],
          },
          {
            stack: [
              { text: 'Employee Details', style: 'subheader', alignment: 'center', margin: [0, 0, 0, 10] },
              {
                table: {
                  widths: ['50%', '50%'],
                  body: [
                    [
                      { text: 'Name', style: 'tableHeader', alignment: 'right', margin: [0, 5, 10, 5] },
                      { text: performanceData.employee.name, style: 'bodyText', margin: [10, 5, 0, 5] },
                    ],
                    [
                      { text: 'ID', style: 'tableHeader', alignment: 'right', margin: [0, 5, 10, 5] },
                      { text: performanceData.employee.staffida, style: 'bodyText', margin: [10, 5, 0, 5] },
                    ],
                    [
                      { text: 'Department', style: 'tableHeader', alignment: 'right', margin: [0, 5, 10, 5] },
                      { text: performanceData.employee.department, style: 'bodyText', margin: [10, 5, 0, 5] },
                    ],
                    [
                      { text: 'Position', style: 'tableHeader', alignment: 'right', margin: [0, 5, 10, 5] },
                      { text: performanceData.employee.position, style: 'bodyText', margin: [10, 5, 0, 5] },
                    ],
                    [
                      { text: 'Grade', style: 'tableHeader', alignment: 'right', margin: [0, 5, 10, 5] },
                      { text: performanceData.employee.grade, style: 'bodyText', margin: [10, 5, 0, 5] },
                    ],
                    [
                      { text: 'Workplace', style: 'tableHeader', alignment: 'right', margin: [0, 5, 10, 5] },
                      { text: performanceData.employee.workplace, style: 'bodyText', margin: [10, 5, 0, 5] },
                    ],
                  ],
                },
                layout: {
                  fillColor: (rowIndex: number) => (rowIndex % 2 === 0 ? '#F7FAFC' : '#FFFFFF'),
                  hLineWidth: () => 0.5,
                  vLineWidth: () => 0.5,
                  hLineColor: () => '#E5E7EB',
                  vLineColor: () => '#E5E7EB',
                },
                margin: [50, 0, 50, 0],
                alignment: 'center',
              },
            ],
            fillColor: '#F9FAFB',
            margin: [0, 20, 0, 20],
            border: [true, true, true, true],
          },
          {
            text: 'Generated by SBP Africa HR',
            style: 'coverFooter',
            alignment: 'center',
            margin: [0, 20, 0, 0],
          },
          { text: '', pageBreak: 'after' },

          // Performance Overview (Table)
          {
            text: 'Performance Overview',
            style: 'sectionHeader',
            margin: [0, 20, 0, 10],
          },
          { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 2, color: '#2563EB' }] },
          {
            table: {
              headerRows: 1,
              widths: ['50%', '50%'],
              body: [
                [
                  { text: 'Metric', style: 'tableHeader' },
                  { text: 'Value', style: 'tableHeader' },
                ],
                [
                  'Performance Score (/100)',
                  performanceData.employee.performstand.toFixed(2),
                ],
                [
                  'Performance Bonus',
                  performanceData.employee.performbonus.toFixed(2),
                ],
                [
                  'Commission',
                  performanceData.employee.commission.toFixed(2),
                ],
                [
                  'Work Days',
                  performanceData.employee.workdays.toFixed(2),
                ],
                [
                  'Leave Days',
                  performanceData.employee.leavedays.toFixed(2),
                ],
                [
                  'Overtime (hours)',
                  `${(performanceData.employee.otworkdays + performanceData.employee.otweekend).toFixed(2)} (${performanceData.employee.otworkdays.toFixed(2)} working days, ${performanceData.employee.otweekend.toFixed(2)} weekend)`,
                ],
                [
                  'Net Pay',
                  performanceData.employee.netpay.toFixed(2),
                ],
                [
                  'Deductions',
                  `${performanceData.employee.leavededuction.toFixed(2)} (leave), ${performanceData.employee.otherdeduction.toFixed(2)} (other)`,
                ],
              ],
            },
            layout: {
              fillColor: (rowIndex: number) => (rowIndex % 2 === 0 && rowIndex !== 0 ? '#F7FAFC' : null),
              hLineWidth: () => 0.5,
              vLineWidth: () => 0.5,
              hLineColor: () => '#E5E7EB',
              vLineColor: () => '#E5E7EB',
            },
            margin: [0, 10, 0, 20],
          },

          // Month-on-Month Comparison
          {
            text: 'Month-on-Month Comparison',
            style: 'sectionHeader',
            margin: [0, 20, 0, 10],
          },
          { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 2, color: '#2563EB' }] },
          {
            table: {
              headerRows: 1,
              widths: ['25%', '25%', '25%', '25%'],
              body: [
                [
                  { text: 'Metric', style: 'tableHeader' },
                  { text: selectedMonth, style: 'tableHeader' },
                  { text: performanceData.comparisonData[1]?.month || 'Previous Month', style: 'tableHeader' },
                  { text: 'Change', style: 'tableHeader' },
                ],
                [
                  'Performance Score',
                  performanceData.employee.performstand.toFixed(2),
                  performanceData.comparisonData[1]?.performstand.toFixed(2) || 'N/A',
                  performanceData.comparisonData[0]?.performstandChange
                    ? { text: `${performanceData.comparisonData[0].performstandChange.toFixed(2)}%`, color: performanceData.comparisonData[0].performstandChange > 0 ? '#16A34A' : '#DC2626' }
                    : 'N/A',
                ],
                [
                  'Performance Bonus',
                  performanceData.employee.performbonus.toFixed(2),
                  performanceData.comparisonData[1]?.performbonus.toFixed(2) || 'N/A',
                  performanceData.comparisonData[0]?.performbonusChange
                    ? { text: `${performanceData.comparisonData[0].performbonusChange.toFixed(2)}%`, color: performanceData.comparisonData[0].performbonusChange > 0 ? '#16A34A' : '#DC2626' }
                    : 'N/A',
                ],
                [
                  'Commission',
                  performanceData.employee.commission.toFixed(2),
                  performanceData.comparisonData[1]?.commission.toFixed(2) || 'N/A',
                  performanceData.comparisonData[0]?.commissionChange
                    ? { text: `${performanceData.comparisonData[0].commissionChange.toFixed(2)}%`, color: performanceData.comparisonData[0].commissionChange > 0 ? '#16A34A' : '#DC2626' }
                    : 'N/A',
                ],
                [
                  'Work Days',
                  performanceData.employee.workdays.toFixed(2),
                  performanceData.comparisonData[1]?.workdays.toFixed(2) || 'N/A',
                  performanceData.comparisonData[0]?.workdaysChange
                    ? { text: `${performanceData.comparisonData[0].workdaysChange.toFixed(2)}%`, color: performanceData.comparisonData[0].workdaysChange > 0 ? '#16A34A' : '#DC2626' }
                    : 'N/A',
                ],
                [
                  'Leave Days',
                  performanceData.employee.leavedays.toFixed(2),
                  performanceData.comparisonData[1]?.leavedays.toFixed(2) || 'N/A',
                  performanceData.comparisonData[0]?.leavedaysChange
                    ? { text: `${performanceData.comparisonData[0].leavedaysChange.toFixed(2)}%`, color: performanceData.comparisonData[0].leavedaysChange > 0 ? '#DC2626' : '#16A34A' }
                    : 'N/A',
                ],
              ],
            },
            layout: {
              fillColor: (rowIndex: number) => (rowIndex % 2 === 0 && rowIndex !== 0 ? '#F7FAFC' : null),
              hLineWidth: () => 0.5,
              vLineWidth: () => 0.5,
              hLineColor: () => '#E5E7EB',
              vLineColor: () => '#E5E7EB',
            },
            margin: [0, 10, 0, 20],
          },

          // Visualizations
          {
            text: 'Visualizations',
            style: 'sectionHeader',
            pageBreak: 'before',
          },
          { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 2, color: '#2563EB' }] },
          {
            stack: [
              { text: 'Performance Coefficient Trend', style: 'subheader' },
              lineChartImage
                ? { image: lineChartImage, width: 450, alignment: 'center', margin: [0, 10, 0, 5] }
                : { text: 'No chart available', style: 'bodyText' },
              { text: 'Higher coefficients indicate better performance scaling.', style: 'caption' },
            ],
            margin: [0, 10, 0, 20],
          },
          {
            stack: [
              { text: 'Work Effort Comparison', style: 'subheader' },
              barChartImage
                ? { image: barChartImage, width: 450, alignment: 'center', margin: [0, 10, 0, 5] }
                : { text: 'No chart available', style: 'bodyText' },
              { text: 'Comparison of work days, leave days, overtime, and coefficient.', style: 'caption' },
            ],
            margin: [0, 10, 0, 20],
          },
          {
            stack: [
              { text: 'Gross Pay Breakdown', style: 'subheader' },
              pieChartImage
                ? { image: pieChartImage, width: 350, alignment: 'center', margin: [0, 10, 0, 5] }
                : { text: 'No chart available', style: 'bodyText' },
              { text: 'Breakdown of gross pay components (basic, allowance, bonus, commission).', style: 'caption' },
            ],
            margin: [0, 10, 0, 20],
          },

          // Summary Insights
          {
            text: 'Summary Insights',
            style: 'sectionHeader',
            margin: [0, 20, 0, 10],
          },
          { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 2, color: '#2563EB' }] },
          [
            { text: summaryInsights.narrative, style: 'bodyText', margin: [0, 10, 0, 10] },
            summaryInsights.achievements.length > 0
              ? [
                  { text: 'Key Achievements:', style: 'subheader', margin: [0, 10, 0, 5] },
                  { ul: summaryInsights.achievements, style: 'bodyText', margin: [20, 5, 0, 10] },
                ]
              : null,
            summaryInsights.improvements.length > 0
              ? [
                  { text: 'Areas for Improvement:', style: 'subheader', margin: [0, 10, 0, 5] },
                  { ul: summaryInsights.improvements, style: 'bodyText', margin: [20, 5, 0, 10] },
                ]
              : null,
          ].filter(Boolean),
        ],
        styles: {
          coverTitle: { fontSize: 24, bold: true, color: '#1E3A8A', alignment: 'center' },
          coverSubtitle: { fontSize: 16, color: '#4B5563', alignment: 'center' },
          coverFooter: { fontSize: 9, color: '#6B7280', alignment: 'center', italics: true },
          sectionHeader: { fontSize: 16, bold: true, color: '#1E3A8A', margin: [0, 10, 0, 5], fillColor: '#EFF6FF' },
          subheader: { fontSize: 14, bold: true, color: '#374151' },
          bodyText: { fontSize: 11, color: '#333333' },
          tableHeader: { fontSize: 11, bold: true, color: '#1E3A8A', fillColor: '#DBEAFE', alignment: 'center' },
          caption: { fontSize: 9, italics: true, color: '#6B7280', margin: [0, 5, 0, 10] },
        },
      };

      console.log("docDefinition:", JSON.stringify(docDefinition, null, 2));
      try {
        pdfMake.createPdf(docDefinition).download(`performance_report_${performanceData.employee.staffida}_${selectedMonth.replace(/\s+/g, "_").toLowerCase()}.pdf`);
      } catch (error) {
        console.error("PDF creation failed:", error);
      }
    }, 500);
  };

  const lineChartData = {
    labels: performanceData.comparisonData.map((data) => data.month),
    datasets: [
      {
        label: "Performance Coefficient (%)",
        data: performanceData.comparisonData.map((data) => (data.coefficient || performanceData.employee.coefficient) * 100),
        borderColor: "rgba(59, 130, 246, 1)", // blue-500
        backgroundColor: "rgba(59, 130, 246, 0.2)",
        fill: true,
        tension: 0.4,
      },
    ],
  };

  const barChartData = {
    labels: [selectedMonth],
    datasets: [
      {
        label: "Work Days",
        data: [performanceData.employee.workdays],
        backgroundColor: "rgba(59, 130, 246, 0.5)", // blue-500
      },
      {
        label: "Leave Days",
        data: [performanceData.employee.leavedays],
        backgroundColor: "rgba(239, 68, 68, 0.5)", // red-500
      },
      {
        label: "OT Work Days",
        data: [performanceData.employee.otworkdays],
        backgroundColor: "rgba(34, 197, 94, 0.5)", // green-500
      },
      {
        label: "OT Weekend",
        data: [performanceData.employee.otweekend],
        backgroundColor: "rgba(249, 115, 22, 0.5)", // orange-500
      },
      {
        label: "Coefficient (%)",
        data: [performanceData.employee.coefficient * 100],
        backgroundColor: "rgba(147, 51, 234, 0.5)", // purple-500
      },
    ],
  };

  const pieChartData = {
    labels: ["Basic", "Allowance", "Performance Bonus", "Commission"],
    datasets: [
      {
        data: [
          performanceData.employee.basic || 0,
          performanceData.employee.allowance || 0,
          performanceData.employee.performbonus || 0,
          performanceData.employee.commission || 0,
        ],
        backgroundColor: [
          "rgba(59, 130, 246, 0.5)", // blue-500
          "rgba(34, 197, 94, 0.5)", // green-500
          "rgba(239, 68, 68, 0.5)", // red-500
          "rgba(249, 115, 22, 0.5)", // orange-500
        ],
        borderColor: [
          "rgba(59, 130, 246, 1)",
          "rgba(34, 197, 94, 1)",
          "rgba(239, 68, 68, 1)",
          "rgba(249, 115, 22, 1)",
        ],
        borderWidth: 1,
      },
    ],
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: "top" as const,
        labels: {
          font: {
            size: 12,
          },
          color: "#656565",
        },
      },
      tooltip: {
        backgroundColor: window.matchMedia("(prefers-color-scheme: dark)").matches ? "#1F2937" : "#FFFFFF",
        titleColor: "#656565",
        bodyColor: "#656565",
        borderColor: window.matchMedia("(prefers-color-scheme: dark)").matches ? "#4B5563" : "#D1D5DB",
        borderWidth: 1,
      },
    },
    scales: {
      x: {
        grid: {
          display: false,
        },
        ticks: {
          color: "#656565",
        },
      },
      y: {
        min: 0,
        max: 100,
        ticks: {
          callback: (value: number) => `${value}%`,
          color: "#656565",
          stepSize: 10,
        },
        grid: {
          color: window.matchMedia("(prefers-color-scheme: dark)").matches ? "#4B5563" : "#E5E7EB",
        },
      },
    },
  };

  const pieChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: "right" as const,
        labels: {
          font: {
            size: 12,
          },
          color: "#656565",
        },
      },
      tooltip: {
        backgroundColor: window.matchMedia("(prefers-color-scheme: dark)").matches ? "#1F2937" : "#FFFFFF",
        titleColor: "#656565",
        bodyColor: "#656565",
        borderColor: window.matchMedia("(prefers-color-scheme: dark)").matches ? "#4B5563" : "#D1D5DB",
        borderWidth: 1,
      },
    },
  };

  const SummaryCard = ({
    icon: Icon,
    title,
    value,
    unit = "",
    color = "text-blue-500",
  }: {
    icon: any;
    title: string;
    value: number | string;
    unit?: string;
    color?: string;
  }) => (
    <Card className="shadow-none border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900">
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <Icon className={`h-5 w-5 ${color}`} />
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <span className={`text-sm font-medium ${color}`}>
          {typeof value === "number" ? `${value.toFixed(2)}` : value} {unit && typeof value === "string" ? unit : ""}
        </span>
      </CardContent>
    </Card>
  );

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Employee Performance Tracker" />
        <div className="flex h-full flex-1 flex-col gap-6 p-6 bg-gray-50 dark:bg-gray-900 rounded-xl">
          {/* Toast Notification */}
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? <Users className="h-5 w-5" /> : <AlertCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
              {toastType === "error" && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="ml-2 p-1 text-white hover:text-white hover:bg-red-600"
                  onClick={() => setShowToast(false)}
                >
                  <AlertCircle className="h-4 w-4" />
                </Button>
              )}
            </div>
          )}

          {/* Modal for Comparison Table */}
          <Dialog.Root open={modalData.open} onOpenChange={closeModal}>
            <Dialog.Portal>
              <Dialog.Overlay className="fixed inset-0 bg-black/50 backdrop-blur-sm z-40" />
              <Dialog.Content
                role="dialog"
                className="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-50 bg-white dark:bg-gray-800 rounded-lg p-6 shadow-xl border border-gray-200 dark:border-gray-700 max-w-5xl w-full mx-4 max-h-[80vh] overflow-hidden"
              >
                <div className="flex justify-between items-center mb-4">
                  <Dialog.Title className="text-lg font-semibold text-gray-800 dark:text-gray-100">{modalData.title}</Dialog.Title>
                  <Dialog.Close asChild>
                    <button className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" aria-label="Close">
                      <Cross2Icon className="h-6 w-6" />
                    </button>
                  </Dialog.Close>
                </div>
                <Dialog.Description className="sr-only">Performance comparison table for {modalData.title}</Dialog.Description>
                <div className="max-h-[60vh] overflow-y-auto">
                  {modalData.items.length > 0 ? (
                    <table className="react-table w-full border-collapse border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                      <caption className="sr-only">{modalData.title} Table</caption>
                      <thead>
                        {table.getHeaderGroups().map((headerGroup) => (
                          <tr key={headerGroup.id} className="bg-gray-50 dark:bg-gray-700">
                            {headerGroup.headers.map((header) => (
                              <th
                                key={header.id}
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300 border border-gray-300 dark:border-gray-600"
                              >
                                {flexRender(header.column.columnDef.header, header.getContext())}
                              </th>
                            ))}
                          </tr>
                        ))}
                      </thead>
                      <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                        {table.getRowModel().rows.map((row) => (
                          <tr
                            key={row.id}
                            className={row.index % 2 === 0 ? "bg-white dark:bg-gray-800" : "bg-gray-50 dark:bg-gray-700"}
                          >
                            {row.getVisibleCells().map((cell) => (
                              <td
                                key={cell.id}
                                className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600"
                              >
                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                              </td>
                            ))}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  ) : (
                    <p className="text-gray-600 dark:text-gray-400">No data available for this selection.</p>
                  )}
                </div>
                <div className="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <Button
                    variant="secondary"
                    className="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600"
                    onClick={closeModal}
                  >
                    Close
                  </Button>
                  <Button className="bg-green-500 text-white hover:bg-green-600" onClick={handleExportCSV}>
                    Export to CSV
                  </Button>
                  <Button className="bg-red-500 text-white hover:bg-red-600" onClick={handleExportPDF}>
                    Export to PDF
                  </Button>
                </div>
              </Dialog.Content>
            </Dialog.Portal>
          </Dialog.Root>

          {/* Welcome Header and Export Button */}
          <div className="mb-8 flex justify-between items-center">
            <h1 className="text-2xl font-bold text-gray-800 dark:text-gray-100">
              Welcome, <span className="text-blue-600 dark:text-blue-400">{auth.user.name}</span>!
            </h1>
            <Button className="bg-blue-500 text-white hover:bg-blue-600" onClick={handleExportPDF}>
              Export PDF Report
            </Button>
          </div>

          {/* Filters */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900">
            <CardHeader>
              <CardTitle className="text-lg font-semibold text-gray-800 dark:text-gray-100">Filters</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col sm:flex-row gap-4">
              <Select
                value={selectedEmployee || performanceData.employee.staffida || ""}
                onValueChange={(val) => {
                  setSelectedEmployee(val);
                  router.get('/performance', {
                    staffida: val,
                    month: selectedMonth || performanceData.availableMonths[0] || "",
                    comparison_period: comparisonPeriod,
                  }, { preserveState: true, replace: true });
                }}
              >
                <SelectTrigger className="w-full sm:w-1/3">
                  <SelectValue placeholder="Select Employee" />
                </SelectTrigger>
                <SelectContent>
                  {performanceData.availableEmployees.map((emp) => (
                    <SelectItem key={emp.staffida} value={emp.staffida}>
                      {emp.name} ({emp.staffida})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select
                value={selectedMonth || performanceData.availableMonths[0] || ""}
                onValueChange={(val) => {
                  setSelectedMonth(val);
                  router.get('/performance', {
                    staffida: selectedEmployee || performanceData.employee.staffida || "",
                    month: val,
                    comparison_period: comparisonPeriod,
                  }, { preserveState: true, replace: true });
                }}
              >
                <SelectTrigger className="w-full sm:w-1/3">
                  <SelectValue placeholder="Select Month" />
                </SelectTrigger>
                <SelectContent>
                  {performanceData.availableMonths.map((month) => (
                    <SelectItem key={month} value={month}>
                      {month}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select
                value={comparisonPeriod}
                onValueChange={(val: "3" | "6") => {
                  setComparisonPeriod(val);
                  router.get('/performance', {
                    staffida: selectedEmployee || performanceData.employee.staffida || "",
                    month: selectedMonth || performanceData.availableMonths[0] || "",
                    comparison_period: val,
                  }, { preserveState: true, replace: true });
                }}
              >
                <SelectTrigger className="w-full sm:w-1/3">
                  <SelectValue placeholder="Select Comparison Period" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="3">Last 3 Months</SelectItem>
                  <SelectItem value="6">Last 6 Months</SelectItem>
                </SelectContent>
              </Select>
            </CardContent>
          </Card>

          {/* Employee Summary */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900">
            <CardHeader>
              <CardTitle className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Employee Summary - {selectedMonth}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-5">
                <SummaryCard icon={Users} title="Name" value={performanceData.employee.name} />
                <SummaryCard icon={FileText} title="Staff ID" value={performanceData.employee.staffida} />
                <SummaryCard icon={FileText} title="Department" value={performanceData.employee.department} />
                <SummaryCard icon={FileText} title="Position" value={performanceData.employee.position} />
                <SummaryCard icon={FileText} title="Grade" value={performanceData.employee.grade} />
                <SummaryCard icon={FileText} title="Workplace" value={performanceData.employee.workplace} />
                <SummaryCard
                  icon={BarChart}
                  title="Performance Score"
                  value={performanceData.employee.performstand}
                  unit="/100"
                  color="text-blue-500"
                />
                <SummaryCard
                  icon={DollarSign}
                  title="Performance Bonus"
                  value={performanceData.employee.performbonus}
                  color="text-green-500"
                />
                <SummaryCard
                  icon={DollarSign}
                  title="Commission"
                  value={performanceData.employee.commission}
                  color="text-red-500"
                />
                <SummaryCard
                  icon={Clock}
                  title="Work Days"
                  value={performanceData.employee.workdays}
                  color="text-blue-500"
                />
                <SummaryCard
                  icon={Clock}
                  title="Leave Days"
                  value={performanceData.employee.leavedays}
                  color="text-red-500"
                />
                <SummaryCard
                  icon={Clock}
                  title="Overtime"
                  value={performanceData.employee.otworkdays + performanceData.employee.otweekend}
                  unit="hours"
                  color="text-blue-500"
                />
                <SummaryCard
                  icon={DollarSign}
                  title="Net Pay"
                  value={performanceData.employee.netpay}
                  color="text-green-500"
                />
                <SummaryCard
                  icon={DollarSign}
                  title="Leave Deduction"
                  value={performanceData.employee.leavededuction}
                  color="text-red-500"
                />
                <SummaryCard
                  icon={DollarSign}
                  title="Other Deduction"
                  value={performanceData.employee.otherdeduction}
                  color="text-red-500"
                />
                <SummaryCard
                  icon={DollarSign}
                  title="Basic"
                  value={performanceData.employee.basic}
                  color="text-green-500"
                />
                <SummaryCard
                  icon={DollarSign}
                  title="Allowance"
                  value={performanceData.employee.allowance}
                  color="text-green-500"
                />
              </div>
            </CardContent>
          </Card>

          {/* Month-on-Month Comparison */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900">
            <CardHeader className="flex justify-between items-center">
              <CardTitle className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Month-on-Month Comparison
              </CardTitle>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => openModal(`Performance Comparison for ${performanceData.employee.name}`, performanceData.comparisonData)}
                className="p-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
              >
                <Users className="h-4 w-4" />
              </Button>
            </CardHeader>
            <CardContent>
              <div className="h-80">
                <Line ref={lineChartRef} data={lineChartData} options={chartOptions} />
              </div>
              <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
                Higher coefficients indicate better performance scaling.
              </p>
            </CardContent>
          </Card>

          {/* Work Effort Comparison */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900">
            <CardHeader>
              <CardTitle className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Work Effort Comparison - {selectedMonth}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="h-80">
                <Bar ref={barChartRef} data={barChartData} options={chartOptions} />
              </div>
              <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
                Comparison of work days, leave days, overtime, and coefficient.
              </p>
            </CardContent>
          </Card>

          {/* Pay Breakdown */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900">
            <CardHeader>
              <CardTitle className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Gross Pay Breakdown - {selectedMonth}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="h-80">
                <Pie ref={pieChartRef} data={pieChartData} options={pieChartOptions} />
              </div>
              <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
                Breakdown of gross pay components (basic, allowance, bonus, commission).
              </p>
            </CardContent>
          </Card>

          {/* Summary Insights */}
          <Card className="shadow-none border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900">
            <CardHeader>
              <CardTitle className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Summary Insights - {selectedMonth}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm text-gray-800 dark:text-gray-100">{summaryInsights.narrative}</p>
              {summaryInsights.achievements.length > 0 && (
                <div className="mt-4">
                  <h4 className="text-sm font-semibold text-gray-800 dark:text-gray-100">Key Achievements:</h4>
                  <ul className="list-disc list-inside text-sm text-gray-600 dark:text-gray-400">
                    {summaryInsights.achievements.map((item, index) => (
                      <li key={index}>{item}</li>
                    ))}
                  </ul>
                </div>
              )}
              {summaryInsights.improvements.length > 0 && (
                <div className="mt-4">
                  <h4 className="text-sm font-semibold text-gray-800 dark:text-gray-100">Areas for Improvement:</h4>
                  <ul className="list-disc list-inside text-sm text-gray-600 dark:text-gray-400">
                    {summaryInsights.improvements.map((item, index) => (
                      <li key={index}>{item}</li>
                    ))}
                  </ul>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
