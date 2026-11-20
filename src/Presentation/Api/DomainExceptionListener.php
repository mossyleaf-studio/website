<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Domain\Sales\Exception\CartOutdated;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class DomainExceptionListener
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof DomainException) {
            return;
        }

        $status = match (true) {
            $exception instanceof NotFound => Response::HTTP_NOT_FOUND,
            $exception instanceof CartOutdated => Response::HTTP_CONFLICT,
            $exception instanceof PaymentUnavailable => Response::HTTP_SERVICE_UNAVAILABLE,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };

        $event->setResponse(new ProblemResponse(
            $this->translator->trans('problem.business_rule'),
            $status,
            $this->translator->trans($exception->getMessage(), $exception->parameters(), 'exceptions'),
            $exception->getMessage(),
        ));
    }
}
