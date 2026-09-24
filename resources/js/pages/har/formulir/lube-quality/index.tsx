import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Download,
    ExternalLink,
    Eye,
    FileCode2,
    FileSpreadsheet,
    Fuel,
    History,
    ImageIcon,
    Info,
    Plus,
    Printer,
    RotateCcw,
    Save,
    SlidersHorizontal,
    Sparkles,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { PdfPreviewFrame } from '@/components/pdf-preview-frame';
import { RichTextEditor } from '@/components/rich-text-editor';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import lubeQualityRoutes from '@/routes/har/formulir/lube-quality';
import harFormulir from '@/routes/har/formulir';
import type { IdName } from '@/types';

export type LubeParameterRow = {
    id: string;
    tanggal: string;
    tbn: string;
    water_content: string;
    viscosity_40: string;
    viscosity_100: string;
    aw_additive: string;
    glycol: string;
    nitration: string;
    oxidation: string;
    soot: string;
    sulfation: string;
    keterangan: string;
};

type EmployeeOption = {
    id: number;
    name: string;
    position: string | null;
};

type MachineOption = {
    id: number;
    name: string;
    type: string | null;
    serial_number: string | null;
    capacity_kw: string | null;
};

type HistoryItem = {
    id: number;
    machine_id: number;
    test_date: string;
    document_number: string;
    revision: string;
    format: string;
    updated_at: string;
};

type Props = {
    unit: {
        id: number;
        name: string;
        service_unit_id: number | null;
        service_unit_name: string | null;
    };
    units: IdName[];
    machines: MachineOption[];
    selected_machine_id: number | null;
    selected_test_date: string;
    record: any | null;
    form_data: {
        document_number: string;
        revision: string;
        effective_date: string;
        page_number: string;
        test_date_raw: string;
        unit_sentral: string;
        machine_name: string;
        machine_number: string;
        serial_number: string;
        sample_point: string;
        parameters: any[];
        status_text: string;
        standard_text: string;
        photo_path: string | null;
        photo_url: string | null;
        photo_caption: string;
        analisa_text: string;
        cba_text: string;
        rekomendasi_text: string;
        signature_location: string;
        signature_date: string;
        manager_ul_id: number | null;
        manager_ul_name: string;
        manager_ul_title: string;
        tl_har_id: number | null;
        tl_har_name: string;
        tl_har_title: string;
        staff_har_id: number | null;
        staff_har_name: string;
        staff_har_title: string;
        page_margin_top: number;
        page_margin_bottom: number;
        page_margin_left: number;
        page_margin_right: number;
        line_spacing: string;
    };
    rendered_html: string;
    manager_options: EmployeeOption[];
    tl_options: EmployeeOption[];
    staff_options: EmployeeOption[];
    history: HistoryItem[];
    pdf_url: string;
    sample_scan_parameters: any[];
    default_status_text: string;
    default_standard_text: string;
    default_analisa_text: string;
    default_cba_text: string;
    default_rekomendasi_text: string;
    can_write: boolean;
};

