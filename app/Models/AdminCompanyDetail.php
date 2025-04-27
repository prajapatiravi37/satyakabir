<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminCompanyDetail extends Model
{
    use HasFactory;

    protected $fillable = ['companyName', 'address', 'phone', 'email'];

    
}
