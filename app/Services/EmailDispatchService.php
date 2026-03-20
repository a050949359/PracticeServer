<?php

namespace App\Services;

use App\Events\SendEmailRequested;

class EmailDispatchService
{
    public function dispatch(string $email, string $type, ?string $name = null, array $data = []): void
    {
        event(new SendEmailRequested(
            email: $email,
            type: $type,
            name: $name,
            data: $data,
        ));
    }
}
