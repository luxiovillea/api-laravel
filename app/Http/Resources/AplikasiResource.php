<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AplikasiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            
            'kode_opd' => $this->opd?->kode_opd,
            'nama_opd' => $this->opd?->nama,
            'akronim_opd' => $this->opd?->akronim,

            'nama_aplikasi' => $this->nama_aplikasi,
            'key_aplikasi' => $this->key_aplikasi,
            'property_id' => $this->property_id,
            'page_path_filter' => $this->page_path_filter,
            'deskripsi' => $this->deskripsi,
            'is_active' => $this->is_active,
            'konfigurasi_tambahan' => $this->konfigurasi_tambahan,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}