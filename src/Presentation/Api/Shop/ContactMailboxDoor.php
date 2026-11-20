<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Contact\StudioMailbox;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class ContactMailboxDoor
{
    public function __construct(private StudioMailbox $mailbox)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ('api_shop_contact' === ShopUrls::route($event->getRequest()) && !$this->mailbox->isOpen()) {
            throw new NotFoundHttpException();
        }
    }
}
