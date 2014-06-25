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
use Thelia\Module\BaseModule;

class Tinymce extends BaseModule
{
    /** The module domain for internationalisation */
    const MODULE_DOMAIN = "tinymce";

    private $jsPath, $mediaPath, $webJsPath, $webMediaPath;

    public function __construct()
    {
        $this->jsPath    = __DIR__ . DS .'Resources' . DS . 'js' . DS . 'tinymce';

        $this->webJsPath    = THELIA_WEB_DIR . 'tinymce';
        $this->webMediaPath = THELIA_WEB_DIR . 'media';
    }
    /**
     * @inheritdoc
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        $fs = new Filesystem();

        // Create symbolic links in the web directory, to make the TinyMCE code available.
        if (false === $fs->exists($this->webJsPath)) {
            $fs->symlink($this->jsPath, $this->webJsPath);
        }

        // Create the media directory in the web root, if required
        if (false === $fs->exists($this->webMediaPath)) {

            $fs->mkdir($this->webMediaPath."/upload");
            $fs->mkdir($this->webMediaPath."/thumbs");
        }
    }

    /**
     * @inheritdoc
     */
    public function postDeactivation(ConnectionInterface $con = null)
    {
        $fs = new Filesystem();

        $fs->remove($this->webJsPath);
    }

    /**
     * @inheritdoc
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        // If we have to delete module data, remove the media directory.
        if ($deleteModuleData) {
            $fs = new Filesystem();

            $fs->remove($this->webMediaPath);
        }
    }
}