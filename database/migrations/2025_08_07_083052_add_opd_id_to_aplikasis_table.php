<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// opd to apk
return new class extends Migration
{

public function up(): void
    {   // edit tabel aplikasi yang sudah ada
        // Blueprint adalah kelas bawaan Laravel yang dipakai untuk mendefinisikan struktur tabel saat membuat migration.
        Schema::table('aplikasi', function (Blueprint $table) {
            // tambah kolom opd_id untuk menyimpan id opd
            $table->unsignedBigInteger('opd_id')->nullable()->after('id');

            // Definisikan foreign key ke tabel opds
            $table->foreign('opd_id')
                ->references('id')      // foreign key menunjuk ke kolom id di tabel opds
                ->on('opds')
                ->onDelete('set null'); // kalau data diopds dihapus, isi opd_id di tabel aplikasi jadi Null
        });
    }


    public function down(): void
    {
        Schema::table('aplikasi', function (Blueprint $table) {
            $table->dropForeign(['opd_id']);
            $table->dropColumn('opd_id');
        });
    }
};