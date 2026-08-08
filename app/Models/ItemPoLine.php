<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPoLine extends Model
{
    protected $fillable = ['material_request_item_id', 'qty', 'nomor_po', 'user_id'];

    public function item()
    {
        return $this->belongsTo(MaterialRequestItem::class, 'material_request_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}