<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aplikasi extends Model
{
    use HasFactory;

    protected $table = 'aplikasi';

    protected $fillable = [
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

    // Scope untuk aplikasi yang aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessor untuk mendapatkan konfigurasi aplikasi dalam format yang dibutuhkan Analytics Controller
    public function getAnalyticsConfigAttribute()
    {
        return [
            'name' => $this->nama_aplikasi,
            'page_path_filter' => $this->page_path_filter,
            'property_id' => $this->property_id,
            'additional_config' => $this->konfigurasi_tambahan ?? [],
        ];
    }
}