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
namespace Thelia\Install;

use PDO;
use PDOException;
use Thelia\Core\Translation\Translator;

/**
 * Class CheckDatabaseConnection.
 *
 * Take care of integration tests (database connection)
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CheckDatabaseConnection extends BaseInstall
{
    protected $validationMessages = [];

    /** @var bool If permissions are OK */
    protected $isValid = true;

    /**
     * @var PDO instance
     */
    protected $connection;

    /**
     * Constructor.
     *
     * @param string     $host          Database host information
     * @param string     $user          Database user information
     * @param string     $password      Database password information
     * @param int        $port          Database port information
     * @param bool       $verifyInstall If verify install
     * @param Translator $translator    Translator Service
     *                                  necessary for install wizard
     */
    public function __construct(protected $host, protected $user, protected $password, protected $port, $verifyInstall = true, protected ?Translator $translator = null)
    {
        parent::__construct($verifyInstall);
    }

    /**
     * Perform database connection check.
     *
     * @return bool
     */
    public function exec()
    {
        $dsn = 'mysql:host=%s;port=%s';

        try {
            $this->connection = new PDO(
                sprintf($dsn, $this->host, $this->port),
                $this->user,
                $this->password
            );
        } catch (PDOException) {
            $this->validationMessages = 'Wrong connection information';

            $this->isValid = false;
        }

        return $this->isValid;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
