import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import React, { useState } from "react";
import {
    Sparkles,
    Upload,
    AlertTriangle,
    ShieldCheck,
    CheckCircle2,
    FileSpreadsheet,
    Loader2,
    Layers,
    Search,
    Brain,
    Building2,
    Calendar,
    ArrowRight,
    Users
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";

interface ClientOption {
    client_name: string;
    slug: string;
}

interface InspectProps {
    clients: ClientOption[];
    defaultMonth: string;
}

interface ColumnMapping {
    [sourceHeader: string]: string;
}

interface AnomalyItem {
    type: string;
    severity: 'critical' | 'warning' | 'info';
    staff_id?: string;
    employee_name?: string;
    field?: string;
    message: string;
    suggested_fix?: string;
}

interface IngestionResult {
    success: boolean;
    client: string;
    period: string;
    tier_used: string;
    confidence: number;
    column_mapping: ColumnMapping;
    headers: string[];
    records: Array<{
        staff_id: string;
        name: string;
        basic_salary: number;
        gross_salary: number;
        ssnit_ee: number;
        petra: number;
        paye: number;
        net_pay: number;
        bank?: string;
        account?: string;
    }>;
    audit: {
        total_rows_scanned: number;
        critical_count: number;
        warning_count: number;
        info_count: number;
        anomalies: AnomalyItem[];
    };
    errors?: string[];
}

export default function Inspect({ clients, defaultMonth }: InspectProps) {
    const [selectedClient, setSelectedClient] = useState<string>(clients[0]?.slug || 'beninbunge');
    const [selectedMonth, setSelectedMonth] = useState<string>(defaultMonth || '2026-10');
    const [file, setFile] = useState<File | null>(null);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<IngestionResult | null>(null);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState<'anomalies' | 'columns' | 'records'>('anomalies');

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setFile(e.target.files[0]);
            setErrorMsg(null);
        }
    };

    const handleInspect = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!file) {
            setErrorMsg('Please select a spreadsheet file (.xlsx, .xls, .csv) to inspect.');
            return;
        }

        setLoading(true);
        setErrorMsg(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('client_slug', selectedClient);
        formData.append('month', selectedMonth);

        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            const res = await fetch('/payroll-ai/inspect', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await res.json();
            if (data.success) {
                setResult(data);
                if (data.audit?.anomalies?.length === 0) {
                    setActiveTab('records');
                } else {
                    setActiveTab('anomalies');
                }
            } else {
                setErrorMsg(data.errors?.[0] || data.message || 'Inspection failed to parse the spreadsheet.');
            }
        } catch (err: any) {
            setErrorMsg(err.message || 'Network error occurred while connecting to Pre-Flight Inspection Engine.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: "Dashboard", href: "/dashboard" },
                { title: "Payroll AI", href: "#" },
                { title: "Pre-Flight Inspection", href: "/payroll-ai/inspect" },
            ]}
        >
            <Head title="Pre-Flight Payroll AI Ingestion & Anomaly Audit" />

            <div className="container mx-auto p-4 md:p-6 space-y-6 max-w-7xl">
                {/* Header Title */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-5">
                    <div>
                        <div className="flex items-center gap-2 text-sky-600 font-semibold text-xs tracking-wider uppercase">
                            <Sparkles className="h-4 w-4 text-sky-500 animate-pulse" />
                            Pre-Flight Ingestion & Anomaly Engine
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 mt-1">
                            Payroll Pre-Flight Inspection
                        </h1>
                        <p className="text-sm text-slate-500 mt-0.5">
                            Simulate 3-tier intelligent column matching (Grok AI &rarr; Rules &rarr; Legacy) and detect fraud, salary spikes, or SSNIT/Petra gaps before committing records.
                        </p>
                    </div>
                </div>

                {/* Upload & Configuration Card */}
                <Card className="border-slate-200 shadow-sm">
                    <CardHeader className="bg-slate-50/50 border-b border-slate-100">
                        <CardTitle className="text-base flex items-center gap-2">
                            <Upload className="h-4 w-4 text-sky-600" />
                            Select Payroll Spreadsheet for Inspection
                        </CardTitle>
                        <CardDescription>
                            Supported file types: .xlsx, .xls, .csv (Max 5MB). Inspection is executed in-memory and will not write to your live database.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <form onSubmit={handleInspect} className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <Label htmlFor="client" className="text-xs font-semibold uppercase text-slate-600">
                                    Target Client
                                </Label>
                                <Select value={selectedClient} onValueChange={setSelectedClient}>
                                    <SelectTrigger id="client" className="mt-1">
                                        <SelectValue placeholder="Select Client" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {clients.map((c) => (
                                            <SelectItem key={c.slug} value={c.slug}>
                                                {c.client_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="month" className="text-xs font-semibold uppercase text-slate-600">
                                    Payroll Period (YYYY-MM)
                                </Label>
                                <Input
                                    id="month"
                                    type="month"
                                    value={selectedMonth}
                                    onChange={(e) => setSelectedMonth(e.target.value)}
                                    className="mt-1"
                                    required
                                />
                            </div>

                            <div>
                                <Label htmlFor="file" className="text-xs font-semibold uppercase text-slate-600">
                                    Spreadsheet File
                                </Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".xlsx,.xls,.csv,.ods"
                                    onChange={handleFileChange}
                                    className="mt-1 cursor-pointer"
                                    required
                                />
                            </div>

                            <div className="md:col-span-3 flex justify-end pt-2">
                                <Button
                                    type="submit"
                                    disabled={loading || !file}
                                    className="bg-sky-600 hover:bg-sky-700 text-white gap-2 px-6"
                                >
                                    {loading ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Analyzing with Grok AI & Rules...
                                        </>
                                    ) : (
                                        <>
                                            <Brain className="h-4 w-4" />
                                            Run Pre-Flight AI Inspection
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>

                        {errorMsg && (
                            <div className="mt-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 shrink-0 text-red-500" />
                                <span>{errorMsg}</span>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Inspection Results */}
                {result && (
                    <div className="space-y-6 animate-in fade-in duration-300">
                        {/* Summary Badges Grid */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <Card className="border-slate-200">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-slate-500 uppercase">Rows Parsed</p>
                                        <h3 className="text-2xl font-bold text-slate-800 mt-0.5">
                                            {result.records.length}
                                        </h3>
                                    </div>
                                    <div className="h-10 w-10 rounded-full bg-sky-50 flex items-center justify-center text-sky-600">
                                        <Users className="h-5 w-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-slate-500 uppercase">Matcher Tier Used</p>
                                        <h3 className="text-base font-bold text-slate-800 mt-1 capitalize flex items-center gap-1.5">
                                            <Badge variant="outline" className="bg-sky-50 text-sky-700 border-sky-200 font-semibold uppercase text-xs">
                                                {result.tier_used}
                                            </Badge>
                                        </h3>
                                    </div>
                                    <div className="h-10 w-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                        <Sparkles className="h-5 w-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-slate-500 uppercase">Match Confidence</p>
                                        <h3 className="text-2xl font-bold text-emerald-600 mt-0.5">
                                            {Math.round(result.confidence * 100)}%
                                        </h3>
                                    </div>
                                    <div className="h-10 w-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                        <CheckCircle2 className="h-5 w-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-slate-200">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-slate-500 uppercase">Anomalies Detected</p>
                                        <h3 className={`text-2xl font-bold mt-0.5 ${
                                            (result.audit.critical_count + result.audit.warning_count) > 0
                                                ? 'text-amber-600'
                                                : 'text-emerald-600'
                                        }`}>
                                            {result.audit.critical_count + result.audit.warning_count}
                                        </h3>
                                    </div>
                                    <div className="h-10 w-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
                                        <AlertTriangle className="h-5 w-5" />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Navigation Tabs */}
                        <div className="flex gap-2 border-b border-slate-200 pb-2">
                            <button
                                onClick={() => setActiveTab('anomalies')}
                                className={`px-4 py-2 text-sm font-semibold rounded-lg transition ${
                                    activeTab === 'anomalies'
                                        ? 'bg-sky-600 text-white'
                                        : 'text-slate-600 hover:bg-slate-100'
                                }`}
                            >
                                Anomaly & Fraud Audit ({result.audit.anomalies.length})
                            </button>
                            <button
                                onClick={() => setActiveTab('columns')}
                                className={`px-4 py-2 text-sm font-semibold rounded-lg transition ${
                                    activeTab === 'columns'
                                        ? 'bg-sky-600 text-white'
                                        : 'text-slate-600 hover:bg-slate-100'
                                }`}
                            >
                                3-Tier Column Mapping ({Object.keys(result.column_mapping).length})
                            </button>
                            <button
                                onClick={() => setActiveTab('records')}
                                className={`px-4 py-2 text-sm font-semibold rounded-lg transition ${
                                    activeTab === 'records'
                                        ? 'bg-sky-600 text-white'
                                        : 'text-slate-600 hover:bg-slate-100'
                                }`}
                            >
                                Parsed Records Preview ({result.records.length})
                            </button>
                        </div>

                        {/* TAB 1: Anomalies */}
                        {activeTab === 'anomalies' && (
                            <div className="space-y-3">
                                {result.audit.anomalies.length === 0 ? (
                                    <Card className="border-emerald-200 bg-emerald-50/50 p-6 text-center">
                                        <ShieldCheck className="h-12 w-12 text-emerald-600 mx-auto mb-2" />
                                        <h3 className="font-bold text-emerald-800 text-base">All Pre-Flight Checks Passed!</h3>
                                        <p className="text-emerald-700 text-sm mt-1">
                                            No duplicate accounts, salary spikes, or SSNIT/Petra variance detected across all {result.records.length} parsed records.
                                        </p>
                                    </Card>
                                ) : (
                                    result.audit.anomalies.map((anom, idx) => (
                                        <div
                                            key={idx}
                                            className={`p-4 rounded-xl border flex items-start gap-3 shadow-sm ${
                                                anom.severity === 'critical'
                                                    ? 'bg-red-50 border-red-200 text-red-900'
                                                    : anom.severity === 'warning'
                                                    ? 'bg-amber-50 border-amber-200 text-amber-900'
                                                    : 'bg-sky-50 border-sky-200 text-sky-900'
                                            }`}
                                        >
                                            <AlertTriangle className={`h-5 w-5 shrink-0 mt-0.5 ${
                                                anom.severity === 'critical'
                                                    ? 'text-red-600'
                                                    : anom.severity === 'warning'
                                                    ? 'text-amber-600'
                                                    : 'text-sky-600'
                                            }`} />
                                            <div className="flex-1 text-xs">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold uppercase tracking-wider text-[11px]">
                                                        {anom.type.replace(/_/g, ' ')}
                                                    </span>
                                                    {anom.staff_id && (
                                                        <Badge variant="outline" className="text-[10px] bg-white font-mono">
                                                            {anom.staff_id}
                                                        </Badge>
                                                    )}
                                                    {anom.employee_name && (
                                                        <span className="font-semibold text-slate-700">
                                                            {anom.employee_name}
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="mt-1 text-sm font-medium leading-relaxed">
                                                    {anom.message}
                                                </p>
                                                {anom.suggested_fix && (
                                                    <p className="mt-1 text-slate-600 italic">
                                                        Recommendation: {anom.suggested_fix}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        )}

                        {/* TAB 2: Column Mapping */}
                        {activeTab === 'columns' && (
                            <Card className="border-slate-200">
                                <CardContent className="p-0 overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Target DB Field</TableHead>
                                                <TableHead>Mapped Spreadsheet Header</TableHead>
                                                <TableHead>Resolution Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {Object.entries(result.column_mapping).map(([dbCol, sourceHeader]) => (
                                                <TableRow key={dbCol}>
                                                    <TableCell className="font-mono text-xs font-semibold text-sky-800">
                                                        {dbCol}
                                                    </TableCell>
                                                    <TableCell className="text-sm font-medium text-slate-800">
                                                        {sourceHeader}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[11px]">
                                                            ✓ Matched via {result.tier_used.toUpperCase()}
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        )}

                        {/* TAB 3: Parsed Records */}
                        {activeTab === 'records' && (
                            <Card className="border-slate-200">
                                <CardContent className="p-0 overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Staff ID</TableHead>
                                                <TableHead>Employee Name</TableHead>
                                                <TableHead className="text-right">Basic (GHS)</TableHead>
                                                <TableHead className="text-right">Gross (GHS)</TableHead>
                                                <TableHead className="text-right">SSNIT 0.5% (GHS)</TableHead>
                                                <TableHead className="text-right">Petra 5% (GHS)</TableHead>
                                                <TableHead className="text-right">Net Take-Home (GHS)</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {result.records.slice(0, 50).map((r, i) => (
                                                <TableRow key={i}>
                                                    <TableCell className="font-mono text-xs font-semibold">
                                                        {r.staff_id || '—'}
                                                    </TableCell>
                                                    <TableCell className="text-xs font-medium">
                                                        {r.name || '—'}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-xs">
                                                        {Number(r.basic_salary).toFixed(2)}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-xs font-semibold">
                                                        {Number(r.gross_salary).toFixed(2)}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-xs text-sky-700">
                                                        {Number(r.ssnit_ee).toFixed(2)}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-xs text-indigo-700">
                                                        {Number(r.petra).toFixed(2)}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-xs font-bold text-emerald-700">
                                                        {Number(r.net_pay).toFixed(2)}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                    {result.records.length > 50 && (
                                        <div className="p-3 bg-slate-50 text-center text-xs text-slate-500 border-t">
                                            Showing first 50 of {result.records.length} parsed employee records.
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
