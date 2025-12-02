<?php

namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class Radreply extends Model
{
    protected $connection = 'mysql_radius';
    protected $table = 'radreply';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'attribute',
        'op',
        'value',
    ];
}
