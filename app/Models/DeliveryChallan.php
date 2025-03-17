<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChallan extends Model
{
    use HasFactory;

    protected $table = 'deliveryChallan';
    protected $primaryKey = 'id';

    protected $fillable = [
        'saleInvoiceId',
        'challanNo',
        'challanDate',
        'challanNote',
        'vehicleNo'
    ];

    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class, 'saleInvoiceId', 'id');
    }

    public function deliveryChallanProduct(): HasMany
    {
        return $this->hasMany(DeliveryChallanProduct::class, 'deliveryChallanId', 'challanNo');
    }
}
