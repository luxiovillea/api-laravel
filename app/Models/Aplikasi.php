<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aplikasi extends Model
{
    use HasFactory; // Aktifkan kemampuan bikin data dummy
    
    protected $table = 'aplikasi';

    protected $fillable = [ // Daftar kolom yang boleh diisi sekaligus lewat create() atau update()
        'opd_id',
        'nama_aplikasi',
        'key_aplikasi',  
        'property_id',
        'page_path_filter',
        'deskripsi',
        'is_active',
        'konfigurasi_tambahan',
    ];

    protected $casts = [  // Mengubah tipe data otomatis saat diambil dari database
        'is_active' => 'boolean', // is_active otomatis jadi true/false, bukan angka 0/1
        'konfigurasi_tambahan' => 'array', // kolom JSON otomatis berubah jadi array PHP
    ];

    public function scopeActive($query)  // Ambil data yang aktif saja
    {
        return $query->where('is_active', true);
    }
   
    public function opd()  // Relasi: setiap aplikasi punya satu OPD
    {
        return $this->belongsTo(Opd::class);
    }
}