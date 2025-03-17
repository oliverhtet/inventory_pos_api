<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewRating extends Model
{
    use HasFactory;
    protected $table = 'reviewRating';
    protected $primaryKey = 'id';
    protected $fillable = [
        'productId',
        'customerId',
        'rating',
        'review',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customerId');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Images::class, 'id');
    }

    public function reviewReply(): HasMany
    {
        return $this->hasMany(ReviewReply::class, 'reviewId');
    }
}
