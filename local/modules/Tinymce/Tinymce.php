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

namespace Tinymce;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Action\Document;
use Thelia\Model\ConfigQuery;
use Thelia\Module\BaseModule;

class Tinymce extends BaseModule
{
    /** The module domain for internationalisation */
    public const MODULE_DOMAIN = 'tinymce';

    private $jsPath;
    private $webJsPath;

    public function __construct()
    {
        $this->jsPath = __DIR__.DS.'Resources'.DS.'js'.DS.'tinymce';

        $this->webJsPath = THELIA_WEB_DIR.'tinymce';
    }

    public function postActivation(ConnectionInterface $con = null): void
    {
        $fileSystem = new Filesystem();

        if (false === $fileSystem->exists($this->webJsPath)) {
            $this->publishTinymce($fileSystem);
        }

        static::setConfigValue(
            'available_text_areas',
            '#timymce_configuration-id-test_zone, .wysiwyg'
        );
    }

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        $fileSystem = new Filesystem();

        // The bundled file manager was removed. When the code was published as a hard copy, an
        // older version left its PHP entry points in web/tinymce/filemanager, so the published
        // directory has to be rebuilt from the module.
        if ($fileSystem->exists($this->webJsPath)) {
            $fileSystem->remove($this->webJsPath);

            $this->publishTinymce($fileSystem);
        }
    }

    public function postDeactivation(ConnectionInterface $con = null): void
    {
        $fileSystem = new Filesystem();

        $fileSystem->remove($this->webJsPath);
    }

    /**
     * Make the TinyMCE code available in the web directory, as a symbolic link or a hard copy
     * according to \Thelia\Action\Document::CONFIG_DELIVERY_MODE.
     */
    private function publishTinymce(Filesystem $fileSystem): void
    {
        if (ConfigQuery::read(Document::CONFIG_DELIVERY_MODE) === 'symlink') {
            $fileSystem->symlink($this->jsPath, $this->webJsPath);

            return;
        }

        $fileSystem->mirror($this->jsPath, $this->webJsPath);
    }
}
