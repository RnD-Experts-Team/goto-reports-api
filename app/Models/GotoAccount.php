<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GotoAccount extends Model
{
    protected $table = 'goto_accounts';
    protected $primaryKey = 'account_key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'account_key',
        'organization_id',
        'name',
        'name_resolved_at',
    ];

    protected $casts = [
        'name_resolved_at' => 'datetime',
    ];
}
