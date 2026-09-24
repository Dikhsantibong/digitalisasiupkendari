<?php

namespace App\Services\K3;

use App\Models\Employee;
use App\Models\Unit;
use App\Support\K3FormulirRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Bagian bersama dokumen resmi Formulir K3 (kop PLN NP - MKP, No. Dokumen,
 * 3 penandatangan, layout halaman, mode editor teks) untuk pratinjau & PDF dompdf.
 * Isi tabel tiap formulir ada di view `k3/formulir/{form}/body.blade.php`.
 */
class K3FormulirDocumentBuilder
{
    /**
     * Kolom dokumen yang disimpan di tabel meta tiap formulir.
     */
    public const DOCUMENT_FIELDS = [
        'document_number', 'revision', 'effective_date',
        'manager_ul_id', 'manager_ul_name', 'manager_ul_title',
        'tl_k3_id', 'tl_k3_name', 'tl_k3_title',
        'staff_k3_id', 'staff_k3_name', 'staff_k3_title',
        'sign_place_date',
        'page_margin_top', 'page_margin_bottom', 'page_margin_left', 'page_margin_right', 'line_spacing',
        'format', 'content_html',
    ];

    /**
     * Kata kunci jabatan yang diurutkan paling atas pada pilihan penandatangan.
     */
    private const SIGNER_KEYWORDS = [
        'manager_ul' => ['manager', 'project leader', 'koordinator project'],
        'tl_k3' => ['team leader k3', 'tl k3', 'k3'],
        'staff_k3' => ['office k3', 'officer k3', 'koordinator k3', 'staf', 'staff', 'k3'],
    ];

    private const PAGE_OVERRIDES = ['page_margin_top', 'page_margin_bottom', 'page_margin_left', 'page_margin_right'];

    /**
     * @param  array{title: string, body_view: string, orientation: string, signers?: list<array{key: string, label: string, title: string, positions: list<string>}>, sign_place?: bool, title_with_period?: bool}  $form
     * @param  array<string, mixed>  $content  Data isi formulir (rows / sections) untuk body view.
     * @param  array<string, mixed>  $input  Nilai meta tersimpan / override dari request.
     * @return array<string, mixed>
     */
    public function buildData(Unit $unit, int $month, int $year, array $form, array $content, array $input = [], int $week = 0): array
    {
        $unit->loadMissing('serviceUnit');
        $serviceUnitName = $unit->serviceUnit?->name ?? $unit->name;
        $ulLabel = str_starts_with(strtoupper($serviceUnitName), 'UL') ? $serviceUnitName : 'UL '.$serviceUnitName;
        $periodLabel = K3FormulirRegistry::periodLabel($month, $year, $week);

        $data = [
            ...$content,
            'title' => $form['title'],
            'kop_title' => ($form['title_with_period'] ?? false) ? $form['title'].' Periode '.$periodLabel : $form['title'],
            'body_view' => $form['body_view'],
            'orientation' => $form['orientation'],
            'unit_name' => $unit->name,
            'ul_label' => $ulLabel,
            'period_label' => $periodLabel,
            'document_number' => $this->filled($input, 'document_number') ?? '-',
            'revision' => $this->filled($input, 'revision') ?? (string) config('k3.document.revision', '00'),
            'effective_date' => $this->filled($input, 'effective_date') ?? '-',
            'sign_place_date' => $this->filled($input, 'sign_place_date')
                ?? (($form['sign_place'] ?? false) ? K3FormulirRegistry::defaultSignPlaceDate($month, $year) : ''),
            'catatan' => (string) ($input['catatan'] ?? ''),
            'notes_label' => array_key_exists('notes_label', $form) ? $form['notes_label'] : 'Catatan / Rekomendasi',
            'signers' => [],

            'page_margin_top' => (int) ($input['page_margin_top'] ?? 10),
            'page_margin_bottom' => (int) ($input['page_margin_bottom'] ?? 10),
            'page_margin_left' => (int) ($input['page_margin_left'] ?? 12),
            'page_margin_right' => (int) ($input['page_margin_right'] ?? 12),
            'line_spacing' => (string) ($input['line_spacing'] ?? '1.15'),

            'logo_pln' => $this->fileToBase64(public_path('logo/sidebar-logo.png')),
            'logo_mkp' => $this->fileToBase64(public_path('logo/mkp.jpg')),
        ];

        foreach ($form['signers'] ?? K3FormulirRegistry::defaultSigners() as $signer) {
            $key = $signer['key'];
            $employee = match (true) {
                ! empty($input[$key.'_id']) => Employee::find($input[$key.'_id']),
                $signer['positions'] !== [] => $this->unitEmployeeByPosition($unit, $signer['positions']),
                $key === 'manager_ul' => $unit->manager(),
                default => null,
            };
            $defaultTitle = $signer['title'] !== '' ? $signer['title'] : 'Manager '.$ulLabel;

            $data[$key.'_id'] = $employee?->id;
            $data[$key.'_name'] = $this->filled($input, $key.'_name') ?? ($employee?->name ?? $defaultTitle);
            $data[$key.'_title'] = $this->filled($input, $key.'_title') ?? $defaultTitle;
            $data[$key.'_signature'] = $this->signatureBase64($employee);
            $data['signers'][] = ['key' => $key, 'label' => $signer['label']];
        }

        return $data;
    }

