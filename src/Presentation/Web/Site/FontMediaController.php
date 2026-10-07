<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\FontStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/media/fonts/{file}', name: 'media_font', requirements: ['file' => '[0-9A-HJKMNP-TV-Z]{26}(\.css|-\d{1,2}\.woff2)'], methods: ['GET'])]
final readonly class FontMediaController
{
    private const int ONE_YEAR = 31536000;

    public function __construct(private FontStorage $storage)
    {
    }

    public function __invoke(string $file): BinaryFileResponse
    {
        $path = $this->storage->path($file) ?? throw new NotFoundHttpException();

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => str_ends_with($file, '.css') ? 'text/css; charset=utf-8' : 'font/woff2']);
        $response->setPublic();
        $response->setMaxAge(self::ONE_YEAR);
        $response->setImmutable();

        return $response;
    }
}
