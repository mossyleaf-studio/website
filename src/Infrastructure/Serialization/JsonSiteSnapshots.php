<?php

declare(strict_types=1);

namespace App\Infrastructure\Serialization;

use App\Application\Content\SiteSnapshots;
use App\Application\Content\SiteView;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class JsonSiteSnapshots implements SiteSnapshots
{
    private const int SAFE_IN_HTML = \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

    public function __construct(private SerializerInterface $serializer)
    {
    }

    public function encode(SiteView $site): string
    {
        return $this->serializer->serialize($site, 'json', [JsonEncode::OPTIONS => self::SAFE_IN_HTML]);
    }
}
