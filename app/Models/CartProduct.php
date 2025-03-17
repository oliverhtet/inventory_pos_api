<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartProduct extends Model
{
    use HasFactory;

    protected $table = 'cartProduct';
    protected $primaryKey = 'id';
    protected $fillable = [
        'cartId',
        'productId',
        'productQuantity',
        'colorId',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cartId', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId', 'id');
    }
    public function colors(): BelongsTo
    {
        return $this->belongsTo(Colors::class, 'colorId', 'id');
    }

    public function cartAttributeValue(): HasMany
    {
        return $this->hasMany(CartAttributeValue::class, 'cartProductId', 'id');
    }

}
