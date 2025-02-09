<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBankDetail extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'account_no', 'ifsc_code', 'bank_name'];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
