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
        'qty_tersedia',
        'keterangan_gudang',
        'unit',
        'item_status',
        'monthly_usage',
        'stock_on_hand',
        'purpose',
        'foto',
        'direksi_decision',
        'direksi_notes',
        'input_accurate',
        'departemen_id',
    ];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function departemen()
    {
        return $this->belongsTo(\App\Models\Departemen::class, 'departemen_id');
    }

    public function item_po_lines()
    {
        return $this->hasMany(\App\Models\ItemPoLine::class, 'material_request_item_id');
    }
}
