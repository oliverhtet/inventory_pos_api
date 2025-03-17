<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartOrder extends Model
{
    use HasFactory;
    use HasUuids;

    // generate random unique string for uuid
    protected $table = 'cartOrder';
    protected $primaryKey = 'id';
    protected string $key = 'string';
    protected $fillable = [
        'date',
        'due',
        'deliveryFeeId',
        'deliveryFee',
        'courierMediumId',
        'totalAmount',
        'paidAmount',
        'profit',
        'couponId',
        'couponAmount',
        'customerId',
        'userId',
        'deliveryAddress',
        'customerPhone',
        'orderStatus',
        'note',
        'isReOrdered',
        'previousCartOrderId'
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = self::generateUniqueKey(15);
        });
    }

    /**
     * @throws Exception
     */
    protected static function generateUniqueKey($length): string
    {
        $characters = "ABCDEFGHOPQRSTUYZ0123456IJKLMN789VWX";
        $key = "";

        for ($i = 0; $i < $length; $i++) {
            $key .= $characters[random_int(0, strlen($characters) - 1)];
        }
        // Ensure the key is unique
        while (static::where('id', $key)->exists()) {
            $key .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $key;
    }

    public function previousCartOrder(): BelongsTo
    {
        return $this->belongsTo(CartOrder::class, 'previousCartOrderId');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customerId');
    }

    public function deliveryFee(): BelongsTo
    {
        return $this->belongsTo(DeliveryFee::class, 'deliveryFeeId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'userId');
    }

    public function courierMedium(): BelongsTo
    {
        return $this->belongsTo(CourierMedium::class, 'courierMediumId');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'couponId');
    }

    public function cartOrderProduct(): HasMany
    {
        return $this->hasMany(CartOrderProduct::class, 'invoiceId');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cartId');
    }

    public function manualPayment(): HasMany
    {
        return $this->hasMany(ManualPayment::class, 'cartOrderId');
    }

    public function transaction(): HasMany
    {
        return $this->hasMany(Transaction::class, 'relatedId');
    }
}
