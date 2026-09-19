import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Download,
    ExternalLink,
    FileCode2,
    FileSpreadsheet,
    Info,
    Plus,
    Printer,
    RotateCcw,
    Save,
    SlidersHorizontal,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { RichTextEditor } from '@/components/rich-text-editor';
import { SpreadsheetEditor } from '@/components/spreadsheet-editor';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { downloadGridAsXlsx } from '@/lib/spreadsheet';
import type { DocumentGrid, GridCell } from '@/lib/spreadsheet';
import { dashboard } from '@/routes';
import beritaAcara from '@/routes/operasi/berita-acara';

type EditorTab = 'form' | 'html' | 'grid' | 'pdf';

type SignatoryOption = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    has_signature: boolean;
    signature_url: string | null;
};

type PemakaianItem = {
    mesin: string;
    liter: number;
};

type FisikItem = {
    tangki: string;
    liter: number;
};

type PelumasRow = {
    jenis: string;
    satuan: string;
    awal: number;
    penerimaan: number;
    stock: number;
    pemakaian: number;
    pengiriman: number;
    administrasi: number;
    fisik_liter: number;
    selisih: number;
};

type FormDataPayload = {
    is_fuel: boolean;
    fuel_label?: string;
    document: {
        number: string;
        title: string;
        revision: string;
        revision_date?: string;
    };
    unit: {
        name: string;
        service_unit?: string;
    };
    period: {
        month: number;
        year: number;
        label: string;
    };
    narrative: {
        hari: string;
        tanggal_terbilang: string;
        bulan: string;
        tahun_terbilang: string;
        tanggal_penuh: string;
    };
    print_place_date: string;
    signers: {
        manajer?: string;
        manajer_title?: string;
        manajer_signature?: string;
        tl_operasi?: string;
        tl_title?: string;
        tl_signature?: string;
    };
    catatan?: string;
    // Fuel specific
    persediaan_awal?: number;
    penerimaan_range?: string;
    penerimaan_total?: number;
    jumlah_stock?: number;
    pemakaian?: PemakaianItem[];
    pemakaian_total?: number;
    pengiriman?: number;
    administrasi?: number;
    fisik?: FisikItem[];
    fisik_total?: number;
    selisih?: number;
    // Lubricant specific
    rows?: PelumasRow[];
};

type Props = {
    type: { value: string; label: string };
    filters: { unit_id: number; month: number; year: number };
    unit: {
        id: number;
        name: string;
        service_unit_id: number | null;
        service_unit_name?: string | null;
    };
    units: Array<{ id: number; name: string }>;
    document_number: string;
    data: FormDataPayload;
    form_data: FormDataPayload;
    content: string;
    content_styles: string;
    letterhead: string;
    grid: DocumentGrid;
    format: 'form' | 'html' | 'grid';
    has_saved: boolean;
    manager_options: SignatoryOption[];
    tl_options: SignatoryOption[];
    pdf_url: string;
    can_create: boolean;
};

const MONTHS = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

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

