<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customer';
    protected $primaryKey = 'id';
    protected $fillable = [
        'email',
        'phone',
        'address',
        'password',
        'roleId',
        'username',
        'googleId',
        'firstName',
        'lastName',
        'profileImage',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function saleInvoice(): HasMany
    {
        return $this->hasMany(SaleInvoice::class, 'customerId');
    }

    public function cartOrder(): HasMany
    {
        return $this->hasMany(CartOrder::class, 'customerId');
    }


    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'customerId');
    }

    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class, 'customerId');
    }
}
