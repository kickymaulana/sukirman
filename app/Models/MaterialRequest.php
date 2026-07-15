<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class);
    }
}
