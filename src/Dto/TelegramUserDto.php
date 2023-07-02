<?php

namespace App\Dto;

class TelegramUserDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $first_name,
        public readonly ?string $username,
        public readonly string $photo_url,
        public readonly string $auth_date,
        private readonly string $hash
    ) {
    }

    public function getHash(): string
    {
        return $this->hash;
    }
}
