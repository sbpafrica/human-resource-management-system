import React, { useState, useEffect, useCallback } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Upload, Download, Trash, CheckCircle, XCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ErrorBoundary from '@/components/ErrorBoundary';

interface Payroll {
  id: string;
  names: string;
  dept: string;
  takehome: number;
  shop: string | null;
  bank: string | null;
  account: string | null;
  code: string | null;
  branch: string | null;
}

interface Props {
  payrolls: {
    data: Payroll[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
  };
  filters: {
    search: string | null;
    sort: string;
    order: string;
    selectedMonth: string;
  };
  formattedMonth: string;
  totalRecords: number;
  searchCount: number | null;
  flash?: {
    success?: string;
    error?: string;
  };
}

interface FormErrors {
  [key: string]: string;
}

const breadcrumbs = [
  { title: 'Hygiene Payroll', href: '/hygiene-payroll' },
];

export default function HygienePayrollIndex({ payrolls, filters, formattedMonth, totalRecords, searchCount, flash }: Props) {
  const [isBulkUploadOpen, setIsBulkUploadOpen] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState<'success' | 'error'>('success');
  const [search, setSearch] = useState(filters.search || '');
  const [sort, setSort] = useState(`${filters.sort}:${filters.order}`);
  const [month, setMonth] = useState(filters.selectedMonth);

  const { data, setData, post, processing, errors, reset } = useForm<{
    file: File | null;
    month: string;
  }>({
    file: null,
    month: filters.selectedMonth,
  });

  const getCsrfToken = (): string | undefined => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? undefined;
  };

  useEffect(() => {
    if (flash?.success) {
      setToastMessage(flash.success);
      setToastType('success');
      setShowToast(true);
    } else if (flash?.error) {
      setToastMessage(flash.error);
      setToastType('error');
      setShowToast(true);
    }
  }, [flash]);

  useEffect(() => {
    if (Object.keys(errors).length > 0) {
      const errorMessage = Object.values(errors).join(' | ');
      setToastMessage(errorMessage);
      setToastType('error');
      setShowToast(true);
    }
  }, [errors]);

  useEffect(() => {
    if (showToast) {
      const timeout = toastType === 'error' ? 60000 : 3000;
      const timer = setTimeout(() => setShowToast(false), timeout);
      return () => clearTimeout(timer);
    }
  }, [showToast, toastType]);

  const handleSearchSort = useCallback(() => {
    const [sortColumn, sortOrder] = sort.split(':');
    router.get(
      route('hygiene-payroll.index'),
      {
        month: data.month,
        search: search || undefined,
        sort: sortColumn,
        order: sortOrder,
      },
      { preserveState: true, preserveScroll: true }
    );
  }, [data.month, search, sort]);

  useEffect(() => {
    const timer = setTimeout(() => handleSearchSort(), 300);
    return () => clearTimeout(timer);
  }, [search, sort, handleSearchSort]);

  const handleMonthChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newMonth = e.target.value;
    setMonth(newMonth);
    setData('month', newMonth);
    post(route('hygiene-payroll.set-month'), {
      preserveState: true,
      onSuccess: () => {
        setToastMessage('Month updated successfully');
        setToastType('success');
        setShowToast(true);
      },
      onError: (err: FormErrors) => {
        setToastMessage('Failed to update month: ' + Object.values(err).join(' | '));
        setToastType('error');
        setShowToast(true);
      },
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] || null;
    setData('file', file);
  };

