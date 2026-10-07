<?php

declare(strict_types=1);

namespace App\Infrastructure\Fonts;

use App\Application\Content\DownloadedFont;
use App\Application\Content\FontFile;
use App\Application\Content\FontLibrary;
use App\Domain\Content\Exception\FontsUnreachable;
use App\Domain\Content\Exception\FontWithoutLatin;
use App\Domain\Content\Exception\UnknownFont;
use App\Domain\Content\Typeface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleFontLibrary implements FontLibrary
{
    public const string CSS_API = 'https://fonts.googleapis.com/css2';
    public const string CATALOG = 'https://fonts.google.com/metadata/fonts';
    public const string FILE_HOST = 'fonts.gstatic.com';

    private const string BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
    private const string WEIGHTS = '100;200;300;400;500;600;700;800;900';
    private const array SUBSETS = ['latin', 'latin-ext'];
    private const string FACE = '~/\*\s*([\w-]+)\s*\*/\s*@font-face\s*\{([^}]*)\}~';
    private const string UNICODE_RANGE = '/^[0-9A-Fa-f?U+, -]+$/';
    private const int ONE_DAY = 86400;
    private const int RETRY_LATER = 300;

    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $cache,
    ) {
    }

    public function families(): array
    {
        return $this->cache->get('google_font_families', function (ItemInterface $item): array {
            $item->expiresAfter(self::ONE_DAY);
            try {
                $catalog = json_decode(ltrim($this->client->request('GET', self::CATALOG)->getContent(), ")]}'\n"), true);
            } catch (ExceptionInterface) {
                $item->expiresAfter(self::RETRY_LATER);

                return [];
            }
            $list = \is_array($catalog) && \is_array($catalog['familyMetadataList'] ?? null) ? $catalog['familyMetadataList'] : [];
            $families = array_values(array_filter(
                array_map(static fn (mixed $font): mixed => \is_array($font) ? ($font['family'] ?? null) : null, $list),
                static fn (mixed $family): bool => \is_string($family) && 1 === preg_match(Typeface::FAMILY_PATTERN, $family),
            ));
            sort($families);

            return $families;
        });
    }

    public function download(string $family, int $preferredWeight): DownloadedFont
    {
        $family = $this->canonical($family);
        $faces = $this->faces($family);
        if ([] === $faces) {
            throw new FontWithoutLatin($family);
        }

        $weights = array_unique(array_column($faces, 'weight'));
        usort($weights, static fn (int $a, int $b): int => [abs($a - $preferredWeight), -$a] <=> [abs($b - $preferredWeight), -$b]);
        $weight = $weights[0];

        $files = [];
        foreach ($faces as $face) {
            if ($weight === $face['weight']) {
                $files[] = new FontFile($face['range'], $this->fetch($face['url']));
            }
        }

        return new DownloadedFont($faces[0]['family'], $weight, $files);
    }

    private function canonical(string $family): string
    {
        return array_find($this->families(), static fn (string $known): bool => 0 === strcasecmp($known, $family)) ?? $family;
    }

    /** @return list<array{family: string, weight: int, range: string, url: string}> */
    private function faces(string $family): array
    {
        try {
            $response = $this->client->request('GET', self::CSS_API, [
                'query' => ['family' => $family.':wght@'.self::WEIGHTS, 'display' => 'swap'],
                'headers' => ['User-Agent' => self::BROWSER],
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new UnknownFont($family);
            }
            $css = $response->getContent();
        } catch (ExceptionInterface) {
            throw new FontsUnreachable();
        }

        preg_match_all(self::FACE, $css, $blocks, \PREG_SET_ORDER);
        $faces = [];
        foreach ($blocks as [, $subset, $body]) {
            $face = self::face($body);
            if (null !== $face && \in_array($subset, self::SUBSETS, true)) {
                $faces[] = $face;
            }
        }

        return $faces;
    }

    /** @return array{family: string, weight: int, range: string, url: string}|null */
    private static function face(string $body): ?array
    {
        if (
            1 !== preg_match("/font-family:\s*'([^']+)'/", $body, $family)
            || 1 !== preg_match('/font-weight:\s*(\d{3})\s*;/', $body, $weight)
            || 1 !== preg_match("~src:\s*url\((https://[^)\s]+)\)\s*format\('woff2'\)~", $body, $url)
            || 1 !== preg_match('/unicode-range:\s*([^;]+);/', $body, $range)
            || 1 !== preg_match(self::UNICODE_RANGE, trim($range[1]))
            || self::FILE_HOST !== parse_url($url[1], \PHP_URL_HOST)
        ) {
            return null;
        }

        return ['family' => Typeface::family($family[1]), 'weight' => (int) $weight[1], 'range' => trim($range[1]), 'url' => $url[1]];
    }

    private function fetch(string $url): string
    {
        try {
            return $this->client->request('GET', $url)->getContent();
        } catch (ExceptionInterface) {
            throw new FontsUnreachable();
        }
    }
}
