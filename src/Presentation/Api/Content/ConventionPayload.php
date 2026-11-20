<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\Convention;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConventionPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: Convention::MAX_NAME, maxMessage: 'text.too_long')]
        public string $name = '',
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: Convention::MAX_PLACE, maxMessage: 'text.too_long')]
        public string $place = '',
        #[Assert\NotBlank(message: 'date.required')]
        #[Assert\Date(message: 'date.invalid')]
        public string $startsOn = '',
        #[Assert\NotBlank(message: 'date.required')]
        #[Assert\Date(message: 'date.invalid')]
        public string $endsOn = '',
        #[Assert\Url(message: 'url.invalid', protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: Convention::MAX_URL, maxMessage: 'text.too_long')]
        public ?string $url = null,
        #[Assert\Length(max: Convention::MAX_NOTE, maxMessage: 'text.too_long')]
        public string $note = '',
        #[Assert\Length(max: Convention::MAX_NOTE, maxMessage: 'text.too_long')]
        public string $noteFr = '',
    ) {
    }

    public function startsOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->startsOn);
    }

    public function endsOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->endsOn);
    }
}
