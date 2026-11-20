<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation;

use App\Presentation\LocaleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\LocaleSwitcher;

final class LocaleListenerTest extends TestCase
{
    public function testTheLocaleOfTheAddressWinsOverTheBrowserLanguage(): void
    {
        [$request, $locales] = self::listen(['_locale' => 'fr'], 'en-GB,en;q=0.9');

        self::assertSame(['fr', 'fr'], [$request->getLocale(), $locales->getLocale()]);
    }

    public function testWithoutALocaleInTheAddressTheBrowserLanguageIsUsed(): void
    {
        [$request, $locales] = self::listen([], 'fr-FR,fr;q=0.9');

        self::assertSame(['fr', 'fr'], [$request->getLocale(), $locales->getLocale()]);
    }

    public function testAnUnknownLocaleInTheAddressIsIgnored(): void
    {
        [$request] = self::listen(['_locale' => 'de'], 'fr');

        self::assertSame('fr', $request->getLocale());
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return array{Request, LocaleSwitcher}
     */
    private static function listen(array $attributes, string $acceptLanguage): array
    {
        $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage]);
        $request->attributes->add($attributes);
        $locales = new LocaleSwitcher('en', []);
        new LocaleListener($locales)(new RequestEvent(new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): never
            {
                throw new \LogicException();
            }
        }, $request, HttpKernelInterface::MAIN_REQUEST));

        return [$request, $locales];
    }
}
