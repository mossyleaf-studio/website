<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Content\DownloadedFont;
use App\Application\Content\FontStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final readonly class LocalFontStorage implements FontStorage
{
    private const string STYLESHEET_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}\.css$/';
    private const string FILE_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}(\.css|-\d{1,2}\.woff2)$/';

    public function __construct(
        #[Autowire('%kernel.share_dir%/fonts')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(Ulid $id, DownloadedFont $font): string
    {
        $name = $id->toBase32();
        $faces = [];
        foreach ($font->files as $index => $file) {
            $binary = $name.'-'.$index.'.'.DownloadedFont::EXTENSION;
            $this->filesystem->dumpFile($this->directory.'/'.$binary, $file->bytes);
            $faces[] = \sprintf(
                "@font-face {\n    font-family: '%s';\n    font-style: normal;\n    font-weight: %d;\n    font-display: swap;\n    src: url(%s) format('woff2');\n    unicode-range: %s;\n}\n",
                $font->family,
                $font->weight,
                $binary,
                $file->unicodeRange,
            );
        }
        $stylesheet = $name.'.css';
        $this->filesystem->dumpFile($this->directory.'/'.$stylesheet, implode("\n", $faces));

        return $stylesheet;
    }

    public function delete(string $file): void
    {
        if (1 !== preg_match(self::STYLESHEET_PATTERN, $file)) {
            return;
        }

        $this->filesystem->remove([$this->directory.'/'.$file, ...(glob($this->directory.'/'.basename($file, '.css').'-*.'.DownloadedFont::EXTENSION) ?: [])]);
    }

    public function files(): array
    {
        $files = array_map(basename(...), glob($this->directory.'/*.css') ?: []);

        return array_values(array_filter($files, static fn (string $file): bool => 1 === preg_match(self::STYLESHEET_PATTERN, $file)));
    }

    public function path(string $file): ?string
    {
        if (1 !== preg_match(self::FILE_PATTERN, $file)) {
            return null;
        }

        $path = $this->directory.'/'.$file;

        return is_file($path) ? $path : null;
    }
}
