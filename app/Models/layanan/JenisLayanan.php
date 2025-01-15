<?php

namespace App\Models\layanan;

use Illuminate\Database\Eloquent\Model;

class JenisLayanan extends Model
{
    protected $table = 'duk_jenis_pelayanan';
    
    protected $fillable = [
        'nama',
        'deskripsi'
    ];

    public function pelayanan()
    {
        return $this->hasMany(Pelayanan::class);
    }
} 