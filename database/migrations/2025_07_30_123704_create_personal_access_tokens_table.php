<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('aplikasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama_aplikasi');
            $table->string('key_aplikasi')->unique(); // lapakami, dashboard, etc
            $table->string('property_id'); // GA4 Property ID
            $table->string('page_path_filter')->default('/'); // Filter untuk path halaman
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('konfigurasi_tambahan')->nullable(); // Untuk konfigurasi khusus
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aplikasi');
    }
};