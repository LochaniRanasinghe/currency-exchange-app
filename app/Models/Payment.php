<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_email',
        'amount',
        'currency',
        'reference_no',
        'transaction_date',
        'usd_amount'
    ];
}
