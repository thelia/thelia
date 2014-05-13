<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\Base\Api as BaseApi;
use Thelia\Tools\Password;

class Api extends BaseApi
{

    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setApiKey(Password::generateHexaRandom(25));

        $this->generateSecureKey();

        return true;
    }

    private function getKeyDir()
    {
        return THELIA_CONF_DIR . DS . 'key';
    }

    private function generateSecureKey()
    {
        $fs = new Filesystem();
        $dir = $this->getKeyDir();
        if (!$fs->exists($dir)) {
            $fs->mkdir($dir, 0700);
        }

        $file = $dir . DS . $this->getApiKey().".key";
        $fs->touch($file);
        file_put_contents($file, Password::generateHexaRandom(45));
        $fs->chmod($file, 0600);

    }

    public function getSecureKey()
    {
        return file_get_contents($this->getKeyDir() . DS . $this->getApiKey() . '.key');
    }

}
