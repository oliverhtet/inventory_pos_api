<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingCharge extends Model
{
    use HasFactory;

    protected $table = 'shippingCharge';

    protected $fillable = [
        'productId',
        'Destination',
        'charge',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId');
    }
    
}
