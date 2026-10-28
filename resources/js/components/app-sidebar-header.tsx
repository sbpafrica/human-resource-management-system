import { useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Sparkles, Scan } from 'lucide-react';
import DocumentScannerModal from '@/components/DocumentScannerModal';
import ExecutiveAnalyticsModal from '@/components/ExecutiveAnalyticsModal';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const [scannerOpen, setScannerOpen] = useState(false);
    const [analyticsOpen, setAnalyticsOpen] = useState(false);

    return (
        <header className="flex h-16 shrink-0 items-center justify-between border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="flex items-center gap-2">
                <button
                    onClick={() => setScannerOpen(true)}
                    type="button"
                    className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition"
                    title="Scan Ghana Card or Contract"
                >
                    <Scan className="h-3.5 w-3.5 text-indigo-600" />
                    <span className="hidden sm:inline">AI Scanner</span>
                </button>

                <button
                    onClick={() => setAnalyticsOpen(true)}
                    type="button"
                    className="flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-sky-600 to-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:from-sky-700 hover:to-indigo-700 transition"
                    title="Ask HRS Anything (Executive Analytics)"
                >
                    <Sparkles className="h-3.5 w-3.5 text-amber-300" />
                    <span>Ask HRS Anything</span>
                </button>
            </div>

            <DocumentScannerModal isOpen={scannerOpen} onClose={() => setScannerOpen(false)} />
            <ExecutiveAnalyticsModal isOpen={analyticsOpen} onClose={() => setAnalyticsOpen(false)} />
        </header>
    );
}
