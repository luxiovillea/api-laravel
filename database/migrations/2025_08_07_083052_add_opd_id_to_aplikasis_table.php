<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// opd to apk
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('aplikasi', function (Blueprint $table) {
            $table->unsignedBigInteger('opd_id')->nullable()->after('id');
            $table->foreign('opd_id')->references('id')->on('opds')->onDelete('set null');
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