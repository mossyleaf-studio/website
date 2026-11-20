<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exception\InvalidLinkUrl;

final class HttpsUrl
{
    public static function check(string $url, int $max): string
    {
        $url = trim($url);
        if (mb_strlen($url) > $max || 'https' !== parse_url($url, \PHP_URL_SCHEME) || false === filter_var($url, \FILTER_VALIDATE_URL)) {
            throw new InvalidLinkUrl($url);
        }

        return $url;
    }
}
