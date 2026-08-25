<?php

namespace App\Utils;

final class IdGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $time = (int) (microtime(true) * 1000);
        $timePart = '';
        for ($i = 9; $i >= 0; $i--) {
            $timePart = self::ALPHABET[$time % 32] . $timePart;
            $time = (int) ($time / 32);
        }

        $randPart = '';
        for ($i = 0; $i < 16; $i++) {
            $randPart .= self::ALPHABET[random_int(0, 31)];
        }

        return $timePart . $randPart;
    }
}
