<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Infrastructure\Fonts\GoogleFontLibrary;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FakeGoogleFonts implements HttpClientInterface
{
    use DecoratorTrait;

    public const string WOFF2 = 'wOF2 fake font';

    private const array FAMILIES = [
        'Caveat' => ['latin' => [400, 700], 'latin-ext' => [400, 700], 'cyrillic' => [400, 700]],
        'Patrick Hand' => ['latin' => [400], 'latin-ext' => [400]],
        'Kanji Only' => ['[0]' => [400], '[1]' => [400]],
    ];

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if (!\in_array($host, ['fonts.googleapis.com', 'fonts.google.com', GoogleFontLibrary::FILE_HOST], true)) {
            return $this->client->request($method, $url, $options);
        }

        return new MockHttpClient(self::answer(...))->request($method, $url, $options);
    }

    /** @param array<string, mixed> $options */
    private static function answer(string $method, string $url, array $options): MockResponse
    {
        if (str_starts_with($url, GoogleFontLibrary::CATALOG)) {
            return new MockResponse(")]}'\n".json_encode(['familyMetadataList' => array_map(static fn (string $family): array => ['family' => $family], array_keys(self::FAMILIES))], \JSON_THROW_ON_ERROR));
        }
        if (GoogleFontLibrary::FILE_HOST === parse_url($url, \PHP_URL_HOST)) {
            return new MockResponse(self::WOFF2);
        }

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $family = explode(':', \is_string($query['family'] ?? null) ? $query['family'] : '')[0];

        return isset(self::FAMILIES[$family]) ? new MockResponse(self::css($family)) : new JsonMockResponse(['error' => 'not found'], ['http_code' => 400]);
    }

    private static function css(string $family): string
    {
        $css = '';
        foreach (self::FAMILIES[$family] as $subset => $weights) {
            foreach ($weights as $weight) {
                $slug = strtolower(str_replace(' ', '', $family));
                $css .= "/* {$subset} */\n@font-face {\n  font-family: '{$family}';\n  font-style: normal;\n  font-weight: {$weight};\n  font-display: swap;\n  src: url(https://fonts.gstatic.com/s/{$slug}/{$subset}-{$weight}.woff2) format('woff2');\n  unicode-range: U+0000-00FF, U+0131;\n}\n";
            }
        }

        return $css;
    }
}
