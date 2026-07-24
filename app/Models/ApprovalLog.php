<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalLog extends Model
{
    protected $fillable = [
        'material_request_id',
        'user_id',
        'role',
        'action',
        'notes',
    ];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
