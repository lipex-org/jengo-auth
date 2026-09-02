<?php

declare(strict_types=1);

namespace Jengo\Auth\Models;

use CodeIgniter\Model;
use Jengo\Auth\Entities\User;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = User::class;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'username',
        'status',
        'status_message',
        'active',
        'last_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    /**
     * Find user by username or email identity.
     */
    public function findByIdentifier(string $identifier): ?User
    {
        // 1. Try direct username
        $user = $this->where('username', $identifier)->first();
        if ($user) {
            return $user;
        }

        // 2. Try identity (email, etc.)
        $identityModel = new UserIdentityModel();
        $identity = $identityModel->where('name', $identifier)->first();
        if ($identity) {
            return $this->find($identity->user_id);
        }

        return null;
    }
}
