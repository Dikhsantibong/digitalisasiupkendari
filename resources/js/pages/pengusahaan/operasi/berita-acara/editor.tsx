import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Download,
    ExternalLink,
    FileCode2,
    FileSpreadsheet,
    Info,
    Loader2,
    Printer,
    RotateCcw,
    Save,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { PdfPreviewFrame } from '@/components/pdf-preview-frame';
import { RichTextEditor } from '@/components/rich-text-editor';
import { SpreadsheetEditor } from '@/components/spreadsheet-editor';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { downloadGridAsXlsx } from '@/lib/spreadsheet';
import type { DocumentGrid } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import beritaAcara from '@/routes/operasi/pengusahaan/berita-acara';
import { FeederEditorSection } from './feeder/FeederEditorSection';
import { FuelEditorSection } from './fuel/FuelEditorSection';
import { PelumasEditorSection } from './pelumas/PelumasEditorSection';
import { DocumentMetadataCard } from './shared/DocumentMetadataCard';
import { PageMarginSettingsCard } from './shared/PageMarginSettingsCard';
import { SignersCard } from './shared/SignersCard';
import {
    MONTHS,
    type AttachmentItem,
    type EditorProps,
    type EditorTab,
    type FeederRow,
    type FeederTotals,
    type FisikItem,
    type PelumasRow,
    type PemakaianItem,
} from './types';

/** Scoped styling for the letterhead banner shown above the spreadsheet editor. */
const LETTERHEAD_STYLES = `
.ba-letterhead { background: #fff; color: #000; }
.ba-letterhead img { height: 52px; }
.ba-letterhead .ba-org { text-align: center; font-weight: bold; line-height: 1.35; font-size: 12px; }
.ba-letterhead .ba-org small { font-weight: normal; }
.ba-letterhead .ba-meta { border: 1px solid #000; border-collapse: collapse; width: 100%; font-size: 11px; }
.ba-letterhead .ba-meta td { border: 1px solid #000; padding: 3px 6px; }
.ba-letterhead .ba-hr { border: none; border-top: 1px solid #000; margin-top: 8px; }
`;

