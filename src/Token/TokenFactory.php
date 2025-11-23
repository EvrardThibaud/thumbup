<?php

namespace App\Token;

final class TokenFactory
{
    public function generate(int $bytes = 24): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
