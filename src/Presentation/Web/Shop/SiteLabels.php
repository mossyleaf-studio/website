<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Domain\Shared\Locale;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SiteLabels
{
    /** @var array<string, array<string, string>> */
    private array $catalogues = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/assets/vue/i18n')]
        private readonly string $directory,
    ) {
    }

    /** @param array<string, scalar> $parameters */
    public function label(string $locale, string $key, array $parameters = []): string
    {
        $language = Locale::tryFrom($locale) ?? Locale::En;
        $message = $this->catalogue($language)[$key] ?? null;
        if (null === $message) {
            return $key;
        }

        if (str_contains($message, '|')) {
            $count = (int) ($parameters['count'] ?? 0);
            $singular = Locale::Fr === $language ? $count <= 1 : 1 === $count;
            $message = trim(explode('|', $message, 2)[$singular ? 0 : 1]);
        }

        return (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $match): string => \array_key_exists($match[1], $parameters) ? (string) $parameters[$match[1]] : $match[0], $message);
    }

    /** @return array<string, string> */
    private function catalogue(Locale $locale): array
    {
        if (!isset($this->catalogues[$locale->value])) {
            $file = \sprintf('%s/%s/site.json', $this->directory, $locale->value);
            $this->catalogues[$locale->value] = is_file($file) ? self::flatten(json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR)) : [];
        }

        return $this->catalogues[$locale->value];
    }

    /** @return array<string, string> */
    private static function flatten(mixed $messages, string $prefix = ''): array
    {
        if (!\is_array($messages)) {
            return [];
        }

        $flat = [];
        foreach ($messages as $key => $message) {
            $flat = [...$flat, ...(\is_string($message) ? [$prefix.$key => $message] : self::flatten($message, $prefix.$key.'.'))];
        }

        return $flat;
    }
}
