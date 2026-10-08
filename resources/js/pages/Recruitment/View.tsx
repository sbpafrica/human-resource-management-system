import { Head } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useState, useEffect } from "react";
import axios from "axios";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

interface Props {
    applicant: { id: number; firstname: string; lastname: string };
}

const sensitiveFields = ['name', 'email', 'mobile_number', 'address', 'references'];

export default function ViewResume({ applicant }: Props) {
    const [parsedData, setParsedData] = useState<Record<string, any> | null>(null);
    const [revealed, setRevealed] = useState(false);
    const [pdfUrl, setPdfUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [openDialog, setOpenDialog] = useState(false);

    useEffect(() => {
        const fetchResume = async () => {
            setLoading(true);
            setError(null);
            try {
                const res = await axios.get(`/recruitment/resume/${applicant.id}`);
                console.log('API Response:', res.data); // Debug
                if (res.data.error) {
                    setError(res.data.error);
                    return;
                }
                setParsedData(res.data.data);
                setRevealed(res.data.revealed);
                setPdfUrl(res.data.pdf_url);
                console.log('PDF URL Set:', res.data.pdf_url); // Debug
            } catch (err: any) {
                setError('Failed to load: ' + (err.response?.data?.error || err.message));
                console.error('Fetch error:', err);
            } finally {
                setLoading(false);
            }
        };
        fetchResume();
    }, [applicant.id]);

    const handleReveal = async () => {
        try {
            await axios.post(`/recruitment/reveal/${applicant.id}`);
            const res = await axios.get(`/recruitment/resume/${applicant.id}`);
            setParsedData(res.data.data);
            setRevealed(res.data.revealed);
            setPdfUrl(res.data.pdf_url);
            setError(null);
        } catch (err: any) {
            setError('Reveal failed');
            console.error('Reveal error:', err);
        }
    };

    const openPdf = async (applicantId: number) => {
        setLoading(true);
        try {
            // 1️⃣ Ask backend to ensure resume is parsed and ready
            await axios.post(`/recruitment/parse-resume/${applicantId}`);

            // 2️⃣ Fetch parsed resume data including pdf_url
            const res = await axios.get(`/recruitment/resume/${applicantId}`);

            if (res.data.pdf_url) {
                setPdfUrl(res.data.pdf_url);
                setOpenDialog(true);
            } else {
                alert("Resume parsing in progress. Please try again in a few moments.");
            }
        } catch (error) {
            console.error("Error opening resume:", error);
            alert("Unable to open this resume.");
        } finally {
            setLoading(false);
        }
    };

    const renderValue = (value: any): string => {
        if (Array.isArray(value)) return value.join(', ');
        return value?.toString() || 'N/A';
    };

    return (
        <AppLayout breadcrumbs={[{ title: "Recruitment", href: "/recruitment" }, { title: "View CV", href: `/recruitment/view/${applicant.id}` }]}>
            <Head title="View CV" />
            <div className="p-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
                <h1 className="text-2xl font-bold mb-6">
                    CV for {applicant.firstname} {applicant.lastname}
                </h1>

                {loading && <p>Loading...</p>}
                {error && <div className="bg-red-100 text-red-700 p-4 rounded">{error}</div>}

                {!loading && parsedData && (
                    <Card className="max-w-4xl mx-auto">
                        <CardHeader><CardTitle>Resume Details</CardTitle></CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid md:grid-cols-2 gap-6">
                                <div>
                                    <h3 className="font-semibold">Contact</h3>
                                    {Object.entries(parsedData).filter(([k]) => sensitiveFields.includes(k)).map(([k, v]) => (
                                        <div key={k}><strong>{k}:</strong> <span className={!revealed ? 'blur-sm' : ''}>{renderValue(v)}</span></div>
                                    ))}
                                </div>
                                <div>
                                    <h3 className="font-semibold">Professional</h3>
                                    {Object.entries(parsedData).filter(([k]) => !sensitiveFields.includes(k)).map(([k, v]) => (
                                        <div key={k}><strong>{k}:</strong> {renderValue(v)}</div>
                                    ))}
                                </div>
                            </div>

                            <Dialog open={openDialog} onOpenChange={setOpenDialog}>
                                <DialogTrigger asChild>
                                    <Button>
                                        {revealed ? 'View Full PDF' : 'View AI Summary PDF'}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-w-[95vw] h-[95vh] p-0">
                                    <DialogHeader className="p-4 border-b">
                                        <DialogTitle>
                                            {revealed ? 'Full Resume' : 'AI-Reformatted Summary'}
                                        </DialogTitle>
                                    </DialogHeader>
                                    <div className="flex-1 overflow-hidden bg-white">
                                        {pdfUrl ? (
                                            <iframe
                                                src={pdfUrl}
                                                width="100%"
                                                height="100%"
                                                className="border-0"
                                                title="PDF Viewer"
                                                sandbox="allow-scripts allow-same-origin allow-downloads"
                                                loading="lazy"
                                                onLoad={() => console.log('PDF Loaded:', pdfUrl)}
                                                onError={(e) => console.error('Iframe load failed:', e)}
                                            />
                                        ) : (
                                            <div className="flex items-center justify-center h-full text-gray-500">
                                                No PDF URL
                                            </div>
                                        )}
                                    </div>
                                    <div className="p-4 border-t flex justify-between">
                                        <span className="text-sm text-gray-600">
                                            URL: {pdfUrl ? pdfUrl.substring(0, 50) + '...' : 'None'}
                                        </span>
                                        {revealed && pdfUrl && (
                                            <a href={pdfUrl} download target="_blank" rel="noopener noreferrer">
                                                <Button variant="outline" size="sm">Download</Button>
                                            </a>
                                        )}
                                        <Button onClick={() => setOpenDialog(false)} variant="outline" size="sm">
                                            Close
                                        </Button>
                                    </div>
                                </DialogContent>
                            </Dialog>

                            {!revealed && (
                                <Button onClick={handleReveal} className="bg-green-600 text-white">
                                    Reveal Full Details
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
