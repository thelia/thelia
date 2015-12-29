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

namespace Tinymce;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Action\Document;
use Thelia\Model\ConfigQuery;
use Thelia\Module\BaseModule;

class Tinymce extends BaseModule
{
    /** The module domain for internationalisation */
    const MODULE_DOMAIN = 'tinymce';

    private $jsPath;
    private $webJsPath;
    private $webMediaPath;
    private $webMediaEnvPath;

    public function __construct()
    {
        $this->jsPath = __DIR__.DS.'Resources'.DS.'js'.DS.'tinymce';

        $this->webJsPath = THELIA_WEB_DIR.'tinymce';
        $this->webMediaPath = THELIA_WEB_DIR.'media';
    }

    /**
     * @inheritdoc
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        $fileSystem = new Filesystem();

        //Check for environment
        if ($env = $this->getContainer()->getParameter('kernel.environment')) {
            //Check for backward compatibility
            if ($env !== "prod" && $env !== "dev") {
                //Remove separtion between dev and prod in particular environment
                $env = str_replace('_dev', '', $env);
                $this->webMediaEnvPath = $this->webMediaPath.DS.$env;
            }
        }

        // Create symbolic links or hard copy in the web directory
        // (according to \Thelia\Action\Document::CONFIG_DELIVERY_MODE),
        // to make the TinyMCE code available.
        if (false === $fileSystem->exists($this->webJsPath)) {
            if (ConfigQuery::read(Document::CONFIG_DELIVERY_MODE) === 'symlink') {
                $fileSystem->symlink($this->jsPath, $this->webJsPath);
            } else {
                $fileSystem->mirror($this->jsPath, $this->webJsPath);
            }
        }

        // Create the media directory in the web root , if required
        if (null !== $this->webMediaEnvPath) {
            if (false === $fileSystem->exists($this->webMediaEnvPath)) {
                $fileSystem->mkdir($this->webMediaEnvPath.DS.'upload');
                $fileSystem->mkdir($this->webMediaEnvPath.DS.'thumbs');
            }
        } else {
            if (false === $fileSystem->exists($this->webMediaPath)) {
                $fileSystem->mkdir($this->webMediaPath.DS.'upload');
                $fileSystem->mkdir($this->webMediaPath.DS.'thumbs');
            }
        }

        static::setConfigValue(
            'available_text_areas',
            '#timymce_configuration-id-test_zone, .wysiwyg'
        );
    }

    /**
     * @inheritdoc
     */
    public function postDeactivation(ConnectionInterface $con = null)
    {
        $fileSystem = new Filesystem();

        $fileSystem->remove($this->webJsPath);
    }

    /**
     * @inheritdoc
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        // If we have to delete module data, remove the media directory.
        if ($deleteModuleData) {
            $fileSystem = new Filesystem();

            $fileSystem->remove($this->webMediaPath);
        }
    }
}
