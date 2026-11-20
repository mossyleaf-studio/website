<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Convention;
use App\Domain\Content\ConventionRepository;
use Psr\Clock\ClockInterface;

final readonly class ConventionQueries
{
    private const string STUDIO_TIMEZONE = 'Europe/Paris';

    public function __construct(
        private ConventionRepository $conventions,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<ConventionView> */
    public function all(): array
    {
        $views = $this->views();
        $past = array_filter($views, static fn (ConventionView $view): bool => !$view->upcoming);

        return [...array_filter($views, static fn (ConventionView $view): bool => $view->upcoming), ...array_reverse($past)];
    }

    /** @return list<ConventionView> */
    public function upcoming(): array
    {
        return array_values(array_filter($this->views(), static fn (ConventionView $view): bool => $view->upcoming));
    }

    public function view(Convention $convention): ConventionView
    {
        return ConventionView::of($convention, $this->today());
    }

    /** @return list<ConventionView> */
    private function views(): array
    {
        $today = $this->today();

        return array_map(static fn (Convention $convention): ConventionView => ConventionView::of($convention, $today), $this->conventions->all());
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone(self::STUDIO_TIMEZONE));
    }
}
