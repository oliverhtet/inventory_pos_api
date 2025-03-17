<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnCartOrderProduct extends Model
{
    use HasFactory;
    protected $primaryKey = 'id';
    protected $table = 'returnCartOrderProduct';
    protected $fillable = [
        'productId',
        'cartOrderProductId',
        'returnCartOrderId',
        'colorId',
        'productQuantity',
        'productSalePrice',
        'productVat',
        'discountType',
        'discount',
    ];

    public function returnCartOrder(): BelongsTo
    {
        return $this->belongsTo(ReturnCartOrder::class, 'returnCartOrderId');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    public function cartOrderProduct(): BelongsTo
    {
        return $this->belongsTo(CartOrderProduct::class, 'cartOrderProductId');
    }

    public function colors(): BelongsTo
    {
        return $this->belongsTo(Colors::class, 'colorId');
    }

    public function returnCartOrderAttributeValue(): HasMany
    {
        return $this->hasMany(ReturnCartOrderAttributeValue::class, 'returnCartOrderProductId');
    }
}