export default function BeritaAcaraEditor({
    type,
    filters,
    unit,
    units,
    document_number,
    data,
    form_data,
    content,
    content_styles,
    letterhead,
    grid,
    format,
    has_saved,
    manager_options = [],
    tl_options = [],
    pdf_url,
    can_create,
}: EditorProps) {
    // Current active tab & editor mode
    const [activeTab, setActiveTab] = useState<EditorTab>(
        format === 'html' ? 'html' : format === 'grid' ? 'grid' : 'form',
    );
    const [editMode, setEditMode] = useState<'form' | 'html' | 'grid'>(
        format === 'html' ? 'html' : format === 'grid' ? 'grid' : 'form',
    );
    const [savedFormat, setSavedFormat] = useState<'form' | 'html' | 'grid' | null>(
        has_saved ? format : null,
    );
    const [previewKey, setPreviewKey] = useState(0);
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
        form_data.narrative?.tanggal_terbilang || data.narrative?.tanggal_terbilang || '',
    );
    const [bulanTerbilang, setBulanTerbilang] = useState<string>(
        form_data.narrative?.bulan || data.narrative?.bulan || '',
    );
    const [tahunTerbilang, setTahunTerbilang] = useState<string>(
        form_data.narrative?.tahun_terbilang || data.narrative?.tahun_terbilang || '',
    );
    const [tanggalPenuh, setTanggalPenuh] = useState<string>(
        form_data.narrative?.tanggal_penuh || data.narrative?.tanggal_penuh || '',
    );
    const [printPlaceDate, setPrintPlaceDate] = useState<string>(
        form_data.print_place_date || data.print_place_date || '',
    );

    // Signatories
    const initialManager = manager_options.find(
        (m) => m.name.trim().toLowerCase() === (form_data.signers?.manajer || '').trim().toLowerCase(),
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
        (t) => t.name.trim().toLowerCase() === (form_data.signers?.tl_operasi || '').trim().toLowerCase(),
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

    // Page Layout Settings
    const [showSettings, setShowSettings] = useState(false);
    const [marginTop, setMarginTop] = useState<number>(15);
    const [marginBottom, setMarginBottom] = useState<number>(15);
    const [marginLeft, setMarginLeft] = useState<number>(15);
    const [marginRight, setMarginRight] = useState<number>(15);
    const [lineSpacing, setLineSpacing] = useState<string>('1.15');

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
    const isFuel = form_data.is_fuel ?? (type.value === 'hsd' || type.value === 'mfo');
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

    // Rich Text & Grid State
    const [html, setHtml] = useState(content);
    const [gridState, setGridState] = useState<DocumentGrid>(grid);

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
        () => Number((jumlahStock - pemakaianTotal - pengirimanTotal).toFixed(2)),
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
                penerimaan: Number((acc.penerimaan + (Number(row.penerimaan) || 0)).toFixed(2)),
                stock: Number((acc.stock + (Number(row.stock) || 0)).toFixed(2)),
                pemakaian: Number((acc.pemakaian + (Number(row.pemakaian) || 0)).toFixed(2)),
                pengiriman: Number((acc.pengiriman + (Number(row.pengiriman) || 0)).toFixed(2)),
                administrasi: Number((acc.administrasi + (Number(row.administrasi) || 0)).toFixed(2)),
                fisik_liter: Number((acc.fisik_liter + (Number(row.fisik_liter) || 0)).toFixed(2)),
                selisih: Number((acc.selisih + (Number(row.selisih) || 0)).toFixed(2)),
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

    const selectedManager = manager_options.find((m) => String(m.id) === managerId);
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

    const handleUpdatePemakaian = (index: number, field: keyof PemakaianItem, val: string | number) => {
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

    const handleUpdateFisik = (index: number, field: keyof FisikItem, val: string | number) => {
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
            row.administrasi = Number((row.stock - pemakaian - pengiriman).toFixed(2));
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

    const handleUpdateFeederKeterangan = (index: number, keterangan: string) => {
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
            reading.hasil = diff > 0 ? Math.round(diff * reading.f_kali * 100) / 100 : 0;
            row[direction] = reading;
            next[index] = row;
            return next;
        });
    };

    const handleRemoveFeederRow = (index: number) => {
        setFeederRows((prev) => prev.filter((_, i) => i !== index));
    };

    // Attachment Handlers
    const handleUploadImages = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (!files || files.length === 0) return;

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

                const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
                const res = await fetch('/operasi/pengusahaan/berita-acara/lampiran', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Gagal mengunggah gambar lampiran.');
                }

                const uploadRes = await res.json();
                setAttachments((prev) => [...prev, uploadRes]);
            }
        } catch (err: any) {
            setUploadError(err.message || 'Terjadi kesalahan saat mengunggah gambar.');
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
                const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
                await fetch('/operasi/pengusahaan/berita-acara/lampiran', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ path: item.path }),
                });
            } catch (e) {
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
    const handleSave = (onSuccessCallback?: () => void) => {
        setIsSaving(true);
        const formatToSave = activeTab === 'pdf' ? editMode : activeTab;

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
            page_margin_top: marginTop,
            page_margin_bottom: marginBottom,
            page_margin_left: marginLeft,
            page_margin_right: marginRight,
            line_spacing: lineSpacing,
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

        const postPayload: Record<string, any> = {
            unit_id: filters.unit_id,
            type: type.value,
            month: filters.month,
            year: filters.year,
            format: formatToSave,
            form_data: formDataPayload,
        };

        if (formatToSave === 'html') {
            postPayload.content_html = html;
        } else if (formatToSave === 'grid') {
            postPayload.content_grid = gridState;
        }

        router.post(beritaAcara.store().url, postPayload, {
            preserveScroll: true,
            onSuccess: () => {
                setSavedFormat(formatToSave);
                setPreviewKey((k) => k + 1);
                if (onSuccessCallback) {
                    onSuccessCallback();
                }
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    };

    const handleSwitchTab = (tab: EditorTab) => {
        if (tab === 'pdf') {
            handleSave(() => {
                setActiveTab('pdf');
            });
            return;
        }
        setActiveTab(tab);
        setEditMode(tab);
    };

    const handleResetToSystemTemplate = () => {
        if (
            !confirm(
                'Apakah Anda yakin ingin memuat ulang data sistem? Perubahan yang belum disimpan akan hilang.',
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

    const activeFormatBadge =
        savedFormat === 'grid'
            ? 'Format: Spreadsheet Grid'
            : savedFormat === 'html'
              ? 'Format: Rich Text'
              : has_saved
                ? 'Format: Formulir Dinamis'
                : 'Belum Disimpan (Draft)';

    const dynamicPdfUrl = useMemo(() => {
        const u = new URL(pdf_url, window.location.origin);
        u.searchParams.set('page_margin_top', String(marginTop));
        u.searchParams.set('page_margin_bottom', String(marginBottom));
        u.searchParams.set('page_margin_left', String(marginLeft));
        u.searchParams.set('page_margin_right', String(marginRight));
        u.searchParams.set('line_spacing', lineSpacing);
        u.searchParams.set('t', String(previewKey));
        return u.toString();
    }, [pdf_url, marginTop, marginBottom, marginLeft, marginRight, lineSpacing, previewKey]);

    return (
        <div className="space-y-6">
            <Head title={`Editor ${type.label} - ${unit.name}`} />

            <PageHeader
                title={`${type.label} - ${unit.name}`}
                description="Lengkapi data formulir, edit langsung tampilan dokumen (HTML), sesuaikan kisi sel (Excel), atau pratinjau PDF sebelum dicetak."
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge tone={has_saved ? 'success' : 'neutral'}>
                            {activeFormatBadge}
                        </StatusBadge>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleResetToSystemTemplate}
                            title="Reset data ke hitungan otomatis sistem"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <RotateCcw className="size-3.5" />
                            Hitung Ulang Sistem
                        </Button>
                        <a href={dynamicPdfUrl} target="_blank" rel="noopener noreferrer">
                            <Button size="sm" variant="outline" className="h-9 gap-1.5 text-xs">
                                <ExternalLink className="size-3.5" />
                                Cetak Langsung
                            </Button>
                        </a>
                        {can_create && (
                            <Button
                                size="sm"
                                onClick={() => handleSave()}
                                disabled={isSaving}
                                className="h-9 gap-1.5 text-xs"
                            >
                                {isSaving ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Save className="size-3.5" />
                                )}
                                Simpan Berita Acara
                            </Button>
                        )}
                    </div>
                }
            />

            {/* Sub-header Filter Toolbar */}
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-3 shadow-xs">
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.get(beritaAcara.index().url)}
                        className="h-8 gap-1.5 text-xs text-muted-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Kembali
                    </Button>
                    <div className="h-4 w-px bg-border" />

                    {/* Unit Select */}
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">Unit:</span>
                        <Select
                            value={String(filters.unit_id)}
                            onValueChange={handleUnitFilterChange}
                        >
                            <SelectTrigger className="w-[180px] h-8 text-xs font-semibold">
                                <SelectValue placeholder="Pilih Unit" />
                            </SelectTrigger>
                            <SelectContent>
                                {units.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)} className="text-xs">
                                        {u.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Month Select */}
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">Bulan:</span>
                        <Select
                            value={String(filters.month)}
                            onValueChange={handleMonthFilterChange}
                        >
                            <SelectTrigger className="w-[130px] h-8 text-xs font-semibold">
                                <SelectValue placeholder="Pilih Bulan" />
                            </SelectTrigger>
                            <SelectContent>
                                {MONTHS.map((m) => (
                                    <SelectItem key={m.value} value={String(m.value)} className="text-xs">
                                        {m.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Year Select */}
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">Tahun:</span>
                        <Select
                            value={String(filters.year)}
                            onValueChange={handleYearFilterChange}
                        >
                            <SelectTrigger className="w-[100px] h-8 text-xs font-semibold">
                                <SelectValue placeholder="Tahun" />
                            </SelectTrigger>
                            <SelectContent>
                                {[filters.year - 1, filters.year, filters.year + 1].map((y) => (
                                    <SelectItem key={y} value={String(y)} className="text-xs">
                                        {y}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Editor Tabs */}
                <div className="flex items-center gap-1 rounded-md bg-muted/60 p-1">
                    <Button
                        type="button"
                        size="sm"
                        variant={activeTab === 'form' ? 'secondary' : 'ghost'}
                        onClick={() => handleSwitchTab('form')}
                        className="h-7 px-3 text-xs gap-1.5"
                    >
                        <Check className="size-3" />
                        Formulir
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={activeTab === 'html' ? 'secondary' : 'ghost'}
                        onClick={() => handleSwitchTab('html')}
                        className="h-7 px-3 text-xs gap-1.5"
                    >
                        <FileCode2 className="size-3" />
                        Rich Text
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={activeTab === 'grid' ? 'secondary' : 'ghost'}
                        onClick={() => handleSwitchTab('grid')}
                        className="h-7 px-3 text-xs gap-1.5"
                    >
                        <FileSpreadsheet className="size-3" />
                        Spreadsheet Grid
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={activeTab === 'pdf' ? 'secondary' : 'ghost'}
                        onClick={() => handleSwitchTab('pdf')}
                        className="h-7 px-3 text-xs gap-1.5"
                    >
                        <Printer className="size-3" />
                        Pratinjau PDF
                    </Button>
                </div>
            </div>

            {/* TAB CONTENT 1: FORMULIR */}
            {activeTab === 'form' && (
                <div className="space-y-6">
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

                    {/* Shared Page Margins & Spacing */}
                    <PageMarginSettingsCard
                        showSettings={showSettings}
                        setShowSettings={setShowSettings}
                        marginTop={marginTop}
                        setMarginTop={setMarginTop}
                        marginBottom={marginBottom}
                        setMarginBottom={setMarginBottom}
                        marginLeft={marginLeft}
                        setMarginLeft={setMarginLeft}
                        marginRight={marginRight}
                        setMarginRight={setMarginRight}
                        lineSpacing={lineSpacing}
                        setLineSpacing={setLineSpacing}
                    />

                    {/* DOMAIN SPECIFIC SECTIONS */}
                    {isFeeder ? (
                        <FeederEditorSection
                            feederRows={feederRows}
                            feederTotals={feederTotals}
                            onAddFeederRow={handleAddFeederRow}
                            onUpdateFeederName={handleUpdateFeederName}
                            onUpdateFeederReading={handleUpdateFeederReading}
                            onUpdateFeederKeterangan={handleUpdateFeederKeterangan}
                            onRemoveFeederRow={handleRemoveFeederRow}
                            catatan={catatan}
                            setCatatan={setCatatan}
                            attachments={attachments}
                            isUploading={isUploading}
                            uploadError={uploadError}
                            onUploadImages={handleUploadImages}
                            onUpdateAttachmentCaption={handleUpdateAttachmentCaption}
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
            )}

            {/* TAB CONTENT 2: RICH TEXT (HTML) */}
            {activeTab === 'html' && (
                <div className="space-y-4">
                    <div className="flex items-center gap-2 p-3 bg-muted/40 rounded-lg text-xs text-muted-foreground border border-border">
                        <Info className="size-4 shrink-0 text-primary" />
                        <span>
                            Mode Rich Text: Anda mengedit langsung kode HTML layout berita acara. Perubahan pada teks atau format tabel di sini akan langsung disimpan ke dokumen resmi.
                        </span>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4">
                        <RichTextEditor
                            value={html}
                            onChange={setHtml}
                        />
                    </div>
                </div>
            )}

            {/* TAB CONTENT 3: SPREADSHEET GRID */}
            {activeTab === 'grid' && (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2 p-3 bg-muted/40 rounded-lg text-xs border border-border">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Info className="size-4 shrink-0 text-primary" />
                            <span>
                                Mode Spreadsheet Grid: Ubah nilai sel angka atau formula secara presisi layaknya Excel / Sheets.
                            </span>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                downloadGridAsXlsx(
                                    gridState,
                                    `BA_${type.value}_${unit.name}_${filters.month}_${filters.year}.xlsx`,
                                )
                            }
                            className="h-7 text-xs gap-1.5"
                        >
                            <Download className="size-3" />
                            Ekspor Excel (.xlsx)
                        </Button>
                    </div>

                    {letterhead && (
                        <div className="overflow-hidden rounded-md border border-border bg-card">
                            <style>{LETTERHEAD_STYLES}</style>
                            <div
                                dangerouslySetInnerHTML={{ __html: letterhead }}
                                className="p-3 bg-white"
                            />
                        </div>
                    )}

                    <div className="rounded-lg border border-border bg-card p-2 overflow-hidden">
                        <SpreadsheetEditor
                            grid={gridState}
                            onChange={setGridState}
                        />
                    </div>
                </div>
            )}

            {/* TAB CONTENT 4: PDF PREVIEW */}
            {activeTab === 'pdf' && (
                <div className="space-y-4">
                    <div className="rounded-lg border border-border bg-card p-2 min-h-[750px]">
                        <PdfPreviewFrame key={previewKey} src={dynamicPdfUrl} title="Pratinjau PDF" />
                    </div>
                </div>
            )}
        </div>
    );
}
