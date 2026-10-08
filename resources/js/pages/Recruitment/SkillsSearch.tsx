import { Head } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    useReactTable,
} from "@tanstack/react-table";
import { useState, useEffect } from "react";
import axios from "axios";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

interface Applicant {
    id: number;
    skills_array: string[];
    qualification: string;
    experience: string;
    location: string;
    gender: string;
    resume: string;
}

export default function SkillsSearch() {
    const [skills, setSkills] = useState<string>("");
    const [qualification, setQualification] = useState<string>("");
    const [experience, setExperience] = useState<string>("");
    const [location, setLocation] = useState<string>("");
    const [gender, setGender] = useState<string>("");
    const [applicants, setApplicants] = useState<Applicant[]>([]);
    const [openDialog, setOpenDialog] = useState<boolean>(false);
    const [pdfUrl, setPdfUrl] = useState<string>("");
    const [loading, setLoading] = useState<boolean>(true);
    const [currentPage, setCurrentPage] = useState<number>(1);
    const [totalPages, setTotalPages] = useState<number>(1);
    const [selectedApplicantId, setSelectedApplicantId] = useState<number | null>(null);

    const performSearch = async (
        searchSkills: string,
        searchQual: string,
        searchExp: string,
        searchLoc: string,
        searchGender: string,
        page: number = 1
    ) => {
        setLoading(true);
        try {
            const res = await axios.post("/recruitment/search-skills", {
                skills: searchSkills,
                qualification: searchQual,
                experience: searchExp,
                location: searchLoc,
                gender: searchGender,
                page,
                per_page: 10,
            });
            setApplicants(res.data.applicants || []);
            setTotalPages(Math.ceil(res.data.total / res.data.per_page));
            setCurrentPage(res.data.page);
        } catch (error) {
            console.error("Search failed:", error);
            setApplicants([]);
            setTotalPages(1);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        performSearch("", "", "", "", "", 1);
    }, []);

    const handleSearch = () => {
        performSearch(skills, qualification, experience, location, gender, 1);
    };

    const handlePageChange = (newPage: number) => {
        performSearch(skills, qualification, experience, location, gender, newPage);
    };

    const openPdf = async (applicantId: number) => {
    setSelectedApplicantId(applicantId);
    setOpenDialog(true);
    setPdfUrl("");

    try {
        // Trigger parsing (idemp Potemkin)
        await axios.post(`/recruitment/parse-resume/${applicantId}`);

        // Poll the generator endpoint
        const poll = async () => {
            try {
                const res = await axios.get(`/recruitment/reformatted-resume/${applicantId}`);
                if (res.data.pdf_url) {
                    setPdfUrl(res.data.pdf_url);
                    return;
                }
            } catch (err: any) {
                if (err.response?.status !== 202) {
                    console.warn("Poll error", err);
                }
            }
            setTimeout(poll, 3000); // Retry every 3s
        };

        poll();

        // Timeout safety
        setTimeout(() => {
            if (!pdfUrl) {
                setOpenDialog(false);
                alert("Generation timed out. Please try again.");
            }
        }, 60000);
    } catch (err: any) {
        setOpenDialog(false);
        alert("Error: " + (err.response?.data?.error || err.message));
    }
};

    const columns: ColumnDef<Applicant>[] = [
        {
            id: "index",
            header: () => <div className="w-12 text-sm p-2">#</div>,
            cell: ({ row }) => {
                const index = (currentPage - 1) * 10 + row.index + 1;
                return <div className="w-12 text-sm p-2">{index}</div>;
            },
        },
        {
            accessorKey: "skills_array",
            header: () => <div className="w-56 text-sm p-2">Skills</div>,
            cell: ({ row }) => (
                <div className="w-56 text-sm p-2 align-top">
                    <ul className="list-disc list-inside max-w-[220px] whitespace-normal space-y-0.5">
                        {row.original.skills_array.map((skill, index) => (
                            <li key={index}>{skill.trim()}</li>
                        ))}
                    </ul>
                </div>
            ),
        },
        {
            accessorKey: "qualification",
            header: () => <div className="w-44 text-sm p-2">Qualification</div>,
            cell: ({ row }) => <div className="w-44 text-sm p-2">{row.original.qualification || "N/A"}</div>,
        },
        {
            accessorKey: "experience",
            header: () => <div className="w-36 text-sm p-2">Experience</div>,
            cell: ({ row }) => <div className="w-36 text-sm p-2">{row.original.experience || "N/A"}</div>,
        },
        {
            accessorKey: "location",
            header: () => <div className="w-44 text-sm p-2">Location</div>,
            cell: ({ row }) => <div className="w-44 text-sm p-2">{row.original.location || "N/A"}</div>,
        },
        {
            accessorKey: "gender",
            header: () => <div className="w-28 text-sm p-2">Gender</div>,
            cell: ({ row }) => <div className="w-28 text-sm p-2">{row.original.gender || "N/A"}</div>,
        },
        {
            id: "actions",
            header: () => <div className="w-28 text-sm p-2">Action</div>,
            cell: ({ row }) => (
                <div className="w-28 text-sm p-2">
                    <Button size="sm" onClick={() => openPdf(row.original.id)}>
                        View CV
                    </Button>
                </div>
            ),
        },
    ];

    const table = useReactTable({
        data: applicants,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        manualPagination: true,
        pageCount: totalPages,
        state: {
            pagination: {
                pageIndex: currentPage - 1,
                pageSize: 10,
            },
        },
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: "Recruitment", href: "/recruitment" },
                { title: "Skills Search", href: "/recruitment/skills-search" },
            ]}
        >
            <Head title="Skills Search" />
            <div className="p-6">
                <Card className="max-w-7xl mx-auto">
                    <CardHeader>
                        <CardTitle>Search Applicants by Skills, Qualification, Experience, Location, Gender</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-5 gap-4 mb-6">
                            <Input placeholder="Skills (e.g., PHP)" value={skills} onChange={(e) => setSkills(e.target.value)} />
                            <Input placeholder="Qualification (e.g., BSc)" value={qualification} onChange={(e) => setQualification(e.target.value)} />
                            <Input placeholder="Experience (e.g., 5 years)" value={experience} onChange={(e) => setExperience(e.target.value)} />
                            <Input placeholder="Location (e.g., Nairobi)" value={location} onChange={(e) => setLocation(e.target.value)} />
                            <Input placeholder="Gender (e.g., Male)" value={gender} onChange={(e) => setGender(e.target.value)} />
                        </div>
                        <Button onClick={handleSearch}>Search</Button>

                        {loading ? (
                            <p className="mt-6 text-center text-gray-500">Loading applicants...</p>
                        ) : applicants.length > 0 ? (
                            <>
                                <div className="overflow-x-auto mt-6">
                                    <Table className="table-fixed w-full">
                                        <TableHeader>
                                            {table.getHeaderGroups().map((headerGroup) => (
                                                <TableRow key={headerGroup.id}>
                                                    {headerGroup.headers.map((header) => (
                                                        <TableHead key={header.id}>
                                                            {flexRender(header.column.columnDef.header, header.getContext())}
                                                        </TableHead>
                                                    ))}
                                                </TableRow>
                                            ))}
                                        </TableHeader>
                                        <TableBody>
                                            {table.getRowModel().rows.map((row) => (
                                                <TableRow key={row.id}>
                                                    {row.getVisibleCells().map((cell) => (
                                                        <TableCell key={cell.id}>
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </TableCell>
                                                    ))}
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                <div className="flex items-center justify-between mt-4">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handlePageChange(currentPage - 1)}
                                        disabled={currentPage === 1}
                                    >
                                        Previous
                                    </Button>
                                    <span className="text-sm text-gray-600">
                                        Page {currentPage} of {totalPages}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handlePageChange(currentPage + 1)}
                                        disabled={currentPage === totalPages}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <p className="mt-6 text-center text-gray-500">No applicants found with skills.</p>
                        )}
                    </CardContent>
                </Card>

            <Dialog open={openDialog} onOpenChange={setOpenDialog}>
    <DialogContent className="max-w-[95vw] w-full max-h-[95vh] h-[95vh] p-0 overflow-hidden"> {/* Taller modal */}
        <DialogHeader className="p-4 border-b">
            <DialogTitle>Reformatted Resume Summary</DialogTitle>
        </DialogHeader>
        <div className="flex-1 h-full overflow-auto"> {/* Full height for iframe */}
            {pdfUrl ? (
                <iframe src={pdfUrl} width="100%" height="100%" className="border-0" />
            ) : (
                <p className="text-center text-gray-500 p-6">Generating summary...</p>
            )}
        </div>
        <div className="p-4 border-t flex justify-between items-center">
            <Button variant="outline" size="sm" onClick={() => setOpenDialog(false)}>
                Close
            </Button>
            <Button
                variant="default"
                size="sm"
                onClick={() => {
                    if (selectedApplicantId) {
                        window.location.href = `/recruitment/view/${selectedApplicantId}`;
                    }
                }}
            >
                View Full CV
            </Button>
        </div>
    </DialogContent>
</Dialog>
            </div>
        </AppLayout>
    );
}
