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
            $table->id();
            $table->string('nama_aplikasi');
            $table->string('key_aplikasi')->unique(); 
            $table->string('property_id'); 
            $table->string('page_path_filter')->default('/'); 
            $table->text('deskripsi')->nullable();
            $table->boolean(column: 'is_active')->default(true);
            $table->json('konfigurasi_tambahan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplikasi');
    }
};