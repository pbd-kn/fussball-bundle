<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Gallery;

use Doctrine\DBAL\Connection;

/** Resolves legacy filenames through Gallery Creator's UUID references. */
final class AlbumImages
{
    private Connection $connection;
    private array $albums = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function path(string $album, string $filename): string
    {
        if (!preg_match('~^(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.(?:gif|png|jpg|jpeg|webp|svg)$~D', $filename)) {
            return '';
        }
        if (!isset($this->albums[$album])) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT f.path, d.path AS directory FROM tl_gallery_creator_albums a '
                .'JOIN tl_gallery_creator_pictures p ON p.pid = a.id '
                .'JOIN tl_files f ON f.uuid = p.uuid '
                .'LEFT JOIN tl_files d ON d.uuid = a.assignedDir '
                .'WHERE a.name = ? AND a.published = ? AND p.published = \'1\' ORDER BY p.sorting, p.id',
                [$album, '1']
            );
            $this->albums[$album] = [];
            foreach ($rows as $row) {
                $path = (string) $row['path'];
                if (!str_starts_with($path, 'files/') || str_contains($path, '..')) {
                    continue;
                }
                $key = !empty($row['directory']) && str_starts_with($path, $row['directory'].'/')
                    ? substr($path, strlen($row['directory']) + 1) : basename($path);
                $this->albums[$album][$key] ??= $path;
            }
        }
        $path = $this->albums[$album][$filename] ?? '';
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
