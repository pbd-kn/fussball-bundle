<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Controller;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\Folder;
use Contao\System;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportFlagsCommand extends Command
{
    public function __construct(private ContaoFramework $framework)
    {
        parent::__construct('fussball:import-flags');
    }

    protected function configure(): void
    {
        $this->setDescription('Importiert die Bundle-Flaggen wiederholbar in das Gallery-Creator-Album Flaggen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
        $source = dirname(__DIR__).'/Resources/public/assets/flaggen';
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'gif') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            $destination = $path.'/'.$relative;
            new Folder(dirname($destination));
            // Existing gallery files are deliberately preserved on repeated imports.
            if (!is_file($root.'/'.$destination) && !copy($file->getPathname(), $root.'/'.$destination)) {
                throw new \RuntimeException('Flagge konnte nicht kopiert werden: '.$relative);
            }
            $model = Dbafs::addResource($destination);
            if (GalleryCreatorPicturesModel::findOneBy(['pid=?', 'uuid=?'], [$album->id, $model->uuid]) !== null) {
                continue;
            }
            $picture = new GalleryCreatorPicturesModel();
            $picture->pid = $album->id;
            $picture->uuid = $model->uuid;
            $picture->published = '1';
            $picture->tstamp = time();
            $picture->date = time();
            $picture->sorting = ++$count * 128;
            $picture->save();
        }
        $output->writeln(sprintf('Album Flaggen: %d Bilder neu registriert; vorhandene Dateien beibehalten.', $count));
        return 0;
    }
}