export default function LubeQualityIndex({
    unit,
    units,
    machines,
    selected_machine_id,
    selected_test_date,
    record,
    form_data,
    rendered_html,
    manager_options,
    tl_options,
    staff_options,
    history,
    pdf_url,
    sample_scan_parameters,
    default_status_text,
    default_standard_text,
    default_analisa_text,
    default_cba_text,
    default_rekomendasi_text,
    can_write,
}: Props) {
    // Tab View state: 'form' | 'html' | 'pdf'
    const [viewTab, setViewTab] = useState<'form' | 'html' | 'pdf'>('form');

    // Document header state
    const [docNumber, setDocNumber] = useState<string>(form_data.document_number || 'FMKD-305-14.3.2.b-A3');
    const [revision, setRevision] = useState<string>(form_data.revision || '');
    const [effectiveDate, setEffectiveDate] = useState<string>(form_data.effective_date || '31 - 07 - 2024');
    const [pageNumber, setPageNumber] = useState<string>(form_data.page_number || '');
    const [testDate, setTestDate] = useState<string>(selected_test_date || form_data.test_date_raw);

    // Machine & Sample Point details
    const [unitSentral, setUnitSentral] = useState<string>(form_data.unit_sentral || '');
    const [machineName, setMachineName] = useState<string>(form_data.machine_name || '');
    const [machineNumber, setMachineNumber] = useState<string>(form_data.machine_number || '');
    const [serialNumber, setSerialNumber] = useState<string>(form_data.serial_number || '');
    const [samplePoint, setSamplePoint] = useState<string>(form_data.sample_point || 'Sump Tank');

    // Parameter Table State
    const [parameters, setParameters] = useState<LubeParameterRow[]>(() => {
        const raw = form_data.parameters || [];
        if (raw.length > 0) {
            return raw.map((r: any, idx: number) => ({
                id: `param-${idx}-${Date.now()}`,
                tanggal: r.tanggal || '',
                tbn: r.tbn || '',
                water_content: r.water_content || '',
                viscosity_40: r.viscosity_40 || '-',
                viscosity_100: r.viscosity_100 || '-',
                aw_additive: r.aw_additive || '',
                glycol: r.glycol || '',
                nitration: r.nitration || '',
                oxidation: r.oxidation || '',
                soot: r.soot || '',
                sulfation: r.sulfation || '',
                keterangan: r.keterangan || 'No Alarm',
            }));
        }
        return [
            {
                id: `param-0-${Date.now()}`,
                tanggal: '28-Aug-26',
                tbn: '23,80',
                water_content: '974,00',
                viscosity_40: '-',
                viscosity_100: '-',
                aw_additive: '157,00',
                glycol: '0,00',
                nitration: '0,00',
                oxidation: '16,00',
                soot: '0,18',
                sulfation: '19,10',
                keterangan: 'No Alarm',
            },
        ];
    });

    // Structured Text Sections
    const [statusText, setStatusText] = useState<string>(form_data.status_text || default_status_text || '- No Alarm Sign');
    const [standardText, setStandardText] = useState<string>(form_data.standard_text || default_standard_text || '- Water Content : 2000 ppm');
    const [analisaText, setAnalisaText] = useState<string>(form_data.analisa_text || default_analisa_text || "- All parameter normal\n- Alat ukur viskositas eror");
    const [cbaText, setCbaText] = useState<string>(form_data.cba_text || default_cba_text || 'N/A');
    const [rekomendasiText, setRekomendasiText] = useState<string>(form_data.rekomendasi_text || default_rekomendasi_text || 'N/A');

    // Photo state
    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(form_data.photo_url || '/images/har/sample-lube-photo.png');
    const [removePhoto, setRemovePhoto] = useState<boolean>(false);
    const [photoCaption, setPhotoCaption] = useState<string>(
        form_data.photo_caption ||
            "28 Agu 2026 15:46:39\n-3°59'46,05188\"S 122°31'50,60101\"E\n154° SE\nKecamatan Wua-Wua\nKota Kendari\nSulawesi Tenggara\nAkurasi 4.1\nIndex number: 2494"
    );
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    // Signatories state
    const [sigLocation, setSigLocation] = useState<string>(form_data.signature_location || 'Kendari');
    const [sigDate, setSigDate] = useState<string>(form_data.signature_date || '31 Agustus 2026');

    const [managerUlId, setManagerUlId] = useState<string>(
        form_data.manager_ul_id ? String(form_data.manager_ul_id) : 'custom'
    );
    const [managerUlName, setManagerUlName] = useState<string>(form_data.manager_ul_name || 'SURYADI PRATAMA');
    const [managerUlTitle, setManagerUlTitle] = useState<string>(
        form_data.manager_ul_title || `Plh. Manager Unit Layanan ${unit.service_unit_name || unit.name}`
    );

    const [tlHarId, setTlHarId] = useState<string>(
        form_data.tl_har_id ? String(form_data.tl_har_id) : 'custom'
    );
    const [tlHarName, setTlHarName] = useState<string>(form_data.tl_har_name || 'SURYADI PRATAMA');
    const [tlHarTitle, setTlHarTitle] = useState<string>(
        form_data.tl_har_title || 'Team Leader Pemeliharaan'
    );

    const [staffHarId, setStaffHarId] = useState<string>(
        form_data.staff_har_id ? String(form_data.staff_har_id) : 'custom'
    );
    const [staffHarName, setStaffHarName] = useState<string>(form_data.staff_har_name || 'MUHAMMAD ABDUL LIIZAL');
    const [staffHarTitle, setStaffHarTitle] = useState<string>(
        form_data.staff_har_title || 'Staff Pemeliharaan'
    );

    // Page settings
    const [marginTop, setMarginTop] = useState<number>(form_data.page_margin_top ?? 8);
    const [marginBottom, setMarginBottom] = useState<number>(form_data.page_margin_bottom ?? 8);
    const [marginLeft, setMarginLeft] = useState<number>(form_data.page_margin_left ?? 10);
    const [marginRight, setMarginRight] = useState<number>(form_data.page_margin_right ?? 10);
    const [lineSpacing, setLineSpacing] = useState<string>(form_data.line_spacing ?? '1.15');

    // Format mode: 'form' | 'html'
    const [formatMode, setFormatMode] = useState<'form' | 'html'>(record?.format === 'html' ? 'html' : 'form');
    const [contentHtml, setContentHtml] = useState<string>(rendered_html || '');

    // Saving and Preview state
    const [isSaving, setIsSaving] = useState<boolean>(false);
    const [previewKey, setPreviewKey] = useState<number>(Date.now());
    const [historyOpen, setHistoryOpen] = useState<boolean>(false);

    // Sync when form_data changes from server
    useEffect(() => {
        setDocNumber(form_data.document_number || 'FMKD-305-14.3.2.b-A3');
        setRevision(form_data.revision || '');
        setEffectiveDate(form_data.effective_date || '31 - 07 - 2024');
        setPageNumber(form_data.page_number || '');
        setUnitSentral(form_data.unit_sentral || '');
        setMachineName(form_data.machine_name || '');
        setMachineNumber(form_data.machine_number || '');
        setSerialNumber(form_data.serial_number || '');
        setSamplePoint(form_data.sample_point || 'Sump Tank');

        if (form_data.parameters && form_data.parameters.length > 0) {
            setParameters(
                form_data.parameters.map((r: any, idx: number) => ({
                    id: `param-${idx}-${Date.now()}`,
                    tanggal: r.tanggal || '',
                    tbn: r.tbn || '',
                    water_content: r.water_content || '',
                    viscosity_40: r.viscosity_40 || '-',
                    viscosity_100: r.viscosity_100 || '-',
                    aw_additive: r.aw_additive || '',
                    glycol: r.glycol || '',
                    nitration: r.nitration || '',
                    oxidation: r.oxidation || '',
                    soot: r.soot || '',
                    sulfation: r.sulfation || '',
                    keterangan: r.keterangan || 'No Alarm',
                }))
            );
        }

        setStatusText(form_data.status_text || default_status_text || '- No Alarm Signal');
        setStandardText(form_data.standard_text || default_standard_text || '- Water Content : 2000 ppm');
        setAnalisaText(form_data.analisa_text || default_analisa_text || "- All parameter normal\n- Alat ukur viskositas eror");
        setCbaText(form_data.cba_text || default_cba_text || 'N/A');
        setRekomendasiText(form_data.rekomendasi_text || default_rekomendasi_text || 'N/A');

        setPhotoPreview(form_data.photo_url || null);
        setPhotoFile(null);
        setRemovePhoto(false);
        setPhotoCaption(form_data.photo_caption || '');

        setSigLocation(form_data.signature_location || 'Kendari');
        setSigDate(form_data.signature_date || '');

        setManagerUlId(form_data.manager_ul_id ? String(form_data.manager_ul_id) : 'custom');
        setManagerUlName(form_data.manager_ul_name || '');
        setManagerUlTitle(form_data.manager_ul_title || `Plh. Manager Unit Layanan ${unit.service_unit_name || unit.name}`);

        setTlHarId(form_data.tl_har_id ? String(form_data.tl_har_id) : 'custom');
        setTlHarName(form_data.tl_har_name || '');
        setTlHarTitle(form_data.tl_har_title || 'Team Leader Pemeliharaan');

        setStaffHarId(form_data.staff_har_id ? String(form_data.staff_har_id) : 'custom');
        setStaffHarName(form_data.staff_har_name || '');
        setStaffHarTitle(form_data.staff_har_title || 'Staff Pemeliharaan');

        setMarginTop(form_data.page_margin_top ?? 8);
        setMarginBottom(form_data.page_margin_bottom ?? 8);
        setMarginLeft(form_data.page_margin_left ?? 10);
        setMarginRight(form_data.page_margin_right ?? 10);
        setLineSpacing(form_data.line_spacing ?? '1.15');

        setFormatMode(record?.format === 'html' ? 'html' : 'form');
        setContentHtml(rendered_html || '');
        setPreviewKey(Date.now());
    }, [form_data, record, rendered_html, unit]);

    // Handle Unit Change
    const handleUnitChange = (newUnitId: string) => {
        router.get(
            lubeQualityRoutes.index.url(),
            { unit_id: newUnitId },
            { preserveState: false }
        );
    };

    // Handle Machine Change
    const handleMachineChange = (newMachineId: string) => {
        router.get(
            lubeQualityRoutes.index.url(),
            {
                unit_id: unit.id,
                machine_id: newMachineId,
                test_date: testDate,
            },
            { preserveState: false }
        );
    };

    // Handle Date Change
    const handleDateChange = (newDate: string) => {
        setTestDate(newDate);
        router.get(
            lubeQualityRoutes.index.url(),
            {
                unit_id: unit.id,
                machine_id: selected_machine_id,
                test_date: newDate,
            },
            { preserveState: false }
        );
    };

    // Parameter Table Handlers
    const handleAddParameterRow = () => {
        setParameters((prev) => [
            ...prev,
            {
                id: `param-${Date.now()}`,
                tanggal: testDate,
                tbn: '',
                water_content: '',
                viscosity_40: '-',
                viscosity_100: '-',
                aw_additive: '',
                glycol: '',
                nitration: '',
                oxidation: '',
                soot: '',
                sulfation: '',
                keterangan: 'No Alarm',
            },
        ]);
    };

    const handleRemoveParameterRow = (index: number) => {
        setParameters((prev) => prev.filter((_, i) => i !== index));
    };

    const handleParameterChange = (
        index: number,
        field: keyof Omit<LubeParameterRow, 'id'>,
        value: string
    ) => {
        setParameters((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], [field]: value };
            return next;
        });
    };

    // Photo Handlers
    const handlePhotoSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setPhotoFile(file);
            setRemovePhoto(false);
            const previewUrl = URL.createObjectURL(file);
            setPhotoPreview(previewUrl);
        }
    };

    const handleRemovePhoto = () => {
        setPhotoFile(null);
        setPhotoPreview(null);
        setRemovePhoto(true);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    // Quick Action: Fill Scan Values
    const handleFillScanValues = () => {
        setParameters([
            {
                id: `scan-0-${Date.now()}`,
                tanggal: '28-Aug-26',
                tbn: '23,80',
                water_content: '974,00',
                viscosity_40: '-',
                viscosity_100: '-',
                aw_additive: '157,00',
                glycol: '0,00',
                nitration: '0,00',
                oxidation: '16,00',
                soot: '0,18',
                sulfation: '19,10',
                keterangan: 'No Alarm',
            },
        ]);
        setStatusText('- No Alarm Sign');
        setStandardText('- Water Content : 2000 ppm');
        setAnalisaText("- All parameter normal\n- Alat ukur viskositas eror");
        setCbaText('N/A');
        setRekomendasiText('N/A');
        setSamplePoint('Sump Tank');
        setMachineName('1');
        setMachineNumber('1');
        setStaffHarName('MUHAMMAD ABDUL LIIZAL');
        setPhotoPreview('/images/har/sample-lube-photo.png');
        setPhotoCaption(
            "28 Agu 2026 15:46:39\n-3°59'46,05188\"S 122°31'50,60101\"E\n154° SE\nKecamatan Wua-Wua\nKota Kendari\nSulawesi Tenggara\nAkurasi 4.1\nIndex number: 2494"
        );
        setPreviewKey(Date.now());
    };

    // Quick Action: Reset to Standard
    const handleResetToStandard = () => {
        if (!confirm('Kembalikan isian formulir ke nilai default?')) return;
        handleFillScanValues();
    };

    // Preview URL with parameters
    const previewPdfUrl = useMemo(() => {
        const separator = pdf_url.includes('?') ? '&' : '?';
        const params = new URLSearchParams();
        params.set('unit_id', String(unit.id));
        if (selected_machine_id) params.set('machine_id', String(selected_machine_id));
        params.set('test_date', testDate);
        if (record?.id) params.set('record_id', String(record.id));
        params.set('t', String(previewKey));
        params.set('document_number', docNumber);
        params.set('revision', revision);
        params.set('effective_date', effectiveDate);
        params.set('page_number', pageNumber);
        params.set('unit_sentral', unitSentral);
        params.set('machine_name', machineName);
        params.set('machine_number', machineNumber);
        params.set('serial_number', serialNumber);
        params.set('sample_point', samplePoint);
        params.set('status_text', statusText);
        params.set('standard_text', standardText);
        params.set('photo_caption', photoCaption);
        params.set('analisa_text', analisaText);
        params.set('cba_text', cbaText);
        params.set('rekomendasi_text', rekomendasiText);
        params.set('signature_location', sigLocation);
        params.set('signature_date', sigDate);
        params.set('manager_ul_id', managerUlId);
        params.set('manager_ul_name', managerUlName);
        params.set('manager_ul_title', managerUlTitle);
        params.set('tl_har_id', tlHarId);
        params.set('tl_har_name', tlHarName);
        params.set('tl_har_title', tlHarTitle);
        params.set('staff_har_id', staffHarId);
        params.set('staff_har_name', staffHarName);
        params.set('staff_har_title', staffHarTitle);
        params.set('page_margin_top', String(marginTop));
        params.set('page_margin_bottom', String(marginBottom));
        params.set('page_margin_left', String(marginLeft));
        params.set('page_margin_right', String(marginRight));
        params.set('line_spacing', lineSpacing);

        parameters.forEach((p, idx) => {
            params.set(`parameters[${idx}][tanggal]`, p.tanggal);
            params.set(`parameters[${idx}][tbn]`, p.tbn);
            params.set(`parameters[${idx}][water_content]`, p.water_content);
            params.set(`parameters[${idx}][viscosity_40]`, p.viscosity_40);
            params.set(`parameters[${idx}][viscosity_100]`, p.viscosity_100);
            params.set(`parameters[${idx}][aw_additive]`, p.aw_additive);
            params.set(`parameters[${idx}][glycol]`, p.glycol);
            params.set(`parameters[${idx}][nitration]`, p.nitration);
            params.set(`parameters[${idx}][oxidation]`, p.oxidation);
            params.set(`parameters[${idx}][soot]`, p.soot);
            params.set(`parameters[${idx}][sulfation]`, p.sulfation);
            params.set(`parameters[${idx}][keterangan]`, p.keterangan);
        });

        return `${pdf_url}${separator}${params.toString()}`;
    }, [
        unit.id,
        selected_machine_id,
        testDate,
        record?.id,
        previewKey,
        pdf_url,
        docNumber,
        revision,
        effectiveDate,
        pageNumber,
        unitSentral,
        machineName,
        machineNumber,
        serialNumber,
        samplePoint,
        statusText,
        standardText,
        photoCaption,
        analisaText,
        cbaText,
        rekomendasiText,
        sigLocation,
        sigDate,
        managerUlId,
        managerUlName,
        managerUlTitle,
        tlHarId,
        tlHarName,
        tlHarTitle,
        staffHarId,
        staffHarName,
        staffHarTitle,
        marginTop,
        marginBottom,
        marginLeft,
        marginRight,
        lineSpacing,
        parameters,
    ]);

    // Save Form
    const handleSave = () => {
        if (!selected_machine_id) {
            alert('Pilih mesin terlebih dahulu sebelum menyimpan formulir.');
            return;
        }

        setIsSaving(true);

        const formData = new FormData();
        formData.append('unit_id', String(unit.id));
        formData.append('machine_id', String(selected_machine_id));
        formData.append('test_date', testDate);
        formData.append('document_number', docNumber);
        formData.append('revision', revision);
        formData.append('effective_date', effectiveDate);
        formData.append('page_number', pageNumber);
        formData.append('unit_sentral', unitSentral);
        formData.append('machine_name', machineName);
        formData.append('machine_number', machineNumber);
        formData.append('serial_number', serialNumber);
        formData.append('sample_point', samplePoint);

        // Parameters
        parameters.forEach((p, idx) => {
            formData.append(`parameters[${idx}][tanggal]`, p.tanggal);
            formData.append(`parameters[${idx}][tbn]`, p.tbn);
            formData.append(`parameters[${idx}][water_content]`, p.water_content);
            formData.append(`parameters[${idx}][viscosity_40]`, p.viscosity_40);
            formData.append(`parameters[${idx}][viscosity_100]`, p.viscosity_100);
            formData.append(`parameters[${idx}][aw_additive]`, p.aw_additive);
            formData.append(`parameters[${idx}][glycol]`, p.glycol);
            formData.append(`parameters[${idx}][nitration]`, p.nitration);
            formData.append(`parameters[${idx}][oxidation]`, p.oxidation);
            formData.append(`parameters[${idx}][soot]`, p.soot);
            formData.append(`parameters[${idx}][sulfation]`, p.sulfation);
            formData.append(`parameters[${idx}][keterangan]`, p.keterangan);
        });

        // Structured texts
        formData.append('status_text', statusText);
        formData.append('standard_text', standardText);
        formData.append('analisa_text', analisaText);
        formData.append('cba_text', cbaText);
        formData.append('rekomendasi_text', rekomendasiText);

        // Photo
        if (photoFile) {
            formData.append('photo', photoFile);
        }
        if (removePhoto) {
            formData.append('remove_photo', '1');
        }
        formData.append('photo_caption', photoCaption);

        // Signatories
        formData.append('signature_location', sigLocation);
        formData.append('signature_date', sigDate);

        if (managerUlId !== 'custom') formData.append('manager_ul_id', managerUlId);
        formData.append('manager_ul_name', managerUlName);
        formData.append('manager_ul_title', managerUlTitle);

        if (tlHarId !== 'custom') formData.append('tl_har_id', tlHarId);
        formData.append('tl_har_name', tlHarName);
        formData.append('tl_har_title', tlHarTitle);

        if (staffHarId !== 'custom') formData.append('staff_har_id', staffHarId);
        formData.append('staff_har_name', staffHarName);
        formData.append('staff_har_title', staffHarTitle);

        // PDF margins
        formData.append('page_margin_top', String(marginTop));
        formData.append('page_margin_bottom', String(marginBottom));
        formData.append('page_margin_left', String(marginLeft));
        formData.append('page_margin_right', String(marginRight));
        formData.append('line_spacing', lineSpacing);

        // Format mode
        formData.append('format', formatMode);
        formData.append('content_html', contentHtml);

        router.post(lubeQualityRoutes.store.url(), formData, {
            onFinish: () => {
                setIsSaving(false);
                setPreviewKey(Date.now());
            },
        });
    };

    // Reset from server
    const handleResetServer = () => {
        if (!confirm('Apakah Anda yakin ingin menghapus data formulir ini dari database?')) return;
        router.post(
            lubeQualityRoutes.reset.url(),
            {
                unit_id: unit.id,
                machine_id: selected_machine_id,
                test_date: testDate,
            },
            {
                onSuccess: () => {
                    setPreviewKey(Date.now());
                },
            }
        );
    };

    const handleDownloadPdf = () => {
        window.open(`${previewPdfUrl}&download=1`, '_blank');
    };

    const handlePrintPdf = () => {
        window.open(previewPdfUrl, '_blank');
    };

    return (
        <>
            <Head title={`Formulir Pengukuran Kualitas Pelumas - ${unit.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Header Navigation */}
                <PageHeader
                    title="Formulir Pengukuran Kualitas Pelumas"
                    description="Pencatatan hasil uji laboratorium dan oil test kit parameter oli pelumas (TBN, Water Content, Viskositas, AW Additive, Kontaminasi)."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                onClick={() => router.get(harFormulir.index().url, { unit_id: unit.id })}
                                className="gap-2"
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setHistoryOpen(true)}
                                className="gap-2"
                            >
                                <History className="size-4" />
                                Riwayat ({history.length})
                            </Button>
                            <Button
                                variant="outline"
                                onClick={handleDownloadPdf}
                                className="gap-2"
                            >
                                <Download className="size-4" />
                                Unduh PDF
                            </Button>
                            {can_write && (
                                <Button
                                    onClick={handleSave}
                                    disabled={isSaving}
                                    className="gap-2"
                                >
                                    <Save className="size-4" />
                                    {isSaving ? 'Menyimpan…' : 'Simpan Formulir'}
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
                        <span className="text-muted-foreground">Status Dokumen:</span>
                        {record ? (
                            <StatusBadge tone="success">
                                Tersimpan ({record.format === 'html' ? 'Teks HTML' : 'Form'})
                            </StatusBadge>
                        ) : (
                            <StatusBadge tone="neutral">Belum Disimpan</StatusBadge>
                        )}
                    </div>
                    <div className="text-[12px] text-muted-foreground">
                        No. Dokumen: <span className="font-mono font-medium">{docNumber}</span> (Rev. {revision || '00'})
                    </div>
                </div>

                {/* 2-Column Layout */}
                <div className="flex flex-col lg:flex-row items-start gap-6 w-full">
                    {/* LEFT COLUMN: Metadata, Signatories, Page Settings */}
                    <div className="w-full lg:w-[360px] xl:w-[380px] shrink-0 space-y-5">
                        {/* Card 1: Metadata Formulir & Mesin */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Metadata Formulir &amp; Mesin
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Informasi dasar unit, mesin, dan titik pengambilan sampel pelumas.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                {/* Unit Selector */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Unit Pembangkit</Label>
                                    {units.length > 1 ? (
                                        <Select
                                            value={String(unit.id)}
                                            onValueChange={handleUnitChange}
                                        >
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue placeholder="Pilih unit" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {units.map((u) => (
                                                    <SelectItem key={u.id} value={String(u.id)} className="text-xs">
                                                        {u.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Input
                                            value={unit.name}
                                            disabled
                                            className="h-8 text-xs bg-muted"
                                        />
                                    )}
                                </div>

                                {/* Machine Selector */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Mesin Pembangkit</Label>
                                    <Select
                                        value={selected_machine_id ? String(selected_machine_id) : ''}
                                        onValueChange={handleMachineChange}
                                    >
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue placeholder="Pilih mesin" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {machines.map((m) => (
                                                <SelectItem key={m.id} value={String(m.id)} className="text-xs">
                                                    {m.name} {m.type ? `(${m.type})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Test Date */}
                                <div className="space-y-1">
                                    <Label className="text-xs">Tanggal Pengambilan / Uji</Label>
                                    <Input
                                        type="date"
                                        value={testDate}
                                        onChange={(e) => handleDateChange(e.target.value)}
                                        className="h-8 text-xs"
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Nomor Dokumen</Label>
                                        <Input
                                            value={docNumber}
                                            onChange={(e) => setDocNumber(e.target.value)}
                                            className="h-8 text-xs font-mono"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Revisi</Label>
                                        <Input
                                            value={revision}
                                            onChange={(e) => setRevision(e.target.value)}
                                            placeholder="00"
                                            className="h-8 text-xs font-mono"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Tanggal Terbit</Label>
                                        <Input
                                            value={effectiveDate}
                                            onChange={(e) => setEffectiveDate(e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Halaman</Label>
                                        <Input
                                            value={pageNumber}
                                            onChange={(e) => setPageNumber(e.target.value)}
                                            placeholder="1/1"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>

                                {/* Subheader Meta */}
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Unit / Sentral</Label>
                                        <Input
                                            value={unitSentral}
                                            onChange={(e) => setUnitSentral(e.target.value)}
                                            placeholder="ULPLTD WUA-WUA"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Titik Sampel</Label>
                                        <Input
                                            value={samplePoint}
                                            onChange={(e) => setSamplePoint(e.target.value)}
                                            placeholder="Sump Tank"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-3 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Mesin</Label>
                                        <Input
                                            value={machineName}
                                            onChange={(e) => setMachineName(e.target.value)}
                                            placeholder="5"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Mesin No.</Label>
                                        <Input
                                            value={machineNumber}
                                            onChange={(e) => setMachineNumber(e.target.value)}
                                            placeholder="5"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">No. Seri</Label>
                                        <Input
                                            value={serialNumber}
                                            onChange={(e) => setSerialNumber(e.target.value)}
                                            placeholder="-"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Card 2: Penandatangan Dokumen */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-4">
                            <div className="border-b border-border pb-2">
                                <h3 className="text-sm font-semibold text-foreground">
                                    Penandatangan Dokumen (3 Kolom)
                                </h3>
                                <p className="text-[12px] text-muted-foreground">
                                    Pejabat penanggung jawab dan tim teknis pengujian kualitas pelumas.
                                </p>
                            </div>

                            <div className="space-y-3 text-xs">
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <Label className="text-xs">Lokasi Dokumen</Label>
                                        <Input
                                            value={sigLocation}
                                            onChange={(e) => setSigLocation(e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">Tanggal Pengesahan</Label>
                                        <Input
                                            value={sigDate}
                                            onChange={(e) => setSigDate(e.target.value)}
                                            placeholder="31 Agustus 2026"
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>

                                {/* 1. Manager UL */}
                                <div className="rounded-md border border-border/70 p-2.5 space-y-2 bg-muted/20">
                                    <div className="font-semibold text-foreground text-xs">
                                        1. Manager Unit Layanan (Kiri)
                                    </div>
                                    <Select
                                        value={managerUlId}
                                        onValueChange={(val) => {
                                            setManagerUlId(val);
                                            if (val !== 'custom') {
                                                const emp = manager_options.find((e) => String(e.id) === val);
                                                if (emp) setManagerUlName(emp.name);
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Pejabat" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="custom" className="text-xs">
                                                — Input Manual —
                                            </SelectItem>
                                            {manager_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)} className="text-xs">
                                                    {e.name} ({e.position || 'Manager'})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        placeholder="Judul Jabatan (misal: Plh. Manager Unit Layanan...)"
                                        value={managerUlTitle}
                                        onChange={(e) => setManagerUlTitle(e.target.value)}
                                        className="h-7 text-xs"
                                    />
                                    <Input
                                        placeholder="Nama Lengkap"
                                        value={managerUlName}
                                        onChange={(e) => setManagerUlName(e.target.value)}
                                        className="h-7 text-xs font-medium"
                                    />
                                </div>

                                {/* 2. Team Leader Har */}
                                <div className="rounded-md border border-border/70 p-2.5 space-y-2 bg-muted/20">
                                    <div className="font-semibold text-foreground text-xs">
                                        2. Team Leader Pemeliharaan (Tengah)
                                    </div>
                                    <Select
                                        value={tlHarId}
                                        onValueChange={(val) => {
                                            setTlHarId(val);
                                            if (val !== 'custom') {
                                                const emp = tl_options.find((e) => String(e.id) === val);
                                                if (emp) setTlHarName(emp.name);
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih TL Pemeliharaan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="custom" className="text-xs">
                                                — Input Manual —
                                            </SelectItem>
                                            {tl_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)} className="text-xs">
                                                    {e.name} ({e.position || 'TL Har'})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        placeholder="Judul Jabatan"
                                        value={tlHarTitle}
                                        onChange={(e) => setTlHarTitle(e.target.value)}
                                        className="h-7 text-xs"
                                    />
                                    <Input
                                        placeholder="Nama Lengkap TL"
                                        value={tlHarName}
                                        onChange={(e) => setTlHarName(e.target.value)}
                                        className="h-7 text-xs font-medium"
                                    />
                                </div>

                                {/* 3. Staff Har */}
                                <div className="rounded-md border border-border/70 p-2.5 space-y-2 bg-muted/20">
                                    <div className="font-semibold text-foreground text-xs">
                                        3. Staff Pemeliharaan (Kanan)
                                    </div>
                                    <Select
                                        value={staffHarId}
                                        onValueChange={(val) => {
                                            setStaffHarId(val);
                                            if (val !== 'custom') {
                                                const emp = staff_options.find((e) => String(e.id) === val);
                                                if (emp) setStaffHarName(emp.name);
                                            }
                                        }}
                                    >
                                        <SelectTrigger className="h-7 text-xs">
                                            <SelectValue placeholder="Pilih Staff Pemeliharaan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="custom" className="text-xs">
                                                — Input Manual —
                                            </SelectItem>
                                            {staff_options.map((e) => (
                                                <SelectItem key={e.id} value={String(e.id)} className="text-xs">
                                                    {e.name} ({e.position || 'Staff'})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        placeholder="Judul Jabatan"
                                        value={staffHarTitle}
                                        onChange={(e) => setStaffHarTitle(e.target.value)}
                                        className="h-7 text-xs"
                                    />
                                    <Input
                                        placeholder="Nama Lengkap Staff"
                                        value={staffHarName}
                                        onChange={(e) => setStaffHarName(e.target.value)}
                                        className="h-7 text-xs font-medium"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Card 3: Pengaturan Margin & Spasi PDF */}
                        <div className="rounded-lg border border-border bg-card p-4 space-y-3">
                            <div className="border-b border-border pb-2 flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                    <SlidersHorizontal className="size-4" />
                                    Pengaturan Format PDF
                                </h3>
                                <Badge variant="outline" className="text-[10px]">
                                    A4 Portrait
                                </Badge>
                            </div>

                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <div className="space-y-1">
                                    <Label className="text-[11px]">Margin Atas (mm)</Label>
                                    <Input
                                        type="number"
                                        value={marginTop}
                                        onChange={(e) => setMarginTop(Number(e.target.value))}
                                        className="h-7 text-xs"
                                        min={0}
                                        max={30}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-[11px]">Margin Bawah (mm)</Label>
                                    <Input
                                        type="number"
                                        value={marginBottom}
                                        onChange={(e) => setMarginBottom(Number(e.target.value))}
                                        className="h-7 text-xs"
                                        min={0}
                                        max={30}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-[11px]">Margin Kiri (mm)</Label>
                                    <Input
                                        type="number"
                                        value={marginLeft}
                                        onChange={(e) => setMarginLeft(Number(e.target.value))}
                                        className="h-7 text-xs"
                                        min={0}
                                        max={30}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-[11px]">Margin Kanan (mm)</Label>
                                    <Input
                                        type="number"
                                        value={marginRight}
                                        onChange={(e) => setMarginRight(Number(e.target.value))}
                                        className="h-7 text-xs"
                                        min={0}
                                        max={30}
                                    />
                                </div>
                            </div>

                            <div className="space-y-1 text-xs">
                                <Label className="text-[11px]">Kerapatan Baris (Line Spacing)</Label>
                                <Select value={lineSpacing} onValueChange={setLineSpacing}>
                                    <SelectTrigger className="h-7 text-xs">
                                        <SelectValue placeholder="Pilih kerapatan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1.05" className="text-xs">Sangat Rapat (1.05)</SelectItem>
                                        <SelectItem value="1.1" className="text-xs">Rapat (1.1)</SelectItem>
                                        <SelectItem value="1.15" className="text-xs">Standar Pas 1 Hal (1.15)</SelectItem>
                                        <SelectItem value="1.2" className="text-xs">Sedang (1.2)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Card 4: Riwayat Pengujian */}
                        {history && history.length > 0 && (
                            <div className="rounded-lg border border-border bg-card p-4 space-y-3">
                                <div className="flex items-center justify-between border-b border-border pb-2">
                                    <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                        <History className="size-4" />
                                        Riwayat Pengujian
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setHistoryOpen(true)}
                                        className="h-6 text-[11px] px-1.5"
                                    >
                                        Lihat Semua ({history.length})
                                    </Button>
                                </div>
                                <div className="space-y-1.5">
                                    {history.slice(0, 4).map((h) => (
                                        <div
                                            key={h.id}
                                            onClick={() => {
                                                router.get(
                                                    lubeQualityRoutes.index.url(),
                                                    {
                                                        unit_id: unit.id,
                                                        machine_id: h.machine_id,
                                                        test_date: h.test_date,
                                                        record_id: h.id,
                                                    },
                                                    { preserveState: false }
                                                );
                                            }}
                                            className={`p-2 rounded cursor-pointer border text-xs transition-colors flex items-center justify-between ${
                                                record?.id === h.id
                                                    ? 'bg-primary/10 border-primary font-medium text-foreground'
                                                    : 'bg-muted/30 border-border hover:bg-muted/60 text-muted-foreground'
                                            }`}
                                        >
                                            <span className="font-mono">{h.test_date}</span>
                                            <span className="text-[11px]">Rev {h.revision || '00'}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* RIGHT COLUMN: Action Tabs & Form Inputs */}
                    <div className="flex-1 min-w-0 w-full space-y-4">
                        <div className="rounded-lg border border-border bg-card shadow-sm">
                            {/* Card Header with View Mode Tabs & Action Buttons */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4 bg-muted/10">
                                <div className="flex items-center gap-2">
                                    <div className="flex items-center rounded-lg border border-border bg-muted/40 p-1">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={viewTab === 'form' ? 'default' : 'ghost'}
                                            onClick={() => setViewTab('form')}
                                            className="h-7 text-xs gap-1.5"
                                        >
                                            <FileSpreadsheet className="size-3.5" />
                                            Form Pengukuran
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={viewTab === 'html' ? 'default' : 'ghost'}
                                            onClick={() => setViewTab('html')}
                                            className="h-7 text-xs gap-1.5"
                                        >
                                            <FileCode2 className="size-3.5" />
                                            Teks / Template HTML
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={viewTab === 'pdf' ? 'default' : 'ghost'}
                                            onClick={() => {
                                                setViewTab('pdf');
                                                setPreviewKey(Date.now());
                                            }}
                                            className="h-7 text-xs gap-1.5"
                                        >
                                            <Eye className="size-3.5" />
                                            Pratinjau Dokumen
                                        </Button>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleDownloadPdf}
                                        className="h-8 text-xs gap-1.5"
                                    >
                                        <Download className="size-3.5" />
                                        Unduh PDF
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handlePrintPdf}
                                        className="h-8 text-xs gap-1.5"
                                    >
                                        <Printer className="size-3.5" />
                                        Cetak PDF
                                    </Button>
                                    {can_write && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={handleSave}
                                            disabled={isSaving}
                                            className="h-8 text-xs gap-1.5 bg-primary text-primary-foreground shadow"
                                        >
                                            <Save className="size-3.5" />
                                            {isSaving ? 'Menyimpan...' : 'Simpan Formulir'}
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {/* TAB 1: FORM PENGUKURAN */}
                            {viewTab === 'form' && (
                                <div className="p-4 space-y-6">
                                    {/* Info Banner */}
                                    <div className="flex items-start gap-2.5 rounded-lg border border-blue-200 bg-blue-50/50 p-3 text-xs text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/20 dark:text-blue-200">
                                        <Info className="size-4 text-blue-600 shrink-0 mt-0.5" />
                                        <div>
                                            <span className="font-semibold">Format Standar Kualitas Pelumas:</span>{' '}
                                            Pengukuran parameter minyak pelumas mencakup TBN, Water Content, Viskositas, AW Additive, Kontaminan kimia, status alarm, foto sampel pelumas, analisa teknis, CBA, dan rekomendasi.
                                        </div>
                                    </div>

                                    {/* SECTION 1: PARAMETER TABLE */}
                                    <div className="rounded-lg border border-border p-3.5 space-y-3 bg-muted/5">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <h4 className="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                                                    <Fuel className="size-3.5 text-primary" />
                                                    Tabel Parameter Pengukuran Kualitas Pelumas
                                                </h4>
                                                <p className="text-[11px] text-muted-foreground">
                                                    Hasil analisis laboratorium atau oil test kit untuk sampel oli pelumas.
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleFillScanValues}
                                                    className="h-7 text-xs gap-1 text-primary border-primary/40 hover:bg-primary/5"
                                                >
                                                    <Sparkles className="size-3.5" />
                                                    Isi Data Scan
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleAddParameterRow}
                                                    className="h-7 text-xs gap-1"
                                                >
                                                    <Plus className="size-3.5" />
                                                    Tambah Baris
                                                </Button>
                                            </div>
                                        </div>

                                        <div className="overflow-x-auto border border-border rounded-lg bg-background">
                                            <table className="w-full text-xs border-collapse">
                                                <thead>
                                                    <tr className="bg-muted/60 border-b border-border text-foreground font-semibold">
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[75px]">
                                                            TANGGAL
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[65px]">
                                                            TBN<br />
                                                            <span className="text-[10px] font-normal text-muted-foreground">(mgKOH/g)</span>
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[75px]">
                                                            WATER CONTENT<br />
                                                            <span className="text-[10px] font-normal text-muted-foreground">(ppm)</span>
                                                        </th>
                                                        <th colSpan={2} className="p-1 text-center border-r border-border min-w-[96px]">
                                                            VISCOSITY (derajat)
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[65px]">
                                                            AW Additive<br />
                                                            <span className="text-[10px] font-normal text-muted-foreground">(%)</span>
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[60px]">
                                                            Glycol<br />
                                                            <span className="text-[10px] font-normal text-muted-foreground">(%)</span>
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[65px]">
                                                            Nitration<br />
                                                            <span className="text-[10px] font-normal text-muted-foreground">(abs/0.1mm)</span>
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[65px]">
                                                            Oxidation<br />
                                                            <span className="text-[10px] font-normal text-muted-foreground">(abs/0.1mm)</span>
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[60px]">
                                                            Soot<br />
                                                            <span className="text-[10px] font-normal text-muted-foreground">(%wt)</span>
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[65px]">
                                                            Sulfation<br />
                                                            <span className="text-[10px] font-normal text-muted-foreground">(abs/1m)</span>
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center border-r border-border min-w-[85px]">
                                                            KETERANGAN
                                                        </th>
                                                        <th rowSpan={2} className="p-1.5 text-center w-8">
                                                            #
                                                        </th>
                                                    </tr>
                                                    <tr className="bg-muted/40 border-b border-border text-foreground font-semibold">
                                                        <th className="p-1 text-center border-r border-border min-w-[48px]">40</th>
                                                        <th className="p-1 text-center border-r border-border min-w-[48px]">100</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {parameters.map((row, idx) => (
                                                        <tr key={row.id} className="border-b border-border hover:bg-muted/20">
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.tanggal}
                                                                    onChange={(e) => handleParameterChange(idx, 'tanggal', e.target.value)}
                                                                    placeholder="28-Aug-26"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.tbn}
                                                                    onChange={(e) => handleParameterChange(idx, 'tbn', e.target.value)}
                                                                    placeholder="26,90"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.water_content}
                                                                    onChange={(e) => handleParameterChange(idx, 'water_content', e.target.value)}
                                                                    placeholder="754,00"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.viscosity_40}
                                                                    onChange={(e) => handleParameterChange(idx, 'viscosity_40', e.target.value)}
                                                                    placeholder="-"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.viscosity_100}
                                                                    onChange={(e) => handleParameterChange(idx, 'viscosity_100', e.target.value)}
                                                                    placeholder="-"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.aw_additive}
                                                                    onChange={(e) => handleParameterChange(idx, 'aw_additive', e.target.value)}
                                                                    placeholder="157,00"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.glycol}
                                                                    onChange={(e) => handleParameterChange(idx, 'glycol', e.target.value)}
                                                                    placeholder="0,00"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.nitration}
                                                                    onChange={(e) => handleParameterChange(idx, 'nitration', e.target.value)}
                                                                    placeholder="0,00"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.oxidation}
                                                                    onChange={(e) => handleParameterChange(idx, 'oxidation', e.target.value)}
                                                                    placeholder="10,10"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.soot}
                                                                    onChange={(e) => handleParameterChange(idx, 'soot', e.target.value)}
                                                                    placeholder="0,19"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.sulfation}
                                                                    onChange={(e) => handleParameterChange(idx, 'sulfation', e.target.value)}
                                                                    placeholder="16,30"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 border-r border-border">
                                                                <Input
                                                                    value={row.keterangan}
                                                                    onChange={(e) => handleParameterChange(idx, 'keterangan', e.target.value)}
                                                                    placeholder="No Alarm"
                                                                    className="h-7 text-xs text-center px-1"
                                                                />
                                                            </td>
                                                            <td className="p-1 text-center">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() => handleRemoveParameterRow(idx)}
                                                                    disabled={parameters.length <= 1}
                                                                    className="size-6 text-muted-foreground hover:text-destructive"
                                                                >
                                                                    <Trash2 className="size-3" />
                                                                </Button>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {/* SECTION 2: STATUS & STANDARD */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Status */}
                                        <div className="rounded-lg border border-border p-3.5 space-y-2 bg-muted/5">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                                                    STATUS
                                                </Label>
                                                <span className="text-[10px] text-muted-foreground">Sinyal / Kondisi Alarm</span>
                                            </div>
                                            <textarea
                                                value={statusText}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setStatusText(e.target.value)}
                                                placeholder="- No Alarm Signal"
                                                rows={2}
                                                className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                            />
                                        </div>

                                        {/* Standard */}
                                        <div className="rounded-lg border border-border p-3.5 space-y-2 bg-muted/5">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                                                    STANDARD
                                                </Label>
                                                <span className="text-[10px] text-muted-foreground">Batas Ambang / Referensi</span>
                                            </div>
                                            <textarea
                                                value={standardText}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setStandardText(e.target.value)}
                                                placeholder="- Water Content : 2000 ppm"
                                                rows={2}
                                                className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                            />
                                        </div>
                                    </div>

                                    {/* SECTION 3: FOTO SAMPEL PELUMAS & ANALISA */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Foto Sampel Pelumas */}
                                        <div className="rounded-lg border border-border p-3.5 space-y-3 bg-muted/5">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 flex items-center gap-1.5">
                                                    <ImageIcon className="size-3.5" />
                                                    FOTO SAMPEL PELUMAS
                                                </Label>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setPhotoFile(null);
                                                            setRemovePhoto(false);
                                                            setPhotoPreview('/images/har/sample-lube-photo.png');
                                                            setPhotoCaption(
                                                                "28 Agu 2026 15:46:39\n-3°59'46,05188\"S 122°31'50,60101\"E\n154° SE\nKecamatan Wua-Wua\nKota Kendari\nSulawesi Tenggara\nAkurasi 4.1\nIndex number: 2494"
                                                            );
                                                            setPreviewKey(Date.now());
                                                        }}
                                                        className="h-6 text-[11px] px-2 text-primary hover:bg-primary/10 gap-1"
                                                        title="Gunakan foto scan asli alat uji oli"
                                                    >
                                                        <Sparkles className="size-3 text-amber-500" />
                                                        Foto Scan Asli
                                                    </Button>
                                                    <span className="text-[10px] text-muted-foreground">Maks 5MB</span>
                                                </div>
                                            </div>

                                            <div className="space-y-3">
                                                {photoPreview ? (
                                                    <div className="relative rounded-lg border border-border p-2 bg-background flex flex-col items-center">
                                                        <img
                                                            src={photoPreview}
                                                            alt="Foto Sampel"
                                                            className="max-h-48 max-w-full rounded object-contain border border-muted shadow-sm"
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={handleRemovePhoto}
                                                            className="mt-2 h-7 text-xs gap-1"
                                                        >
                                                            <Trash2 className="size-3" />
                                                            Hapus Foto
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <div
                                                        onClick={() => fileInputRef.current?.click()}
                                                        className="border-2 border-dashed border-border hover:border-primary rounded-lg p-6 text-center cursor-pointer transition-colors bg-background/60 hover:bg-muted/10 flex flex-col items-center justify-center gap-2"
                                                    >
                                                        <Upload className="size-6 text-muted-foreground" />
                                                        <div className="text-xs font-medium text-foreground">
                                                            Klik untuk memilih foto sampel pelumas
                                                        </div>
                                                        <div className="text-[11px] text-muted-foreground">
                                                            Format JPG, PNG, atau WEBP
                                                        </div>
                                                    </div>
                                                )}

                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept="image/*"
                                                    onChange={handlePhotoSelect}
                                                    className="hidden"
                                                />

                                                <div className="space-y-1">
                                                    <Label className="text-[11px]">Keterangan Foto / Timestamp / Koordinat</Label>
                                                    <textarea
                                                        value={photoCaption}
                                                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setPhotoCaption(e.target.value)}
                                                        placeholder="28 Agu 2026 15:33:56&#10;PLN NP UPDK Kendari&#10;Kendari, Sulawesi Tenggara"
                                                        rows={2}
                                                        className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {/* Analisa */}
                                        <div className="rounded-lg border border-border p-3.5 space-y-2 bg-muted/5 flex flex-col">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                                                    ANALISA
                                                </Label>
                                                <span className="text-[10px] text-muted-foreground">Hasil Pembacaan Teknis</span>
                                            </div>
                                            <textarea
                                                value={analisaText}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setAnalisaText(e.target.value)}
                                                placeholder="- All parameter normal&#10;- Alat ukur viskositas eror"
                                                rows={7}
                                                className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring flex-1"
                                            />
                                        </div>
                                    </div>

                                    {/* SECTION 4: CBA & REKOMENDASI */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* CBA */}
                                        <div className="rounded-lg border border-border p-3.5 space-y-2 bg-muted/5">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                                                    CBA
                                                </Label>
                                                <span className="text-[10px] text-muted-foreground">Condition Based Assessment</span>
                                            </div>
                                            <textarea
                                                value={cbaText}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setCbaText(e.target.value)}
                                                placeholder="N/A"
                                                rows={3}
                                                className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                            />
                                        </div>

                                        {/* Rekomendasi */}
                                        <div className="rounded-lg border border-border p-3.5 space-y-2 bg-muted/5">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                                                    REKOMENDASI
                                                </Label>
                                                <span className="text-[10px] text-muted-foreground">Tindak Lanjut Pemeliharaan</span>
                                            </div>
                                            <textarea
                                                value={rekomendasiText}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setRekomendasiText(e.target.value)}
                                                placeholder="N/A"
                                                rows={3}
                                                className="w-full rounded-md border border-input bg-background p-2.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* TAB 2: TEKS / TEMPLATE HTML */}
                            {viewTab === 'html' && (
                                <div className="p-4 space-y-4">
                                    <div className="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200">
                                        <Info className="size-4 text-amber-600 shrink-0 mt-0.5" />
                                        <div>
                                            <span className="font-semibold">Mode Teks / Template HTML:</span>{' '}
                                            Jika Anda beralih ke format HTML, tampilan cetak PDF akan menggunakan konten teks editor di bawah ini secara langsung menggantikan formulir tabel default.
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Label className="text-xs font-medium">Format Output:</Label>
                                            <Select
                                                value={formatMode}
                                                onValueChange={(val: 'form' | 'html') => setFormatMode(val)}
                                            >
                                                <SelectTrigger className="h-7 text-xs w-44">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="form" className="text-xs">
                                                        Form Terstruktur (Default)
                                                    </SelectItem>
                                                    <SelectItem value="html" className="text-xs">
                                                        Kustom Teks HTML
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setContentHtml(rendered_html || '')}
                                            className="h-7 text-xs gap-1"
                                        >
                                            <RotateCcw className="size-3" />
                                            Muat Ulang Template Standar
                                        </Button>
                                    </div>

                                    <div className="rounded-lg border border-border overflow-hidden">
                                        <RichTextEditor
                                            value={contentHtml}
                                            onChange={setContentHtml}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* TAB 3: PDF PREVIEW IFRAME */}
                            {viewTab === 'pdf' && (
                                <div className="p-4 flex flex-col gap-3">
                                    <div className="flex items-center justify-between bg-muted/40 p-2.5 rounded-lg border border-border">
                                        <div className="text-xs text-muted-foreground">
                                            Menampilkan pratinjau dokumen PDF secara real-time.
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => window.open(previewPdfUrl, '_blank')}
                                                className="h-7 text-xs gap-1.5"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                Buka Layar Penuh
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setPreviewKey(Date.now())}
                                                className="h-7 text-xs gap-1.5"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                Segarkan Pratinjau
                                            </Button>
                                        </div>
                                    </div>
                                    <div
                                        className="w-full rounded-lg border border-border overflow-hidden bg-white shadow-sm"
                                        style={{ height: '850px', minHeight: '850px' }}
                                    >
                                        <PdfPreviewFrame
                                            key={previewKey}
                                            className="w-full"
                                            src={`${previewPdfUrl}#toolbar=0&navpanes=0`}
                                            style={{ width: '100%', height: '100%', minHeight: '850px', border: 'none' }}
                                            title="PDF Preview"
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Bottom Card Bar */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 bg-muted/20 rounded-b-lg">
                                <div className="text-xs text-muted-foreground flex items-center gap-1.5">
                                    <Fuel className="size-3.5 text-primary" />
                                    Data disimpan per Unit, Mesin, dan Tanggal Pengukuran.
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetToStandard}
                                        className="h-8 text-xs gap-1.5"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Reset ke Standar
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setViewTab('pdf');
                                            setPreviewKey(Date.now());
                                        }}
                                        className="h-8 text-xs gap-1.5"
                                    >
                                        <Eye className="size-3.5" />
                                        Buka Pratinjau PDF
                                    </Button>
                                    {can_write && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={handleSave}
                                            disabled={isSaving}
                                            className="h-8 text-xs gap-1.5 bg-primary text-primary-foreground shadow"
                                        >
                                            <Check className="size-3.5" />
                                            {isSaving ? 'Menyimpan...' : 'Simpan Perubahan'}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* History Modal Dialog */}
            <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <History className="size-4 text-primary" />
                            Riwayat Pengukuran Kualitas Pelumas
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Daftar catatan formulir yang tersimpan pada {unit.name}.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-96 overflow-y-auto divide-y divide-border border rounded-md">
                        {history && history.length > 0 ? (
                            history.map((item) => (
                                <div
                                    key={item.id}
                                    className="p-3 flex items-center justify-between hover:bg-muted/40 transition-colors text-xs"
                                >
                                    <div>
                                        <div className="font-semibold text-foreground">
                                            Tanggal: {item.test_date}
                                        </div>
                                        <div className="text-[11px] text-muted-foreground font-mono">
                                            No. Dok: {item.document_number} (Rev: {item.revision || '00'}) • Mode: {item.format}
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setHistoryOpen(false);
                                            router.get(
                                                lubeQualityRoutes.index.url(),
                                                {
                                                    unit_id: unit.id,
                                                    machine_id: item.machine_id,
                                                    test_date: item.test_date,
                                                    record_id: item.id,
                                                },
                                                { preserveState: false }
                                            );
                                        }}
                                        className="h-7 text-xs"
                                    >
                                        Buka Formulir
                                    </Button>
                                </div>
                            ))
                        ) : (
                            <div className="p-4 text-center text-xs text-muted-foreground">
                                Belum ada riwayat formulir tersimpan.
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
