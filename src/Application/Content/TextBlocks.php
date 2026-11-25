<?php

declare(strict_types=1);

namespace App\Application\Content;

/**
 * @phpstan-type Segment array{text: string, href?: string}
 * @phpstan-type Block array{type: 'heading'|'paragraph', segments: list<Segment>}|array{type: 'list', items: list<list<Segment>>}
 */
final class TextBlocks
{
    private const string LINK = '/\[([^\]\n]+)\]\((https:\/\/[^ \t\n\x0B\f\r)]+)\)/';
    private const string BLANKS = " \t";

    /** @return list<Block> */
    public static function parse(string $text): array
    {
        $blocks = [];
        $paragraph = [];
        $items = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            [$marker, $content] = self::read($line);
            if (null !== $marker && '' === $content) {
                continue;
            }
            if (self::endsOpenBlock($marker, $content, $paragraph, $items)) {
                self::close($blocks, $paragraph, $items);
            }
            if ('heading' === $marker) {
                $blocks[] = ['type' => 'heading', 'segments' => self::segments($content)];
            } elseif ('item' === $marker) {
                $items[] = self::segments($content);
            } elseif ('' !== $content) {
                $paragraph[] = $content;
            }
        }
        self::close($blocks, $paragraph, $items);

        return $blocks;
    }

    /** @return array{'heading'|'item'|null, string} */
    private static function read(string $line): array
    {
        $line = ltrim($line, self::BLANKS);
        $marker = str_starts_with($line, '## ') ? 'heading' : (str_starts_with($line, '- ') ? 'item' : null);

        return [$marker, trim(null === $marker ? $line : substr($line, 'heading' === $marker ? 3 : 2), self::BLANKS)];
    }

    /**
     * @param list<string>        $paragraph
     * @param list<list<Segment>> $items
     */
    private static function endsOpenBlock(?string $marker, string $content, array $paragraph, array $items): bool
    {
        return '' === $content || 'heading' === $marker || ('item' === $marker ? [] !== $paragraph : [] !== $items);
    }

    /**
     * @param list<Block>         $blocks
     * @param list<string>        $paragraph
     * @param list<list<Segment>> $items
     *
     * @param-out list<Block> $blocks
     * @param-out list<string> $paragraph
     * @param-out list<list<Segment>> $items
     */
    private static function close(array &$blocks, array &$paragraph, array &$items): void
    {
        if ([] !== $paragraph) {
            $blocks[] = ['type' => 'paragraph', 'segments' => self::segments(implode("\n", $paragraph))];
        }
        if ([] !== $items) {
            $blocks[] = ['type' => 'list', 'items' => $items];
        }
        $paragraph = [];
        $items = [];
    }

    /** @return list<Segment> */
    private static function segments(string $text): array
    {
        $segments = [];
        $cursor = 0;
        preg_match_all(self::LINK, $text, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            if ($match[0][1] > $cursor) {
                $segments[] = ['text' => substr($text, $cursor, $match[0][1] - $cursor)];
            }
            $segments[] = ['text' => $match[1][0], 'href' => $match[2][0]];
            $cursor = $match[0][1] + \strlen($match[0][0]);
        }
        if ($cursor < \strlen($text)) {
            $segments[] = ['text' => substr($text, $cursor)];
        }

        return $segments;
    }
}