    /**
     * Dokumen HTML utuh untuk dompdf; `$contentHtml` = isi hasil Editor Teks (HTML).
     *
     * @param  array<string, mixed>  $data
     */
    public function renderHtml(array $data, ?string $contentHtml = null): string
    {
        return view('k3.formulir.pdf', ['data' => $data, 'contentHtml' => $contentHtml])->render();
    }

    /**
     * Isi <body> saja, untuk Editor Teks (HTML).
     *
     * @param  array<string, mixed>  $data
     */
    public function renderBody(array $data): string
    {
        return view($data['body_view'], ['data' => $data])->render();
    }

    /**
     * CSS dokumen untuk ditampilkan di permukaan Editor Teks.
     *
     * @param  array<string, mixed>  $data
     */
    public function renderStyles(array $data): string
    {
        return view('k3.formulir.partials.styles', ['data' => $data])->render();
    }

    /**
     * Nilai meta tersimpan sebagai input builder (kosong bila belum pernah disimpan).
     *
     * @return array<string, mixed>
     */
    public function metaInput(?Model $meta): array
    {
        return $meta?->only(['catatan', ...self::DOCUMENT_FIELDS]) ?? [];
    }

    /**
     * Override layout halaman dari query string pratinjau PDF.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function applyPageOverrides(array $input, Request $request): array
    {
        foreach (self::PAGE_OVERRIDES as $key) {
            if ($request->filled($key)) {
                $input[$key] = max(0, min(50, $request->integer($key)));
            }
        }
        if (in_array($request->input('line_spacing'), ['1.0', '1.15', '1.5'], true)) {
            $input['line_spacing'] = $request->input('line_spacing');
        }

        return $input;
    }

    /**
     * Aturan validasi kolom dokumen (semua opsional agar klien lama tetap valid).
     *
     * @return array<string, list<string>>
     */
    public static function documentRules(): array
    {
        return [
            'document_number' => ['nullable', 'string', 'max:100'],
            'revision' => ['nullable', 'string', 'max:20'],
            'effective_date' => ['nullable', 'string', 'max:100'],
            'manager_ul_id' => ['nullable', 'integer', 'exists:employees,id'],
            'manager_ul_name' => ['nullable', 'string', 'max:150'],
            'manager_ul_title' => ['nullable', 'string', 'max:150'],
            'tl_k3_id' => ['nullable', 'integer', 'exists:employees,id'],
            'tl_k3_name' => ['nullable', 'string', 'max:150'],
            'tl_k3_title' => ['nullable', 'string', 'max:150'],
            'staff_k3_id' => ['nullable', 'integer', 'exists:employees,id'],
            'staff_k3_name' => ['nullable', 'string', 'max:150'],
            'staff_k3_title' => ['nullable', 'string', 'max:150'],
            'sign_place_date' => ['nullable', 'string', 'max:150'],
            'page_margin_top' => ['nullable', 'integer', 'between:0,50'],
            'page_margin_bottom' => ['nullable', 'integer', 'between:0,50'],
            'page_margin_left' => ['nullable', 'integer', 'between:0,50'],
            'page_margin_right' => ['nullable', 'integer', 'between:0,50'],
            'line_spacing' => ['nullable', 'in:1.0,1.15,1.5'],
            'format' => ['nullable', 'in:form,html'],
            'content_html' => ['nullable', 'string'],
        ];
    }

