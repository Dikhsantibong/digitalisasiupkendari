<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class K3KondisiK3Test extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_authorized_user_can_view_kondisi_k3_page(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $response = $this->actingAs($user)->get(route('k3.input.kondisi-k3.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('k3/input/kondisi-k3')
            ->has('rows')
            ->has('options')
            ->where('filters.unit_id', $unit->id)
            ->where('filters.month', 8)
            ->where('filters.year', 2026)
        );
    }

    public function test_authorized_user_can_save_kondisi_k3_data(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [
                [
                    'no_urut' => '1',
                    'periode' => '05/08/2026',
                    'kategori' => 'Unsafe Action',
                    'temuan' => 'Pekerja tidak menggunakan helm saat berada di area workshop',
                    'kondisi' => 'Terdapat aktivitas pengangkatan beban di dekat area',
                    'tindak_lanjut' => 'Diberikan teguran lisan dan langsung dipasangkan helm safety',
                    'rekomendasi' => 'Sosialisasi kepatuhan APD saat safety talk pagi',
                    'lokasi' => 'Ruang Workshop',
                    'keterangan' => 'Closed',
                    'eviden_sebelum' => 'https://example.com/foto1.jpg',
                    'eviden_sesudah' => 'https://example.com/foto2.jpg',
                    'sort_order' => 0,
                ],
                [
                    'no_urut' => '2',
                    'periode' => '12/08/2026',
                    'kategori' => 'Unsafe Condition',
                    'temuan' => 'Kabel pompa transfer BBM terkelupas',
                    'kondisi' => 'Berpotensi bahaya korsleting dan percikan api dekat tangki',
                    'tindak_lanjut' => 'Pompa dimatikan sementara dan diisolasi',
                    'rekomendasi' => 'Penggantian kabel power berisolasi ganda',
                    'lokasi' => 'Area Tangki BBM',
                    'keterangan' => 'On Progress',
                    'eviden_sebelum' => null,
                    'eviden_sesudah' => null,
                    'sort_order' => 1,
                ],
            ],
            'meta' => [
                'catatan' => 'Semua temuan bulan Agustus telah ditindaklanjuti secara bertahap.',
            ],
        ];

        $response = $this->actingAs($user)->post(route('k3.input.kondisi-k3.store'), $payload);

        $response->assertRedirect(route('k3.input.kondisi-k3.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $this->assertDatabaseHas('k3_kondisi_k3s', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'kategori' => 'Unsafe Action',
            'temuan' => 'Pekerja tidak menggunakan helm saat berada di area workshop',
            'keterangan' => 'Closed',
        ]);

        $this->assertDatabaseHas('k3_kondisi_k3s', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'kategori' => 'Unsafe Condition',
            'temuan' => 'Kabel pompa transfer BBM terkelupas',
            'keterangan' => 'On Progress',
        ]);

        $this->assertDatabaseHas('k3_kondisi_k3_meta', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'catatan' => 'Semua temuan bulan Agustus telah ditindaklanjuti secara bertahap.',
        ]);
    }

    public function test_authorized_user_can_upload_eviden_image(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $file = UploadedFile::fake()->image('eviden_temuan.jpg', 600, 400);

        $response = $this->actingAs($user)->postJson(route('k3.input.kondisi-k3.upload'), [
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['url', 'path']);

        $path = $response->json('path');
        Storage::disk('public')->assertExists($path);
    }
}
