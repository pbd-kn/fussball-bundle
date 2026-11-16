<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Gallery;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Util\SymlinkUtil;
use Contao\Controller;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\Folder;
use Contao\System;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Doctrine\DBAL\Connection;

final class FlagImporter
{
    public function __construct(private ContaoFramework $framework, private Connection $connection)
    {
    }

    private function captionField(): string
    {
        $schema = method_exists($this->connection, 'createSchemaManager') ? $this->connection->createSchemaManager() : $this->connection->getSchemaManager();
        return isset($schema->listTableColumns('tl_gallery_creator_pictures')['caption']) ? 'caption' : 'comment';
    }

    private function nationNames(): array
    {
        $schema = method_exists($this->connection, 'createSchemaManager') ? $this->connection->createSchemaManager() : $this->connection->getSchemaManager();
        if (!$schema->tablesExist(['tl_hy_nation'])) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative('SELECT Nation, Alfa2, Alfa3, Image FROM tl_hy_nation ORDER BY ID');
        $names = [];
        // Country codes take precedence over potentially incorrect image assignments.
        foreach ($rows as $nation) {
            foreach (['Alfa2', 'Alfa3'] as $field) {
                $code = strtolower(trim((string) $nation[$field]));
                if ($code !== '' && trim((string) $nation['Nation']) !== '') {
                    $names[$code] ??= (string) $nation['Nation'];
                }
            }
        }
        foreach ($rows as $nation) {
            $code = strtolower(pathinfo((string) $nation['Image'], PATHINFO_FILENAME));
            if ($code !== '' && trim((string) $nation['Nation']) !== '') {
                $names[$code] ??= (string) $nation['Nation'];
            }
        }
        return $names;
    }

    public function shouldRun(): bool
    {
        $schema = method_exists($this->connection, 'createSchemaManager') ? $this->connection->createSchemaManager() : $this->connection->getSchemaManager();
        if (!$schema->tablesExist(['tl_gallery_creator_albums', 'tl_gallery_creator_pictures', 'tl_files'])) {
            return false;
        }
        $album = $this->connection->fetchAssociative('SELECT a.id, f.path FROM tl_gallery_creator_albums a LEFT JOIN tl_files f ON f.uuid = a.assignedDir WHERE a.name = ? ORDER BY a.id LIMIT 1', ['Flaggen']);
        if (!$album || empty($album['path'])) {
            return true;
        }
        $paths = [];
        $captionField = $this->captionField();
        $names = $this->nationNames();
        foreach ($this->connection->fetchAllAssociative('SELECT f.path, p.title, p.'.$captionField.' AS caption FROM tl_gallery_creator_pictures p JOIN tl_files f ON f.uuid = p.uuid WHERE p.pid = ?', [$album['id']]) as $picture) {
            $paths[$picture['path']] = $picture;
        }
        $root = System::getContainer()->getParameter('kernel.project_dir');
        foreach ($this->sources() as $relative => $source) {
            $path = $album['path'].'/'.$relative;
            if (!isset($paths[$path]) || trim((string) $paths[$path]['title']) === '' || !is_file($root.'/'.$path)
                || (isset($names[strtolower(pathinfo($relative, PATHINFO_FILENAME))]) && trim((string) $paths[$path]['caption']) === '')) {
                return true;
            }
        }
        $webDir = System::getContainer()->getParameter('contao.web_dir');
        return realpath($webDir.'/'.$album['path']) !== realpath($root.'/'.$album['path']);
    }

    private function sources(): iterable
    {
        $directory = dirname(__DIR__).'/Resources/public/assets/flaggen';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'gif') {
                yield str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1)) => $file->getPathname();
            }
        }
    }

    public function import(): int
    {
        $this->framework->initialize();
        Controller::loadDataContainer('tl_gallery_creator_albums');
        $container = System::getContainer();
        $root = $container->getParameter('kernel.project_dir');
        $album = GalleryCreatorAlbumsModel::findOneBy('name', 'Flaggen');
        if ($album === null) {
            if (GalleryCreatorAlbumsModel::findOneBy('alias', 'flaggen') !== null) {
                throw new \RuntimeException('Der Album-Alias flaggen ist bereits vergeben. Bitte das Album zuerst prüfen.');
            }
            $album = new GalleryCreatorAlbumsModel();
            $album->name = 'Flaggen';
            $album->alias = 'flaggen';
            $album->published = '1';
            $album->date = time();
            $preserveField = isset($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['preserveFilename']) ? 'preserveFilename' : 'preserve_filename';
            $album->$preserveField = '1';
        }
        $directory = $album->assignedDir ? FilesModel::findByUuid($album->assignedDir) : null;
        $path = $directory ? $directory->path : rtrim($container->getParameter('markocupic_gallery_creator.upload_path'), '/').'/flaggen';
        if (!str_starts_with($path, 'files/') || str_contains($path, '..')) {
            throw new \RuntimeException('Das Flaggen-Album benötigt einen Ordner unter files/.');
        }
        $folder = new Folder($path);
        $folder->unprotect();
        $album->assignedDir = Dbafs::addResource($path)->uuid;
        $album->tstamp = time();
        $album->save();
        $count = 0;
        $captionField = $this->captionField();
        $names = $this->nationNames();
        foreach ($this->sources() as $relative => $source) {
            $caption = $names[strtolower(pathinfo($relative, PATHINFO_FILENAME))] ?? '';
            $destination = $path.'/'.$relative;
            new Folder(dirname($destination));
            // Existing gallery files are deliberately preserved on repeated imports.
            if (!is_file($root.'/'.$destination) && !copy($source, $root.'/'.$destination)) {
                throw new \RuntimeException('Flagge konnte nicht kopiert werden: '.$relative);
            }
            $model = Dbafs::addResource($destination);
            $existing = GalleryCreatorPicturesModel::findOneBy(['pid=?', 'uuid=?'], [$album->id, $model->uuid]);
            if ($existing !== null) {
                $changed = false;
                if (trim((string) $existing->title) === '') {
                    $existing->title = $relative;
                    $changed = true;
                }
                if (trim((string) $existing->$captionField) === '' && $caption !== '') {
                    $existing->$captionField = $caption;
                    $changed = true;
                }
                if ($changed) {
                    $existing->tstamp = time();
                    $existing->save();
                }
                continue;
            }
            $picture = new GalleryCreatorPicturesModel();
            $picture->pid = $album->id;
            $picture->uuid = $model->uuid;
            $picture->title = $relative;
            $picture->$captionField = $caption;
            $picture->published = '1';
            $picture->tstamp = time();
            $picture->date = time();
            $picture->sorting = ++$count * 128;
            $picture->save();
        }
        // The import runs after Composer's setup, so publish this new folder now.
        $webDir = $container->getParameter('contao.web_dir');
        $link = $webDir.'/'.$path;
        if (realpath($link) !== realpath($root.'/'.$path)) {
            SymlinkUtil::symlink($path, $link, $root);
        }
        return $count;
    }
}
