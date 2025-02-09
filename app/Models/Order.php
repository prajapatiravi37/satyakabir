<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'dealer_id',
        'quantity',
        'redeem_points',
        'order_status',
        'order_date',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class); 
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }
}
