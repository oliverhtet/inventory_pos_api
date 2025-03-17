<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnCartOrder extends Model
{
    use HasFactory;
    protected $primaryKey = 'id';
    protected $table = 'returnCartOrder';

    protected $fillable = [
        'cartOrderId',
        'date',
        'totalAmount',
        'totalVatAmount',
        'totalDiscountAmount',
        'note',
        'couponAmount',
        'returnType',
        'returnCartOrderStatus'
    ];

    public function cartOrder(): BelongsTo
    {
        return $this->belongsTo(CartOrder::class, 'cartOrderId', 'id');
    }

    public function returnCartOrderProduct(): HasMany
    {
        return $this->hasMany(ReturnCartOrderProduct::class, 'returnCartOrderId', 'id');
    }
}
