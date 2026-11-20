<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContactPayload
{
    private const string NO_LINE_BREAK = '/[\r\n]/';

    public function __construct(
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: SendContactMessage::MAX_NAME, maxMessage: 'text.too_long')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        public string $name = '',
        #[Assert\NotBlank(message: 'email.required')]
        #[Assert\Length(max: SendContactMessage::MAX_EMAIL, maxMessage: 'text.too_long')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        #[Assert\Email(message: 'email.invalid', mode: Assert\Email::VALIDATION_MODE_STRICT)]
        public string $email = '',
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: SendContactMessage::MAX_MESSAGE, maxMessage: 'text.too_long')]
        public string $message = '',
        public string $website = '',
    ) {
    }
}
