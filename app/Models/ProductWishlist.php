<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductWishlist extends Model
{
    use HasFactory;

    public $table = 'productWishlist';

    protected $fillable = [
        'wishlistId',
        'productId',
    ];

    public function wishlist()
    {
        return $this->belongsTo(Wishlist::class, 'wishlistId');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId');
    }


    
}
