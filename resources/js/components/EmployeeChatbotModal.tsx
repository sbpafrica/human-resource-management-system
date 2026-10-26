import React, { useState, useRef, useEffect } from 'react';
import {
    MessageSquare,
    Send,
    Download,
    FileText,
    HelpCircle,
    Calendar,
    ShieldCheck,
    X,
    Lock,
    UserCheck,
    Loader2,
    RefreshCw,
    Bot
} from 'lucide-react';

interface ChatMessage {
    id: string;
    sender: 'user' | 'assistant';
    text: string;
    action?: {
        type: string;
        label: string;
        url: string;
        period?: string;
    } | null;
    timestamp: string;
}

interface EmployeeContext {
    staff_id: string;
    name: string;
    company: string;
    position: string;
}

export default function EmployeeChatbotModal() {
    const [isOpen, setIsOpen] = useState(false);
    const [isAuthenticated, setIsAuthenticated] = useState(false);
    const [staffId, setStaffId] = useState('');
    const [pin, setPin] = useState('');
    const [authLoading, setAuthLoading] = useState(false);
    const [authError, setAuthError] = useState('');
    const [employee, setEmployee] = useState<EmployeeContext | null>(null);

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [inputText, setInputText] = useState('');
    const [isTyping, setIsTyping] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages, isTyping]);

    const handleLogin = async (e: React.FormEvent) => {
        e.preventDefault();
        setAuthLoading(true);
        setAuthError('');

        try {
            const res = await fetch('/copilot/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ staff_id: staffId, pin }),
            });

            const data = await res.json();
            if (res.ok && data.success) {
                setIsAuthenticated(true);
                setEmployee(data.employee);

                setMessages([
                    {
                        id: 'welcome',
                        sender: 'assistant',
                        text: `Hello ${data.employee.name}! 👋 I am your HRS Copilot. How can I help you today? You can ask for your latest payslip, check deduction breakdowns, review leave balance, or view pension contributions.`,
                        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                    },
                ]);
            } else {
                setAuthError(data.message || 'Invalid Staff ID or PIN. Please check your credentials.');
            }
        } catch {
            setAuthError('Network error. Please try again.');
        } finally {
            setAuthLoading(false);
        }
    };

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
                    staff_id: employee?.staff_id,
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
                    timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                };
                setMessages((prev) => [...prev, botMsg]);
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

    return (
        <>
            {/* Floating Chat Trigger Button */}
            <button
                onClick={() => setIsOpen(true)}
                className="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-full bg-sky-600 px-5 py-3 text-white shadow-xl hover:bg-sky-700 transition-all hover:scale-105 focus:outline-none focus:ring-4 focus:ring-sky-300"
                aria-label="Open HRS Copilot"
            >
                <Bot className="h-5 w-5 animate-pulse" />
                <span className="font-semibold text-sm">HRS Copilot</span>
            </button>

            {/* Chatbot Modal Dialog */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:justify-end sm:p-6 bg-black/40 backdrop-blur-sm">
                    <div className="flex flex-col h-[600px] w-full sm:w-[420px] rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden animate-in slide-in-from-bottom duration-200">
                        {/* Header */}
                        <div className="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-sky-600 to-indigo-700 text-white">
                            <div className="flex items-center gap-2">
                                <div className="h-9 w-9 rounded-full bg-white/20 flex items-center justify-center">
                                    <Bot className="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-sm leading-tight">HRS Employee Copilot</h3>
                                    <p className="text-xs text-sky-100 flex items-center gap-1">
                                        <ShieldCheck className="h-3 w-3" /> Secure Self-Service
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

                        {/* Authentication Screen */}
                        {!isAuthenticated ? (
                            <div className="flex-1 p-6 flex flex-col justify-center bg-slate-50">
                                <div className="text-center mb-6">
                                    <div className="mx-auto h-12 w-12 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 mb-3">
                                        <Lock className="h-6 w-6" />
                                    </div>
                                    <h4 className="font-bold text-slate-800 text-lg">Employee Verification</h4>
                                    <p className="text-xs text-slate-500 mt-1">
                                        Enter your Staff ID and 4-digit PIN (or Ghana Card PIN) to access your payslips and deductions.
                                    </p>
                                </div>

                                <form onSubmit={handleLogin} className="space-y-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Staff ID / Employee ID
                                        </label>
                                        <input
                                            type="text"
                                            value={staffId}
                                            onChange={(e) => setStaffId(e.target.value.toUpperCase())}
                                            placeholder="e.g. JK001 / OFI123"
                                            required
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            4-Digit PIN or Ghana Card PIN
                                        </label>
                                        <input
                                            type="password"
                                            value={pin}
                                            onChange={(e) => setPin(e.target.value)}
                                            placeholder="PIN code or GHA-XXXXXXXXX-X"
                                            required
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                        />
                                    </div>

                                    {authError && (
                                        <p className="text-xs text-rose-600 bg-rose-50 p-2 rounded border border-rose-200">
                                            {authError}
                                        </p>
                                    )}

                                    <button
                                        type="submit"
                                        disabled={authLoading}
                                        className="w-full flex items-center justify-center gap-2 rounded-lg bg-sky-600 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 transition disabled:opacity-50"
                                    >
                                        {authLoading ? (
                                            <>
                                                <Loader2 className="h-4 w-4 animate-spin" /> Verifying...
                                            </>
                                        ) : (
                                            <>
                                                <UserCheck className="h-4 w-4" /> Verify & Access Chatbot
                                            </>
                                        )}
                                    </button>
                                </form>
                            </div>
                        ) : (
                            /* Authenticated Chat Screen */
                            <div className="flex-1 flex flex-col justify-between overflow-hidden bg-slate-50">
                                {/* Employee Context Strip */}
                                <div className="px-4 py-2 bg-sky-50 border-b border-sky-100 flex items-center justify-between text-xs text-sky-800">
                                    <span className="font-semibold">{employee?.name} ({employee?.staff_id})</span>
                                    <span className="text-slate-500">{employee?.company}</span>
                                </div>

                                {/* Chat Messages Container */}
                                <div className="flex-1 p-4 overflow-y-auto space-y-3">
                                    {messages.map((m) => (
                                        <div
                                            key={m.id}
                                            className={`flex flex-col ${m.sender === 'user' ? 'items-end' : 'items-start'}`}
                                        >
                                            <div
                                                className={`max-w-[85%] rounded-2xl px-4 py-2.5 text-sm ${
                                                    m.sender === 'user'
                                                        ? 'bg-sky-600 text-white rounded-br-none'
                                                        : 'bg-white text-slate-800 shadow-sm border border-slate-200 rounded-bl-none whitespace-pre-line'
                                                }`}
                                            >
                                                {m.text}

                                                {/* Action Link (e.g. Payslip PDF Download) */}
                                                {m.action && m.action.type === 'download_payslip' && (
                                                    <a
                                                        href={m.action.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="mt-3 flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition shadow"
                                                    >
                                                        <Download className="h-4 w-4" /> {m.action.label}
                                                    </a>
                                                )}
                                            </div>
                                            <span className="text-[10px] text-slate-400 mt-1 px-1">
                                                {m.timestamp}
                                            </span>
                                        </div>
                                    ))}

                                    {isTyping && (
                                        <div className="flex items-center gap-2 text-slate-400 text-xs py-1">
                                            <Loader2 className="h-4 w-4 animate-spin text-sky-600" />
                                            <span>Copilot is typing...</span>
                                        </div>
                                    )}

                                    <div ref={messagesEndRef} />
                                </div>

                                {/* Quick-Prompt Pills */}
                                <div className="p-2 border-t border-slate-200 bg-white flex gap-1.5 overflow-x-auto scrollbar-none">
                                    <button
                                        onClick={() => handleQuickPrompt('Please download my latest payslip')}
                                        className="shrink-0 flex items-center gap-1 rounded-full bg-slate-100 hover:bg-sky-50 hover:text-sky-700 px-3 py-1 text-[11px] font-medium text-slate-600 border border-slate-200 transition"
                                    >
                                        <FileText className="h-3 w-3" /> Latest Payslip
                                    </button>
                                    <button
                                        onClick={() => handleQuickPrompt('Why was my pay reduced this month?')}
                                        className="shrink-0 flex items-center gap-1 rounded-full bg-slate-100 hover:bg-sky-50 hover:text-sky-700 px-3 py-1 text-[11px] font-medium text-slate-600 border border-slate-200 transition"
                                    >
                                        <HelpCircle className="h-3 w-3" /> Explain Deductions
                                    </button>
                                    <button
                                        onClick={() => handleQuickPrompt('How many days of leave do I have remaining?')}
                                        className="shrink-0 flex items-center gap-1 rounded-full bg-slate-100 hover:bg-sky-50 hover:text-sky-700 px-3 py-1 text-[11px] font-medium text-slate-600 border border-slate-200 transition"
                                    >
                                        <Calendar className="h-3 w-3" /> Leave Balance
                                    </button>
                                </div>

                                {/* Input Field */}
                                <div className="p-3 bg-white border-t border-slate-200 flex items-center gap-2">
                                    <input
                                        type="text"
                                        value={inputText}
                                        onChange={(e) => setInputText(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleSendMessage()}
                                        placeholder="Ask a question about your pay or leave..."
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
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
