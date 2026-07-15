<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialRequestItem extends Model
{
    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }
}
