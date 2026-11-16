<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Command;

use PBDKN\FussballBundle\Gallery\FlagImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportFlagsCommand extends Command
{
    public function __construct(private FlagImporter $importer)
    {
        parent::__construct('fussball:import-flags');
    }

    protected function configure(): void
    {
        $this->setDescription('Importiert die Bundle-Flaggen wiederholbar in das Gallery-Creator-Album Flaggen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->importer->import();
        $output->writeln(sprintf('Album Flaggen: %d Bilder neu registriert; vorhandene Dateien beibehalten.', $count));
        return 0;
    }
}
