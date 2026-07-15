<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class MaterialRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_request_id',
        'item_code',
        'item_name',
        'specification',
        'qty',
        'unit',
        'item_status',
        'monthly_usage',
        'stock_on_hand',
        'purpose',
    ];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }
}
