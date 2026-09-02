<?php

declare(strict_types=1);

namespace Jengo\Auth\Models;

use CodeIgniter\Model;
use Jengo\Auth\Entities\UserIdentity;

class UserIdentityModel extends Model
{
    protected $table            = 'user_identities';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = UserIdentity::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'type',
        'name',
        'secret',
        'secret2',
        'expires',
        'extra',
        'force_reset',
        'last_used_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Find identity by type and name.
     */
    public function findByTypeAndName(string $type, string $name): ?UserIdentity
    {
        return $this->where('type', $type)->where('name', $name)->first();
    }
}
