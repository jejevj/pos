<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Station extends Model
{
    use SoftDeletes;

    protected $table = 'stations';

    protected $fillable = ['nama', 'deskripsi', 'warna', 'icon', 'is_active', 'urutan'];

    protected $casts = ['is_active' => 'boolean'];

    public function menu()
    {
        return $this->hasMany(MenuOutlet::class, 'station_id');
    }
}
