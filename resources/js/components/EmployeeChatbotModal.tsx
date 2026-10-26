import React, { useState, useRef, useEffect } from 'react';
import {
    Send,
    Download,
    FileText,
    HelpCircle,
    Calendar,
    Sparkles,
    X,
    Loader2,
    Bot,
    Calculator,
    Layers,
    ExternalLink,
    CheckCircle2,
    ShieldAlert,
    Briefcase
} from 'lucide-react';

interface ActionItem {
    type?: string;
    label: string;
    url: string;
    period?: string;
}

interface ChatMessage {
    id: string;
    sender: 'user' | 'assistant';
    text: string;
    action?: ActionItem | null;
    actions?: ActionItem[];
    timestamp: string;
}

export default function EmployeeChatbotModal() {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>([
        {
            id: 'welcome',
            sender: 'assistant',
            text: `Hello! 👋 I am your HRS Operations Copilot.\n\nI assist HR administrators and payroll officers with everything on the system:\n• Payslip Generation: Instantly generate and open monthly payslips for all 31 clients (e.g. Bunge Benin, Melcom, Twellium, Jay Kay)\n• Payroll Ingestion: Direct navigation to client upload sheets and template downloads\n• Worker Deductions: Breakdown of why deductions happened (SSNIT Tier 1, Petra Tier 2, PAYE, days absent)\n• Statutory Rules: Ghanaian labor law, overtime rates, and pension contribution formulas\n• System Audits: Direct access to SSNIT, Bank, Petra, and Contact reconciliation audits.`,
            actions: [
                { type: 'view_payslips', label: 'Open Bunge Benin October Payslips', url: '/payslips/beninbunge?month=2026-10' },
                { type: 'ai_audit', label: 'Run Pre-Flight AI Ingestion Audit', url: '/payroll-ai/inspect' },
                { type: 'audit_ssnit', label: 'Open SSNIT Audit', url: '/hr-audit/ssnit' },
            ],
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        },
    ]);
    const [inputText, setInputText] = useState('');
    const [isTyping, setIsTyping] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        if (isOpen) {
            scrollToBottom();
        }
    }, [messages, isTyping, isOpen]);

    const handleSendMessage = async (textToSend?: string) => {
        const text = textToSend || inputText.trim();
        if (!text || isTyping) return;

        const userMsg: ChatMessage = {
            id: String(Date.now()),
            sender: 'user',
            text,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        };

        setMessages((prev) => [...prev, userMsg]);
        if (!textToSend) setInputText('');
        setIsTyping(true);

        try {
            const res = await fetch('/copilot/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    message: text,
                    history: messages.map((m) => ({ sender: m.sender, text: m.text })),
                }),
            });

            const data = await res.json();
            if (data.success) {
                const botMsg: ChatMessage = {
                    id: String(Date.now() + 1),
                    sender: 'assistant',
                    text: data.reply,
                    action: data.action,
                    actions: data.actions || (data.action ? [data.action] : []),
                    timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                };
                setMessages((prev) => [...prev, botMsg]);
            } else {
                setMessages((prev) => [
                    ...prev,
                    {
                        id: String(Date.now() + 1),
                        sender: 'assistant',
                        text: data.message || 'Sorry, I could not process that request. Please try again.',
                        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                    },
                ]);
            }
        } catch {
            setMessages((prev) => [
                ...prev,
                {
                    id: String(Date.now() + 1),
                    sender: 'assistant',
                    text: 'Sorry, I am having trouble connecting to the HR server right now. Please try again in a moment.',
                    timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                },
            ]);
        } finally {
            setIsTyping(false);
        }
    };

    const handleQuickPrompt = (prompt: string) => {
        handleSendMessage(prompt);
    };

    const renderFormattedText = (text: string) => {
        // Completely strip all markdown asterisks (** and *) to present pristine text
        return text.replace(/\*\*/g, '').replace(/\*/g, '');
    };

    return (
        <>
            {/* Floating Chat Trigger Button */}
            <button
                onClick={() => setIsOpen(true)}
                className="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-full bg-sky-600 px-5 py-3 text-white shadow-xl hover:bg-sky-700 transition-all hover:scale-105 focus:outline-none focus:ring-4 focus:ring-sky-300"
                aria-label="Open HR Copilot"
            >
                <Bot className="h-5 w-5 animate-pulse" />
                <span className="font-semibold text-sm">HRS Copilot</span>
            </button>

            {/* Chatbot Dialog */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:justify-end sm:p-6 bg-black/40 backdrop-blur-sm">
                    <div className="flex flex-col h-[620px] w-full sm:w-[460px] rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden animate-in slide-in-from-bottom duration-200">
                        {/* Header */}
                        <div className="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-sky-600 to-indigo-700 text-white">
                            <div className="flex items-center gap-2">
                                <div className="h-9 w-9 rounded-full bg-white/20 flex items-center justify-center">
                                    <Bot className="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-sm leading-tight">HRS Operations Copilot</h3>
                                    <p className="text-xs text-sky-100 flex items-center gap-1">
                                        <Sparkles className="h-3 w-3 text-amber-300" /> HR & Payroll Assistant (All 31 Clients)
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => setIsOpen(false)}
                                className="h-8 w-8 rounded-full hover:bg-white/20 flex items-center justify-center text-white/80 hover:text-white transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {/* Chat Messages Screen */}
                        <div className="flex-1 flex flex-col justify-between overflow-hidden bg-slate-50">
                            {/* Messages List */}
                            <div className="flex-1 p-4 overflow-y-auto space-y-3">
                                {messages.map((m) => {
                                    const actionList = (m.actions && m.actions.length > 0)
                                        ? m.actions
                                        : (m.action ? [m.action] : []);

                                    return (
                                        <div
                                            key={m.id}
                                            className={`flex flex-col ${m.sender === 'user' ? 'items-end' : 'items-start'}`}
                                        >
                                            <div
                                                className={`max-w-[88%] rounded-2xl px-4 py-2.5 text-sm ${
                                                    m.sender === 'user'
                                                        ? 'bg-sky-600 text-white rounded-br-none'
                                                        : 'bg-white text-slate-800 shadow-sm border border-slate-200 rounded-bl-none whitespace-pre-line'
                                                }`}
                                            >
                                                {renderFormattedText(m.text)}

                                                {/* Render Action Buttons / Links */}
                                                {actionList.length > 0 && (
                                                    <div className="mt-3 pt-2 border-t border-slate-100 space-y-2">
                                                        {actionList.map((act, idx) => {
                                                            const isDownload = act.type === 'download_payslip';
                                                            const isPayslip = act.type === 'view_payslips';
                                                            const isAudit = act.type?.includes('audit');

                                                            return (
                                                                <a
                                                                    key={idx}
                                                                    href={act.url}
                                                                    target={isDownload ? '_blank' : '_self'}
                                                                    rel="noopener noreferrer"
                                                                    className={`flex items-center justify-between gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition shadow-sm ${
                                                                        isDownload
                                                                            ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                                                            : isPayslip
                                                                            ? 'bg-sky-600 hover:bg-sky-700 text-white'
                                                                            : isAudit
                                                                            ? 'bg-indigo-600 hover:bg-indigo-700 text-white'
                                                                            : 'bg-slate-100 hover:bg-sky-50 text-slate-800 hover:text-sky-900 border border-slate-200'
                                                                    }`}
                                                                >
                                                                    <span className="flex items-center gap-2">
                                                                        {isDownload ? (
                                                                            <Download className="h-4 w-4 shrink-0" />
                                                                        ) : isPayslip ? (
                                                                            <FileText className="h-4 w-4 shrink-0" />
                                                                        ) : isAudit ? (
                                                                            <ShieldAlert className="h-4 w-4 shrink-0" />
                                                                        ) : (
                                                                            <ExternalLink className="h-3.5 w-3.5 shrink-0" />
                                                                        )}
                                                                        <span className="line-clamp-1">{act.label}</span>
                                                                    </span>
                                                                    <span className="text-[11px] opacity-80 shrink-0">Open &rarr;</span>
                                                                </a>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>
                                            <span className="text-[10px] text-slate-400 mt-1 px-1">
                                                {m.timestamp}
                                            </span>
                                        </div>
                                    );
                                })}

                                {isTyping && (
                                    <div className="flex items-center gap-2 text-slate-400 text-xs py-1">
                                        <Loader2 className="h-4 w-4 animate-spin text-sky-600" />
                                        <span>Copilot is analyzing...</span>
                                    </div>
                                )}

                                <div ref={messagesEndRef} />
                            </div>

                            {/* Quick-Prompt Suggestions for HR Users */}
                            <div className="p-2 border-t border-slate-200 bg-white flex gap-1.5 overflow-x-auto scrollbar-none">
                                <button
                                    onClick={() => handleQuickPrompt('Generate a payslip for Bunge Benin for October')}
                                    className="shrink-0 flex items-center gap-1 rounded-full bg-slate-100 hover:bg-sky-50 hover:text-sky-700 px-3 py-1 text-[11px] font-medium text-slate-600 border border-slate-200 transition"
                                >
                                    <FileText className="h-3 w-3 text-sky-600" /> Bunge Benin Payslips
                                </button>
                                <button
                                    onClick={() => handleQuickPrompt('How is SSNIT 5.5% and Petra Tier 2 5% calculated?')}
                                    className="shrink-0 flex items-center gap-1 rounded-full bg-slate-100 hover:bg-sky-50 hover:text-sky-700 px-3 py-1 text-[11px] font-medium text-slate-600 border border-slate-200 transition"
                                >
                                    <Calculator className="h-3 w-3 text-emerald-600" /> Statutory Formulas
                                </button>
                                <button
                                    onClick={() => handleQuickPrompt('How are absenteeism deductions calculated in Ghanaian payroll?')}
                                    className="shrink-0 flex items-center gap-1 rounded-full bg-slate-100 hover:bg-sky-50 hover:text-sky-700 px-3 py-1 text-[11px] font-medium text-slate-600 border border-slate-200 transition"
                                >
                                    <HelpCircle className="h-3 w-3 text-indigo-600" /> Absenteeism Rules
                                </button>
                                <button
                                    onClick={() => handleQuickPrompt('Open SSNIT Audit and Bank Audit')}
                                    className="shrink-0 flex items-center gap-1 rounded-full bg-slate-100 hover:bg-sky-50 hover:text-sky-700 px-3 py-1 text-[11px] font-medium text-slate-600 border border-slate-200 transition"
                                >
                                    <ShieldAlert className="h-3 w-3 text-purple-600" /> Reconciliation Audits
                                </button>
                                <button
                                    onClick={() => handleQuickPrompt('How do I run a Pre-Flight AI Ingestion Audit?')}
                                    className="shrink-0 flex items-center gap-1 rounded-full bg-slate-100 hover:bg-sky-50 hover:text-sky-700 px-3 py-1 text-[11px] font-medium text-slate-600 border border-slate-200 transition"
                                >
                                    <Sparkles className="h-3 w-3 text-amber-500" /> Pre-Flight AI Audit
                                </button>
                            </div>

                            {/* Input Field */}
                            <div className="p-3 bg-white border-t border-slate-200 flex items-center gap-2">
                                <input
                                    type="text"
                                    value={inputText}
                                    onChange={(e) => setInputText(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && handleSendMessage()}
                                    placeholder="Ask about payslips, client payrolls, worker deductions, or audits..."
                                    className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                />
                                <button
                                    onClick={() => handleSendMessage()}
                                    disabled={!inputText.trim() || isTyping}
                                    className="h-9 w-9 rounded-lg bg-sky-600 text-white flex items-center justify-center hover:bg-sky-700 transition disabled:opacity-40"
                                >
                                    <Send className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
