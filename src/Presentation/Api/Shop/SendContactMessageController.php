<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\SendContactMessage\SendContactMessageHandler;
use App\Presentation\Api\ProblemResponse;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
#[Route('/api/shop/{_locale}/contact', name: 'api_shop_contact', requirements: ['_locale' => 'en|fr'], methods: ['POST'], format: 'json', stateless: true)]
final readonly class SendContactMessageController
{
    public function __construct(
        private SendContactMessageHandler $sendContactMessage,
        #[Target('contact')]
        private RateLimiterFactoryInterface $limiter,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] ContactPayload $payload): Response
    {
        $limit = $this->limiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            $response = new ProblemResponse($this->translator->trans('problem.too_many_requests.title'), Response::HTTP_TOO_MANY_REQUESTS, $this->translator->trans('problem.too_many_requests.detail'), 'problem.too_many_requests');
            $response->headers->set('Retry-After', (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()));

            return $response;
        }

        ($this->sendContactMessage)(new SendContactMessage($payload->name, $payload->email, $payload->message, ShopUrls::locale($request), $payload->website));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
