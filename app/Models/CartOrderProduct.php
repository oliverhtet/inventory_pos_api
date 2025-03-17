<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartOrderProduct extends Model
{
    use HasFactory;

    protected $table = 'cartOrderProduct';
    protected $primaryKey = 'id';
    protected $fillable = [
        'productId',
        'invoiceId',
        'productQuantity',
        'productSalePrice',
        'productVat',
        'discountType',
        'discount',
        'colorId'
    ];

    public function cartOrder(): BelongsTo
    {
        return $this->belongsTo(CartOrder::class, 'invoiceId');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    public function colors(): BelongsTo
    {
        return $this->belongsTo(Colors::class, 'colorId');
    }

    public function cartOrderAttributeValue(): HasMany
    {
        return $this->hasMany(CartOrderAttributeValue::class, 'cartOrderProductId');
    }
}
