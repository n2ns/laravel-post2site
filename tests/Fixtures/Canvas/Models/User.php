<?php

namespace Canvas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $table = 'canvas_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
