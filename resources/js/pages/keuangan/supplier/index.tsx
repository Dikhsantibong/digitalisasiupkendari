import { Head, router } from '@inertiajs/react';
import { ImageUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import cashCard from '@/routes/keuangan/cash-card';
import supplierRoutes from '@/routes/keuangan/supplier';
import type { IdName } from '@/types';

type Supplier = {
    id: number;
    name: string;
    email: string | null;
    npwp: string | null;
    ktp_url: string | null;
    is_active: boolean;
    used: number;
};

type Props = {
    ul: IdName;
    filters: { ul_id: number };
    options: { uls: IdName[]; years: number[] };
    suppliers: Supplier[];
    can_manage: boolean;
};

type FormState = {
    id: number | null;
    name: string;
    email: string;
    npwp: string;
    is_active: boolean;
    ktp: File | null;
    ktp_url: string | null;
};

const EMPTY: FormState = {
    id: null,
    name: '',
    email: '',
    npwp: '',
    is_active: true,
    ktp: null,
    ktp_url: null,
};

/**
 * Master Supplier of a UL (Administrasi & Keuangan): the shops where the cash
 * card buys — nama, email, NPWP and the KTP photo, which go automatically into
 * the lampiran of every cash card row bought there.
 */
export default function SupplierIndex({
    ul,
    filters,
    options,
    suppliers,
    can_manage,
}: Props) {
    const [form, setForm] = useState<FormState | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const save = () => {
        if (!form) {
            return;
        }

        setSaving(true);
        const payload = {
            ul_id: filters.ul_id,
            name: form.name,
            email: form.email,
            npwp: form.npwp,
            is_active: form.is_active ? 1 : 0,
            ...(form.ktp ? { ktp: form.ktp } : {}),
        };
        router.post(
            form.id === null
                ? supplierRoutes.store().url
                : supplierRoutes.update(form.id).url,
            payload,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setForm(null);
                    setErrors({});
                },
                onError: (next) => setErrors(next),
                onFinish: () => setSaving(false),
            },
        );
    };

    const remove = (supplier: Supplier) => {
        if (
            !window.confirm(
                supplier.used > 0
                    ? `${supplier.name} sudah dipakai di ${supplier.used} baris cash card, sehingga hanya dinonaktifkan. Lanjutkan?`
                    : `Hapus supplier ${supplier.name}?`,
            )
        ) {
            return;
        }

        router.delete(supplierRoutes.destroy(supplier.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Master Supplier" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={`Master Supplier — ${ul.name}`}
                    description="Toko / tempat pembelian cash card. Nama, email, NPWP dan foto KTP otomatis muncul di lampiran bukti kas kecil."
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.get(
                                        cashCard.index({
                                            query: { ul_id: ul.id },
                                        }).url,
                                    )
                                }
                            >
                                Laporan Cash Card
                            </Button>
                            {can_manage && (
                                <Button onClick={() => setForm({ ...EMPTY })}>
                                    <Plus className="size-4" /> Tambah Supplier
                                </Button>
                            )}
                        </>
                    }
                />

                {options.uls.length > 1 && (
                    <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                        <OperasiSelect
                            label="Unit Layanan (UL)"
                            value={String(filters.ul_id)}
                            onChange={(value) =>
                                router.get(
                                    supplierRoutes.index({
                                        query: { ul_id: Number(value) },
                                    }).url,
                                )
                            }
                            className="w-64"
                            options={options.uls.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                        />
                    </div>
                )}

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="excel-sheet w-full min-w-[760px] text-[13px]">
                        <thead>
                            <tr>
                                <th className="w-10 p-2">No</th>
                                <th className="p-2 text-left">Nama Supplier</th>
                                <th className="p-2 text-left">Email</th>
                                <th className="p-2 text-left">NPWP</th>
                                <th className="w-28 p-2">Foto KTP</th>
                                <th className="w-24 p-2">Status</th>
                                {can_manage && <th className="w-24 p-2" />}
                            </tr>
                        </thead>
                        <tbody>
                            {suppliers.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={can_manage ? 7 : 6}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        Belum ada supplier. Tambahkan supplier
                                        agar bisa dipilih di Laporan Cash Card.
                                    </td>
                                </tr>
                            )}
                            {suppliers.map((supplier, index) => (
                                <tr key={supplier.id}>
                                    <td className="p-2 text-center">
                                        {index + 1}
                                    </td>
                                    <td className="p-2 font-medium">
                                        {supplier.name}
                                        {supplier.used > 0 && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                ({supplier.used} transaksi)
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-2">
                                        {supplier.email ?? '—'}
                                    </td>
                                    <td className="p-2">
                                        {supplier.npwp ?? '—'}
                                    </td>
                                    <td className="p-2 text-center">
                                        {supplier.ktp_url ? (
                                            <a
                                                href={supplier.ktp_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <img
                                                    src={supplier.ktp_url}
                                                    alt={`KTP ${supplier.name}`}
                                                    className="mx-auto h-10 rounded object-cover"
                                                />
                                            </a>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                Belum ada
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-2 text-center">
                                        <StatusBadge
                                            tone={
                                                supplier.is_active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {supplier.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </StatusBadge>
                                    </td>
                                    {can_manage && (
                                        <td className="p-2 text-center whitespace-nowrap">
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Ubah ${supplier.name}`}
                                                onClick={() =>
                                                    setForm({
                                                        id: supplier.id,
                                                        name: supplier.name,
                                                        email:
                                                            supplier.email ??
                                                            '',
                                                        npwp:
                                                            supplier.npwp ?? '',
                                                        is_active:
                                                            supplier.is_active,
                                                        ktp: null,
                                                        ktp_url:
                                                            supplier.ktp_url,
                                                    })
                                                }
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Hapus ${supplier.name}`}
                                                onClick={() => remove(supplier)}
                                            >
                                                <Trash2 className="size-4 text-red-600" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={form !== null}
                onOpenChange={(open) => !open && setForm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {form?.id ? 'Ubah Supplier' : 'Tambah Supplier'}
                        </DialogTitle>
                    </DialogHeader>
                    {form && (
                        <div className="flex flex-col gap-3">
                            <label className="flex flex-col gap-1 text-[13px]">
                                Nama supplier / toko *
                                <Input
                                    value={form.name}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            name: e.target.value,
                                        })
                                    }
                                    placeholder="mis. TOKO DILLA JAYA"
                                />
                                {errors.name && (
                                    <span className="text-xs text-red-600">
                                        {errors.name}
                                    </span>
                                )}
                            </label>
                            <label className="flex flex-col gap-1 text-[13px]">
                                Email
                                <Input
                                    type="email"
                                    value={form.email}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            email: e.target.value,
                                        })
                                    }
                                />
                                {errors.email && (
                                    <span className="text-xs text-red-600">
                                        {errors.email}
                                    </span>
                                )}
                            </label>
                            <label className="flex flex-col gap-1 text-[13px]">
                                No. NPWP
                                <Input
                                    value={form.npwp}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            npwp: e.target.value,
                                        })
                                    }
                                />
                            </label>
                            <div className="flex flex-col gap-1 text-[13px]">
                                Foto KTP
                                <div className="flex items-center gap-3">
                                    {(form.ktp || form.ktp_url) && (
                                        <img
                                            src={
                                                form.ktp
                                                    ? URL.createObjectURL(
                                                          form.ktp,
                                                      )
                                                    : (form.ktp_url ?? '')
                                            }
                                            alt="KTP"
                                            className="h-16 rounded border object-cover"
                                        />
                                    )}
                                    <label className="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-border px-3 py-2 hover:bg-secondary">
                                        <ImageUp className="size-4" />
                                        {form.ktp || form.ktp_url
                                            ? 'Ganti foto'
                                            : 'Pilih foto'}
                                        <input
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    ktp:
                                                        e.target.files?.[0] ??
                                                        null,
                                                })
                                            }
                                        />
                                    </label>
                                </div>
                                {errors.ktp && (
                                    <span className="text-xs text-red-600">
                                        {errors.ktp}
                                    </span>
                                )}
                            </div>
                            {form.id !== null && (
                                <label className="flex items-center gap-2 text-[13px]">
                                    <Checkbox
                                        checked={form.is_active}
                                        onCheckedChange={(checked) =>
                                            setForm({
                                                ...form,
                                                is_active: checked === true,
                                            })
                                        }
                                    />
                                    Aktif (bisa dipilih di cash card)
                                </label>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setForm(null)}>
                            Batal
                        </Button>
                        <Button onClick={save} disabled={saving}>
                            {saving ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

SupplierIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Master Supplier', href: supplierRoutes.index() },
    ],
};
