<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingTime extends Model
{
    use HasFactory;

    protected $table = 'shippingTime';

    protected $fillable = [
        'productId',
        'Destination',
        'EstimatedTimeDays',
    ];

    public function product(): HasMany
    {
        return $this->hasMany(Product::class, 'productId');
    }
}
