<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use Twig\Attribute\AsTwigFilter;

final class RichTextExtension
{
    private const string LINK = '/\[([^\]\n]+)\]\((https:\/\/[^\s)]+)\)/';

    #[AsTwigFilter('rich_text', isSafe: ['html'])]
    public function richText(string $text): string
    {
        $html = '';
        $cursor = 0;
        preg_match_all(self::LINK, $text, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $html .= self::escape(substr($text, $cursor, $match[0][1] - $cursor));
            $html .= \sprintf('<a href="%s">%s</a>', self::escape($match[2][0]), self::escape($match[1][0]));
            $cursor = $match[0][1] + \strlen($match[0][0]);
        }

        return $html.self::escape(substr($text, $cursor));
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
