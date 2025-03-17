<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChallanProduct extends Model
{
    use HasFactory;

    protected $table = 'deliveryChallanProduct';
    protected $primaryKey = 'id';

    protected $fillable = [
        'deliveryChallanId',
        'productId',
        'quantity'
    ];

    public function deliveryChallan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'deliveryChallanId', 'challanNo');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId', 'id');
    }
}
