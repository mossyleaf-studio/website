<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Sales\CartLine;
use App\Domain\Sales\Country;
use App\Domain\Sales\Delivery;
use App\Domain\Sales\Exception\UnknownCountry;
use App\Domain\Sales\Zone;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class OrderPayload
{
    private const string NO_LINE_BREAK = '/[\x00-\x1F\x7F\x{2028}\x{2029}]/u';

    /** @param list<QuoteLinePayload> $lines */
    public function __construct(
        #[Assert\Valid]
        public array $lines = [],
        #[Assert\NotBlank(message: 'country.required')]
        public string $country = '',
        public int $expectedTotal = 0,
        #[Assert\NotBlank(message: 'email.required')]
        #[Assert\Length(max: Delivery::MAX_EMAIL, maxMessage: 'text.too_long')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        #[Assert\Email(message: 'email.invalid', mode: Assert\Email::VALIDATION_MODE_STRICT)]
        public string $email = '',
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: Delivery::MAX_NAME, maxMessage: 'text.too_long', normalizer: 'trim')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        public string $name = '',
        #[Assert\Length(max: Delivery::MAX_PHONE, maxMessage: 'text.too_long', normalizer: 'trim')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        public ?string $phone = null,
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: Delivery::MAX_LINE, maxMessage: 'text.too_long', normalizer: 'trim')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        public string $line1 = '',
        #[Assert\Length(max: Delivery::MAX_LINE, maxMessage: 'text.too_long', normalizer: 'trim')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        public ?string $line2 = null,
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: Delivery::MAX_POSTAL_CODE, maxMessage: 'text.too_long', normalizer: 'trim')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        public string $postalCode = '',
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: Delivery::MAX_CITY, maxMessage: 'text.too_long', normalizer: 'trim')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        public string $city = '',
        #[Assert\Length(max: Delivery::MAX_REGION, maxMessage: 'text.too_long', normalizer: 'trim')]
        #[Assert\Regex(pattern: self::NO_LINE_BREAK, match: false, message: 'text.line_break')]
        public ?string $region = null,
        #[Assert\IsTrue(message: 'terms.required')]
        public bool $terms = false,
    ) {
    }

    #[Assert\Callback]
    public function regionForTheWorld(ExecutionContextInterface $context): void
    {
        if ('' !== trim($this->region ?? '')) {
            return;
        }

        try {
            $world = Zone::World === Zone::of(Country::of($this->country));
        } catch (UnknownCountry) {
            return;
        }

        if ($world) {
            $context->buildViolation('region.required')->atPath('region')->addViolation();
        }
    }

    /** @return list<CartLine> */
    public function lines(): array
    {
        return array_map(static fn (QuoteLinePayload $line): CartLine => $line->line(), $this->lines);
    }

    public function delivery(): Delivery
    {
        return new Delivery($this->email, $this->name, $this->phone, $this->line1, $this->line2, $this->postalCode, $this->city, $this->region);
    }
}
