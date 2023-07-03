<?php

namespace App\Security;

use App\Dto\TelegramUserDto;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TelegramCheckingAuthorization
{
    public function __construct(
        #[Autowire('%env(BOT_TOKEN)%')]
        private readonly string $botToken
    ) {
    }

    /**
     * @see https://core.telegram.org/widgets/login#checking-authorization
     */
    public function isValid(TelegramUserDto $teleUser): bool
    {
        $data = [];
        foreach (get_object_vars($teleUser) as $key => $value) {
            $data[] = sprintf('%s=%s', $key, $value);
        }
        sort($data);
        $data = implode("\n", $data);
        $secretKey = hash('sha256', $this->botToken, true);

        return hash_hmac('sha256', $data, $secretKey) === $teleUser->getHash();
    }
}
