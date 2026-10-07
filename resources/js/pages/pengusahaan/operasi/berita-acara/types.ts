import type { DocumentGrid } from '@/lib/spreadsheet';

export type EditorTab = 'form' | 'html' | 'grid' | 'pdf';

export type SignatoryOption = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    has_signature: boolean;
    signature_url: string | null;
};

export type PemakaianItem = {
    mesin: string;
    liter: number;
};

export type FisikItem = {
    tangki: string;
    liter: number;
};

export type PelumasRow = {
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

export type FeederReading = {
    awal: number;
    akhir: number;
    f_kali: number;
    hasil: number;
};

export type FeederRow = {
    feeder_name: string;
    export: FeederReading;
    import: FeederReading;
    keterangan?: string;
};

export type FeederTotals = {
    jumlah_export: number;
    jumlah_import: number;
    total_unit: number;
};

export type AttachmentItem = {
    id: string;
    url: string;
    path: string;
    caption: string;
    file_name: string;
};

export type FormDataPayload = {
    is_fuel?: boolean;
    is_feeder?: boolean;
    fuel_label?: string;
    document?: {
        number?: string;
        title?: string;
        revision?: string;
        revision_date?: string;
    };
    unit?: {
        id: number;
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
    // Feeder specific
    feeder_rows?: FeederRow[];
    totals?: FeederTotals;
    attachments?: AttachmentItem[];
};

export type EditorProps = {
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

export const MONTHS = [
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

export const formatNum = (v: number | string | undefined | null) => {
    const num = Number(v) || 0;
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};
