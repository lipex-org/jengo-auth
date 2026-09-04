<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;
use Jengo\Base\Validation\FormFailedResponseHolder;

Events::on('jengo.form.failed', static function (FormFailedResponseHolder $holder): void {
    if ($holder->getResponse() === null) {
        auth()->getResponseHandler()->handleFormFailed($holder);
    }
});
