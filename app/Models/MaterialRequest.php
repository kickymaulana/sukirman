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
        'manager_id',
        'direksi_id',
        'type',
        'factory',
        'allocation',
        'status_pembelian',
        'status_workflow',
        'revision_notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function direksi()
    {
        return $this->belongsTo(User::class, 'direksi_id');
    }

    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(ApprovalLog::class);
    }
}
