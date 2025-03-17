<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartAttributeValue extends Model
{
    use HasFactory;

    protected $table = 'cartAttributeValue';
    protected $primaryKey = 'id';
    protected $fillable = [
        'cartProductId',
        'productAttributeValueId',
    ];

    public function cartProduct(): BelongsTo
    {
        return $this->belongsTo(CartProduct::class, 'cartProductId', 'id');
    }

    public function productAttributeValue(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeValue::class, 'productAttributeValueId', 'id');
    }
}