const formatNum = (v: number | string | undefined | null) => {
    const num = Number(v) || 0;
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};

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
}: Props) {
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
    const [marginTop, setMarginTop] = useState<number>(15);
    const [marginBottom, setMarginBottom] = useState<number>(15);
    const [marginLeft, setMarginLeft] = useState<number>(15);
    const [marginRight, setMarginRight] = useState<number>(15);
    const [lineSpacing, setLineSpacing] = useState<string>('1.15');

    // Catatan Selisih
    const [catatan, setCatatan] = useState<string>(
        form_data.catatan || '',
    );

    // BBM Form Fields
    const [persediaanAwal, setPersediaanAwal] = useState<number>(
        Number(form_data.persediaan_awal ?? data.persediaan_awal ?? 0),
    );
    const [penerimaanRange, setPenerimaanRange] = useState<string>(
        form_data.penerimaan_range || data.penerimaan_range || '',
    );
    const [penerimaanTotal, setPenerimaanTotal] = useState<number>(
        Number(form_data.penerimaan_total ?? data.penerimaan_total ?? 0),
    );
    const [pemakaianList, setPemakaianList] = useState<PemakaianItem[]>(
        form_data.pemakaian || data.pemakaian || [],
    );
    const [pengirimanTotal, setPengirimanTotal] = useState<number>(
        Number(form_data.pengiriman ?? data.pengiriman ?? 0),
    );
    const [fisikList, setFisikList] = useState<FisikItem[]>(
        form_data.fisik || data.fisik || [],
    );

    // Pelumas Form Fields
    const [pelumasRows, setPelumasRows] = useState<PelumasRow[]>(
        (form_data.rows || data.rows || []).map((row) => ({
            jenis: row.jenis || '',
            satuan: row.satuan || 'Liter',
            awal: Number(row.awal) || 0,
            penerimaan: Number(row.penerimaan) || 0,
            stock: Number(row.stock) || ((Number(row.awal) || 0) + (Number(row.penerimaan) || 0)),
            pemakaian: Number(row.pemakaian) || 0,
            pengiriman: Number(row.pengiriman) || 0,
            administrasi:
                Number(row.administrasi) ||
                ((Number(row.awal) || 0) + (Number(row.penerimaan) || 0) - (Number(row.pemakaian) || 0) - (Number(row.pengiriman) || 0)),
            fisik_liter: Number(row.fisik_liter) || 0,
            selisih:
                Number(row.selisih) ||
                ((Number(row.fisik_liter) || 0) -
                    ((Number(row.awal) || 0) + (Number(row.penerimaan) || 0) - (Number(row.pemakaian) || 0) - (Number(row.pengiriman) || 0))),
        })),
    );

    // Rich Text & Grid State
    const [html, setHtml] = useState(content);
    const [gridState, setGridState] = useState<DocumentGrid>(grid);

    // Computed BBM Real-Time Math
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
            (acc, r) => ({
                awal: acc.awal + (Number(r.awal) || 0),
                penerimaan: acc.penerimaan + (Number(r.penerimaan) || 0),
                stock: acc.stock + (Number(r.stock) || 0),
                pemakaian: acc.pemakaian + (Number(r.pemakaian) || 0),
                pengiriman: acc.pengiriman + (Number(r.pengiriman) || 0),
                administrasi: acc.administrasi + (Number(r.administrasi) || 0),
                fisik_liter: acc.fisik_liter + (Number(r.fisik_liter) || 0),
                selisih: acc.selisih + (Number(r.selisih) || 0),
            }),
            {
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

    // Signatory Handlers
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

    // BBM Pemakaian List Handlers
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

    // BBM Fisik List Handlers
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

    // Pelumas Row Handlers
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

    const handleUpdatePelumasRow = (index: number, field: keyof PelumasRow, val: string | number) => {
        setPelumasRows((prev) => {
            const next = [...prev];
            const current = { ...next[index], [field]: val };

            const awal = Number(current.awal) || 0;
            const penerimaan = Number(current.penerimaan) || 0;
            const pemakaian = Number(current.pemakaian) || 0;
            const pengiriman = Number(current.pengiriman) || 0;
            const fisik_liter = Number(current.fisik_liter) || 0;

            const stock = awal + penerimaan;
            const administrasi = stock - pemakaian - pengiriman;
            const selisih = fisik_liter - administrasi;

            next[index] = {
                ...current,
                stock: Number(stock.toFixed(2)),
                administrasi: Number(administrasi.toFixed(2)),
                selisih: Number(selisih.toFixed(2)),
            };
            return next;
        });
    };

    const handleRemovePelumasRow = (index: number) => {
        setPelumasRows((prev) => prev.filter((_, i) => i !== index));
    };

    // Navigation filters change
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
            ...(form_data.is_fuel
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
            content_html: formatToSave === 'html' ? html : undefined,
            content_grid: formatToSave === 'grid' ? gridState : undefined,
        };

        router.post(
            beritaAcara.store().url,
            postPayload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSavedFormat(formatToSave);
                    setPreviewKey((k) => k + 1);
                    if (onSuccessCallback) {
                        onSuccessCallback();
                    }
                },
                onFinish: () => setIsSaving(false),
            },
        );
    };

    // PDF Actions
    const previewPdfUrl = useMemo(() => {
        const separator = pdf_url.includes('?') ? '&' : '?';
        const params = new URLSearchParams();
        params.set('t', String(previewKey));
        params.set('page_margin_top', String(marginTop));
        params.set('page_margin_bottom', String(marginBottom));
        params.set('page_margin_left', String(marginLeft));
        params.set('page_margin_right', String(marginRight));
        params.set('line_spacing', lineSpacing);
        return `${pdf_url}${separator}${params.toString()}`;
    }, [pdf_url, previewKey, marginTop, marginBottom, marginLeft, marginRight, lineSpacing]);

    const handleDownloadPdf = () => {
        const separator = pdf_url.includes('?') ? '&' : '?';
        const params = new URLSearchParams();
        params.set('download', '1');
        params.set('page_margin_top', String(marginTop));
        params.set('page_margin_bottom', String(marginBottom));
        params.set('page_margin_left', String(marginLeft));
        params.set('page_margin_right', String(marginRight));
        params.set('line_spacing', lineSpacing);
        window.open(`${pdf_url}${separator}${params.toString()}`, '_blank');
    };

    const handleOpenPreview = () => {
        if (can_create) {
            handleSave(() => {
                setActiveTab('pdf');
            });
        } else {
            setActiveTab('pdf');
            setPreviewKey((k) => k + 1);
        }
    };

    const handleSwitchTab = (tab: EditorTab) => {
        if (tab === 'form' || tab === 'html' || tab === 'grid') {
            setEditMode(tab);
        }
        setActiveTab(tab);
    };

    const downloadXlsx = () => {
        const cols = gridState.cols;
        const header: GridCell[][] = [
            [{ t: 'UNIT INDUK PEMBANGKITAN DAN PENYALURAN SULAWESI', b: true, a: 'c' }],
            [{ t: 'UNIT PELAKSANA PENGENDALIAN PEMBANGKITAN KENDARI', b: true, a: 'c' }],
            [{ t: 'SISTEM MANAJEMEN TERINTEGRASI (9001-14001-45001-SMK3-SMP)' }],
            [{ t: `No. Surat: ${docNumber}` }],
        ];
        const offset = header.length;
        const shifted = (gridState.merges ?? []).map(
            ([r1, c1, r2, c2]): [number, number, number, number] => [
                r1 + offset,
                c1,
                r2 + offset,
                c2,
            ],
        );
        const headerMerges = header.map(
            (_, i): [number, number, number, number] => [i, 0, i, cols - 1],
        );
        const exportGrid: DocumentGrid = {
            ...gridState,
            rows: [...header, ...gridState.rows],
            merges: [...headerMerges, ...shifted],
        };
        downloadGridAsXlsx(exportGrid, `BA-${type.value}-${filters.unit_id}-${filters.month}-${filters.year}.xlsx`);
    };

    return (
        <>
            <Head title={`Buat & Edit ${type.label} - ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Standard Page Header */}
                <PageHeader
                    title={`Buat & Edit ${type.label}`}
                    description="Input data pemeriksaan berita acara, atur penandatangan & margin layout, dan cetak PDF resmi."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                onClick={() => router.get(beritaAcara.index().url)}
                                className="gap-2"
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                            <Button
                                variant="outline"
                                onClick={handleDownloadPdf}
                                className="gap-2"
                            >
                                <Download className="size-4" />
                                Unduh PDF
                            </Button>
                            {editMode === 'grid' && (
                                <Button variant="outline" onClick={downloadXlsx} className="gap-2">
                                    <FileSpreadsheet className="size-4" />
                                    Unduh Excel
                                </Button>
                            )}
                            {can_create && (
                                <Button
                                    onClick={() => handleSave()}
                                    disabled={isSaving}
                                    className="gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
                                >
                                    <Save className="size-4" />
                                    {isSaving ? 'Menyimpan…' : 'Simpan Berita Acara'}
                                </Button>
                            )}
                        </div>
                    }
                />

                {/* Status Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-3 text-[13px]">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground">Unit:</span>
                        <strong className="text-foreground">{unit.name}</strong>
                        <span className="text-muted-foreground mx-1">•</span>
                        <span className="text-muted-foreground">Service Unit (UL):</span>
                        <span className="font-medium text-foreground">
                            {unit.service_unit_name || '—'}
                        </span>
                        <span className="text-muted-foreground mx-1">•</span>
                        <span className="text-muted-foreground">Periode:</span>
                        <span className="font-medium text-foreground">
                            {MONTHS.find((m) => m.value === filters.month)?.label} {filters.year}
                        </span>
                        <span className="text-muted-foreground mx-1">•</span>
                        <span className="text-muted-foreground">Status Dokumen:</span>
                        {savedFormat !== null ? (
                            <StatusBadge tone="success">
                                Tersimpan ({savedFormat === 'form' ? 'Formulir' : savedFormat === 'html' ? 'Teks HTML' : 'Excel'})
                            </StatusBadge>
                        ) : (
                            <StatusBadge tone="neutral">Belum Disimpan</StatusBadge>
                        )}
                    </div>
                    <div className="text-[12px] text-muted-foreground">
                        No. Dokumen: <span className="font-mono font-medium">{docNumber}</span> (Rev. {revision})
                    </div>
                </div>

                {/* 2-Column Layout */}
                <div className="flex flex-col lg:flex-row items-start gap-6 w-full">
                    {/* LEFT COLUMN: Metadata, Signatories, Page Settings */}
                    <div className="w-full lg:w-[380px] xl:w-[420px] shrink-0 space-y-5">
                        {/* Card 1: Metadata Formulir & Dokumen */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Metadata Berita Acara
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Identitas unit, periode, nomor surat, dan tanggal pemeriksaan.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                {/* Unit Selector */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Unit Pembangkit</Label>
                                    {units.length > 1 ? (
                                        <Select
                                            value={String(unit.id)}
                                            onValueChange={handleUnitFilterChange}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue placeholder="Pilih unit" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {units.map((u) => (
                                                    <SelectItem key={u.id} value={String(u.id)}>
                                                        {u.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Input value={unit.name} disabled className="h-8 text-xs bg-muted" />
                                    )}
                                </div>

                                {/* Periode Bulan & Tahun */}
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Bulan</Label>
                                        <Select
                                            value={String(filters.month)}
                                            onValueChange={handleMonthFilterChange}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {MONTHS.map((m) => (
                                                    <SelectItem key={m.value} value={String(m.value)}>
                                                        {m.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Tahun</Label>
                                        <Select
                                            value={String(filters.year)}
                                            onValueChange={handleYearFilterChange}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Array.from({ length: 6 }, (_, i) => filters.year - 3 + i).map((yr) => (
                                                    <SelectItem key={yr} value={String(yr)}>
                                                        {yr}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                {/* No. Surat BA & No. Dokumen */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <div className="space-y-1">
                                        <Label className="text-[11px] font-medium">No. Surat Berita Acara</Label>
                                        <Input
                                            value={docNumber}
                                            onChange={(e) => setDocNumber(e.target.value)}
                                            placeholder="contoh: 001/BA-BBM/UPKDR/2026"
                                            className="h-7 text-xs font-mono font-medium"
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Revisi</Label>
                                            <Input
                                                value={revision}
                                                onChange={(e) => setRevision(e.target.value)}
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Tgl Revisi</Label>
                                            <Input
                                                value={revisionDate}
                                                onChange={(e) => setRevisionDate(e.target.value)}
                                                placeholder="YYYY-MM-DD"
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Narasi Pemeriksaan */}
                                <div className="space-y-2 rounded-md border border-border/70 p-2.5 bg-muted/20">
                                    <Label className="text-[11px] font-semibold text-foreground">
                                        Waktu Pelaksanaan / Pemeriksaan
                                    </Label>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[10px] text-muted-foreground">Hari</Label>
                                            <Input
                                                value={hari}
                                                onChange={(e) => setHari(e.target.value)}
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] text-muted-foreground">Tanggal Penuh</Label>
                                            <Input
                                                value={tanggalPenuh}
                                                onChange={(e) => setTanggalPenuh(e.target.value)}
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[10px] text-muted-foreground">Tanggal Terbilang</Label>
                                        <Input
                                            value={tanggalTerbilang}
                                            onChange={(e) => setTanggalTerbilang(e.target.value)}
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[10px] text-muted-foreground">Bulan Terbilang</Label>
                                            <Input
                                                value={bulanTerbilang}
                                                onChange={(e) => setBulanTerbilang(e.target.value)}
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] text-muted-foreground">Tahun Terbilang</Label>
                                            <Input
                                                value={tahunTerbilang}
                                                onChange={(e) => setTahunTerbilang(e.target.value)}
                                                className="h-7 text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[10px] text-muted-foreground">Tempat &amp; Tanggal Tanda Tangan</Label>
                                        <Input
                                            value={printPlaceDate}
                                            onChange={(e) => setPrintPlaceDate(e.target.value)}
                                            placeholder="Kendari, 31 Januari 2026"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Card 2: Penandatangan Dokumen */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Penandatangan Dokumen
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Hierarki tanda tangan: Menyetujui (Manajer) dan Membuat (TL Operasi).
                                </p>
                            </div>

                            <div className="space-y-4 text-xs">
                                {/* 1. Manajer (Menyetujui - Kiri) */}
                                <div className="space-y-2.5 rounded-md border border-border/70 p-3 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            1. Menyetujui (Manajer)
                                        </Label>
                                        {selectedManager?.has_signature ? (
                                            <Badge variant="outline" className="border-emerald-500/40 text-emerald-700 bg-emerald-50 text-[10px] py-0">
                                                TTD Siap
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-muted-foreground text-[10px] py-0">
                                                Tanpa TTD
                                            </Badge>
                                        )}
                                    </div>
                                    <Select
                                        value={managerId}
                                        onValueChange={handleManagerChange}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Manager UL..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {manager_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)}>
                                                    {e.name} {e.position ? `(${e.position})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            value={managerName}
                                            onChange={(e) => setManagerName(e.target.value)}
                                            placeholder="Nama Manajer"
                                            className="h-7 text-xs"
                                        />
                                        <Input
                                            value={managerTitle}
                                            onChange={(e) => setManagerTitle(e.target.value)}
                                            placeholder="Jabatan"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                    {/* Preview space tanda tangan */}
                                    <div className="flex h-14 w-full items-center justify-center rounded border border-dashed border-border/80 bg-background/60 p-1 text-[11px] text-muted-foreground">
                                        {selectedManager?.signature_url ? (
                                            <img
                                                src={selectedManager.signature_url}
                                                alt="TTD Manajer"
                                                className="max-h-12 max-w-[120px] object-contain"
                                            />
                                        ) : (
                                            <span className="italic text-[10px] text-muted-foreground">
                                                (Space Area Tanda Tangan Manajer)
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* 2. TL Operasi (Membuat - Kanan) */}
                                <div className="space-y-2.5 rounded-md border border-border/70 p-3 bg-muted/20">
                                    <div className="flex items-center justify-between">
                                        <Label className="font-semibold text-foreground">
                                            2. Membuat (TL Operasi)
                                        </Label>
                                        {selectedTl?.has_signature ? (
                                            <Badge variant="outline" className="border-emerald-500/40 text-emerald-700 bg-emerald-50 text-[10px] py-0">
                                                TTD Siap
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-muted-foreground text-[10px] py-0">
                                                Tanpa TTD
                                            </Badge>
                                        )}
                                    </div>
                                    <Select
                                        value={tlId}
                                        onValueChange={handleTlChange}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih TL Operasi..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tl_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)}>
                                                    {e.name} {e.position ? `(${e.position})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            value={tlName}
                                            onChange={(e) => setTlName(e.target.value)}
                                            placeholder="Nama TL Operasi"
                                            className="h-7 text-xs"
                                        />
                                        <Input
                                            value={tlTitle}
                                            onChange={(e) => setTlTitle(e.target.value)}
                                            placeholder="Jabatan"
                                            className="h-7 text-xs"
                                        />
                                    </div>
                                    {/* Preview space tanda tangan */}
                                    <div className="flex h-14 w-full items-center justify-center rounded border border-dashed border-border/80 bg-background/60 p-1 text-[11px] text-muted-foreground">
                                        {selectedTl?.signature_url ? (
                                            <img
                                                src={selectedTl.signature_url}
                                                alt="TTD TL Operasi"
                                                className="max-h-12 max-w-[120px] object-contain"
                                            />
                                        ) : (
                                            <span className="italic text-[10px] text-muted-foreground">
                                                (Space Area Tanda Tangan TL Operasi)
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-md border border-border bg-card p-2 text-[11px] text-muted-foreground">
                                    <Info className="size-3.5 inline mr-1 text-primary" />
                                    Tanda tangan digital pegawai otomatis terpasang pada dokumen PDF jika sudah diunggah pada master pegawai.
                                </div>
                            </div>
                        </div>

                        {/* Card 3: Page Settings */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Page Settings
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Pengaturan layout &amp; margin PDF cetak.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                <div className="grid grid-cols-4 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Atas (mm)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={50}
                                            value={marginTop}
                                            onChange={(e) => setMarginTop(Number(e.target.value))}
                                            className="h-7 text-xs text-center"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Bawah (mm)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={50}
                                            value={marginBottom}
                                            onChange={(e) => setMarginBottom(Number(e.target.value))}
                                            className="h-7 text-xs text-center"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Kiri (mm)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={50}
                                            value={marginLeft}
                                            onChange={(e) => setMarginLeft(Number(e.target.value))}
                                            className="h-7 text-xs text-center"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-[11px]">Kanan (mm)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={50}
                                            value={marginRight}
                                            onChange={(e) => setMarginRight(Number(e.target.value))}
                                            className="h-7 text-xs text-center"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <Label className="text-[11px]">Spasi Baris</Label>
                                    <Select value={lineSpacing} onValueChange={setLineSpacing}>
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1.0">1,0 (Rapat)</SelectItem>
                                            <SelectItem value="1.15">1,15 (Standar ISO)</SelectItem>
                                            <SelectItem value="1.5">1,5 (Lebar)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* RIGHT COLUMN: Main Content Area */}
                    <div className="flex-1 w-full min-w-0 flex flex-col gap-4">
                        <div className="flex flex-1 flex-col justify-between rounded-lg border border-border bg-card shadow-sm">
                            {/* Card Header with View Tabs */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                                <div>
                                    <h3 className="text-base font-semibold text-foreground">
                                        Isi Berita Acara &amp; Dokumen
                                    </h3>
                                    <p className="text-[12px] text-muted-foreground">
                                        Kop surat, tanggal, kalkulasi persediaan, dan tanda tangan otomatis disesuaikan pada PDF.
                                    </p>
                                </div>

                                {/* Tabs switch */}
                                <div className="flex flex-wrap overflow-hidden rounded-md border border-border text-xs">
                                    <button
                                        type="button"
                                        onClick={() => handleSwitchTab('form')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 transition-colors whitespace-nowrap ${
                                            activeTab === 'form'
                                                ? 'bg-primary text-primary-foreground font-medium'
                                                : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                                        }`}
                                    >
                                        <SlidersHorizontal className="size-3.5" />
                                        Editor Formulir
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => handleSwitchTab('html')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 transition-colors whitespace-nowrap ${
                                            activeTab === 'html'
                                                ? 'bg-primary text-primary-foreground font-medium'
                                                : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                                        }`}
                                    >
                                        <FileCode2 className="size-3.5" />
                                        Editor Teks (HTML)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => handleSwitchTab('grid')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 transition-colors whitespace-nowrap ${
                                            activeTab === 'grid'
                                                ? 'bg-primary text-primary-foreground font-medium'
                                                : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                                        }`}
                                    >
                                        <FileSpreadsheet className="size-3.5" />
                                        Spreadsheet (Excel)
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleOpenPreview}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 transition-colors whitespace-nowrap ${
                                            activeTab === 'pdf'
                                                ? 'bg-primary text-primary-foreground font-medium'
                                                : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                                        }`}
                                    >
                                        <Printer className="size-3.5" />
                                        Pratinjau PDF
                                    </button>
                                </div>
                            </div>

                            {/* TAB 1: FORMULIR INPUT CODINGAN BIASA */}
                            {activeTab === 'form' && (
                                <div className="p-4 space-y-6">
                                    {form_data.is_fuel ? (
                                        /* ================= BBM SECTION ================= */
                                        <div className="space-y-6">
                                            {/* Section 1: Persediaan Awal & Penerimaan */}
                                            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                                                <div className="flex items-center justify-between border-b border-border pb-2">
                                                    <div>
                                                        <h4 className="text-sm font-semibold text-foreground">
                                                            1. Persediaan Awal &amp; 2. Penerimaan BBM ({form_data.fuel_label})
                                                        </h4>
                                                        <p className="text-[12px] text-muted-foreground">
                                                            Pencatatan saldo awal dan penerimaan BBM selama periode ini.
                                                        </p>
                                                    </div>
                                                    <Badge variant="outline" className="font-mono text-xs">
                                                        A = Awal + Penerimaan
                                                    </Badge>
                                                </div>

                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                                    <div className="space-y-1.5">
                                                        <Label className="font-semibold text-foreground">
                                                            1. Persediaan Awal (Liter)
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            value={persediaanAwal}
                                                            onChange={(e) => setPersediaanAwal(Number(e.target.value) || 0)}
                                                            className="h-8 font-mono text-right"
                                                        />
                                                    </div>

                                                    <div className="space-y-1.5">
                                                        <Label className="font-semibold text-foreground">
                                                            Rentang Tanggal Penerimaan
                                                        </Label>
                                                        <Input
                                                            value={penerimaanRange}
                                                            onChange={(e) => setPenerimaanRange(e.target.value)}
                                                            placeholder="contoh: 01 Januari 2026 s/d 31 Januari 2026"
                                                            className="h-8"
                                                        />
                                                    </div>

                                                    <div className="space-y-1.5 md:col-span-2">
                                                        <Label className="font-semibold text-foreground">
                                                            2. Total Penerimaan BBM (Liter)
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            value={penerimaanTotal}
                                                            onChange={(e) => setPenerimaanTotal(Number(e.target.value) || 0)}
                                                            className="h-8 font-mono text-right"
                                                        />
                                                    </div>
                                                </div>

                                                {/* Subtotal Box A */}
                                                <div className="flex items-center justify-between rounded-md border border-primary/20 bg-primary/5 p-3 text-xs">
                                                    <span className="font-semibold text-foreground">
                                                        A. Jumlah Stock BBM {form_data.fuel_label} (1 + 2)
                                                    </span>
                                                    <span className="text-sm font-bold font-mono text-primary">
                                                        {formatNum(jumlahStock)} Liter
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Section 2: Pemakaian Mesin */}
                                            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                                                    <div>
                                                        <h4 className="text-sm font-semibold text-foreground">
                                                            3. Pemakaian Mesin PLN
                                                        </h4>
                                                        <p className="text-[12px] text-muted-foreground">
                                                            Rincian liter pemakaian bahan bakar per mesin unit pembangkit.
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={handleAddPemakaian}
                                                        className="h-7 text-xs gap-1"
                                                    >
                                                        <Plus className="size-3.5" />
                                                        Tambah Mesin
                                                    </Button>
                                                </div>

                                                <div className="space-y-2">
                                                    {pemakaianList.length === 0 ? (
                                                        <div className="p-4 text-center text-xs text-muted-foreground border border-dashed rounded-md">
                                                            Belum ada data pemakaian mesin. Klik &ldquo;Tambah Mesin&rdquo; untuk menambahkan.
                                                        </div>
                                                    ) : (
                                                        pemakaianList.map((item, idx) => (
                                                            <div
                                                                key={idx}
                                                                className="flex items-center gap-3 rounded-md border border-border p-2 bg-muted/10 text-xs"
                                                            >
                                                                <div className="w-1/2">
                                                                    <Input
                                                                        value={item.mesin}
                                                                        onChange={(e) =>
                                                                            handleUpdatePemakaian(idx, 'mesin', e.target.value)
                                                                        }
                                                                        placeholder="Nama Mesin / Generator"
                                                                        className="h-7 text-xs"
                                                                    />
                                                                </div>
                                                                <div className="w-1/2 flex items-center gap-2">
                                                                    <Input
                                                                        type="number"
                                                                        step="0.01"
                                                                        value={item.liter}
                                                                        onChange={(e) =>
                                                                            handleUpdatePemakaian(idx, 'liter', e.target.value)
                                                                        }
                                                                        placeholder="Liter"
                                                                        className="h-7 text-xs font-mono text-right"
                                                                    />
                                                                    <span className="text-[11px] text-muted-foreground">Liter</span>
                                                                    <Button
                                                                        type="button"
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        onClick={() => handleRemovePemakaian(idx)}
                                                                        className="size-7 text-destructive hover:bg-destructive/10"
                                                                    >
                                                                        <Trash2 className="size-3.5" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        ))
                                                    )}
                                                </div>

                                                {/* Subtotal Box B */}
                                                <div className="flex items-center justify-between rounded-md border border-primary/20 bg-primary/5 p-3 text-xs">
                                                    <span className="font-semibold text-foreground">
                                                        B. Jumlah Pemakaian (3)
                                                    </span>
                                                    <span className="text-sm font-bold font-mono text-primary">
                                                        {formatNum(pemakaianTotal)} Liter
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Section 3: Pengiriman & Administrasi */}
                                            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                                                <div className="border-b border-border pb-2">
                                                    <h4 className="text-sm font-semibold text-foreground">
                                                        Pengiriman &amp; Persediaan Administrasi
                                                    </h4>
                                                    <p className="text-[12px] text-muted-foreground">
                                                        Perhitungan sisa persediaan BBM menurut catatan administrasi pembukuan.
                                                    </p>
                                                </div>

                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                                    <div className="space-y-1.5">
                                                        <Label className="font-semibold text-foreground">
                                                            C. Jumlah Pengiriman (Liter)
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            value={pengirimanTotal}
                                                            onChange={(e) => setPengirimanTotal(Number(e.target.value) || 0)}
                                                            className="h-8 font-mono text-right"
                                                        />
                                                    </div>

                                                    <div className="flex flex-col justify-end">
                                                        <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 p-2.5 text-xs">
                                                            <span className="font-semibold text-foreground">
                                                                D. Persediaan Administrasi (A - B - C)
                                                            </span>
                                                            <span className="text-sm font-bold font-mono text-foreground">
                                                                {formatNum(administrasiTotal)} Liter
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Section 4: Pemeriksaan Fisik Tangki */}
                                            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
                                                    <div>
                                                        <h4 className="text-sm font-semibold text-foreground">
                                                            Jumlah Persediaan menurut Fisik
                                                        </h4>
                                                        <p className="text-[12px] text-muted-foreground">
                                                            Hasil pengukuran fisik (sounding) tangki BBM di lokasi pembangkit.
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={handleAddFisik}
                                                        className="h-7 text-xs gap-1"
                                                    >
                                                        <Plus className="size-3.5" />
                                                        Tambah Tangki
                                                    </Button>
                                                </div>

                                                <div className="space-y-2">
                                                    {fisikList.length === 0 ? (
                                                        <div className="p-4 text-center text-xs text-muted-foreground border border-dashed rounded-md">
                                                            Belum ada data tangki fisik. Klik &ldquo;Tambah Tangki&rdquo; untuk menambahkan.
                                                        </div>
                                                    ) : (
                                                        fisikList.map((item, idx) => (
                                                            <div
                                                                key={idx}
                                                                className="flex items-center gap-3 rounded-md border border-border p-2 bg-muted/10 text-xs"
                                                            >
                                                                <div className="w-1/2">
                                                                    <Input
                                                                        value={item.tangki}
                                                                        onChange={(e) =>
                                                                            handleUpdateFisik(idx, 'tangki', e.target.value)
                                                                        }
                                                                        placeholder="Nama Tangki"
                                                                        className="h-7 text-xs"
                                                                    />
                                                                </div>
                                                                <div className="w-1/2 flex items-center gap-2">
                                                                    <Input
                                                                        type="number"
                                                                        step="0.01"
                                                                        value={item.liter}
                                                                        onChange={(e) =>
                                                                            handleUpdateFisik(idx, 'liter', e.target.value)
                                                                        }
                                                                        placeholder="Liter"
                                                                        className="h-7 text-xs font-mono text-right"
                                                                    />
                                                                    <span className="text-[11px] text-muted-foreground">Liter</span>
                                                                    <Button
                                                                        type="button"
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        onClick={() => handleRemoveFisik(idx)}
                                                                        className="size-7 text-destructive hover:bg-destructive/10"
                                                                    >
                                                                        <Trash2 className="size-3.5" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        ))
                                                    )}
                                                </div>

                                                {/* Subtotal Box E */}
                                                <div className="flex items-center justify-between rounded-md border border-primary/20 bg-primary/5 p-3 text-xs">
                                                    <span className="font-semibold text-foreground">
                                                        E. Jumlah Persediaan menurut Fisik
                                                    </span>
                                                    <span className="text-sm font-bold font-mono text-primary">
                                                        {formatNum(fisikTotal)} Liter
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Section 5: Selisih & Catatan */}
                                            <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                                                <div className="border-b border-border pb-2">
                                                    <h4 className="text-sm font-semibold text-foreground">
                                                        F. Selisih Administrasi vs Fisik &amp; Catatan
                                                    </h4>
                                                    <p className="text-[12px] text-muted-foreground">
                                                        Evaluasi perbedaan volume fisik aktual terhadap saldo administrasi.
                                                    </p>
                                                </div>

                                                {/* Selisih Result Banner */}
                                                <div
                                                    className={`flex items-center justify-between rounded-md border p-3.5 text-xs ${
                                                        selisihTotal === 0
                                                            ? 'border-emerald-500/30 bg-emerald-50/70 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300'
                                                            : selisihTotal > 0
                                                              ? 'border-blue-500/30 bg-blue-50/70 dark:bg-blue-950/30 text-blue-800 dark:text-blue-300'
                                                              : 'border-amber-500/30 bg-amber-50/70 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300'
                                                    }`}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-semibold text-sm">
                                                            F. Selisih Administrasi vs Fisik (E - D):
                                                        </span>
                                                        <Badge
                                                            variant="outline"
                                                            className={
                                                                selisihTotal === 0
                                                                    ? 'border-emerald-600 text-emerald-700 bg-emerald-100/50'
                                                                    : selisihTotal > 0
                                                                      ? 'border-blue-600 text-blue-700 bg-blue-100/50'
                                                                      : 'border-amber-600 text-amber-700 bg-amber-100/50'
                                                            }
                                                        >
                                                            {selisihTotal === 0 ? 'Nihil / Sesuai' : selisihTotal > 0 ? 'Lebih Fisik (+)' : 'Kurang Fisik (-)'}
                                                        </Badge>
                                                    </div>
                                                    <span className="text-base font-extrabold font-mono">
                                                        {selisihTotal > 0 ? `+${formatNum(selisihTotal)}` : formatNum(selisihTotal)} Liter
                                                    </span>
                                                </div>

                                                {/* Catatan Selisih */}
                                                <div className="space-y-1.5 text-xs">
                                                    <Label className="font-semibold text-foreground">
                                                        Catatan: * Selisih disebabkan karena:
                                                    </Label>
                                                    <textarea
                                                        rows={3}
                                                        value={catatan}
                                                        onChange={(e) => setCatatan(e.target.value)}
                                                        placeholder="Tuliskan alasan teknis selisih BBM bila ada (misal: penguapan, kalibrasi sounding tangki, dsb.)..."
                                                        className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        /* ================= PELUMAS SECTION ================= */
                                        <div className="space-y-4">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <h4 className="text-sm font-semibold text-foreground">
                                                        Tabel Pemeriksaan Fisik Minyak Pelumas
                                                    </h4>
                                                    <p className="text-[12px] text-muted-foreground">
                                                        Pemeriksaan saldo awal, penerimaan, pemakaian, pengiriman, saldo administrasi, dan stok fisik pelumas.
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={handleAddPelumasRow}
                                                    className="h-7 text-xs gap-1"
                                                >
                                                    <Plus className="size-3.5" />
                                                    Tambah Jenis Pelumas
                                                </Button>
                                            </div>

                                            <div className="overflow-x-auto rounded-md border border-border">
                                                <table className="w-full text-xs text-left border-collapse">
                                                    <thead className="bg-muted/60 text-foreground font-semibold border-b border-border text-[11px]">
                                                        <tr>
                                                            <th className="p-2 w-8 text-center">No</th>
                                                            <th className="p-2 min-w-[140px]">Jenis Pelumas</th>
                                                            <th className="p-2 w-20">Satuan</th>
                                                            <th className="p-2 min-w-[100px] text-right">Persediaan Awal</th>
                                                            <th className="p-2 min-w-[100px] text-right">Penerimaan</th>
                                                            <th className="p-2 min-w-[100px] text-right bg-muted/40 font-bold">Stock</th>
                                                            <th className="p-2 min-w-[100px] text-right">Pemakaian</th>
                                                            <th className="p-2 min-w-[100px] text-right">Pengiriman</th>
                                                            <th className="p-2 min-w-[100px] text-right bg-muted/40 font-bold">Saldo Adm</th>
                                                            <th className="p-2 min-w-[100px] text-right">Stock Fisik</th>
                                                            <th className="p-2 min-w-[100px] text-right font-bold">Selisih</th>
                                                            <th className="p-2 w-10 text-center">Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-border">
                                                        {pelumasRows.length === 0 ? (
                                                            <tr>
                                                                <td colSpan={12} className="p-4 text-center text-muted-foreground">
                                                                    Belum ada data master pelumas. Klik &ldquo;Tambah Jenis Pelumas&rdquo; untuk menambahkan.
                                                                </td>
                                                            </tr>
                                                        ) : (
                                                            pelumasRows.map((row, idx) => (
                                                                <tr key={idx} className="hover:bg-muted/10">
                                                                    <td className="p-2 text-center text-muted-foreground">{idx + 1}</td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={row.jenis}
                                                                            onChange={(e) =>
                                                                                handleUpdatePelumasRow(idx, 'jenis', e.target.value)
                                                                            }
                                                                            className="h-7 text-xs"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={row.satuan}
                                                                            onChange={(e) =>
                                                                                handleUpdatePelumasRow(idx, 'satuan', e.target.value)
                                                                            }
                                                                            className="h-7 text-xs"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="number"
                                                                            step="0.01"
                                                                            value={row.awal}
                                                                            onChange={(e) =>
                                                                                handleUpdatePelumasRow(idx, 'awal', Number(e.target.value) || 0)
                                                                            }
                                                                            className="h-7 text-xs font-mono text-right"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="number"
                                                                            step="0.01"
                                                                            value={row.penerimaan}
                                                                            onChange={(e) =>
                                                                                handleUpdatePelumasRow(idx, 'penerimaan', Number(e.target.value) || 0)
                                                                            }
                                                                            className="h-7 text-xs font-mono text-right"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2 text-right font-mono font-semibold bg-muted/20">
                                                                        {formatNum(row.stock)}
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="number"
                                                                            step="0.01"
                                                                            value={row.pemakaian}
                                                                            onChange={(e) =>
                                                                                handleUpdatePelumasRow(idx, 'pemakaian', Number(e.target.value) || 0)
                                                                            }
                                                                            className="h-7 text-xs font-mono text-right"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="number"
                                                                            step="0.01"
                                                                            value={row.pengiriman}
                                                                            onChange={(e) =>
                                                                                handleUpdatePelumasRow(idx, 'pengiriman', Number(e.target.value) || 0)
                                                                            }
                                                                            className="h-7 text-xs font-mono text-right"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2 text-right font-mono font-semibold bg-muted/20">
                                                                        {formatNum(row.administrasi)}
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="number"
                                                                            step="0.01"
                                                                            value={row.fisik_liter}
                                                                            onChange={(e) =>
                                                                                handleUpdatePelumasRow(idx, 'fisik_liter', Number(e.target.value) || 0)
                                                                            }
                                                                            className="h-7 text-xs font-mono text-right"
                                                                        />
                                                                    </td>
                                                                    <td
                                                                        className={`p-2 text-right font-mono font-bold ${
                                                                            row.selisih === 0
                                                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                                                : row.selisih > 0
                                                                                  ? 'text-blue-700 dark:text-blue-400'
                                                                                  : 'text-amber-700 dark:text-amber-400'
                                                                        }`}
                                                                    >
                                                                        {row.selisih > 0 ? `+${formatNum(row.selisih)}` : formatNum(row.selisih)}
                                                                    </td>
                                                                    <td className="p-2 text-center">
                                                                        <Button
                                                                            type="button"
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            onClick={() => handleRemovePelumasRow(idx)}
                                                                            className="size-7 text-destructive hover:bg-destructive/10"
                                                                        >
                                                                            <Trash2 className="size-3.5" />
                                                                        </Button>
                                                                    </td>
                                                                </tr>
                                                            ))
                                                        )}

                                                        {/* Summary Total Row */}
                                                        {pelumasRows.length > 0 && (
                                                            <tr className="bg-muted/60 font-bold border-t-2 border-border text-foreground">
                                                                <td colSpan={3} className="p-2 text-center">
                                                                    JUMLAH TOTAL
                                                                </td>
                                                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.awal)}</td>
                                                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.penerimaan)}</td>
                                                                <td className="p-2 text-right font-mono bg-muted/50">{formatNum(pelumasTotals.stock)}</td>
                                                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.pemakaian)}</td>
                                                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.pengiriman)}</td>
                                                                <td className="p-2 text-right font-mono bg-muted/50">{formatNum(pelumasTotals.administrasi)}</td>
                                                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.fisik_liter)}</td>
                                                                <td className="p-2 text-right font-mono">{formatNum(pelumasTotals.selisih)}</td>
                                                                <td></td>
                                                            </tr>
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>

                                            {/* Catatan Selisih Pelumas */}
                                            <div className="rounded-lg border border-border bg-card p-4 space-y-2 text-xs">
                                                <Label className="font-semibold text-foreground">
                                                    Catatan: * Selisih disebabkan karena:
                                                </Label>
                                                <textarea
                                                    rows={3}
                                                    value={catatan}
                                                    onChange={(e) => setCatatan(e.target.value)}
                                                    placeholder="Tuliskan catatan atau penyebab selisih pelumas bila ada..."
                                                    className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* TAB 2: RICH TEXT HTML EDITOR */}
                            {activeTab === 'html' && (
                                <div className="p-4 space-y-3">
                                    <div className="rounded-md border border-amber-300 bg-amber-50 p-2.5 text-xs text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300">
                                        Mode Editor Teks (HTML) memungkinkan penyesuaian isi dokumen secara langsung. PDF yang diekspor akan mengikuti teks hasil pengeditan ini jika disimpan pada mode ini.
                                    </div>
                                    <div className="rounded-md border border-border bg-card">
                                        <RichTextEditor
                                            value={html}
                                            extraContentStyle={content_styles}
                                            onChange={setHtml}
                                            disabled={!can_create}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* TAB 3: SPREADSHEET EXCEL EDITOR */}
                            {activeTab === 'grid' && (
                                <div className="space-y-3 p-4">
                                    <div className="overflow-x-auto rounded-md border border-border bg-white p-4">
                                        <style>{LETTERHEAD_STYLES}</style>
                                        <div
                                            className="ba-letterhead mx-auto max-w-[1000px]"
                                            dangerouslySetInnerHTML={{ __html: letterhead }}
                                        />
                                        <p className="mx-auto max-w-[1000px] pt-1 text-[12px] text-muted-foreground">
                                            Kop surat (logo + header) di atas ikut tercetak di PDF &amp; Excel. Isi tabel diedit di bawah.
                                        </p>
                                    </div>
                                    <SpreadsheetEditor grid={gridState} onChange={setGridState} />
                                </div>
                            )}

                            {/* TAB 4: PRATINJAU PDF */}
                            {activeTab === 'pdf' && (
                                <div className="p-4 space-y-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground border-b border-border pb-3">
                                        <div className="flex items-center gap-2">
                                            <Printer className="size-4 text-primary" />
                                            <span>
                                                Pratinjau ini identik dengan hasil cetak PDF A4 resmi PT PLN Nusantara Power.
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setPreviewKey((k) => k + 1)}
                                                className="h-7 gap-1 text-xs"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                Segarkan Pratinjau
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => window.open(previewPdfUrl, '_blank')}
                                                className="h-7 gap-1 text-xs"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                Tab Baru
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={handleDownloadPdf}
                                                className="h-7 gap-1 text-xs"
                                            >
                                                <Download className="size-3.5" />
                                                Unduh PDF
                                            </Button>
                                        </div>
                                    </div>
                                    <iframe
                                        key={`${previewKey}-${marginTop}-${marginBottom}`}
                                        title={`Pratinjau PDF ${type.label}`}
                                        src={previewPdfUrl}
                                        className="h-[750px] w-full rounded-md border border-border bg-white shadow-xs"
                                    />
                                </div>
                            )}

                            {/* Card Bottom Actions */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-muted/10 p-4">
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    {savedFormat !== null ? (
                                        <div className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-medium">
                                            <Check className="size-4" />
                                            Tersimpan pada format: {savedFormat.toUpperCase()}
                                        </div>
                                    ) : (
                                        <span>Perubahan belum disimpan ke server</span>
                                    )}
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        onClick={() => router.get(beritaAcara.index().url)}
                                    >
                                        Kembali
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={handleOpenPreview}
                                        className="gap-1.5"
                                    >
                                        <Printer className="size-4" />
                                        Pratinjau PDF
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={handleDownloadPdf}
                                        className="gap-1.5"
                                    >
                                        <Download className="size-4" />
                                        Unduh PDF
                                    </Button>
                                    {can_create && (
                                        <Button
                                            onClick={() => handleSave()}
                                            disabled={isSaving}
                                            className="gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
                                        >
                                            <Save className="size-4" />
                                            {isSaving ? 'Menyimpan…' : 'Simpan Berita Acara'}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

BeritaAcaraEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Berita Acara', href: beritaAcara.index() },
        { title: 'Buat & Edit', href: beritaAcara.index() },
    ],
};

