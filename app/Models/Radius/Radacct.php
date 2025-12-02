<?php

namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class Radacct extends Model
{
    protected $connection = 'radius';
    protected $table = 'radacct';
    public $timestamps = false;

    protected $primaryKey = 'radacctid';

    protected $fillable = [
        'radacctid',
        'acctsessionid',
        'acctuniqueid',
        'username',
        'groupname',
        'realm',
        'nasipaddress',
        'nasportid',
        'nasporttype',
        'acctstarttime',
        'acctupdatetime',
        'acctstoptime',
        'acctsessiontime',
        'acctauthentic',
        'connectinfo_start',
        'connectinfo_stop',
        'acctinputoctets',
        'acctoutputoctets',
        'calledstationid',
        'callingstationid',
        'acctterminatecause',
        'services',
        'framedprotocol',
        'framedipaddress',
    ];
}
