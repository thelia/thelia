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
    private $jsPath, $mediaPath, $webJsPath, $webMediaPath;

    public function __construct()
    {
        $this->jsPath    = __DIR__ . DS .'Resources' . DS . 'js' . DS . 'tinymce';
        $this->mediaPath = __DIR__ . DS .'Resources' . DS . 'media';

        $this->webJsPath    = THELIA_WEB_DIR . 'tinymce';
        $this->webMediaPath = THELIA_WEB_DIR . 'media';
    }
    /**
     * @inheritdoc
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        // Create symbolic links in the web directory, to make the TinyMCE code
        // and the content of the 'media' directory available.
        $fs = new Filesystem();

        if (false === $fs->exists($this->webJsPath)) {
            $fs->symlink($this->jsPath, $this->webJsPath);
        }

        // Create the media directory in the web root
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
        $fs->remove($this->mediaPath);
    }

    /**
     * @inheritdoc
     */
    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        if ($deleteModuleData) {
            $fs = new Filesystem();

            $fs->remove($this->webMediaPath);
        }
    }
}