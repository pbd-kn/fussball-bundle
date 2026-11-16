<?php

declare(strict_types=1);

$loader = require $argv[1] ?? dirname(__DIR__).'/vendor/autoload.php';
$loader->addPsr4('PBDKN\\FussballBundle\\', dirname(__DIR__).'/src/', true);
$db = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$framework = (new ReflectionClass(Contao\CoreBundle\Framework\ContaoFramework::class))->newInstanceWithoutConstructor();
$container = new Symfony\Component\DependencyInjection\ContainerBuilder();
$container->setParameter('kernel.project_dir', dirname(__DIR__));
$container->setParameter('contao.web_dir', dirname(__DIR__));
Contao\System::setContainer($container);
$importer = new PBDKN\FussballBundle\Gallery\FlagImporter($framework, $db);
$migration = new PBDKN\FussballBundle\Migration\FlagGalleryMigration($importer);
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$check(!$migration->shouldRun(), 'Must wait until Gallery Creator tables exist.');
$db->executeStatement('CREATE TABLE tl_gallery_creator_albums (id INTEGER, name TEXT, assignedDir TEXT)');
$db->executeStatement("CREATE TABLE tl_gallery_creator_pictures (pid INTEGER, uuid TEXT, title TEXT DEFAULT 'Flagge', caption TEXT DEFAULT '')");
$db->executeStatement('CREATE TABLE tl_files (uuid TEXT, path TEXT)');
$check($migration->shouldRun(), 'Fresh installation must import flags.');
$directory = 'src/Resources/public/assets/flaggen';
$db->insert('tl_files', ['uuid' => 'folder', 'path' => $directory]);
$db->insert('tl_gallery_creator_albums', ['id' => 1, 'name' => 'Flaggen', 'assignedDir' => 'folder']);
$check($migration->shouldRun(), 'Empty existing album must be completed.');
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/'.$directory, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'gif') {
        continue;
    }
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname(__DIR__)) + 1));
    $db->insert('tl_files', ['uuid' => $path, 'path' => $path]);
    $db->insert('tl_gallery_creator_pictures', ['pid' => 1, 'uuid' => $path]);
}
$check(!$migration->shouldRun(), 'Complete import must not be repeated.');
$db->executeStatement("UPDATE tl_gallery_creator_pictures SET title = '' WHERE uuid = ?", [$directory.'/de.gif']);
$check($migration->shouldRun(), 'Existing images without titles must be backfilled.');
$db->executeStatement("UPDATE tl_gallery_creator_pictures SET title = 'Eigener Titel' WHERE uuid = ?", [$directory.'/de.gif']);
$check(!$migration->shouldRun(), 'Custom titles must be preserved.');
$db->executeStatement('CREATE TABLE tl_hy_nation (ID INTEGER, Nation TEXT, Alfa2 TEXT, Alfa3 TEXT, Image TEXT)');
$db->executeStatement("INSERT INTO tl_hy_nation VALUES (1, 'Deutschland', 'de', 'ger', 'de.gif')");
$check($migration->shouldRun(), 'Missing nation captions must be backfilled.');
$db->executeStatement("UPDATE tl_gallery_creator_pictures SET caption = 'Eigene Bildunterschrift'");
$check(!$migration->shouldRun(), 'Existing captions must be preserved.');
$db->delete('tl_gallery_creator_pictures', ['uuid' => $directory.'/de.gif']);
$check($migration->shouldRun(), 'Interrupted import must be resumed.');
echo "OK: waits for schema, fresh installation, empty album, completed import and interrupted import\n";
