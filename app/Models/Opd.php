<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opd extends Model
{
    use HasFactory;
    
    protected $table = 'opds';

    protected $fillable = [
        'kode_opd',
        'nama',
        'akronim',
    ];

    // Relasi dengan aplikasi
    public function aplikasis()
    {
        return $this->hasMany(Aplikasi::class);
    }

    // Scope untuk mencari berdasarkan kode
    public function scopeByKode($query, $kode)
    {
        return $query->where('kode_opd', $kode);
    }
}