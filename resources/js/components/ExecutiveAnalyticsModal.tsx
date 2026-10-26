import React, { useState } from 'react';
import {
    Sparkles,
    Search,
    BarChart3,
    ArrowUpRight,
    TrendingUp,
    FileSpreadsheet,
    HelpCircle,
    X,
    Loader2
} from 'lucide-react';

interface MetricCard {
    label: string;
    value: string;
    change?: string;
}

interface AnalyticsResult {
    question: string;
    headline: string;
    explanation: string;
    metrics: MetricCard[];
    chart?: {
        type: string;
        labels: string[];
        datasets: Array<{ label: string; data: number[] }>;
    } | null;
    table?: {
        columns: string[];
        rows: string[][];
    } | null;
    follow_up_questions: string[];
}

interface ExecutiveAnalyticsModalProps {
    isOpen: boolean;
    onClose: () => void;
}

export default function ExecutiveAnalyticsModal({ isOpen, onClose }: ExecutiveAnalyticsModalProps) {
    const [query, setQuery] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [result, setResult] = useState<AnalyticsResult | null>(null);
    const [error, setError] = useState('');

    const suggestions = [
        'What was our total Petra Tier 2 liability across all OFI sites for last quarter?',
        'Compare overtime spend between Twellium Accra and Twellium Kumasi.',
        'Which client had the highest gross payroll last month?',
        'Show active casual headcount and average daily rates across all factory clients.',
    ];

    if (!isOpen) return null;

    const handleAsk = async (questionToAsk?: string) => {
        const q = questionToAsk || query.trim();
        if (!q || isLoading) return;

        setIsLoading(true);
        setError('');
        if (questionToAsk) setQuery(questionToAsk);

        try {
            const res = await fetch('/executive-ai/ask', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ question: q }),
            });

            const data = await res.json();
            if (data.success && data.data) {
                setResult(data.data);
            } else {
                setError(data.message || 'Could not generate report.');
            }
        } catch {
            setError('Network error while processing analytics.');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div className="w-full max-w-4xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="px-6 py-4 bg-gradient-to-r from-indigo-700 via-purple-700 to-sky-700 text-white flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Sparkles className="h-6 w-6 text-amber-300 animate-pulse" />
                        <div>
                            <h3 className="font-bold text-base leading-tight">Ask HRS Anything — Executive Analytics</h3>
                            <p className="text-xs text-indigo-100">
                                Ask natural language questions across all 31 client payrolls, overtime costs, and Petra/SSNIT liabilities.
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="h-8 w-8 rounded-full hover:bg-white/20 flex items-center justify-center text-white/80 hover:text-white"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Search Bar & Suggestions */}
                <div className="p-6 bg-slate-50 border-b border-slate-200">
                    <div className="flex items-center gap-2 bg-white rounded-xl border border-slate-300 p-2 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500">
                        <Search className="h-5 w-5 text-slate-400 ml-2" />
                        <input
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleAsk()}
                            placeholder="e.g. Total Petra Tier 2 liability across OFI sites for Q2 2026..."
                            className="flex-1 bg-transparent border-none text-sm text-slate-800 placeholder-slate-400 focus:outline-none"
                        />
                        <button
                            onClick={() => handleAsk()}
                            disabled={!query.trim() || isLoading}
                            className="flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition disabled:opacity-50"
                        >
                            {isLoading ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" /> Thinking...
                                </>
                            ) : (
                                <>
                                    <Sparkles className="h-4 w-4 text-amber-300" /> Ask AI
                                </>
                            )}
                        </button>
                    </div>

                    {/* Quick Suggestion Pills */}
                    <div className="mt-3 flex items-center gap-2 overflow-x-auto scrollbar-none">
                        <span className="text-[11px] font-semibold text-slate-400 shrink-0">Try asking:</span>
                        {suggestions.map((s, idx) => (
                            <button
                                key={idx}
                                onClick={() => handleAsk(s)}
                                className="shrink-0 rounded-full bg-white hover:bg-indigo-50 hover:text-indigo-700 px-3 py-1 text-[11px] text-slate-600 border border-slate-200 transition"
                            >
                                {s}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Analytics Results Area */}
                <div className="flex-1 overflow-y-auto p-6 space-y-6">
                    {error && (
                        <div className="p-4 rounded-xl bg-rose-50 text-rose-800 border border-rose-200 text-sm">
                            {error}
                        </div>
                    )}

                    {result ? (
                        <div className="space-y-6 animate-in fade-in duration-300">
                            {/* Headline Banner */}
                            <div className="rounded-xl bg-gradient-to-r from-sky-50 to-indigo-50 border border-sky-100 p-5">
                                <span className="text-[11px] font-bold text-sky-600 uppercase tracking-wider">
                                    Executive Finding
                                </span>
                                <h4 className="text-xl font-bold text-slate-900 mt-1 leading-snug">
                                    {result.headline}
                                </h4>
                                {result.explanation && (
                                    <p className="text-xs text-slate-600 mt-1">{result.explanation}</p>
                                )}
                            </div>

                            {/* Metric Badges */}
                            {result.metrics && result.metrics.length > 0 && (
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    {result.metrics.map((metric, idx) => (
                                        <div
                                            key={idx}
                                            className="p-4 rounded-xl bg-white border border-slate-200 shadow-sm"
                                        >
                                            <span className="text-xs text-slate-500 font-medium">{metric.label}</span>
                                            <div className="text-2xl font-black text-slate-800 mt-1">
                                                {metric.value}
                                            </div>
                                            {metric.change && (
                                                <span className="text-[11px] text-emerald-600 font-semibold flex items-center gap-1 mt-1">
                                                    <TrendingUp className="h-3 w-3" /> {metric.change}
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Visual Chart Bars (CSS-rendered for zero dependencies) */}
                            {result.chart && result.chart.labels && result.chart.datasets && (
                                <div className="p-5 rounded-xl bg-white border border-slate-200 shadow-sm">
                                    <div className="flex items-center justify-between mb-4">
                                        <h5 className="font-bold text-sm text-slate-800 flex items-center gap-2">
                                            <BarChart3 className="h-4 w-4 text-indigo-600" /> Comparison Breakdown
                                        </h5>
                                        <span className="text-xs text-slate-400 font-medium">
                                            {result.chart.datasets[0]?.label}
                                        </span>
                                    </div>

                                    <div className="space-y-3">
                                        {result.chart.labels.map((label, idx) => {
                                            const val = result.chart?.datasets[0]?.data[idx] || 0;
                                            const max = Math.max(...(result.chart?.datasets[0]?.data || [1]));
                                            const pct = max > 0 ? Math.round((val / max) * 100) : 0;

                                            return (
                                                <div key={idx} className="space-y-1">
                                                    <div className="flex justify-between text-xs font-semibold text-slate-700">
                                                        <span>{label}</span>
                                                        <span>GHS {val.toLocaleString()}</span>
                                                    </div>
                                                    <div className="h-3 w-full rounded-full bg-slate-100 overflow-hidden">
                                                        <div
                                                            className="h-full bg-gradient-to-r from-sky-500 to-indigo-600 rounded-full transition-all duration-500"
                                                            style={{ width: `${pct}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Data Table */}
                            {result.table && result.table.columns && result.table.rows && (
                                <div className="rounded-xl border border-slate-200 overflow-hidden bg-white shadow-sm">
                                    <div className="px-5 py-3 border-b border-slate-100 bg-slate-50 flex items-center gap-2">
                                        <FileSpreadsheet className="h-4 w-4 text-slate-500" />
                                        <span className="text-xs font-bold text-slate-700 uppercase">
                                            Detailed Data Table
                                        </span>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-xs">
                                            <thead>
                                                <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                                    {result.table.columns.map((col, idx) => (
                                                        <th key={idx} className="p-3">
                                                            {col}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {result.table.rows.map((row, rIdx) => (
                                                    <tr key={rIdx} className="hover:bg-slate-50">
                                                        {row.map((cell, cIdx) => (
                                                            <td key={cIdx} className="p-3 text-slate-800">
                                                                {cell}
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {/* Follow-up Questions */}
                            {result.follow_up_questions && result.follow_up_questions.length > 0 && (
                                <div className="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                    <span className="text-xs font-bold text-slate-600 flex items-center gap-1.5 mb-2">
                                        <HelpCircle className="h-3.5 w-3.5 text-indigo-600" /> Suggested Follow-Up
                                        Questions
                                    </span>
                                    <div className="flex flex-wrap gap-2">
                                        {result.follow_up_questions.map((fq, idx) => (
                                            <button
                                                key={idx}
                                                onClick={() => handleAsk(fq)}
                                                className="flex items-center gap-1 rounded-lg bg-white border border-slate-300 hover:border-indigo-500 hover:text-indigo-600 px-3 py-1.5 text-xs text-slate-700 transition shadow-sm"
                                            >
                                                <span>{fq}</span>
                                                <ArrowUpRight className="h-3 w-3 text-slate-400" />
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="text-center py-12 text-slate-400">
                            <Sparkles className="h-10 w-10 mx-auto text-slate-300 mb-2" />
                            <p className="text-sm font-semibold text-slate-600">No question asked yet</p>
                            <p className="text-xs text-slate-400 mt-1">
                                Type a query or click one of the suggested prompts above to see instant numbers and graphs.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
