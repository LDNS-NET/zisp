<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\TenantDatabase;
use Illuminate\Support\Facades\Config;

class Tenant extends \App\Models\BaseTenant implements TenantWithDatabase
{
    use HasDomains, TenantDatabase;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'username',
        'database', // make sure this column exists
    ];

    public function configure()
    {
        Config::set('database.connections.tenant.database', $this->databasePath());
    }

    protected function databasePath(): string
    {
        return database_path("tenants/{$this->id}.sqlite");
    }

    // ... your subscription and other methods remain unchanged
}
