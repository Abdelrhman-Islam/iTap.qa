<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NfcInventory extends Model
{
    use HasFactory;

    protected $primaryKey = 'tag_id';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tag_id',
        'batch_id',
        'secret_key',
        'status', // IN_STOCK, ASSIGNED, BLACKLISTED
        'deliverd',
        'nfc_assigned_to_card',
    ];
}