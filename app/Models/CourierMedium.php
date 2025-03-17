<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourierMedium extends Model
{
    use HasFactory;

    protected $table = 'courierMedium';
    protected $primaryKey = 'id';

    protected $fillable = [
        'courierMediumName',
        'address',
        'phone',
        'email',
        'type',
        'subAccountId',
    ];

    public function cartOrder(): HasMany
    {
        return $this->hasMany(CartOrder::class, 'courierMediumId');
    }

    public function subAccount(): BelongsTo
    {
        return $this->belongsTo(SubAccount::class, 'subAccountId');
    }
}
