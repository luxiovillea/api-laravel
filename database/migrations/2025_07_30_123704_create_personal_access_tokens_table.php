<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// aplikasi
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplikasi', function (Blueprint $table) {
            $table->id(); // Kolom id otomatis (primary key, auto increment)
            $table->string('nama_aplikasi');
            $table->string('key_aplikasi')->unique(); 
            $table->string('property_id'); 
            $table->string('page_path_filter')->default('/'); 
            $table->text('deskripsi')->nullable();
            $table->boolean(column: 'is_active')->default(true);
            $table->json('konfigurasi_tambahan')->nullable(); // Kolom JSON untuk simpan setting tambahan, boleh kosong
            $table->timestamps(); // Kolom created_at dan updated_at otomatis
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplikasi');
        // Hapus tabel "aplikasi" kalau rollback
    }
};