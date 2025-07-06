<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Install;

use Michelf\Markdown;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Exception\ParseException;
use Thelia\Config\DatabaseConfigurationSource;
use Thelia\Core\Thelia;
use Thelia\Install\Exception\UpdateException;
use Thelia\Install\Exception\UpToDateException;
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

    protected $version;

    /** @var Tlog|null */
    protected $logger;

    /** @var array log messages */
    protected $logs = [];

    /** @var array post instructions */
    protected $postInstructions = [];

    /** @var array */
    protected $updatedVersions = [];

    /** @var \PDO */
    protected $connection;

    /** @var string|null */
    protected $backupFile;

    /** @var string */
    protected $backupDir = 'local/backup/';

    /** @var array */
    protected $messages = [];

    /** @var Translator */
    protected $translator;

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

        $dbConfig = null;

        try {
            $this->connection = Propel::getConnection(
                ProductTableMap::DATABASE_NAME
            );

            // Get the PDO connection from the WrappedConnection
            if ($this->connection instanceof ConnectionWrapper) {
                $this->connection = $this->connection->getWrappedConnection();
            }
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
     *
     * @return \PDO
     */
    protected function getDatabasePDO()
    {
        if (!Thelia::isInstalled()) {
            throw new UpdateException('Thelia is not installed yet');
        }

        $definePropel = new DatabaseConfigurationSource(
            $this->getEnvParameters()
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
    protected function getEnvParameters()
    {
        $parameters = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'SYMFONY__')) {
                $parameters[strtolower(str_replace('__', '.', substr($key, 9)))] = $value;
            }
        }

        return $parameters;
    }

    public function isLatestVersion($version = null)
    {
        if (null === $version) {
            $version = $this->getCurrentVersion();
        }
        $lastEntry = end($this->version);

        return $lastEntry == $version;
    }

    public function process()
    {
        $this->updatedVersions = [];

        $currentVersion = $this->getCurrentVersion();
        $this->log('debug', 'start update process');

        if (true === $this->isLatestVersion($currentVersion)) {
            $this->log('debug', 'You already have the latest version. No update available');
            throw new UpToDateException('You already have the latest version. No update available');
        }

        $index = array_search($currentVersion, $this->version);

        $this->connection->beginTransaction();

        $database = new Database($this->connection);
        $version = null;

        try {
            $size = \count($this->version);

            for ($i = ++$index; $i < $size; ++$i) {
                $version = $this->version[$i];
                $this->updateToVersion($version, $database);
                $this->updatedVersions[] = $version;
            }

            $currentVersion = Version::parse();
            $this->log('debug', sprintf('setting database configuration to %s', $currentVersion['version']));
            $updateConfigVersion = [
                'thelia_version' => $currentVersion['version'],
                'thelia_major_version' => $currentVersion['major'],
                'thelia_minus_version' => $currentVersion['minus'],
                'thelia_release_version' => $currentVersion['release'],
                'thelia_extra_version' => $currentVersion['extra'],
            ];

            foreach ($updateConfigVersion as $name => $value) {
                $stmt = $this->connection->prepare('SELECT * FROM `config` WHERE `name` = ?');
                $stmt->execute([$name]);

                if ($stmt->rowCount()) {
                    $stmt = $this->connection->prepare('UPDATE `config` SET `value` = ? WHERE `name` = ?');
                    $stmt->execute([$version, $value]);
                } else {
                    $stmt = $this->connection->prepare('INSERT INTO `config` (?) VALUES (?)');
                    $stmt->execute([$version, $value]);
                }
            }

            $this->connection->commit();
            $this->log('debug', 'update successfully');
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->log('error', sprintf('error during update process with message : %s', $e->getMessage()));

            $ex = new UpdateException($e->getMessage(), $e->getCode(), $e->getPrevious());
            $ex->setVersion($version);
            throw $ex;
        }
        $this->log('debug', 'end of update processing');

        return $this->updatedVersions;
    }

    /**
     * Backup current DB to file local/backup/update.sql.
     *
     * @throws \Exception
     *
     * @return bool if it succeeds, false otherwise
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
            $this->log('debug', sprintf('Backup database to file : %s', $this->backupFile));

            // test if backup dir exists
            if (!$fs->exists($backupDir)) {
                $fs->mkdir($backupDir);
            }

            if (!is_writable($backupDir)) {
                throw new \RuntimeException(sprintf('impossible to write in directory : %s', $backupDir));
            }

            // test if backup file already exists
            if ($fs->exists($this->backupFile)) {
                // remove file
                $fs->remove($this->backupFile);
            }

            $database->backupDb($this->backupFile);
        } catch (\Exception $ex) {
            $this->log('error', sprintf('error during backup process with message : %s', $ex->getMessage()));
            throw $ex;
        }
    }

    /**
     * Restores file local/backup/update.sql to current DB.
     *
     * @return bool if it succeeds, false otherwise
     */
    public function restoreDb()
    {
        $database = new Database($this->connection);

        try {
            $this->log('debug', sprintf('Restore database with file : %s', $this->backupFile));

            if (!file_exists($this->backupFile)) {
                return false;
            }

            $database->restoreDb($this->backupFile);
        } catch (\Exception $ex) {
            $this->log('error', sprintf('error during restore process with message : %s', $ex->getMessage()));
            echo $ex->getMessage();

            return false;
        }

        return true;
    }

    /**
     * @return string|null
     */
    public function getBackupFile()
    {
        return $this->backupFile;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    protected function log($level, $message): void
    {
        if ($this->usePropel) {
            switch ($level) {
                case 'debug':
                    $this->logger->debug($message);
                    break;
                case 'info':
                    $this->logger->info($message);
                    break;
                case 'notice':
                    $this->logger->notice($message);
                    break;
                case 'warning':
                    $this->logger->warning($message);
                    break;
                case 'error':
                    $this->logger->error($message);
                    break;
                case 'critical':
                    $this->logger->critical($message);
                    break;
            }
        } else {
            $this->logs[] = [$level, $message];
        }
    }

    protected function updateToVersion($version, Database $database): void
    {
        // sql update
        $filename = sprintf(
            '%s%s%s',
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::SQL_DIR),
            $version.'.sql'
        );

        if (file_exists($filename)) {
            $this->log('debug', sprintf('inserting file %s', $version.'.sql'));
            $database->insertSql(null, [$filename]);
            $this->log('debug', sprintf('end inserting file %s', $version.'.sql'));
        }

        // php update
        $filename = sprintf(
            '%s%s%s',
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::PHP_DIR),
            $version.'.php'
        );

        if (file_exists($filename)) {
            $this->log('debug', sprintf('executing file %s', $version.'.php'));
            include_once $filename;
            $this->log('debug', sprintf('end executing file %s', $version.'.php'));
        }

        // instructions
        $filename = sprintf(
            '%s%s%s',
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::INSTRUCTION_DIR),
            $version.'.md'
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
        $currentVersion = null;

        if (null !== $this->connection) {
            try {
                $stmt = $this->connection->prepare('UPDATE config set value = ? where name = ?');
                $stmt->execute([$version, 'thelia_version']);
            } catch (\PDOException $e) {
                $this->log('error', sprintf('Error setting current version : %s', $e->getMessage()));

                throw $e;
            }
        }
    }

    /**
     * Returns the database size in Mo.
     *
     * @throws \Exception
     *
     * @return float
     */
    public function getDataBaseSize()
    {
        $stmt = $this->connection->query(
            "SELECT sum(data_length) / 1024 / 1024 'size' FROM information_schema.TABLES WHERE table_schema = DATABASE() GROUP BY table_schema"
        );

        if ($stmt->rowCount()) {
            return (float) $stmt->fetch(\PDO::FETCH_OBJ)->size;
        }

        throw new \Exception('Impossible to calculate the database size');
    }

    /**
     * Checks whether it is possible to make a data base backup.
     *
     * @return bool
     */
    public function checkBackupIsPossible()
    {
        $size = 0;
        if (preg_match('/^(\d+)(.)$/', \ini_get('memory_limit'), $matches)) {
            switch (strtolower($matches[2])) {
                case 'k':
                    $size = $matches[1] / 1024;
                    break;
                case 'm':
                    $size = $matches[1];
                    break;
                case 'g':
                    $size = $matches[1] * 1024;
                    break;
            }
        }

        if ($this->getDataBaseSize() > ($size - 64) / 8) {
            return false;
        }

        return true;
    }

    public function getLatestVersion()
    {
        return end($this->version);
    }

    public function getVersions()
    {
        return $this->version;
    }

    /**
     * @return array
     */
    public function getUpdatedVersions()
    {
        return $this->updatedVersions;
    }

    /**
     * @param array $updatedVersions
     */
    public function setUpdatedVersions($updatedVersions): void
    {
        $this->updatedVersions = $updatedVersions;
    }

    /**
     * Add a new post update instruction.
     *
     * @param string $instructions content of the instruction un markdown format
     */
    protected function addPostInstructions($version, $instructions): void
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
    public function getPostInstructions($format = 'plain')
    {
        $content = [];

        if (\count($this->postInstructions) == 0) {
            return null;
        }

        ksort($this->postInstructions);

        foreach ($this->postInstructions as $version => $instructions) {
            $content[] = sprintf('## %s', $version);
            foreach ($instructions as $instruction) {
                $content[] = sprintf('%s', $instruction);
            }
        }

        $content = implode("\n\n", $content);

        if ($format === 'html') {
            $content = Markdown::defaultTransform($content);
        }

        return $content;
    }

    public function hasPostInstructions()
    {
        return \count($this->postInstructions) !== 0;
    }

    public function getVersionList()
    {
        $list = [];
        $finder = new Finder();
        $path = sprintf('%s%s', THELIA_SETUP_DIRECTORY, str_replace('/', DS, self::SQL_DIR));
        $sort = function (\SplFileInfo $a, \SplFileInfo $b) {
            $a = strtolower(substr($a->getRelativePathname(), 0, -4));
            $b = strtolower(substr($b->getRelativePathname(), 0, -4));

            return version_compare($a, $b);
        };

        $files = $finder->name('*.sql')->in($path)->sort($sort);
        foreach ($files as $file) {
            $list[] = substr($file->getRelativePathname(), 0, -4);
        }

        return $list;
    }

    /**
     * @param string $message
     * @param string $type
     *
     * @return $this
     */
    public function setMessage($message, $type = 'info')
    {
        $this->messages[] = [$message, $type];

        return $this;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function trans($string)
    {
        return $this->translator ? $this->translator->trans($string) : $string;
    }

    /**
     * @return $this
     */
    public function setTranslator(Translator $translator)
    {
        $this->translator = $translator;

        return $this;
    }

    public function getWebVersion()
    {
        $url = 'http://thelia.net/version.php';
        $curl = curl_init($url);
        curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, \CURLOPT_HEADER, false);
        curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, \CURLOPT_TIMEOUT, 5);
        $res = curl_exec($curl);

        try {
            if (Version::parse($res)) {
                return trim($res);
            }
        } catch (\Exception) {
            return null;
        }
    }
}
