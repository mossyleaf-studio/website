<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Domain\Shared\Locale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ShopUrls
{
    private const string PREVIEW = 'beta_';
    private const string SHOP = 'shop_';

    public function __construct(private UrlGeneratorInterface $router)
    {
    }

    /** @param array<string, string> $parameters */
    public function to(string $route, Locale $locale, bool $preview, array $parameters = []): string
    {
        return $this->router->generate(($preview ? self::PREVIEW : '').$route, [...$parameters, '_locale' => $locale->value]);
    }

    /** @param array<string, string> $parameters */
    public function canonical(string $route, Locale $locale, array $parameters = []): string
    {
        return $this->router->generate($route, [...$parameters, '_locale' => $locale->value], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function closed(): string
    {
        return $this->router->generate('home');
    }

    public static function isShop(Request $request): bool
    {
        return str_starts_with(self::route($request), self::SHOP);
    }

    public static function route(Request $request): string
    {
        $route = $request->attributes->getString('_route');

        return str_starts_with($route, self::PREVIEW) ? substr($route, \strlen(self::PREVIEW)) : $route;
    }

    /** @return array<string, string> */
    public static function parameters(Request $request): array
    {
        $parameters = [];
        foreach ($request->attributes->all('_route_params') as $name => $value) {
            if (\is_string($value) && !str_starts_with((string) $name, '_')) {
                $parameters[(string) $name] = $value;
            }
        }

        return $parameters;
    }

    public static function locale(Request $request): Locale
    {
        return Locale::tryFrom($request->attributes->getString('_locale')) ?? Locale::En;
    }
}
