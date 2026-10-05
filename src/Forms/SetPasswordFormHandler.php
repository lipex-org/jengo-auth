<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Forms\AbstractFormHandler;

class SetPasswordFormHandler extends AbstractFormHandler
{
    public function getRules(): array
    {
        return [
            'password' => [
                'rules'  => 'required|min_length[8]',
                'label'  => 'New Password',
                'errors' => [
                    'required'   => 'Password is required.',
                    'min_length' => 'Password must be at least 8 characters long.',
                ],
                
            ],
            'password_confirm' => [
                'rules'  => 'required|matches[password]',
                'label'  => 'Confirm Password',
                'errors' => [
                    'required' => 'Please confirm your password.',
                    'matches'  => 'Password confirmation does not match.',
                ],
            ],
        ];
    }

    public function getPassword(): string
    {
        return (string) $this->getValidData()['password'];
    }
}
