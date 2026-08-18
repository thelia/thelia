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

namespace Thelia\Core\Install;

use Michelf\Markdown;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Exception\ParseException;
use Thelia\Config\DatabaseConfigurationSource;
use Thelia\Core\Install\Exception\UpdateException;
use Thelia\Core\Install\Exception\UpToDateException;
use Thelia\Core\TheliaKernel;
use Thelia\Log\Tlog;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Tools\Version\Version;

/**
 * Class Update.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Update
{
    public const SQL_DIR = 'update/sql/';
    public const PHP_DIR = 'update/php/';
    public const INSTRUCTION_DIR = 'update/instruction/';

    protected array $version;
    protected ?Tlog $logger;

    /** @var array log messages */
    protected array $logs = [];

    /** @var array post instructions */
    protected array $postInstructions = [];

    protected array $updatedVersions = [];
    protected ConnectionInterface|\PDO $connection;
    protected ?string $backupFile = null;
    protected ?string $restoreFailure = null;
    protected string $backupDir = 'local/backup/';
    protected array $messages = [];
    protected Translator $translator;

    /**
     * @param bool $usePropel
     */
    public function __construct(protected $usePropel = true)
    {
        if ($this->usePropel) {
            $this->logger = Tlog::getInstance();
            $this->logger->setLevel(Tlog::DEBUG);
        } else {
            $this->logs = [];
        }

        try {
            $connection = Propel::getConnection(
                ProductTableMap::DATABASE_NAME,
            );

            // Unwrap the wrapper Propel returns, so that the update runs on the one
            // connection it opened rather than through its transaction bookkeeping.
            // What comes out is a PdoConnection, not a \PDO: it exposes the same
            // query(), prepare() and exec(), and keeps its own \PDO private.
            if ($connection instanceof ConnectionWrapper) {
                $connection = $connection->getWrappedConnection();
            }

            $this->connection = $connection;
        } catch (ParseException $ex) {
            throw new UpdateException('database.yml is not a valid file : '.$ex->getMessage());
        } catch (\PDOException $ex) {
            throw new UpdateException('Wrong connection information'.$ex->getMessage());
        }

        $this->version = $this->getVersionList();
    }

    /**
     * retrieve the database connection.
     *
     * @throws ParseException
     * @throws \PDOException
     */
    protected function getDatabasePDO(): \PDO
    {
        if (!TheliaKernel::isInstalled()) {
            throw new UpdateException('Thelia is not installed yet');
        }

        $definePropel = new DatabaseConfigurationSource(
            $this->getEnvParameters(),
        );

        return $definePropel->getTheliaConnectionPDO();
    }

    /**
     * Gets the environment parameters.
     *
     * Only the parameters starting with "SYMFONY__" are considered.
     *
     * @return array An array of parameters
     */
    protected function getEnvParameters(): array
    {
        $parameters = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'SYMFONY__')) {
                $parameters[strtolower(str_replace('__', '.', substr($key, 9)))] = $value;
            }
        }

        return $parameters;
    }

    public function isLatestVersion($version = null): bool
    {
        if (null === $version) {
            $version = $this->getCurrentVersion();
        }

        $lastEntry = end($this->version);

        return version_compare((string) $lastEntry, (string) $version, '<=') === true;
    }

    /**
     * Find the position of the current version in the update script list.
     *
     * Some releases ship without an update script, so the current version is not always part of
     * that list. In that case, the update resumes from the closest known version below the
     * current one instead of replaying every script from the beginning.
     *
     * @throws UpdateException when no known version precedes the current one
     */
    protected function getStartIndex(string $currentVersion): int
    {
        $index = array_search($currentVersion, $this->version, true);

        if (false !== $index) {
            return (int) $index;
        }

        $closestIndex = null;

        foreach ($this->version as $position => $knownVersion) {
            if (version_compare($knownVersion, $currentVersion, '<=')) {
                $closestIndex = $position;
            }
        }

        if (null === $closestIndex) {
            throw new UpdateException(\sprintf('Unknown installed version "%s", unable to find where to start the update.', $currentVersion));
        }

        return $closestIndex;
    }

    public function process(): array
    {
        $this->updatedVersions = [];

        $currentVersion = $this->getCurrentVersion();
        $this->log('debug', 'start update process');

        if ($this->isLatestVersion($currentVersion)) {
            $this->log('debug', 'You already have the latest version. No update available');

            throw new UpToDateException('You already have the latest version. No update available');
        }

        $index = $this->getStartIndex((string) $currentVersion);

        // The loop runs outside any transaction. MariaDB commits implicitly on every
        // CREATE, ALTER and DROP, so a transaction spanning a schema migration ends at
        // the first one and leaves everything written before it committed: it promises
        // all-or-nothing and delivers neither. What makes an interrupted run recoverable
        // is the version marker updateToVersion() writes after each version, so the next
        // run resumes where this one stopped, and the backup update.php offers
        // beforehand, the only thing that can undo a schema change.
        $database = new Database($this->connection);
        $version = null;

        try {
            $size = \count($this->version);

            for ($i = $index + 1; $i < $size; ++$i) {
                $version = $this->version[$i];
                $this->updateToVersion($version, $database);
                $this->updatedVersions[] = $version;
            }

            // The variables set below track the database update level (the last update
            // script applied), not the code version: update scripts are committed before
            // the version number itself is bumped, so using the code version here would
            // make the last script run again on every following update. thelia_version
            // has already been set to $version by updateToVersion(); only the derived
            // variables are recomputed, from that same script version.
            $updateConfigVersion = [];

            try {
                $parsedVersion = Version::parse($version);

                $updateConfigVersion = [
                    'thelia_major_version' => $parsedVersion['major'],
                    'thelia_minus_version' => $parsedVersion['minus'],
                    'thelia_release_version' => $parsedVersion['release'],
                    'thelia_extra_version' => $parsedVersion['extra'],
                ];
            } catch (\InvalidArgumentException) {
                $this->log('error', \sprintf('unable to parse version %s, detailed version variables were left unchanged', $version));
            }

            $this->log('debug', \sprintf('setting database configuration to %s', $version));

            foreach ($updateConfigVersion as $name => $value) {
                $stmt = $this->connection->prepare('SELECT * FROM `config` WHERE `name` = ?');
                $stmt->execute([$name]);

                if ($stmt->rowCount()) {
                    $stmt = $this->connection->prepare('UPDATE `config` SET `value` = ? WHERE `name` = ?');
                    $stmt->execute([$value, $name]);
                } else {
                    $stmt = $this->connection->prepare('INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (?, ?, 1, 1, NOW(), NOW())');
                    $stmt->execute([$name, $value]);
                }
            }

            $this->log('debug', 'update successfully');
        } catch (\Exception $exception) {
            $this->log('error', \sprintf('error during update process with message : %s', $exception->getMessage()));

            // A failing statement reaches here as a PDOException, whose getCode() is the
            // SQLSTATE string ('42S02'). Exception only takes an int, so wrapping it
            // raised a TypeError of its own and no SQL failure ever reached update.php:
            // the operator got a fatal error instead of the offer to restore the backup.
            $code = $exception->getCode();

            $ex = new UpdateException($exception->getMessage(), \is_int($code) ? $code : 0, $exception);
            $ex->setVersion($version);

            throw $ex;
        }

        $this->log('debug', 'end of update processing');

        return $this->updatedVersions;
    }

    /**
     * Backup current DB to file local/backup/update.sql.
     *
     * @return bool if it succeeds, false otherwise
     *
     * @throws \Exception
     */
    public function backupDb(): void
    {
        $database = new Database($this->connection);

        if (!$this->checkBackupIsPossible()) {
            $message = 'Your database is too big for an automatic backup';

            $this->log('error', $message);

            throw new UpdateException($message);
        }

        $this->backupFile = THELIA_ROOT.$this->backupDir.'update.sql';
        $backupDir = THELIA_ROOT.$this->backupDir;

        $fs = new Filesystem();

        try {
            $this->log('debug', \sprintf('Backup database to file : %s', $this->backupFile));

            // test if backup dir exists
            if (!$fs->exists($backupDir)) {
                $fs->mkdir($backupDir);
            }

            if (!is_writable($backupDir)) {
                throw new \RuntimeException(\sprintf('impossible to write in directory : %s', $backupDir));
            }

            // test if backup file already exists
            if ($fs->exists($this->backupFile)) {
                // remove file
                $fs->remove($this->backupFile);
            }

            $database->backupDb($this->backupFile);
        } catch (\Exception $exception) {
            $this->log('error', \sprintf('error during backup process with message : %s', $exception->getMessage()));

            throw $exception;
        }
    }

    /**
     * Restores file local/backup/update.sql to current DB.
     *
     * @return bool if it succeeds, false otherwise
     */
    public function restoreDb(): bool
    {
        if (null === $this->backupFile) {
            return false;
        }

        $database = new Database($this->connection);

        try {
            $this->log('debug', \sprintf('Restore database with file : %s', $this->backupFile));

            if (!file_exists($this->backupFile)) {
                return false;
            }

            $database->restoreDb($this->backupFile);
        } catch (\Exception $exception) {
            // Kept for the caller to print: it names the table the restore stopped on,
            // and what the database holds now. Written on the object rather than echoed,
            // so the caller decides where it goes.
            $this->restoreFailure = $exception->getMessage();

            $this->log('error', \sprintf('error during restore process with message : %s', $exception->getMessage()));

            return false;
        }

        return true;
    }

    /**
     * Why the last restore failed, or null if none did.
     */
    public function getRestoreFailure(): ?string
    {
        return $this->restoreFailure;
    }

    public function getBackupFile(): ?string
    {
        return $this->backupFile;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    protected function log($level, $message): void
    {
        if (!$this->usePropel) {
            $this->logs[] = [$level, $message];

            return;
        }

        if (!$this->logger instanceof Tlog) {
            return;
        }

        // Tlog keys its levels by the numeric constants, not by the PSR-3 names, so passing
        // 'debug' straight to Tlog::log() would silently log nothing.
        $tlogLevel = match ($level) {
            'debug' => Tlog::DEBUG,
            'info' => Tlog::INFO,
            'notice' => Tlog::NOTICE,
            'warning' => Tlog::WARNING,
            'error' => Tlog::ERROR,
            'critical' => Tlog::CRITICAL,
            default => null,
        };

        if (null !== $tlogLevel) {
            $this->logger->log($tlogLevel, $message);
        }
    }

    protected function updateToVersion(string $version, Database $database): void
    {
        // sql update
        $filename = \sprintf(
            '%s%s%s',
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::SQL_DIR),
            $version.'.sql',
        );

        if (file_exists($filename)) {
            $this->log('debug', \sprintf('inserting file %s', $version.'.sql'));
            $database->insertSql(null, [$filename]);
            $this->log('debug', \sprintf('end inserting file %s', $version.'.sql'));
        }

        // php update
        $filename = \sprintf(
            '%s%s%s',
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::PHP_DIR),
            $version.'.php',
        );

        if (file_exists($filename)) {
            $this->log('debug', \sprintf('executing file %s', $version.'.php'));
            include_once $filename;
            $this->log('debug', \sprintf('end executing file %s', $version.'.php'));
        }

        // instructions
        $filename = \sprintf(
            '%s%s%s',
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::INSTRUCTION_DIR),
            $version.'.md',
        );

        if (file_exists($filename)) {
            $this->addPostInstructions($version, file_get_contents($filename));
        }

        $this->setCurrentVersion($version);
    }

    public function getCurrentVersion()
    {
        $stmt = $this->connection->query("SELECT `value` FROM `config` WHERE name='thelia_version'");

        return $stmt->fetchColumn();
    }

    public function setCurrentVersion($version): void
    {
        // No instanceof \PDO guard here: the connection Propel hands out is a
        // PdoConnection, so the guard silently skipped the only write that records
        // how far the update went, and every run started over from the same version.
        try {
            $stmt = $this->connection->prepare('UPDATE config set value = ? where name = ?');
            $stmt->execute([$version, 'thelia_version']);
        } catch (\PDOException $e) {
            $this->log('error', \sprintf('Error setting current version : %s', $e->getMessage()));

            throw $e;
        }
    }

    /**
     * Returns the database size in Mo.
     *
     * @throws \Exception
     */
    public function getDataBaseSize(): float
    {
        $statement = $this->connection->query(
            "SELECT sum(data_length) / 1024 / 1024 'size' FROM information_schema.TABLES WHERE table_schema = DATABASE() GROUP BY table_schema",
        );

        if ($statement instanceof \PDOStatement && $statement->rowCount() > 0) {
            return (float) $statement->fetch(\PDO::FETCH_OBJ)->size;
        }

        throw new \Exception('Impossible to calculate the database size');
    }

    /**
     * Checks whether it is possible to make a data base backup.
     *
     * The backup accumulates the whole dump in memory, so a finite memory_limit
     * caps the database size the backup can handle. A negative memory_limit
     * means unlimited memory: the backup is always possible.
     */
    public function checkBackupIsPossible(): bool
    {
        $memoryLimit = self::parseMemoryLimit(\ini_get('memory_limit'));

        if ($memoryLimit < 0) {
            return true;
        }

        $memoryLimitInMegabytes = $memoryLimit / (1024 ** 2);

        return !($this->getDataBaseSize() > ($memoryLimitInMegabytes - 64) / 8);
    }

    /**
     * Converts a php.ini shorthand-byte value to bytes, following the PHP
     * semantics: the leading integer part is kept (a fractional prefix such as
     * "0.5G" truncates to 0), the k/m/g suffix is case-insensitive, and a value
     * without a suffix is already in bytes. A negative result means unlimited.
     *
     * @internal exposed for tests
     */
    public static function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $bytes = (int) $memoryLimit;

        return match (strtolower(substr($memoryLimit, -1))) {
            'k' => $bytes * 1024,
            'm' => $bytes * 1024 ** 2,
            'g' => $bytes * 1024 ** 3,
            default => $bytes,
        };
    }

    public function getLatestVersion(): mixed
    {
        return end($this->version);
    }

    public function getVersions(): array
    {
        return $this->version;
    }

    public function getUpdatedVersions(): array
    {
        return $this->updatedVersions;
    }

    public function setUpdatedVersions(array $updatedVersions): void
    {
        $this->updatedVersions = $updatedVersions;
    }

    /**
     * Add a new post update instruction.
     *
     * @param string $instructions content of the instruction un markdown format
     */
    protected function addPostInstructions($version, string $instructions): void
    {
        if (!isset($this->postInstructions[$version])) {
            $this->postInstructions[$version] = [];
        }

        $this->postInstructions[$version][] = $instructions;
    }

    /**
     * Return the content of all instructions.
     *
     * @param string $format the format of the export : plain (default) or html
     *
     * @return string the instructions in plain text or html
     */
    public function getPostInstructions(string $format = 'plain'): string
    {
        $content = [];

        if ([] === $this->postInstructions) {
            return '';
        }

        ksort($this->postInstructions);

        foreach ($this->postInstructions as $version => $instructions) {
            $content[] = \sprintf('## %s', $version);

            foreach ($instructions as $instruction) {
                $content[] = \sprintf('%s', $instruction);
            }
        }

        $content = implode("\n\n", $content);

        if ('html' === $format) {
            $content = Markdown::defaultTransform($content);
        }

        return $content;
    }

    public function hasPostInstructions(): bool
    {
        return [] !== $this->postInstructions;
    }

    /**
     * @return list<string>
     */
    public function getVersionList(): array
    {
        $list = [];
        $finder = new Finder();
        $path = \sprintf('%s%s', THELIA_SETUP_DIRECTORY, str_replace('/', DS, self::SQL_DIR));
        $sort = static function (SplFileInfo $a, SplFileInfo $b): int {
            $left = strtolower(substr($a->getRelativePathname(), 0, -4));
            $right = strtolower(substr($b->getRelativePathname(), 0, -4));

            return (int) version_compare($left, $right);
        };

        $files = $finder->name('*.sql')->in($path)->sort($sort);

        foreach ($files as $file) {
            $list[] = substr($file->getRelativePathname(), 0, -4);
        }

        return $list;
    }

    /**
     * @return $this
     */
    public function setMessage(string $message, string $type = 'info'): static
    {
        $this->messages[] = [$message, $type];

        return $this;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function trans(string $string): string
    {
        return $this->translator ? $this->translator->trans($string) : $string;
    }

    /**
     * @return $this
     */
    public function setTranslator(Translator $translator): static
    {
        $this->translator = $translator;

        return $this;
    }

    public function getWebVersion(): ?string
    {
        $url = 'http://thelia.net/version.php';
        $curl = curl_init($url);
        curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, \CURLOPT_HEADER, false);
        curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, \CURLOPT_TIMEOUT, 5);
        $res = curl_exec($curl);

        if (!\is_string($res)) {
            return null;
        }

        try {
            if (Version::parse($res)) {
                return trim($res);
            }
        } catch (\Exception) {
            return null;
        }

        return null;
    }
}
