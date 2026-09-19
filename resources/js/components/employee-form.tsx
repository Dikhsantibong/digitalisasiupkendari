import { Form, Link } from '@inertiajs/react';
import { FileSignature, Image as ImageIcon, PenTool, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { FormField } from '@/components/form-field';
import { SignaturePad } from '@/components/signature-pad';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import employees from '@/routes/admin/employees';
import type { EmployeeRow, FormAction, IdName } from '@/types';

type Props = {
    /** A Wayfinder form definition for store or update. */
    action: FormAction;
    employee?: EmployeeRow;
    options: {
        units: IdName[];
        service_units?: IdName[];
        users?: IdName[];
    };
    submitLabel: string;
};

const NO_SELECTION = 'none';

export function EmployeeForm({ action, employee, options, submitLabel }: Props) {
    const [previewUrl, setPreviewUrl] = useState<string | null>(employee?.signature_url ?? null);
    const [signatureMode, setSignatureMode] = useState<'canvas' | 'upload'>('canvas');
    const [signatureBase64, setSignatureBase64] = useState<string>('');
    const [removeSignature, setRemoveSignature] = useState(false);

    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleCanvasChange = (dataUrl: string | null) => {
        if (dataUrl) {
            setSignatureBase64(dataUrl);
            setPreviewUrl(dataUrl);
            setRemoveSignature(false);
        } else {
            setSignatureBase64('');
            setPreviewUrl(employee?.signature_url ?? null);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (file) {
            setPreviewUrl(URL.createObjectURL(file));
            setSignatureBase64('');
            setRemoveSignature(false);
        }
    };

    const handleRemoveSignature = () => {
        setPreviewUrl(null);
        setRemoveSignature(true);
        setSignatureBase64('');

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    return (
        <Form
            {...action}
            transform={(data) => {
                const res: Record<string, any> = {
                    ...data,
                    unit_id: data.unit_id === NO_SELECTION ? '' : data.unit_id,
                    service_unit_id: data.service_unit_id === NO_SELECTION ? '' : data.service_unit_id,
                    user_id: data.user_id === NO_SELECTION ? '' : data.user_id,
                    remove_signature: removeSignature ? '1' : '0',
                    signature_base64: signatureBase64 || '',
                };

                if (res.signature instanceof File && res.signature.size === 0) {
                    delete res.signature;
                }

                return res;
            }}
            encType="multipart/form-data"
            className="flex flex-col gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <h2 className="text-base font-semibold text-foreground">
                            Identitas Pegawai
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nama"
                                htmlFor="name"
                                required
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={employee?.name ?? ''}
                                    autoComplete="off"
                                    required
                                />
                            </FormField>

                            <FormField
                                label="NID / NIP"
                                htmlFor="nip"
                                hint="Nomor induk pegawai"
                                error={errors.nip}
                            >
                                <Input
                                    id="nip"
                                    name="nip"
                                    defaultValue={employee?.nip ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Unit Pembangkit"
                                hint="Kosongkan bila bertugas di tingkat UL / non-pembangkit."
                                error={errors.unit_id}
                            >
                                <Select
                                    name="unit_id"
                                    defaultValue={
                                        employee?.unit_id
                                            ? String(employee.unit_id)
                                            : NO_SELECTION
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih unit pembangkit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_SELECTION}>
                                            Tanpa unit pembangkit
                                        </SelectItem>
                                        {options.units.map((unit) => (
                                            <SelectItem
                                                key={unit.id}
                                                value={String(unit.id)}
                                            >
                                                {unit.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Unit Layanan"
                                hint="Khusus Manager UL atau penugasan tingkat UL."
                                error={errors.service_unit_id}
                            >
                                <Select
                                    name="service_unit_id"
                                    defaultValue={
                                        employee?.service_unit_id
                                            ? String(employee.service_unit_id)
                                            : NO_SELECTION
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih unit layanan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_SELECTION}>
                                            Tanpa unit layanan
                                        </SelectItem>
                                        {(options.service_units ?? []).map((su) => (
                                            <SelectItem
                                                key={su.id}
                                                value={String(su.id)}
                                            >
                                                {su.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Jabatan"
                                htmlFor="position"
                                hint="Penanda tangan laporan harus persis: Manager UL, Team Leader Pemeliharaan, Koordinator Pemeliharaan, Project Leader, Office Pemeliharaan/Operasi/K3/PDM/Logistik, PIC PDM (1 pegawai aktif per unit)."
                                error={errors.position}
                            >
                                <Input
                                    id="position"
                                    name="position"
                                    defaultValue={employee?.position ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>

                            <FormField
                                label="Akun Pengguna"
                                hint="Akun login pegawai ini — hanya akun ini yang dapat menyetujui / menandatangani laporan atas jabatannya."
                                error={errors.user_id}
                            >
                                <Select
                                    name="user_id"
                                    defaultValue={
                                        employee?.user_id
                                            ? String(employee.user_id)
                                            : NO_SELECTION
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih akun pengguna" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_SELECTION}>
                                            Tidak ditautkan
                                        </SelectItem>
                                        {(options.users ?? []).map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={String(user.id)}
                                            >
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Status Data"
                                error={errors.is_active}
                            >
                                <label className="flex h-9 items-center gap-2 rounded-md border border-border bg-secondary px-3">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    />
                                    <Checkbox
                                        name="is_active"
                                        value="1"
                                        defaultChecked={
                                            employee?.is_active ?? true
                                        }
                                    />
                                    <span className="text-[13px]">
                                        Pegawai aktif
                                    </span>
                                </label>
                            </FormField>
                        </div>
                    </section>

                    <section className="flex flex-col gap-4 rounded-md border border-border bg-card p-4">
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <FileSignature className="size-4 text-primary" />
                                <h2 className="text-base font-semibold text-foreground">
                                    Tanda Tangan Digital
                                </h2>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Digunakan untuk pengesahan dokumen laporan (khususnya untuk <strong>Manager UL</strong>, <strong>TL Operasi</strong>, <strong>TL K3</strong>, dan <strong>TL Pemeliharaan</strong>).
                            </p>
                        </div>

                        {/* Mode Selector */}
                        <div className="flex items-center gap-2 border-b border-border pb-3">
                            <Button
                                type="button"
                                variant={signatureMode === 'canvas' ? 'default' : 'outline'}
                                size="sm"
                                className="h-8 text-xs gap-1.5"
                                onClick={() => setSignatureMode('canvas')}
                            >
                                <PenTool className="size-3.5" />
                                Coret di Canvas (Manual)
                            </Button>
                            <Button
                                type="button"
                                variant={signatureMode === 'upload' ? 'default' : 'outline'}
                                size="sm"
                                className="h-8 text-xs gap-1.5"
                                onClick={() => setSignatureMode('upload')}
                            >
                                <Upload className="size-3.5" />
                                Unggah Berkas Gambar
                            </Button>
                        </div>

                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
                            {/* Signature Drawing / Upload Area */}
                            <div className="flex-1 min-w-0">
                                {signatureMode === 'canvas' ? (
                                    <SignaturePad onChange={handleCanvasChange} />
                                ) : (
                                    <div className="flex flex-col gap-3">
                                        <FormField
                                            label="Berkas Gambar Tanda Tangan"
                                            htmlFor="signature"
                                            hint="Format: PNG, JPG, WebP, SVG (maks. 2 MB). Disarankan menggunakan gambar PNG transparan."
                                            error={errors.signature}
                                        >
                                            <input
                                                ref={fileInputRef}
                                                id="signature"
                                                name="signature"
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                                onChange={handleFileChange}
                                                className="block w-full text-xs text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20 cursor-pointer"
                                            />
                                        </FormField>
                                    </div>
                                )}
                            </div>

                            {/* Preview Area */}
                            <div className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/20 p-3 w-full lg:w-64 shrink-0 min-h-[170px]">
                                <div className="mb-2 text-xs font-medium text-foreground">
                                    Hasil Tanda Tangan:
                                </div>
                                {previewUrl ? (
                                    <div className="flex flex-col items-center gap-2 w-full">
                                        <div className="flex h-24 w-full max-w-[220px] items-center justify-center rounded border bg-white p-2 shadow-xs">
                                            <img
                                                src={previewUrl}
                                                alt="Preview Tanda Tangan"
                                                className="max-h-full max-w-full object-contain"
                                            />
                                        </div>
                                        <span className="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                                            Tanda tangan siap digunakan
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mt-1 h-7 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive gap-1"
                                            onClick={handleRemoveSignature}
                                        >
                                            <Trash2 className="size-3" />
                                            Hapus Tanda Tangan
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center gap-1.5 text-center text-muted-foreground py-6">
                                        <ImageIcon className="size-8 stroke-[1.5]" />
                                        <span className="text-xs">Belum ada tanda tangan</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>

                    <div className="flex items-center justify-end gap-2">
                        <Button variant="secondary" asChild>
                            <Link href={employees.index()}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
