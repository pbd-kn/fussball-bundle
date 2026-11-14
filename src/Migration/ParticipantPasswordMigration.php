<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Migration;

use Contao\CoreBundle\Migration\MigrationInterface;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use PBDKN\FussballBundle\Security\ParticipantPassword;

final class ParticipantPasswordMigration implements MigrationInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'Fußball: vorhandene Teilnehmerpasswörter hashen';
    }

    private function candidates(): array
    {
        $schema = method_exists($this->connection, 'createSchemaManager') ? $this->connection->createSchemaManager() : $this->connection->getSchemaManager();
        if (!$schema->tablesExist(['tl_hy_teilnehmer']) || !isset($schema->listTableColumns('tl_hy_teilnehmer')['pwd'])) {
            return [];
        }
        return array_values(array_filter($this->connection->fetchAllAssociative('SELECT ID, pwd FROM tl_hy_teilnehmer WHERE pwd <> ?', ['']), static fn (array $row): bool => !ParticipantPassword::isHash((string) $row['pwd'])));
    }

    public function shouldRun(): bool
    {
        return $this->candidates() !== [];
    }

    public function run(): MigrationResult
    {
        $this->connection->transactional(function (): void {
            foreach ($this->candidates() as $row) {
                $plain = (string) $row['pwd'];
                // Malformed/overlong legacy credentials need a new password; never retain plaintext.
                $hash = ParticipantPassword::hash(strlen($plain) <= 72 && !str_contains($plain, "\0") ? $plain : bin2hex(random_bytes(32)));
                $this->connection->update('tl_hy_teilnehmer', ['pwd' => $hash], ['ID' => $row['ID'], 'pwd' => $plain]);
            }
        });
        return new MigrationResult(true, 'Vorhandene Teilnehmerpasswörter sind gehasht. Unzulässige alte Passwörter müssen neu gesetzt werden.');
    }
}
