<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const SELLABLE_CODES = ['starter'];

    protected $guarded = [];
}
