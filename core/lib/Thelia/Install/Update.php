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

use PDO;
use PDOException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Thelia\Install\Exception\UpdateException;
use Thelia\Install\Exception\UpToDateException;
use Thelia\Log\Tlog;

/**
 * Class Update
 * @package Thelia\Install
 * @author Manuel Raynaud <manu@thelia.net>
 */
class Update
{
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
        '12' => '2.1.0-alpha1',
    );

    protected $usePropel = null;

    /** @var null|Tlog */
    protected $logger = null;

    /** @var array log messages */
    protected $logs = [];

    /** @var array */
    protected $updatedVersions = [];

    /** @var PDO  */
    protected $connection = null;

    public function __construct($usePropel = true)
    {
        $this->usePropel = $usePropel;

        if ($this->usePropel) {
            $this->logger = Tlog::getInstance();
            $this->logger->setLevel(Tlog::DEBUG);
        } else {
            $this->logger = [];
        }

        $dbConfig = null;

        $configPath = THELIA_ROOT . "/local/config/database.yml";

        if (!file_exists($configPath)) {
            throw new UpdateException("Thelia is not installed yet");
        }

        try {
            $dbConfig = Yaml::parse($configPath);
            $dbConfig = $dbConfig['database']['connection'];
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
        if (file_exists(THELIA_ROOT . '/setup/update/' . $version . '.sql')) {
            $this->log('debug', sprintf('inserting file %s', $version . '.sql'));
            $database->insertSql(null, array(THELIA_ROOT . '/setup/update/' . $version . '.sql'));
            $this->log('debug', sprintf('end inserting file %s', $version . '.sql'));
        }

        // php update
        if (file_exists(THELIA_ROOT . '/setup/update/' . $version . '.php')) {
            $this->log('debug', sprintf('executing file %s', $version . '.php'));
            include_once THELIA_ROOT . '/setup/update/'.$version . '.php';
            $this->log('debug', sprintf('end executing file %s', $version . '.php'));
        }

        $this->setCurrentVersion($version);
    }

    public function getCurrentVersion()
    {
        $currentVersion = null;

        if (null !== $this->connection) {
            try {
                $stmt = $this->connection->prepare('SELECT * from config where name = ? LIMIT 1');
                $stmt->execute(['thelia_version']);
                if (false !== $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $currentVersion = $row['value'];
                }
            } catch (PDOException $e) {
                $this->log('error', sprintf('Error retrieving current version : %s', $e->getMessage()));

                throw $e;
            }
        }

        return $currentVersion;
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
}
