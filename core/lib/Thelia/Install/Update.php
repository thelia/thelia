<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Install;

use Michelf\Markdown;
use PDO;
use PDOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Config\DefinePropel;
use Thelia\Install\Exception\UpdateException;
use Thelia\Install\Exception\UpToDateException;
use Thelia\Log\Tlog;

/**
 * Class Update
 * @package Thelia\Install
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Update
{
    const SQL_DIR = 'update/sql/';

    const PHP_DIR = 'update/php/';

    const INSTRUCTION_DIR = 'update/instruction/';

    protected static $version = array(
        '0' => '2.0.0-beta1',
        '1' => '2.0.0-beta2',
        '2' => '2.0.0-beta3',
        '3' => '2.0.0-beta4',
        '4' => '2.0.0-RC1',
        '5' => '2.0.0',
        '6' => '2.0.1',
        '7' => '2.0.2',
        '8' => '2.0.3-beta',
        '9' => '2.0.3-beta2',
        '10' => '2.0.3',
        '11' => '2.0.4',
        '12' => '2.0.5',
        '13' => '2.0.6',
        '14' => '2.0.7',
        '15' => '2.0.8',
        '16' => '2.0.9',
        '17' => '2.0.10',
        '18' => '2.1.0-alpha1',
        '19' => '2.1.0-alpha2',
        '20' => '2.1.0-beta1',
        '21' => '2.1.0-beta2',
        '22' => '2.1.0',
        '23' => '2.1.1',
        '24' => '2.1.2',
        '25' => '2.1.3',
        '26' => '2.1.4',
        '27' => '2.1.5',
        '28' => '2.1.6',
        '29' => '2.2.0-alpha1',
        '30' => '2.2.0-alpha2',
        '31' => '2.2.0-beta1',
        '32' => '2.2.0-beta2',
        '33' => '2.2.0-beta3',
        '34' => '2.2.0',
    );

    /** @var bool */
    protected $usePropel = null;

    /** @var null|Tlog */
    protected $logger = null;

    /** @var array log messages */
    protected $logs = [];

    /** @var array post instructions */
    protected $postInstructions = [];

    /** @var array */
    protected $updatedVersions = [];

    /** @var PDO  */
    protected $connection = null;

    /** @var string|null  */
    protected $backupFile = null;

    /** @var string */
    protected $backupDir = 'local/backup/';

    public function __construct($usePropel = true)
    {
        $this->usePropel = $usePropel;

        if ($this->usePropel) {
            $this->logger = Tlog::getInstance();
            $this->logger->setLevel(Tlog::DEBUG);
        } else {
            $this->logs = [];
        }

        $dbConfig = null;

        try {
            $dbConfig = $this->getDatabaseConfig();
        } catch (ParseException $ex) {
            throw new UpdateException("database.yml is not a valid file : " . $ex->getMessage());
        }

        try {
            $this->connection = new \PDO(
                $dbConfig['dsn'],
                $dbConfig['user'],
                $dbConfig['password']
            );
        } catch (\PDOException $ex) {
            throw new UpdateException('Wrong connection information' . $ex->getMessage());
        }
    }

    /**
     * retrieve the database configuration
     *
     * @return array containing the database
     */
    protected function getDatabaseConfig()
    {
        $configPath = THELIA_CONF_DIR . "database.yml";

        if (!file_exists($configPath)) {
            throw new UpdateException("Thelia is not installed yet");
        }

        $definePropel = new DefinePropel(
            new DatabaseConfiguration(),
            Yaml::parse(file_get_contents($configPath)),
            $this->getEnvParameters()
        );

        return $definePropel->getConfig();
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
        $parameters = array();
        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'SYMFONY__')) {
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
        $lastEntry = end(self::$version);

        return $lastEntry == $version;
    }

    public function process()
    {
        $this->updatedVersions = array();

        $currentVersion = $this->getCurrentVersion();
        $this->log('debug', "start update process");

        if (true === $this->isLatestVersion($currentVersion)) {
            $this->log('debug', "You already have the latest version. No update available");
            throw new UpToDateException('You already have the latest version. No update available');
        }

        $index = array_search($currentVersion, self::$version);

        $this->connection->beginTransaction();

        $database = new Database($this->connection);
        $version = null;

        try {
            $size = count(self::$version);

            for ($i = ++$index; $i < $size; $i++) {
                $version = self::$version[$i];
                $this->updateToVersion($version, $database);
                $this->updatedVersions[] = $version;
            }

            $this->connection->commit();
            $this->log('debug', 'update successfully');
        } catch (\Exception $e) {
            $this->connection->rollBack();

            $this->log('error', sprintf('error during update process with message : %s', $e->getMessage()));

            $ex = new UpdateException($e->getMessage(), $e->getCode(), $e->getPrevious());
            $ex->setVersion($version);
            throw $ex;
        }
        $this->log('debug', 'end of update processing');

        return $this->updatedVersions;
    }

    /**
     * Backup current DB to file local/backup/update.sql
     * @return bool if it succeeds, false otherwise
     * @throws \Exception
     */
    public function backupDb()
    {
        $database = new Database($this->connection);

        if (! $this->checkBackupIsPossible()) {
            $message = 'Your database is too big for an automatic backup';

            $this->log('error', $message);

            throw new UpdateException($message);
        }

        $this->backupFile = THELIA_ROOT . $this->backupDir . 'update.sql';
        $backupDir = THELIA_ROOT . $this->backupDir;

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
     * Restores file local/backup/update.sql to current DB
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
            print $ex->getMessage();
            return false;
        }

        return true;
    }

    /**
     * @return null|string
     */
    public function getBackupFile()
    {
        return $this->backupFile;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    protected function log($level, $message)
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

    protected function updateToVersion($version, Database $database)
    {
        // sql update
        $filename = sprintf(
            "%s%s%s",
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::SQL_DIR),
            $version . '.sql'
        );

        if (file_exists($filename)) {
            $this->log('debug', sprintf('inserting file %s', $version . '.sql'));
            $database->insertSql(null, [$filename]);
            $this->log('debug', sprintf('end inserting file %s', $version . '.sql'));
        }

        // php update
        $filename = sprintf(
            "%s%s%s",
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::PHP_DIR),
            $version . '.php'
        );

        if (file_exists($filename)) {
            $this->log('debug', sprintf('executing file %s', $version . '.php'));
            include_once($filename);
            $this->log('debug', sprintf('end executing file %s', $version . '.php'));
        }

        // instructions
        $filename = sprintf(
            "%s%s%s",
            THELIA_SETUP_DIRECTORY,
            str_replace('/', DS, self::INSTRUCTION_DIR),
            $version . '.md'
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

    public function setCurrentVersion($version)
    {
        $currentVersion = null;

        if (null !== $this->connection) {
            try {
                $stmt = $this->connection->prepare('UPDATE config set value = ? where name = ?');
                $stmt->execute([$version, 'thelia_version']);
            } catch (PDOException $e) {
                $this->log('error', sprintf('Error setting current version : %s', $e->getMessage()));

                throw $e;
            }
        }
    }

    /**
     * Returns the database size in Mo
     * @return float
     * @throws \Exception
     */
    public function getDataBaseSize()
    {
        $stmt = $this->connection->query(
            "SELECT sum(data_length) / 1024 / 1024 'size' FROM information_schema.TABLES WHERE table_schema = DATABASE() GROUP BY table_schema"
        );

        if ($stmt->rowCount()) {
            return floatval($stmt->fetch(PDO::FETCH_OBJ)->size);
        }

        throw new \Exception('Impossible to calculate the database size');
    }


    /**
     * Checks whether it is possible to make a data base backup
     *
     * @return bool
     */
    public function checkBackupIsPossible()
    {
        $size = 0;
        if (preg_match('/^(\d+)(.)$/', ini_get('memory_limit'), $matches)) {
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
        return end(self::$version);
    }

    public function getVersions()
    {
        return self::$version;
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
    public function setUpdatedVersions($updatedVersions)
    {
        $this->updatedVersions = $updatedVersions;
    }

    /**
     * Add a new post update instruction
     *
     * @param string $instructions content of the instruction un markdown format
     */
    protected function addPostInstructions($version, $instructions)
    {
        if (!isset($this->postInstructions[$version])) {
            $this->postInstructions[$version] = [];
        }

        $this->postInstructions[$version][] = $instructions;
    }

    /**
     * Return the content of all instructions
     *
     * @param string $format the format of the export : plain (default) or html
     * @return string the instructions in plain text or html
     */
    public function getPostInstructions($format = 'plain')
    {
        $content = [];

        if (count($this->postInstructions) != 0) {
            return null;
        }

        ksort($this->postInstructions);

        foreach ($this->postInstructions as $version => $instructions) {
            $content[] = sprintf("\n\n## %s", $version);
            foreach ($instructions as $instruction) {
                $content[] = sprintf("\n\n%s", $instruction);
            }
        }

        $content = implode('', $content);

        if ($format === 'html') {
            $content = Markdown::defaultTransform($content);
        }

        return $content;
    }

    public function hasPostInstructions()
    {
        return (count($this->postInstructions) !== 0);
    }


}
