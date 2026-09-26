<?php

namespace Tests\Feature\K3;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\K3MetodePengujianPeralatanMeta;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class MetodePengujianPeralatanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_authorized_user_can_view_metode_pengujian_page(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $response = $this->actingAs($user)->get(route('k3.formulir.metode-pengujian.index', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('k3/formulir/metode-pengujian/index')
            ->has('rows', 4) // 4 default template rows
            ->has('options')
            ->has('meta')
            ->where('filters.unit_id', $unit->id)
            ->where('filters.month', 9)
            ->where('filters.year', 2026)
        );
    }

    public function test_authorized_user_can_save_metode_pengujian_data(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
            'rows' => [
                [
                    'no_urut' => '1',
                    'nama_peralatan' => 'Crane',
                    'no_pengesahan' => '-',
                    'nama_kategori_alat' => '-',
                    'uji_visual' => '-',
                    'uji_fungsi' => '-',
                    'uji_beban' => '-',
                    'uji_hydro' => '-',
                    'ndt' => '-',
                    'uji_ultrasonic_thickness' => '-',
                    'uji_ketahanan' => '-',
                    'sertifikasi_terakhir' => '-',
                    'sertifikasi_ulang' => '-',
                    'keterangan' => 'Tidak ada crane',
                    'sort_order' => 0,
                ],
                [
                    'no_urut' => '2',
                    'nama_peralatan' => 'Tangki Timbun',
                    'no_pengesahan' => '500/123/DISNAKER',
                    'nama_kategori_alat' => 'Tangki 25 KL Containerized',
                    'uji_visual' => 'Memenuhi',
                    'uji_fungsi' => 'Memenuhi',
                    'uji_beban' => 'Memenuhi',
                    'uji_hydro' => 'Memenuhi',
                    'ndt' => 'Memenuhi',
                    'uji_ultrasonic_thickness' => 'Memenuhi',
                    'uji_ketahanan' => 'Memenuhi',
                    'sertifikasi_terakhir' => '2024-01-10',
                    'sertifikasi_ulang' => '2027-01-10',
                    'keterangan' => 'Kondisi baik dan laik operasi',
                    'sort_order' => 1,
                ],
            ],
            'meta' => [
                'catatan' => 'Semua pengujian telah memenuhi standar K3.',
            ],
        ];

        $response = $this->actingAs($user)->post(route('k3.formulir.metode-pengujian.store'), $payload);

        $response->assertRedirect(route('k3.formulir.metode-pengujian.index', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
        ]));

        $this->assertDatabaseHas('k3_metode_pengujian_peralatans', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 9,
            'nama_peralatan' => 'Crane',
            'keterangan' => 'Tidak ada crane',
        ]);

        $this->assertDatabaseHas('k3_metode_pengujian_peralatans', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 9,
            'nama_peralatan' => 'Tangki Timbun',
            'no_pengesahan' => '500/123/DISNAKER',
            'uji_visual' => 'Memenuhi',
        ]);

        $this->assertDatabaseHas('k3_metode_pengujian_peralatan_meta', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 9,
            'catatan' => 'Semua pengujian telah memenuhi standar K3.',
        ]);
    }

    public function test_unauthorized_user_cannot_access(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('k3.formulir.metode-pengujian.index', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
        ]));

        $response->assertForbidden();
    }

    public function test_page_includes_document_settings_with_default_signers(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);
        $teamLeader = Employee::factory()->forUnit($unit)->create([
            'name' => 'Budi TL K3',
            'position' => EmployeePosition::TeamLeaderK3->value,
        ]);

        $response = $this->actingAs($user)->get(route('k3.formulir.metode-pengujian.index', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('k3/formulir/metode-pengujian/index')
            ->where('document.format', 'form')
            ->where('document.tl_k3_id', $teamLeader->id)
            ->where('document.tl_k3_name', 'Budi TL K3')
            ->where('document.page_margin_top', 10)
            ->has('signers', 3)
            ->has('signer_options.tl_k3')
            ->has('history', 0)
            ->where('rendered_html', fn (string $html): bool => str_contains($html, 'FORMULIR METODE PENGUJIAN PERALATAN'))
            ->where('pdf_url', fn (string $url): bool => str_contains($url, '/k3/formulir/metode-pengujian/pdf'))
        );
    }

    public function test_saving_document_settings_persists_meta_and_html_mode(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->post(route('k3.formulir.metode-pengujian.store'), [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
            'rows' => [['nama_peralatan' => 'Crane', 'uji_visual' => 'Memenuhi']],
            'document_number' => 'SMT-FM-AK3-99',
            'revision' => '01',
            'tl_k3_name' => 'Nama TL',
            'page_margin_top' => 15,
            'line_spacing' => '1.5',
            'format' => 'html',
            'content_html' => '<p>Isi dokumen disunting</p>',
        ])->assertRedirect();

        $this->assertDatabaseHas('k3_metode_pengujian_peralatan_meta', [
            'unit_id' => $unit->id,
            'document_number' => 'SMT-FM-AK3-99',
            'revision' => '01',
            'tl_k3_name' => 'Nama TL',
            'page_margin_top' => 15,
            'line_spacing' => '1.5',
            'format' => 'html',
            'content_html' => '<p>Isi dokumen disunting</p>',
        ]);

        $this->actingAs($user)->get(route('k3.formulir.metode-pengujian.index', [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
        ]))->assertInertia(fn ($page) => $page
            ->where('document.format', 'html')
            ->where('document.document_number', 'SMT-FM-AK3-99')
            ->where('rendered_html', '<p>Isi dokumen disunting</p>')
            ->has('history', 1)
        );
    }

    public function test_switching_back_to_form_mode_clears_edited_html(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);
        $payload = [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
            'rows' => [['nama_peralatan' => 'Crane']],
        ];

        $this->actingAs($user)->post(route('k3.formulir.metode-pengujian.store'), [...$payload, 'format' => 'html', 'content_html' => '<p>x</p>']);
        $this->actingAs($user)->post(route('k3.formulir.metode-pengujian.store'), [...$payload, 'format' => 'form', 'content_html' => '<p>x</p>']);

        $meta = K3MetodePengujianPeralatanMeta::query()->where('unit_id', $unit->id)->sole();
        $this->assertSame('form', $meta->format);
        $this->assertNull($meta->content_html);
    }

    public function test_invalid_document_settings_are_rejected(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->post(route('k3.formulir.metode-pengujian.store'), [
            'unit_id' => $unit->id,
            'month' => 9,
            'year' => 2026,
            'rows' => [['nama_peralatan' => 'Crane']],
            'page_margin_top' => 80,
            'line_spacing' => '3',
            'format' => 'docx',
        ])->assertSessionHasErrors(['page_margin_top', 'line_spacing', 'format']);
    }

    public function test_pdf_is_rendered_inline_and_as_download(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);
        $params = ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026, 'page_margin_top' => 20];

        $inline = $this->actingAs($user)->get(route('k3.formulir.metode-pengujian.pdf', $params));
        $inline->assertOk();
        $inline->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $inline->headers->get('Content-Disposition'));

        $download = $this->actingAs($user)->get(route('k3.formulir.metode-pengujian.pdf', [...$params, 'download' => 1]));
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
    }

    public function test_pdf_requires_permission(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('k3.formulir.metode-pengujian.pdf', ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026]))
            ->assertForbidden();
    }
}
