<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'mr_number',
        'user_id',
        'type',
        'factory',
        'allocation',
        'status_pembelian',
        'status_workflow',
    ];
    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class);
    }
}
