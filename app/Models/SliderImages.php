<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SliderImages extends Model
{
    use HasFactory;
    protected $table = 'sliderImages';

    protected $fillable = [
        'image',
        'linkUrl',
        'index'
        ];
}
