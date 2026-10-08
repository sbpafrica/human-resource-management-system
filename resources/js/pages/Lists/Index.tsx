// resources/js/Pages/Lists/Index.tsx
import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Plus, Pencil, Trash, CheckCircle2, XCircle } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useState, useEffect } from "react";
import { Input as ShadcnInput } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import ErrorBoundary from "@/components/ErrorBoundary";

interface List {
  id: number;
  title: string;
  description: string | null;
}

interface Props {
  lists: {
    data: List[];
    links: any[];
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

const breadcrumbs = [
  { title: "Lists", href: "/lists" },
];

export default function ListsIndex({ lists, flash }: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const [editingList, setEditingList] = useState<List | null>(null);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [deleteListId, setDeleteListId] = useState<number | null>(null);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState("");
  const [toastType, setToastType] = useState<"success" | "error">("success");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("title");
  const [order, setOrder] = useState("asc");

  const { data, setData, post, put, processing, reset, delete: destroy, get } = useForm({
    title: "",
    description: "",
  });

  useEffect(() => {
    if (flash?.success) {
      setToastMessage(flash.success);
      setToastType("success");
      setShowToast(true);
    } else if (flash?.error) {
      setToastMessage(flash.error);
      setToastType("error");
      setShowToast(true);
    }
  }, [flash]);

  useEffect(() => {
    if (showToast) {
      const timer = setTimeout(() => setShowToast(false), 3000);
      return () => clearTimeout(timer);
    }
  }, [showToast]);

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (editingList) {
      put(route("lists.update", editingList.id), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
          setEditingList(null);
        },
      });
    } else {
      post(route("lists.store"), {
        onSuccess: () => {
          setIsOpen(false);
          reset();
        },
      });
    }
  };

  const handleEdit = (list: List) => {
    setEditingList(list);
    setData({
      title: list.title,
      description: list.description || "",
    });
    setIsOpen(true);
  };

  const handleDelete = (listId: number) => {
    setDeleteListId(listId);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (deleteListId) {
      destroy(route("lists.destroy", deleteListId), {
        onSuccess: () => setIsDeleteOpen(false),
      });
    }
  };

  const handleSearchSort = () => {
    get(route("lists.index", { search, sort, order }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <ErrorBoundary>
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Lists" />
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
          {showToast && (
            <div
              className={`fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg p-4 shadow-lg ${
                toastType === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
              } animate-in fade-in slide-in-from-top-5`}
            >
              {toastType === "success" ? <CheckCircle2 className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              <span>{toastMessage}</span>
            </div>
          )}

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h1 className="text-2xl font-bold">Lists</h1>
            <div className="flex flex-col sm:flex-row gap-4">
              <ShadcnInput
                placeholder="Search lists..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyUp={handleSearchSort}
                className="w-full sm:w-64"
              />
              <Select
                value={`${sort}:${order}`}
                onValueChange={(value) => {
                  setSort(value.split(":")[0]);
                  setOrder(value.split(":")[1]);
                  handleSearchSort();
                }}
              >
                <SelectTrigger className="w-full sm:w-48">
                  <SelectValue placeholder="Sort by" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="title:asc">Title (A-Z)</SelectItem>
                  <SelectItem value="title:desc">Title (Z-A)</SelectItem>
                  <SelectItem value="created_at:asc">Created (Oldest)</SelectItem>
                  <SelectItem value="created_at:desc">Created (Newest)</SelectItem>
                </SelectContent>
              </Select>
              <Dialog
                open={isOpen}
                onOpenChange={(open) => {
                  setIsOpen(open);
                  if (!open) {
                    reset();
                    setEditingList(null);
                  }
                }}
              >
                <DialogTrigger asChild>
                  <Button className="w-full sm:w-auto">
                    <Plus className="h-4 w-4 mr-2" />
                    New List
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>{editingList ? "Edit List" : "Create New List"}</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                          id="title"
                          value={data.title}
                          onChange={(e) => setData("title", e.target.value)}
                          required
                        />
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                          id="description"
                          value={data.description}
                          onChange={(e) => setData("description", e.target.value)}
                        />
                      </div>
                    </div>
                    <DialogFooter>
                      <Button type="submit" disabled={processing}>
                        {editingList ? "Update" : "Create"}
                      </Button>
                    </DialogFooter>
                  </form>
                </DialogContent>
              </Dialog>
            </div>
          </div>

          {lists.data.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Your Lists</CardTitle>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Title</TableHead>
                      <TableHead>Description</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {lists.data.map((list) => (
                      <TableRow key={list.id}>
                        <TableCell>{list.title}</TableCell>
                        <TableCell>{list.description || "No description"}</TableCell>
                        <TableCell>
                          <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => handleEdit(list)}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button variant="destructive" size="sm" onClick={() => handleDelete(list.id)}>
                              <Trash className="h-4 w-4" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                <div className="flex justify-end mt-4">
                  <div className="flex gap-2">
                    {lists.links.map((link, index) => (
                      <Button
                        key={index}
                        variant={link.active ? "default" : "outline"}
                        disabled={!link.url}
                        asChild={!!link.url}
                      >
                        {link.url ? (
                          <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ) : (
                          <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        )}
                      </Button>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent className="pt-6">
                <p className="text-center text-gray-500">No lists found. Create a new list to get started!</p>
              </CardContent>
            </Card>
          )}

          <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Confirm Deletion</DialogTitle>
              </DialogHeader>
              <p>Are you sure you want to delete this list? This action cannot be undone.</p>
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsDeleteOpen(false)}>
                  Cancel
                </Button>
                <Button variant="destructive" onClick={confirmDelete}>
                  Delete
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </AppLayout>
    </ErrorBoundary>
  );
}
