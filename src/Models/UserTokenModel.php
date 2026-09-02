<?php

declare(strict_types=1);

namespace Jengo\Auth\Models;

use CodeIgniter\Model;
use Jengo\Auth\Entities\UserToken;

class UserTokenModel extends Model
{
    protected $table            = 'user_tokens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = UserToken::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'name',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Find token entity by raw plain-text token.
     */
    public function findByPlainTextToken(string $plainTextToken): ?UserToken
    {
        $hash = hash('sha256', $plainTextToken);
        return $this->where('token_hash', $hash)->first();
    }
}
