<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPoLine extends Model
{
    protected $fillable = ['material_request_item_id', 'qty', 'nomor_po', 'tgl_po', 'expected_date', 'tanggal_disetujui_direksi', 'user_id'];

    public function item()
    {
        return $this->belongsTo(MaterialRequestItem::class, 'material_request_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}