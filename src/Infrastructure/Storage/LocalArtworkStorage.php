<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Content\ArtworkStorage;
use App\Application\Content\ResizedImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final readonly class LocalArtworkStorage implements ArtworkStorage
{
    private const string FILE_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}\.webp$/';

    public function __construct(
        #[Autowire('%kernel.share_dir%/artworks')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(Ulid $id, ResizedImage $image): string
    {
        $file = $id->toBase32().'.'.ResizedImage::EXTENSION;
        $this->filesystem->dumpFile($this->directory.'/'.$file, $image->bytes);

        return $file;
    }

    public function delete(string $file): void
    {
        $path = $this->path($file);
        if (null !== $path) {
            $this->filesystem->remove($path);
        }
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
