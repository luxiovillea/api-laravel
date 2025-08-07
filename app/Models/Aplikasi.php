<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aplikasi extends Model
{
    use HasFactory;
    
    // TAMBAHKAN INI - specify nama tabel yang benar
    protected $table = 'aplikasi';

    protected $fillable = [
        'opd_id',
        'nama_aplikasi',
        'key_aplikasi',  
        'property_id',
        'page_path_filter',
        'deskripsi',
        'is_active',
        'konfigurasi_tambahan',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'konfigurasi_tambahan' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
   
    public function opd()
    {
        return $this->belongsTo(Opd::class);
    }
}