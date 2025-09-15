<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// opd
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opds', function (Blueprint $table) {
            $table->id(); 
            $table->string('kode_opd')->unique(); 
            $table->string('nama');
            $table->string('akronim');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opds');
    }
};