<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'user_profile';
    protected $fillable = [
        'user_id',
        'nik',
        'nama_lengkap',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'alamat',
        'no_hp',
        'foto',
        'status_pernikahan',
        'pekerjaan',
        'kewarganegaraan',
        'agama',
        'status_verifikasi',
        'keterangan_verifikasi'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFotoUrlAttribute()
    {
        return url('storage/profile/' . $this->foto);
    }
}
