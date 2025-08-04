<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Aplikasi;

class AplikasiSeeder extends Seeder
{
    /**
     * Jalankan database seeds.
     */
    public function run(): void
    {
        $aplikasiData = [
            [
                'nama_aplikasi' => 'Aplikasi Lapak Kami',
                'key_aplikasi' => 'lapakami',
                'property_id' => env('GA_PROPERTY_ID', '372607757'),
                'page_path_filter' => '/',
                'deskripsi' => 'Aplikasi untuk laporan pengaduan masyarakat',
                'is_active' => true,
                'konfigurasi_tambahan' => [
                    'custom_events' => ['form_submit', 'report_view'],
                    'conversion_goals' => ['contact_form', 'download_report']
                ],
            ],
        ];

        foreach ($aplikasiData as $data) {
            Aplikasi::updateOrCreate(
                ['key_aplikasi' => $data['key_aplikasi']],
                $data
            );
        }
    }
}