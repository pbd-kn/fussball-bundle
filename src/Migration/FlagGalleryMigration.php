<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Migration;

use Contao\CoreBundle\Migration\MigrationInterface;
use Contao\CoreBundle\Migration\MigrationResult;
use PBDKN\FussballBundle\Gallery\FlagImporter;

final class FlagGalleryMigration implements MigrationInterface
{
    public function __construct(private FlagImporter $importer)
    {
    }

    public function getName(): string
    {
        return 'Fußball: Flaggen in das Gallery-Creator-Album importieren';
    }

    public function shouldRun(): bool
    {
        return $this->importer->shouldRun();
    }

    public function run(): MigrationResult
    {
        try {
            $count = $this->importer->import();
            return new MigrationResult(true, sprintf('Album Flaggen angelegt bzw. vervollständigt: %d Bilder neu importiert.', $count));
        } catch (\Throwable $exception) {
            return new MigrationResult(false, 'Flaggen-Import fehlgeschlagen: '.$exception->getMessage());
        }
    }
}
