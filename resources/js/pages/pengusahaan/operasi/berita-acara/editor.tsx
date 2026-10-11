import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Eye, Loader2, RotateCcw, Save } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useCompactLayout, useInMobileShell } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import beritaAcara from '@/routes/operasi/pengusahaan/berita-acara';
import { FeederEditorSection } from './feeder/FeederEditorSection';
import { FuelEditorSection } from './fuel/FuelEditorSection';
import { PelumasEditorSection } from './pelumas/PelumasEditorSection';
import { DocumentMetadataCard } from './shared/DocumentMetadataCard';
import { SignersCard } from './shared/SignersCard';
import type {
    AttachmentItem,
    EditorProps,
    FeederRow,
    FeederTotals,
    FisikItem,
    PelumasRow,
    PemakaianItem,
} from './types';

export default function BeritaAcaraEditor({
    type,
    filters,
    unit,
    units,
    document_number,
    data,
    form_data,
    has_saved,
    manager_options = [],
    tl_options = [],
    pdf_url,
    can_create,
}: EditorProps) {
    // One simple form: Simpan stores it, Pratinjau PDF shows the printed Berita Acara.
    const compact = useCompactLayout();
    // In the phone shell the top bar's back arrow returns to the Berita Acara list.
    const inMobileShell = useInMobileShell();
    const [saved, setSaved] = useState(has_saved);
    const [isSaving, setIsSaving] = useState(false);

    // Document Metadata
    const [docNumber, setDocNumber] = useState<string>(
        form_data.document?.number || document_number || '',
    );
    const [docTitle, setDocTitle] = useState<string>(
        form_data.document?.title || data.document?.title || '',
    );
    const [revision, setRevision] = useState<string>(
        form_data.document?.revision || '00',
    );
    const [revisionDate, setRevisionDate] = useState<string>(
        form_data.document?.revision_date || '',
    );

    // Narrative Date
    const [hari, setHari] = useState<string>(
        form_data.narrative?.hari || data.narrative?.hari || '',
    );
    const [tanggalTerbilang, setTanggalTerbilang] = useState<string>(
        form_data.narrative?.tanggal_terbilang ||
            data.narrative?.tanggal_terbilang ||
            '',
    );
    const [bulanTerbilang, setBulanTerbilang] = useState<string>(
        form_data.narrative?.bulan || data.narrative?.bulan || '',
    );
    const [tahunTerbilang, setTahunTerbilang] = useState<string>(
        form_data.narrative?.tahun_terbilang ||
            data.narrative?.tahun_terbilang ||
            '',
    );
    const [tanggalPenuh, setTanggalPenuh] = useState<string>(
        form_data.narrative?.tanggal_penuh ||
            data.narrative?.tanggal_penuh ||
            '',
    );
    const [printPlaceDate, setPrintPlaceDate] = useState<string>(
        form_data.print_place_date || data.print_place_date || '',
    );

    // Signatories
    const initialManager = manager_options.find(
        (m) =>
            m.name.trim().toLowerCase() ===
            (form_data.signers?.manajer || '').trim().toLowerCase(),
    );
    const [managerId, setManagerId] = useState<string>(
        initialManager ? String(initialManager.id) : '',
    );
    const [managerName, setManagerName] = useState<string>(
        form_data.signers?.manajer || '',
    );
    const [managerTitle, setManagerTitle] = useState<string>(
        form_data.signers?.manajer_title || 'Manajer',
    );

    const initialTl = tl_options.find(
        (t) =>
            t.name.trim().toLowerCase() ===
            (form_data.signers?.tl_operasi || '').trim().toLowerCase(),
    );
    const [tlId, setTlId] = useState<string>(
        initialTl ? String(initialTl.id) : '',
    );
    const [tlName, setTlName] = useState<string>(
        form_data.signers?.tl_operasi || '',
    );
    const [tlTitle, setTlTitle] = useState<string>(
        form_data.signers?.tl_title || 'TL. Operasi',
    );

    // Fuel State
    const [persediaanAwal, setPersediaanAwal] = useState<number>(
        Number(form_data.persediaan_awal ?? data.persediaan_awal) || 0,
    );
    const [penerimaanRange, setPenerimaanRange] = useState<string>(
        form_data.penerimaan_range || data.penerimaan_range || '',
    );
    const [penerimaanTotal, setPenerimaanTotal] = useState<number>(
        Number(form_data.penerimaan_total ?? data.penerimaan_total) || 0,
    );
    const [pemakaianList, setPemakaianList] = useState<PemakaianItem[]>(
        () => form_data.pemakaian || data.pemakaian || [],
    );
    const [pengirimanTotal, setPengirimanTotal] = useState<number>(
        Number(form_data.pengiriman ?? data.pengiriman) || 0,
    );
    const [fisikList, setFisikList] = useState<FisikItem[]>(
        () => form_data.fisik || data.fisik || [],
    );
    const [catatan, setCatatan] = useState<string>(
        form_data.catatan || data.catatan || '',
    );

    // Lubricant State
    const [pelumasRows, setPelumasRows] = useState<PelumasRow[]>(
        () => form_data.rows || data.rows || [],
    );

    // Feeder State
    const isFeeder = type.value === 'feeder';
    const isFuel =
        form_data.is_fuel ?? (type.value === 'hsd' || type.value === 'mfo');
    const [feederRows, setFeederRows] = useState<FeederRow[]>(
        () => form_data.feeder_rows || data.feeder_rows || [],
    );
    const [attachments, setAttachments] = useState<AttachmentItem[]>(
        () => form_data.attachments || data.attachments || [],
    );
    const [isUploading, setIsUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    // Computed Feeder Totals
    const feederTotals = useMemo<FeederTotals>(() => {
        let exp = 0;
        let imp = 0;
        feederRows.forEach((r) => {
            exp += Number(r.export?.hasil) || 0;
            imp += Number(r.import?.hasil) || 0;
        });

        return {
            jumlah_export: Math.round(exp * 100) / 100,
            jumlah_import: Math.round(imp * 100) / 100,
            total_unit: Math.round((exp - imp) * 100) / 100,
        };
    }, [feederRows]);

    // Computed Fuel Real-Time Math
    const jumlahStock = useMemo(
        () => Number((persediaanAwal + penerimaanTotal).toFixed(2)),
        [persediaanAwal, penerimaanTotal],
    );

    const pemakaianTotal = useMemo(
        () =>
            Number(
                pemakaianList
                    .reduce((sum, item) => sum + (Number(item.liter) || 0), 0)
                    .toFixed(2),
            ),
        [pemakaianList],
    );

    const administrasiTotal = useMemo(
        () =>
            Number((jumlahStock - pemakaianTotal - pengirimanTotal).toFixed(2)),
        [jumlahStock, pemakaianTotal, pengirimanTotal],
    );

    const fisikTotal = useMemo(
        () =>
            Number(
                fisikList
                    .reduce((sum, item) => sum + (Number(item.liter) || 0), 0)
                    .toFixed(2),
            ),
        [fisikList],
    );

    const selisihTotal = useMemo(
        () => Number((fisikTotal - administrasiTotal).toFixed(2)),
        [fisikTotal, administrasiTotal],
    );

    // Computed Pelumas Summary Row
    const pelumasTotals = useMemo(() => {
        return pelumasRows.reduce(
            (acc, row) => ({
                jenis: 'TOTAL',
                satuan: '',
                awal: Number((acc.awal + (Number(row.awal) || 0)).toFixed(2)),
                penerimaan: Number(
                    (acc.penerimaan + (Number(row.penerimaan) || 0)).toFixed(2),
                ),
                stock: Number(
                    (acc.stock + (Number(row.stock) || 0)).toFixed(2),
                ),
                pemakaian: Number(
                    (acc.pemakaian + (Number(row.pemakaian) || 0)).toFixed(2),
                ),
                pengiriman: Number(
                    (acc.pengiriman + (Number(row.pengiriman) || 0)).toFixed(2),
                ),
                administrasi: Number(
                    (
                        acc.administrasi + (Number(row.administrasi) || 0)
                    ).toFixed(2),
                ),
                fisik_liter: Number(
                    (acc.fisik_liter + (Number(row.fisik_liter) || 0)).toFixed(
                        2,
                    ),
                ),
                selisih: Number(
                    (acc.selisih + (Number(row.selisih) || 0)).toFixed(2),
                ),
            }),
            {
                jenis: 'TOTAL',
                satuan: '',
                awal: 0,
                penerimaan: 0,
                stock: 0,
                pemakaian: 0,
                pengiriman: 0,
                administrasi: 0,
                fisik_liter: 0,
                selisih: 0,
            },
        );
    }, [pelumasRows]);

    const selectedManager = manager_options.find(
        (m) => String(m.id) === managerId,
    );
    const selectedTl = tl_options.find((t) => String(t.id) === tlId);

    const handleManagerChange = (val: string) => {
        setManagerId(val);
        const opt = manager_options.find((m) => String(m.id) === val);

        if (opt) {
            setManagerName(opt.name);

            if (opt.position) {
                setManagerTitle(opt.position);
            }
        }
    };

    const handleTlChange = (val: string) => {
        setTlId(val);
        const opt = tl_options.find((t) => String(t.id) === val);

        if (opt) {
            setTlName(opt.name);

            if (opt.position) {
                setTlTitle(opt.position);
            }
        }
    };

    // Fuel Handlers
    const handleAddPemakaian = () => {
        setPemakaianList((prev) => [
            ...prev,
            { mesin: `Mesin #${prev.length + 1}`, liter: 0 },
        ]);
    };

    const handleUpdatePemakaian = (
        index: number,
        field: keyof PemakaianItem,
        val: string | number,
    ) => {
        setPemakaianList((prev) => {
            const next = [...prev];
            next[index] = {
                ...next[index],
                [field]: field === 'liter' ? Number(val) || 0 : val,
            };

            return next;
        });
    };

    const handleRemovePemakaian = (index: number) => {
        setPemakaianList((prev) => prev.filter((_, i) => i !== index));
    };

    const handleAddFisik = () => {
        setFisikList((prev) => [
            ...prev,
            { tangki: `Tangki Storage ${prev.length + 1}`, liter: 0 },
        ]);
    };

    const handleUpdateFisik = (
        index: number,
        field: keyof FisikItem,
        val: string | number,
    ) => {
        setFisikList((prev) => {
            const next = [...prev];
            next[index] = {
                ...next[index],
                [field]: field === 'liter' ? Number(val) || 0 : val,
            };

            return next;
        });
    };

    const handleRemoveFisik = (index: number) => {
        setFisikList((prev) => prev.filter((_, i) => i !== index));
    };

    // Pelumas Handlers
    const handleAddPelumasRow = () => {
        setPelumasRows((prev) => [
            ...prev,
            {
                jenis: 'Pelumas Baru',
                satuan: 'Liter',
                awal: 0,
                penerimaan: 0,
                stock: 0,
                pemakaian: 0,
                pengiriman: 0,
                administrasi: 0,
                fisik_liter: 0,
                selisih: 0,
            },
        ]);
    };

    const handleUpdatePelumasRow = (
        index: number,
        field: keyof PelumasRow,
        val: string | number,
    ) => {
        setPelumasRows((prev) => {
            const next = [...prev];
            const row = { ...next[index], [field]: val };

            const awal = Number(row.awal) || 0;
            const penerimaan = Number(row.penerimaan) || 0;
            const pemakaian = Number(row.pemakaian) || 0;
            const pengiriman = Number(row.pengiriman) || 0;
            const fisik = Number(row.fisik_liter) || 0;

            row.stock = Number((awal + penerimaan).toFixed(2));
            row.administrasi = Number(
                (row.stock - pemakaian - pengiriman).toFixed(2),
            );
            row.selisih = Number((fisik - row.administrasi).toFixed(2));

            next[index] = row;

            return next;
        });
    };

    const handleRemovePelumasRow = (index: number) => {
        setPelumasRows((prev) => prev.filter((_, i) => i !== index));
    };

    // Feeder Handlers
    const handleAddFeederRow = () => {
        setFeederRows((prev) => [
            ...prev,
            {
                feeder_name: `Feeder Outgoing ${prev.length + 1}`,
                export: { awal: 0, akhir: 0, f_kali: 1, hasil: 0 },
                import: { awal: 0, akhir: 0, f_kali: 1, hasil: 0 },
                keterangan: '',
            },
        ]);
    };

    const handleUpdateFeederName = (index: number, name: string) => {
        setFeederRows((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], feeder_name: name };

            return next;
        });
    };

    const handleUpdateFeederKeterangan = (
        index: number,
        keterangan: string,
    ) => {
        setFeederRows((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], keterangan };

            return next;
        });
    };

    const handleUpdateFeederReading = (
        index: number,
        direction: 'export' | 'import',
        field: 'awal' | 'akhir' | 'f_kali',
        val: number,
    ) => {
        setFeederRows((prev) => {
            const next = [...prev];
            const row = { ...next[index] };
            const reading = { ...row[direction] };
            reading[field] = val;
            const diff = reading.akhir - reading.awal;
            reading.hasil =
                diff > 0 ? Math.round(diff * reading.f_kali * 100) / 100 : 0;
            row[direction] = reading;
            next[index] = row;

            return next;
        });
    };

    const handleRemoveFeederRow = (index: number) => {
        setFeederRows((prev) => prev.filter((_, i) => i !== index));
    };

    // Attachment Handlers
    const handleUploadImages = async (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        const files = e.target.files;

        if (!files || files.length === 0) {
            return;
        }

        setIsUploading(true);
        setUploadError(null);

        try {
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const formData = new FormData();
                formData.append('file', file);
                formData.append('unit_id', String(filters.unit_id));
                const caption = file.name.replace(/\.[^/.]+$/, '');
                formData.append('caption', caption);

                const csrfToken = (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content;
                const res = await fetch(
                    '/operasi/pengusahaan/berita-acara/lampiran',
                    {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken || '',
                            Accept: 'application/json',
                        },
                        body: formData,
                    },
                );

                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));

                    throw new Error(
                        err.message || 'Gagal mengunggah gambar lampiran.',
                    );
                }

                const uploadRes = await res.json();
                setAttachments((prev) => [...prev, uploadRes]);
            }
        } catch (err: any) {
            setUploadError(
                err.message || 'Terjadi kesalahan saat mengunggah gambar.',
            );
        } finally {
            setIsUploading(false);
            e.target.value = '';
        }
    };

    const handleUpdateAttachmentCaption = (index: number, caption: string) => {
        setAttachments((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], caption };

            return next;
        });
    };

    const handleRemoveAttachment = async (index: number) => {
        const item = attachments[index];

        if (item?.path) {
            try {
                const csrfToken = (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content;
                await fetch('/operasi/pengusahaan/berita-acara/lampiran', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ path: item.path }),
                });
            } catch {
                // Ignore cleanup errors
            }
        }

        setAttachments((prev) => prev.filter((_, i) => i !== index));
    };

    // Filter Navigation
    const handleUnitFilterChange = (newUnitId: string) => {
        router.get(beritaAcara.show({ type: type.value }).url, {
            unit_id: newUnitId,
            month: filters.month,
            year: filters.year,
        });
    };

    const handleMonthFilterChange = (newMonth: string) => {
        router.get(beritaAcara.show({ type: type.value }).url, {
            unit_id: filters.unit_id,
            month: newMonth,
            year: filters.year,
        });
    };

    const handleYearFilterChange = (newYear: string) => {
        router.get(beritaAcara.show({ type: type.value }).url, {
            unit_id: filters.unit_id,
            month: filters.month,
            year: newYear,
        });
    };

    // Save Action
    const handleSave = (onSuccess?: () => void) => {
        setIsSaving(true);

        const formDataPayload: Record<string, any> = {
            document: {
                number: docNumber,
                title: docTitle,
                revision: revision,
                revision_date: revisionDate,
            },
            narrative: {
                hari,
                tanggal_terbilang: tanggalTerbilang,
                bulan: bulanTerbilang,
                tahun_terbilang: tahunTerbilang,
                tanggal_penuh: tanggalPenuh,
            },
            print_place_date: printPlaceDate,
            signers: {
                manajer: managerName,
                manajer_title: managerTitle,
                manajer_signature: selectedManager?.signature_url || undefined,
                tl_operasi: tlName,
                tl_title: tlTitle,
                tl_signature: selectedTl?.signature_url || undefined,
            },
            catatan,
            ...(isFeeder
                ? {
                      is_fuel: false,
                      is_feeder: true,
                      feeder_rows: feederRows,
                      totals: feederTotals,
                      attachments: attachments,
                  }
                : form_data.is_fuel
                  ? {
                        is_fuel: true,
                        fuel_label: form_data.fuel_label,
                        persediaan_awal: persediaanAwal,
                        penerimaan_range: penerimaanRange,
                        penerimaan_total: penerimaanTotal,
                        jumlah_stock: jumlahStock,
                        pemakaian: pemakaianList,
                        pemakaian_total: pemakaianTotal,
                        pengiriman: pengirimanTotal,
                        administrasi: administrasiTotal,
                        fisik: fisikList,
                        fisik_total: fisikTotal,
                        selisih: selisihTotal,
                    }
                  : {
                        is_fuel: false,
                        rows: pelumasRows,
                    }),
        };

        router.post(
            beritaAcara.store().url,
            {
                unit_id: filters.unit_id,
                type: type.value,
                month: filters.month,
                year: filters.year,
                format: 'form',
                form_data: formDataPayload,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaved(true);
                    onSuccess?.();
                },
                onFinish: () => setIsSaving(false),
            },
        );
    };

    /** The printed Berita Acara: saved first, so the PDF shows what is on the form. */
    const openPreview = () => {
        if (!can_create) {
            window.open(pdf_url, '_blank', 'noopener');

            return;
        }

        const tab = window.open('', '_blank');
        handleSave(() => {
            const url = new URL(pdf_url, window.location.origin);
            url.searchParams.set('t', String(Date.now()));

            if (tab) {
                tab.location.href = url.toString();
            } else {
                window.open(url.toString(), '_blank', 'noopener');
            }
        });
    };

    const handleRecalculate = () => {
        if (
            !confirm(
                'Muat ulang angka dari data sistem? Perubahan yang belum disimpan akan hilang.',
            )
        ) {
            return;
        }

        router.get(
            beritaAcara.show({ type: type.value }).url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
            { replace: true },
        );
    };

    const years = [filters.year - 1, filters.year, filters.year + 1];
    const saveButton = can_create && (
        <Button onClick={() => handleSave()} disabled={isSaving}>
            {isSaving ? (
                <Loader2 className="size-4 animate-spin" />
            ) : (
                <Save className="size-4" />
            )}
            Simpan
        </Button>
    );

    return (
        <>
            <Head title={`${type.label} - ${unit.name}`} />
            <div
                className={cn(
                    'flex min-w-0 flex-1 flex-col gap-4 p-4 md:p-6',
                    compact && can_create && 'pb-28',
                )}
            >
                <PageHeader
                    title={`${type.label} — ${unit.name}`}
                    description="Lengkapi formulir berita acara, simpan, lalu lihat hasil cetaknya di Pratinjau PDF."
                    actions={
                        <>
                            {can_create && (
                                <Button
                                    variant="outline"
                                    onClick={handleRecalculate}
                                    title="Muat ulang angka dari data sistem"
                                >
                                    <RotateCcw className="size-4" />
                                    Hitung Ulang Data
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={openPreview}
                                disabled={isSaving}
                            >
                                <Eye className="size-4" />
                                Pratinjau PDF
                            </Button>
                            {!compact && saveButton}
                        </>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
                    {!inMobileShell && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="self-end text-muted-foreground"
                            asChild
                        >
                            <Link href={beritaAcara.index().url}>
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Link>
                        </Button>
                    )}
                    <OperasiSelect
                        label="Unit"
                        className="w-52"
                        value={String(filters.unit_id)}
                        onChange={handleUnitFilterChange}
                        options={units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        className="w-36"
                        value={String(filters.month)}
                        onChange={handleMonthFilterChange}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        className="w-28"
                        value={String(filters.year)}
                        onChange={handleYearFilterChange}
                        options={years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    <p className="ml-auto self-center text-[13px] text-muted-foreground">
                        {saved ? 'Tersimpan' : 'Belum pernah disimpan'}
                    </p>
                </div>

                <div className="flex flex-col gap-4">
                    {/* Shared Document Metadata */}
                    <DocumentMetadataCard
                        docNumber={docNumber}
                        setDocNumber={setDocNumber}
                        docTitle={docTitle}
                        setDocTitle={setDocTitle}
                        revision={revision}
                        setRevision={setRevision}
                        revisionDate={revisionDate}
                        setRevisionDate={setRevisionDate}
                        hari={hari}
                        setHari={setHari}
                        tanggalTerbilang={tanggalTerbilang}
                        setTanggalTerbilang={setTanggalTerbilang}
                        bulanTerbilang={bulanTerbilang}
                        setBulanTerbilang={setBulanTerbilang}
                        tahunTerbilang={tahunTerbilang}
                        setTahunTerbilang={setTahunTerbilang}
                        tanggalPenuh={tanggalPenuh}
                        setTanggalPenuh={setTanggalPenuh}
                        printPlaceDate={printPlaceDate}
                        setPrintPlaceDate={setPrintPlaceDate}
                    />

                    {/* Shared Signatories Card */}
                    <SignersCard
                        managerOptions={manager_options}
                        managerId={managerId}
                        onManagerChange={handleManagerChange}
                        managerName={managerName}
                        setManagerName={setManagerName}
                        managerTitle={managerTitle}
                        setManagerTitle={setManagerTitle}
                        selectedManager={selectedManager}
                        tlOptions={tl_options}
                        tlId={tlId}
                        onTlChange={handleTlChange}
                        tlName={tlName}
                        setTlName={setTlName}
                        tlTitle={tlTitle}
                        setTlTitle={setTlTitle}
                        selectedTl={selectedTl}
                    />

                    {/* DOMAIN SPECIFIC SECTIONS */}
                    {isFeeder ? (
                        <FeederEditorSection
                            feederRows={feederRows}
                            feederTotals={feederTotals}
                            onAddFeederRow={handleAddFeederRow}
                            onUpdateFeederName={handleUpdateFeederName}
                            onUpdateFeederReading={handleUpdateFeederReading}
                            onUpdateFeederKeterangan={
                                handleUpdateFeederKeterangan
                            }
                            onRemoveFeederRow={handleRemoveFeederRow}
                            catatan={catatan}
                            setCatatan={setCatatan}
                            attachments={attachments}
                            isUploading={isUploading}
                            uploadError={uploadError}
                            onUploadImages={handleUploadImages}
                            onUpdateAttachmentCaption={
                                handleUpdateAttachmentCaption
                            }
                            onRemoveAttachment={handleRemoveAttachment}
                        />
                    ) : isFuel ? (
                        <FuelEditorSection
                            fuelLabel={form_data.fuel_label}
                            persediaanAwal={persediaanAwal}
                            setPersediaanAwal={setPersediaanAwal}
                            penerimaanRange={penerimaanRange}
                            setPenerimaanRange={setPenerimaanRange}
                            penerimaanTotal={penerimaanTotal}
                            setPenerimaanTotal={setPenerimaanTotal}
                            jumlahStock={jumlahStock}
                            pemakaianList={pemakaianList}
                            pemakaianTotal={pemakaianTotal}
                            onAddPemakaian={handleAddPemakaian}
                            onUpdatePemakaian={handleUpdatePemakaian}
                            onRemovePemakaian={handleRemovePemakaian}
                            pengirimanTotal={pengirimanTotal}
                            setPengirimanTotal={setPengirimanTotal}
                            administrasiTotal={administrasiTotal}
                            fisikList={fisikList}
                            fisikTotal={fisikTotal}
                            onAddFisik={handleAddFisik}
                            onUpdateFisik={handleUpdateFisik}
                            onRemoveFisik={handleRemoveFisik}
                            selisihTotal={selisihTotal}
                            catatan={catatan}
                            setCatatan={setCatatan}
                        />
                    ) : (
                        <PelumasEditorSection
                            pelumasRows={pelumasRows}
                            pelumasTotals={pelumasTotals}
                            onAddPelumasRow={handleAddPelumasRow}
                            onUpdatePelumasRow={handleUpdatePelumasRow}
                            onRemovePelumasRow={handleRemovePelumasRow}
                            catatan={catatan}
                            setCatatan={setCatatan}
                        />
                    )}
                </div>
            </div>

            {compact && can_create && (
                <StickyActionBar>{saveButton}</StickyActionBar>
            )}
        </>
    );
}
