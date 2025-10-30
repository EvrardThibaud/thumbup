<?php

namespace App\Service;

final class TokenFactory
{
    public function generate(int $bytes = 24): string
    {
        // URL-safe, ~32 chars
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