    /**
     * Atribut meta dari request tervalidasi — hanya kolom yang benar-benar dikirim.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function metaAttributes(array $validated): array
    {
        $attributes = array_intersect_key($validated, array_flip(self::DOCUMENT_FIELDS));

        if (array_key_exists('format', $attributes)) {
            $attributes['format'] ??= 'form';
            if ($attributes['format'] !== 'html') {
                $attributes['content_html'] = null;
            }
        }

        if (isset($validated['meta']) && is_array($validated['meta'])) {
            $attributes['catatan'] = $validated['meta']['catatan'] ?? null;
        }

        return $attributes;
    }

    /**
     * Props Inertia bersama: pengaturan dokumen, isi editor HTML, opsi penandatangan & URL PDF.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function pageProps(Unit $unit, array $data, ?Model $meta, string $pdfUrl): array
    {
        $generatedHtml = $this->renderBody($data);
        $savedHtml = $meta?->getAttribute('format') === 'html' ? $meta->getAttribute('content_html') : null;

        return [
            'document' => [
                'format' => $savedHtml ? 'html' : 'form',
                ...collect(self::DOCUMENT_FIELDS)
                    ->reject(fn (string $field): bool => in_array($field, ['format', 'content_html'], true))
                    ->mapWithKeys(fn (string $field): array => [$field => $data[$field] ?? (str_ends_with($field, '_id') ? null : '')])
                    ->all(),
            ],
            'signers' => $data['signers'],
            'rendered_html' => $savedHtml ?: $generatedHtml,
            'generated_html' => $generatedHtml,
            'document_styles' => $this->renderStyles($data),
            'signer_options' => collect($data['signers'])->mapWithKeys(fn (array $signer): array => [
                $signer['key'] => $this->signerOptions($unit, self::SIGNER_KEYWORDS[$signer['key']] ?? []),
            ])->all(),
            'pdf_url' => $pdfUrl,
        ];
    }

    /**
     * Pilihan penandatangan: pegawai aktif unit / UL, jabatan terkait diurutkan di atas.
     *
     * @param  list<string>  $preferredKeywords
     * @return list<array{id: int, name: string, nip: string|null, position: string|null, has_signature: bool}>
     */
    public function signerOptions(Unit $unit, array $preferredKeywords): array
    {
        $bindings = array_map(fn (string $keyword): string => '%'.strtolower($keyword).'%', $preferredKeywords);
        $priority = implode(' OR ', array_fill(0, count($bindings), 'LOWER(position) LIKE ?'));

        return Employee::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($unit): void {
                $query->where('unit_id', $unit->id);
                if ($unit->service_unit_id) {
                    $query->orWhere('service_unit_id', $unit->service_unit_id);
                }
            })
            ->when($priority !== '', fn (Builder $query) => $query->orderByRaw("CASE WHEN {$priority} THEN 0 ELSE 1 END", $bindings))
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'nip' => $employee->nip,
                'position' => $employee->position,
                'has_signature' => ! empty($employee->signature_path),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $positions
     */
    private function unitEmployeeByPosition(Unit $unit, array $positions): ?Employee
    {
        return $unit->employees()
            ->where('is_active', true)
            ->whereIn('position', $positions)
            ->orderByRaw('CASE WHEN position = ? THEN 0 ELSE 1 END', [$positions[0]])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function filled(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function signatureBase64(?Employee $employee): ?string
    {
        if (! $employee || empty($employee->signature_path) || ! Storage::disk('public')->exists($employee->signature_path)) {
            return null;
        }

        return $this->fileToBase64(Storage::disk('public')->path($employee->signature_path));
    }

    private function fileToBase64(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return null;
        }

        $mime = match (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }
}
