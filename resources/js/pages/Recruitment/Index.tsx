import { Head, Link } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useState } from "react";
import axios from "axios";

interface Applicant {
    id: number;
    firstname: string;
    lastname: string;
    skills: string | null;
    experience: string | null;
    qualification: string | null;
    resume: string | null;
}

export default function RecruitmentIndex({ applicants }: { applicants: { data: Applicant[] } }) {
    const [parseStatus, setParseStatus] = useState<string | null>(null);

    const handleParse = async (id: number) => {
        setParseStatus('Parsing...');
        try {
            const res = await axios.post(`/recruitment/parse/${id}`);
            setParseStatus(res.data.message || 'Parse queued');

            // Poll for parse completion using the data endpoint
            const checkStatus = async () => {
                try {
                    const viewRes = await axios.get(`/recruitment/resume/${id}`);
                    if (viewRes.data.data) {
                        setParseStatus('Parsed successfully');
                    } else if (viewRes.data.error) {
                        setParseStatus(`Parse status: ${viewRes.data.error}`);
                        setTimeout(checkStatus, 2000); // Continue polling if not ready
                    } else {
                        setTimeout(checkStatus, 2000);
                    }
                } catch (err: any) {
                    setParseStatus('Error checking status: ' + (err.response?.data?.error || 'Unknown error'));
                }
            };
            setTimeout(checkStatus, 2000); // Start polling after 2 seconds
        } catch (err: any) {
            setParseStatus('Parse error: ' + (err.response?.data?.error || 'Unknown error'));
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: "Recruitment", href: "/recruitment" }]}>
            <Head title="Recruitment" />
            <div className="p-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
                <h1 className="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6">Applicant CV Reviews</h1>

                {parseStatus && (
                    <div className={`mb-4 p-4 rounded-lg ${
                        parseStatus.includes('error') || parseStatus.includes('failed')
                            ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200'
                            : 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200'
                    }`}>
                        {parseStatus}
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {applicants.data.map((applicant) => (
                        <Card key={applicant.id} className="shadow-md hover:shadow-lg transition-shadow duration-200 border border-gray-200 dark:border-gray-700">
                            <CardHeader>
                                <CardTitle className="text-lg font-semibold">{`${applicant.firstname} ${applicant.lastname}`}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                    <p><strong>Skills:</strong> {applicant.skills || 'N/A'}</p>
                                    <p><strong>Experience:</strong> {applicant.experience || 'N/A'}</p>
                                    <p><strong>Qualification:</strong> {applicant.qualification || 'N/A'}</p>
                                </div>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Button
                                        onClick={() => handleParse(applicant.id)}
                                        variant="default"
                                        size="sm"
                                        className="bg-blue-600 hover:bg-blue-700 text-white"
                                    >
                                        Parse CV
                                    </Button>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={`/recruitment/view/${applicant.id}`}>
                                            View CV
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {applicants.data.length === 0 && (
                    <p className="text-center text-gray-500 dark:text-gray-400 mt-8">No applicants found.</p>
                )}
            </div>
        </AppLayout>
    );
}
