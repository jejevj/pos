<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriMenu extends Model
{
    use SoftDeletes;

    protected $table = 'kategori_menu';

    protected $fillable = ['nama', 'deskripsi', 'urutan', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function menu()
    {
        return $this->hasMany(MenuOutlet::class, 'kategori_id');
    }
}
