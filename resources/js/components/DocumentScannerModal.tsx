import React, { useState } from 'react';
import {
    Scan,
    UploadCloud,
    CheckCircle2,
    AlertCircle,
    User,
    CreditCard,
    FileCheck,
    Briefcase,
    Loader2,
    Save,
    X,
    Eye
} from 'lucide-react';

interface ExtractedData {
    employeename?: string;
    dateofbirth?: string;
    gender?: string;
    ghcard?: string;
    nationality?: string;
    expiry_date?: string;
    bank?: string;
    account?: string;
    branch?: string;
    ssnit_number?: string;
    company?: string;
    position?: string;
    shop?: string;
    startdate?: string;
    basic_salary?: number;
    daily_rate?: number;
}

interface DocumentScannerModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess?: (employeeData: any) => void;
}

export default function DocumentScannerModal({ isOpen, onClose, onSuccess }: DocumentScannerModalProps) {
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [documentType, setDocumentType] = useState<string>('auto');
    const [isScanning, setIsScanning] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [scanResult, setScanResult] = useState<{
        success: boolean;
        document_type: string;
        confidence: number;
        extracted: ExtractedData;
    } | null>(null);
    const [staffId, setStaffId] = useState('');
    const [formData, setFormData] = useState<ExtractedData>({});
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    if (!isOpen) return null;

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setSelectedFile(file);
            setPreviewUrl(URL.createObjectURL(file));
            setScanResult(null);
            setStatusMessage(null);
        }
    };

    const handleScan = async () => {
        if (!selectedFile) return;

        setIsScanning(true);
        setStatusMessage(null);

        const uploadData = new FormData();
        uploadData.append('file', selectedFile);
        uploadData.append('document_type', documentType);

        try {
            const res = await fetch('/document-ai/scan', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: uploadData,
            });

            const data = await res.json();
            if (data.success) {
                setScanResult(data);
                setFormData(data.extracted || {});
                setStatusMessage({
                    type: 'success',
                    text: `Successfully scanned ${data.document_type.replace('_', ' ').toUpperCase()} (${Math.round(data.confidence * 100)}% confidence).`,
                });
            } else {
                setStatusMessage({ type: 'error', text: data.error || 'Failed to scan document.' });
            }
        } catch {
            setStatusMessage({ type: 'error', text: 'Network error while contacting OCR service.' });
        } finally {
            setIsScanning(false);
        }
    };

    const handleSave = async () => {
        if (!staffId.trim()) {
            setStatusMessage({ type: 'error', text: 'Please assign a Staff ID before saving.' });
            return;
        }

        setIsSaving(true);
        try {
            const res = await fetch('/document-ai/save-employee', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    staff_id: staffId,
                    ...formData,
                }),
            });

            const data = await res.json();
            if (data.success) {
                setStatusMessage({ type: 'success', text: data.message });
                if (onSuccess) onSuccess(data.employee);
                setTimeout(() => {
                    onClose();
                }, 1500);
            } else {
                setStatusMessage({ type: 'error', text: data.message || 'Error saving employee.' });
            }
        } catch {
            setStatusMessage({ type: 'error', text: 'Network error saving record.' });
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div className="w-full max-w-4xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="px-6 py-4 bg-gradient-to-r from-sky-600 to-indigo-700 text-white flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Scan className="h-6 w-6" />
                        <div>
                            <h3 className="font-bold text-base leading-tight">AI Document Scanner for Onboarding</h3>
                            <p className="text-xs text-sky-100">
                                Scan Ghana Card, SSNIT Card, Bank Slips, and Contracts to auto-populate employee master data.
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

                {/* Content Grid */}
                <div className="flex-1 overflow-y-auto p-6 grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50">
                    {/* Left: Upload & Document Preview */}
                    <div className="flex flex-col space-y-4">
                        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                            <label className="block text-xs font-semibold text-slate-700 mb-2">
                                1. Select Document Category
                            </label>
                            <div className="grid grid-cols-2 gap-2 mb-4">
                                {[
                                    { id: 'auto', label: 'Auto-Detect' },
                                    { id: 'ghana_card', label: 'Ghana Card' },
                                    { id: 'bank_document', label: 'Bank Cheque / Slip' },
                                    { id: 'contract', label: 'Contract / Letter' },
                                ].map((type) => (
                                    <button
                                        key={type.id}
                                        type="button"
                                        onClick={() => setDocumentType(type.id)}
                                        className={`px-3 py-2 text-xs font-medium rounded-lg border transition ${
                                            documentType === type.id
                                                ? 'bg-sky-50 border-sky-600 text-sky-700'
                                                : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                                        }`}
                                    >
                                        {type.label}
                                    </button>
                                ))}
                            </div>

                            <label className="block text-xs font-semibold text-slate-700 mb-2">
                                2. Upload Document (Photo or PDF)
                            </label>
                            <div className="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center hover:border-sky-500 transition cursor-pointer relative bg-slate-50/50">
                                <input
                                    type="file"
                                    accept="image/*,application/pdf"
                                    onChange={handleFileChange}
                                    className="absolute inset-0 opacity-0 cursor-pointer"
                                />
                                <UploadCloud className="h-8 w-8 text-sky-600 mx-auto mb-2" />
                                <p className="text-xs font-semibold text-slate-700">
                                    {selectedFile ? selectedFile.name : 'Click or drag & drop to upload image'}
                                </p>
                                <p className="text-[11px] text-slate-400 mt-1">PNG, JPG, WEBP, or PDF up to 10MB</p>
                            </div>

                            {previewUrl && (
                                <div className="mt-4 rounded-lg overflow-hidden border border-slate-200 bg-black/5 flex items-center justify-center max-h-48">
                                    <img src={previewUrl} alt="Preview" className="max-h-48 object-contain" />
                                </div>
                            )}

                            <button
                                onClick={handleScan}
                                disabled={!selectedFile || isScanning}
                                className="mt-4 w-full flex items-center justify-center gap-2 rounded-lg bg-sky-600 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 transition disabled:opacity-50 shadow"
                            >
                                {isScanning ? (
                                    <>
                                        <Loader2 className="h-4 w-4 animate-spin" /> Scanning with Grok Vision...
                                    </>
                                ) : (
                                    <>
                                        <Scan className="h-4 w-4" /> Scan & Extract Fields
                                    </>
                                )}
                            </button>
                        </div>
                    </div>

                    {/* Right: Extracted Fields & Review */}
                    <div className="flex flex-col space-y-4">
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex-1 flex flex-col justify-between">
                            <div>
                                <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                                    <h4 className="font-bold text-slate-800 text-sm flex items-center gap-2">
                                        <FileCheck className="h-4 w-4 text-emerald-600" /> Extracted Employee Details
                                    </h4>
                                    {scanResult && (
                                        <span className="text-xs bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">
                                            {Math.round(scanResult.confidence * 100)}% Match
                                        </span>
                                    )}
                                </div>

                                <div className="space-y-3">
                                    <div>
                                        <label className="block text-[11px] font-bold text-slate-700 uppercase">
                                            Staff ID (Required)
                                        </label>
                                        <input
                                            type="text"
                                            value={staffId}
                                            onChange={(e) => setStaffId(e.target.value.toUpperCase())}
                                            placeholder="e.g. OFI-084 or JK-102"
                                            className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold focus:border-sky-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-[11px] font-bold text-slate-700 uppercase">
                                            Full Name
                                        </label>
                                        <input
                                            type="text"
                                            value={formData.employeename || ''}
                                            onChange={(e) => setFormData({ ...formData, employeename: e.target.value })}
                                            placeholder="Extracted from ID"
                                            className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-sky-500 focus:outline-none"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-700 uppercase">
                                                Ghana Card PIN
                                            </label>
                                            <input
                                                type="text"
                                                value={formData.ghcard || ''}
                                                onChange={(e) => setFormData({ ...formData, ghcard: e.target.value })}
                                                placeholder="GHA-XXXXXXXXX-X"
                                                className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-sky-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-700 uppercase">
                                                Date of Birth
                                            </label>
                                            <input
                                                type="date"
                                                value={formData.dateofbirth || ''}
                                                onChange={(e) => setFormData({ ...formData, dateofbirth: e.target.value })}
                                                className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-sky-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-700 uppercase">
                                                Bank Name
                                            </label>
                                            <input
                                                type="text"
                                                value={formData.bank || ''}
                                                onChange={(e) => setFormData({ ...formData, bank: e.target.value })}
                                                placeholder="e.g. GCB / Ecobank"
                                                className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-sky-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-700 uppercase">
                                                Account Number
                                            </label>
                                            <input
                                                type="text"
                                                value={formData.account || ''}
                                                onChange={(e) => setFormData({ ...formData, account: e.target.value })}
                                                placeholder="e.g. 1441001234567"
                                                className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-sky-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-700 uppercase">
                                                Company / Client
                                            </label>
                                            <input
                                                type="text"
                                                value={formData.company || ''}
                                                onChange={(e) => setFormData({ ...formData, company: e.target.value })}
                                                placeholder="e.g. OFI Cocoa"
                                                className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-sky-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-[11px] font-bold text-slate-700 uppercase">
                                                Job Position
                                            </label>
                                            <input
                                                type="text"
                                                value={formData.position || ''}
                                                onChange={(e) => setFormData({ ...formData, position: e.target.value })}
                                                placeholder="e.g. Warehouse Officer"
                                                className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-sky-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Status and Save Action */}
                            <div className="pt-4 border-t border-slate-100 mt-4">
                                {statusMessage && (
                                    <div
                                        className={`p-2.5 rounded-lg text-xs mb-3 flex items-center gap-2 ${
                                            statusMessage.type === 'success'
                                                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                                : 'bg-rose-50 text-rose-800 border border-rose-200'
                                        }`}
                                    >
                                        {statusMessage.type === 'success' ? (
                                            <CheckCircle2 className="h-4 w-4 shrink-0" />
                                        ) : (
                                            <AlertCircle className="h-4 w-4 shrink-0" />
                                        )}
                                        <span>{statusMessage.text}</span>
                                    </div>
                                )}

                                <button
                                    onClick={handleSave}
                                    disabled={!formData.employeename || isSaving}
                                    className="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition disabled:opacity-50 shadow"
                                >
                                    {isSaving ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin" /> Saving to Master Data...
                                        </>
                                    ) : (
                                        <>
                                            <Save className="h-4 w-4" /> 1-Click Save to Employee Master
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
