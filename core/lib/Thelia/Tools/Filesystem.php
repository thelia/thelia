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

namespace Thelia\Tools;

use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class Filesystem
{
    /** @var SymfonyFilesystem */
    private $filesystem;

    public function __construct()
    {
        $this->filesystem = new SymfonyFilesystem();
    }

    /**
     * Same function as \Symfony\Component\Filesystem\Filesystem::makePathRelative(), but converts back separators
     * to the platform separator.
     *
     * @see \Symfony\Component\Filesystem\Filesystem::makePathRelative()
     */
    public function makePathRelative($endPath, $startPath)
    {
        $path = $this->filesystem->makePathRelative($endPath, $startPath);

        if ('/' !== DS) {
            $path = str_replace('/', DS, $path);
        }

        return $path;
    }
}
