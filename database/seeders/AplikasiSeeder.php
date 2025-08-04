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
                'nama_aplikasi' => 'Aplikasi Lapakami',
                'key_aplikasi' => 'lapakami',
                'property_id' => env('GA_PROPERTY_ID', '123456789'), // Ganti dengan Property ID yang sebenarnya
                'page_path_filter' => '/',
                'deskripsi' => 'Aplikasi untuk laporan pengaduan masyarakat',
                'is_active' => true,
                'konfigurasi_tambahan' => [
                    'custom_events' => ['form_submit', 'report_view'],
                    'conversion_goals' => ['contact_form', 'download_report']
                ],
            ],
            [
                'nama_aplikasi' => 'Dashboard Admin',
                'key_aplikasi' => 'dashboard_admin',
                'property_id' => '987654321', // Ganti dengan Property ID yang sebenarnya
                'page_path_filter' => '/admin',
                'deskripsi' => 'Dashboard untuk administrator sistem',
                'is_active' => true,
                'konfigurasi_tambahan' => [
                    'custom_events' => ['login', 'logout', 'admin_action'],
                    'restricted_access' => true
                ],
            ],
            [
                'nama_aplikasi' => 'Portal Publik',
                'key_aplikasi' => 'portal_publik',
                'property_id' => '456789123', // Ganti dengan Property ID yang sebenarnya
                'page_path_filter' => '/public',
                'deskripsi' => 'Portal informasi publik',
                'is_active' => true,
                'konfigurasi_tambahan' => [
                    'custom_events' => ['page_view', 'document_download', 'search'],
                    'public_access' => true
                ],
            ],
            [
                'nama_aplikasi' => 'Aplikasi Mobile',
                'key_aplikasi' => 'mobile_app',
                'property_id' => '789123456', // Ganti dengan Property ID yang sebenarnya
                'page_path_filter' => '/mobile',
                'deskripsi' => 'Aplikasi mobile untuk akses cepat',
                'is_active' => false, // Tidak aktif sebagai contoh
                'konfigurasi_tambahan' => [
                    'custom_events' => ['app_launch', 'feature_use', 'notification_click'],
                    'platform' => 'mobile'
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