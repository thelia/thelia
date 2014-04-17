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
    /**
     * YOU HAVE TO IMPLEMENT HERE ABSTRACT METHODD FROM BaseModule Class
     * Like install and destroy
     */
    public function postActivation(ConnectionInterface $con = null)
    {
        $fs = new Filesystem();

        $fs->mirror(__DIR__ . DS .'Resources'.DS.'js'.DS.'tinymce', THELIA_WEB_DIR . 'tinymce');
        $fs->symlink(__DIR__ . DS .'Resources'.DS.'media', THELIA_WEB_DIR . 'media');
    }
}
