<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Integration\Install;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Install\Database;
use Thelia\Core\Install\Update;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * Documents published before the update are served by the web server as they are,
 * without the headers the shop sets on its own answers. The update writes the
 * .htaccess of the document cache, rather than leaving it to the next document the
 * shop publishes.
 */
final class UpdateDocumentCacheProtectionTest extends IntegrationTestCase
{
    private const CACHE_VARIABLE = 'document_cache_dir_from_web_root';

    protected bool $useTransaction = false;

    /** @var array<string, string> */
    private array $initialVersionRows = [];

    private string|false $initialCacheVariable = false;

    /** @var list<string> */
    private array $directories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialVersionRows = $this->readVersionRows();
        $statement = $this->connection()->prepare('SELECT `value` FROM `config` WHERE `name` = ?');
        $statement->execute([self::CACHE_VARIABLE]);
        $this->initialCacheVariable = $statement->fetchColumn();
    }

    protected function tearDown(): void
    {
        $this->writeVersionRows($this->initialVersionRows);

        if (false === $this->initialCacheVariable) {
            $this->connection()->prepare('DELETE FROM `config` WHERE `name` = ?')->execute([self::CACHE_VARIABLE]);
        } else {
            $this->writeCacheVariable((string) $this->initialCacheVariable);
        }

        (new Filesystem())->remove($this->directories);

        parent::tearDown();
    }

    public function testAnUpdateProtectsTheDocumentsAlreadyPublished(): void
    {
        $cacheDirectory = $this->documentCacheInTheWebSpace();
        file_put_contents($cacheDirectory.\DIRECTORY_SEPARATOR.'published-before-the-update.html', '<html></html>');

        $versions = (new Update(false))->getVersions();
        $this->writeVersionMarker($versions[\count($versions) - 2]);

        $this->updateDoingNothing()->process();

        $htaccess = $cacheDirectory.\DIRECTORY_SEPARATOR.'.htaccess';
        self::assertFileExists($htaccess);
        self::assertStringContainsString('Header set Content-Disposition "attachment"', (string) file_get_contents($htaccess));
    }

    public function testAnHtaccessTheShopWroteIsKept(): void
    {
        $cacheDirectory = $this->documentCacheInTheWebSpace();
        file_put_contents($cacheDirectory.\DIRECTORY_SEPARATOR.'.htaccess', '# the shop rules');

        $this->updateDoingNothing()->protectDocumentCache();

        self::assertSame('# the shop rules', file_get_contents($cacheDirectory.\DIRECTORY_SEPARATOR.'.htaccess'));
    }

    public function testNothingIsWrittenOutsideTheWebSpaceNorAtItsRoot(): void
    {
        $outside = THELIA_ROOT.'var'.\DIRECTORY_SEPARATOR.uniqid('documents-update-test-');
        mkdir($outside, 0o777, true);
        $this->directories[] = $outside;

        $this->writeCacheVariable('..'.\DIRECTORY_SEPARATOR.'var'.\DIRECTORY_SEPARATOR.basename($outside));
        $this->updateDoingNothing()->protectDocumentCache();
        self::assertFileDoesNotExist($outside.\DIRECTORY_SEPARATOR.'.htaccess');

        // An empty variable would name the web root itself: its .htaccess is the shop's.
        $webRootHtaccess = rtrim(THELIA_WEB_DIR, '/').\DIRECTORY_SEPARATOR.'.htaccess';
        $before = is_file($webRootHtaccess) ? file_get_contents($webRootHtaccess) : null;
        $this->writeCacheVariable('');
        $this->updateDoingNothing()->protectDocumentCache();
        self::assertSame($before, is_file($webRootHtaccess) ? file_get_contents($webRootHtaccess) : null);
    }

    public function testAShopThatPublishedNoDocumentYetGetsNoDirectory(): void
    {
        $fromWebRoot = 'cache'.\DIRECTORY_SEPARATOR.uniqid('documents-update-test-');
        $this->directories[] = THELIA_WEB_DIR.$fromWebRoot;
        $this->writeCacheVariable($fromWebRoot);

        $this->updateDoingNothing()->protectDocumentCache();

        self::assertDirectoryDoesNotExist(THELIA_WEB_DIR.$fromWebRoot);
    }

    private function documentCacheInTheWebSpace(): string
    {
        $fromWebRoot = 'cache'.\DIRECTORY_SEPARATOR.uniqid('documents-update-test-');
        $directory = THELIA_WEB_DIR.$fromWebRoot;
        mkdir($directory, 0o777, true);
        $this->directories[] = $directory;
        $this->writeCacheVariable($fromWebRoot);

        return $directory;
    }

    /**
     * The shipped scripts are no use here: the run only has to go through.
     */
    private function updateDoingNothing(): Update
    {
        return new class(false) extends Update {
            protected function updateToVersion(string $version, Database $database): void
            {
                $this->setCurrentVersion($version);
            }
        };
    }

    private function writeCacheVariable(string $value): void
    {
        $statement = $this->connection()->prepare('SELECT COUNT(*) FROM `config` WHERE `name` = ?');
        $statement->execute([self::CACHE_VARIABLE]);

        $sql = 0 === (int) $statement->fetchColumn()
            ? 'INSERT INTO `config` (`value`, `name`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (?, ?, 0, 0, NOW(), NOW())'
            : 'UPDATE `config` SET `value` = ? WHERE `name` = ?';

        $this->connection()->prepare($sql)->execute([$value, self::CACHE_VARIABLE]);
    }

    private function writeVersionMarker(string $version): void
    {
        $this->connection()->prepare("UPDATE `config` SET `value` = ? WHERE `name` = 'thelia_version'")->execute([$version]);
    }

    /**
     * @return array<string, string>
     */
    private function readVersionRows(): array
    {
        $rows = [];
        $statement = $this->connection()->query("SELECT `name`, `value` FROM `config` WHERE `name` LIKE 'thelia\\_%version'");

        while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $rows[(string) $row['name']] = (string) $row['value'];
        }

        return $rows;
    }

    /**
     * @param array<string, string> $rows
     */
    private function writeVersionRows(array $rows): void
    {
        $statement = $this->connection()->prepare('UPDATE `config` SET `value` = ? WHERE `name` = ?');

        foreach ($rows as $name => $value) {
            $statement->execute([$value, $name]);
        }
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getConnection(ProductTableMap::DATABASE_NAME);
    }
}
