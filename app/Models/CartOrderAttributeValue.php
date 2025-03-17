<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartOrderAttributeValue extends Model
{
    use HasFactory;
    protected $table = 'cartOrderAttributeValue';
    protected $primaryKey = 'id';
    protected $fillable = [
        'cartOrderProductId',
        'productAttributeValueId',
    ];

    public function cartOrderProduct(): BelongsTo
    {
        return $this->belongsTo(CartOrderProduct::class, 'cartOrderProductId');
    }

    public function productAttributeValue(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeValue::class, 'productAttributeValueId');
    }
}
