<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah dari 'aplikasis' ke 'aplikasi'
        Schema::table('aplikasi', function (Blueprint $table) {
            $table->foreignId('opd_id')->nullable()->after('id')->constrained('opds')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Ubah dari 'aplikasis' ke 'aplikasi'  
        Schema::table('aplikasi', function (Blueprint $table) {
            $table->dropForeign(['opd_id']);
            $table->dropColumn('opd_id');
        });
    }
};