  const handleBulkUpload = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!data.file) {
      setToastMessage('Please select a file to upload.');
      setToastType('error');
      setShowToast(true);
      return;
    }
    post(route('hygiene-payroll.upload'), {
      forceFormData: true,
      onSuccess: () => {
        setIsBulkUploadOpen(false);
        reset();
        setToastMessage('File uploaded successfully');
        setToastType('success');
        setShowToast(true);
      },
      onError: (err: FormErrors) => {
        setToastMessage('Failed to upload file: ' + Object.values(err).join(' | '));
        setToastType('error');
        setShowToast(true);
      },
    });
  };

  const handleDelete = () => {
    if (window.confirm('Are you sure you want to delete all records?')) {
      post(route('hygiene-payroll.delete'), {
        onSuccess: () => {
          setSearch('');
          setSort('id:asc');
          handleSearchSort();
          setToastMessage('Records deleted successfully');
          setToastType('success');
          setShowToast(true);
        },
        onError: (err: FormErrors) => {
          setToastMessage('Failed to delete records: ' + Object.values(err).join(' | '));
          setToastType('error');
          setShowToast(true);
        },
      });
    }
  };

  const handleDownloadTemplate = () => {
    const headers: HeadersInit = {
      Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    };
    const csrfToken = getCsrfToken();
    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    fetch(route('hygiene-payroll.download-template'), { headers })
      .then((response) => {
        if (!response.ok) {
          return response.json().then((err) => {
            throw new Error(err.message || 'Failed to download template');
          });
        }
        return response.blob();
      })
      .then((blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'hygiene_payroll_template.xlsx';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      })
      .catch((error) => {
        setToastMessage('Failed to download template: ' + error.message);
        setToastType('error');
        setShowToast(true);
      });
  };

  const handleDownloadTable = () => {
    const [sortColumn, sortOrder] = sort.split(':');
    const url = new URL(route('hygiene-payroll.download-table'));
    url.searchParams.append('month', data.month);
    if (search) url.searchParams.append('search', search);
    url.searchParams.append('sort', sortColumn);
    url.searchParams.append('order', sortOrder);

    const headers: HeadersInit = {
      Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    };
    const csrfToken = getCsrfToken();
    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    fetch(url, { headers })
      .then((response) => {
        if (!response.ok) {
          return response.json().then((err) => {
            throw new Error(err.message || 'Failed to download salary table');
          });
        }
        return response.blob();
      })
      .then((blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Payroll_Table_${formattedMonth}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      })
      .catch((error) => {
        setToastMessage('Failed to download salary table: ' + error.message);
        setToastType('error');
        setShowToast(true);
      });
  };

  const handleDownloadPayroll = () => {
    console.log('handleDownloadPayroll triggered', { month: data.month });
    const url = new URL(route('hygiene-payroll.download'));
    url.searchParams.append('month', data.month);

    const headers: HeadersInit = {
      Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    };
    const csrfToken = getCsrfToken();
    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    fetch(url, { headers })
      .then((response) => {
        console.log('Response received:', {
          status: response.status,
          statusText: response.statusText,
          contentType: response.headers.get('content-type'),
          headers: Object.fromEntries(response.headers.entries()),
        });
        if (!response.ok) {
          return response.text().then((text) => {
            try {
              const json = JSON.parse(text);
              throw new Error(json.error || json.message || 'Failed to download general payroll');
            } catch (e) {
              throw new Error('Server returned an error: ' + (e.message || text));
            }
          });
        }
        if (response.headers.get('content-type') !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
          throw new Error(`Unexpected content type: ${response.headers.get('content-type')}`);
        }
        return response.blob();
      })
      .then((blob) => {
        console.log('Blob received:', { size: blob.size, type: blob.type });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Hygiene_Payroll_${formattedMonth}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      })
      .catch((error) => {
        console.error('Download Payroll Error:', error);
        setToastMessage('Failed to download general payroll: ' + error.message);
        setToastType('error');
        setShowToast(true);
      });
  };

  const handleSortChange = (value: string) => {
    setSort(value);
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Hygiene Payroll" />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg w-full sm:w-auto max-w-sm ${
                toastType === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === 'success' ? <CheckCircle className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
              {toastType === 'error' && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="ml-2 p-1 text-white hover:text-white hover:bg-red-600"
                  onClick={() => setShowToast(false)}
                >
                  <XCircle className="h-4 w-4" />
                </Button>
              )}
            </div>
          )}

          <div className="flex flex-col gap-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <h1 className="text-2xl font-bold">Payroll for {formattedMonth}</h1>
              <div className="flex flex-row items-center gap-2 flex-wrap">
                <div className="flex items-center gap-2">
                  <Label htmlFor="month">Select Month:</Label>
                  <Input
                    id="month"
                    type="month"
                    value={month}
                    onChange={handleMonthChange}
                    min="2020-01"
                    max={new Date().toISOString().slice(0, 7)}
                    className="w-40"
                  />
                </div>
                <Dialog
                  open={isBulkUploadOpen}
                  onOpenChange={(open) => {
                    setIsBulkUploadOpen(open);
                    if (!open) {
                      reset();
                    }
                  }}
                >
                  <DialogTrigger asChild>
                    <Button className="bg-green-500 hover:bg-green-600">
                      <Upload className="h-4 w-4 mr-2" />
                      Upload File
                    </Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader>
                      <DialogTitle>Upload Hygiene Payroll</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleBulkUpload} className="space-y-4">
                      <div>
                        <Label htmlFor="upload-file">Excel File</Label>
                        <Input
                          id="upload-file"
                          type="file"
                          accept=".xlsx,.xls,.csv,.ods"
                          onChange={handleFileChange}
                        />
                        {errors.file && <p className="text-red-500 text-sm">{errors.file}</p>}
                      </div>
                      <DialogFooter>
                        <Button type="submit" disabled={processing} className="bg-green-500 hover:bg-green-600">
                          {processing ? 'Uploading...' : 'Upload'}
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          onClick={() => setIsBulkUploadOpen(false)}
                        >
                          Cancel
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
                <Button
                  onClick={handleDelete}
                  className="bg-red-500 hover:bg-red-600"
                  disabled={payrolls.data.length === 0}
                >
                  <Trash className="h-4 w-4 mr-2" />
                  Delete Records
                </Button>
              </div>
            </div>

            <div className="flex flex-row items-center gap-2 flex-wrap">
              <div className="flex items-center gap-2">
                <Label htmlFor="search">Search:</Label>
                <Input
                  id="search"
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Search by ID, name, or department..."
                  className="w-64"
                />
              </div>
              <div className="flex items-center gap-2">
                <Label htmlFor="sort">Sort By:</Label>
                <Select value={sort} onValueChange={handleSortChange}>
                  <SelectTrigger id="sort" className="w-48">
                    <SelectValue placeholder="Sort By" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="id:asc">Staff ID (Asc)</SelectItem>
                    <SelectItem value="id:desc">Staff ID (Desc)</SelectItem>
                    <SelectItem value="names:asc">Name (Asc)</SelectItem>
                    <SelectItem value="names:desc">Name (Desc)</SelectItem>
                    <SelectItem value="dept:asc">Department (Asc)</SelectItem>
                    <SelectItem value="dept:desc">Department (Desc)</SelectItem>
                    <SelectItem value="bank:asc">Bank Name (Asc)</SelectItem>
                    <SelectItem value="bank:desc">Bank Name (Desc)</SelectItem>
                    <SelectItem value="takehome:asc">Net Salary (Asc)</SelectItem>
                    <SelectItem value="takehome:desc">Net Salary (Desc)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <Button
                onClick={handleDownloadTemplate}
                className="bg-blue-500 hover:bg-blue-600"
              >
                <Download className="h-4 w-4 mr-2" />
                Template
              </Button>
              <Button
                onClick={handleDownloadTable}
                className="bg-blue-500 hover:bg-blue-600"
              >
                <Download className="h-4 w-4 mr-2" />
                Salary Table
              </Button>
              <Button
                onClick={handleDownloadPayroll}
                className="bg-blue-500 hover:bg-blue-600"
              >
                <Download className="h-4 w-4 mr-2" />
                General Payroll
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>
                Payroll Records (Total: {totalRecords}
                {searchCount !== null && `, Showing: ${searchCount}`})
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Staff ID</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Department</TableHead>
                    <TableHead>Shop</TableHead>
                    <TableHead>Bank</TableHead>
                    <TableHead>Account</TableHead>
                    <TableHead>Branch Code</TableHead>
                    <TableHead>Branch</TableHead>
                    <TableHead>Net Salary</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {payrolls.data.length > 0 ? (
                    payrolls.data.map((payroll) => (
                      <TableRow key={payroll.id}>
                        <TableCell>{payroll.id}</TableCell>
                        <TableCell>{payroll.names}</TableCell>
                        <TableCell>{payroll.dept}</TableCell>
                        <TableCell>{payroll.shop ?? 'N/A'}</TableCell>
                        <TableCell>{payroll.bank ?? 'N/A'}</TableCell>
                        <TableCell>{payroll.account ?? 'N/A'}</TableCell>
                        <TableCell>{payroll.code ?? 'N/A'}</TableCell>
                        <TableCell>{payroll.branch ?? 'N/A'}</TableCell>
                        <TableCell>{payroll.takehome.toFixed(2)}</TableCell>
                      </TableRow>
                    ))
                  ) : (
                    <TableRow>
                      <TableCell colSpan={9} className="text-center">
                        No records found for {formattedMonth}.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          {payrolls.links.length > 3 && (
            <div className="flex justify-center mt-4">
              <div className="flex gap-2 flex-wrap">
                {payrolls.links.map((link, index) => (
                  <Button
                    key={index}
                    variant={link.active ? 'default' : 'outline'}
                    disabled={!link.url || link.active}
                    onClick={() => link.url && router.get(link.url)}
                    className="px-3 py-1"
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
            </div>
          )}
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
