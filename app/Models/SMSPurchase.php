<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SMSPurchase extends Model
{
    use HasFactory;

    protected $table = 'smsPurchase';

    protected $fillable = [
        'purchaseTotal',
        'sendTotal',
    ];
}
