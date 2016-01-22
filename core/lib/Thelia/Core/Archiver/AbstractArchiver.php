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

namespace Thelia\Core\Archiver;

/**
 * Class AbstractArchiver
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractArchiver implements ArchiverInterface
{
    /**
     * @var mixed The archive resource
     */
    protected $archive;

    /**
     * @var string Path to archive
     */
    protected $archivePath;

    public function getArchivePath()
    {
        return $this->archivePath;
    }

    public function setArchivePath($archivePath)
    {
        $this->archivePath = $archivePath;

        return $this;
    }

    public function add($path, $pathInArchive = null)
    {
        $path = realpath($path);
        if (!file_exists($path)) {
            throw new \RuntimeException('File ' . $path . ' doesn\'t exists');
        }

        if ($pathInArchive === null) {
            $pathInArchive = basename($path);
        }

        if (is_dir($path)) {
            foreach (new \DirectoryIterator($path) as $dirItem) {
                if ($dirItem->isDot()) {
                    continue;
                }
                $this->add($dirItem->getPathname(), $pathInArchive . DS . $dirItem->getFilename());
            }
        } else {
            $this->archive->addFile($path, $pathInArchive);
        }

        return $this;
    }

    public function extract($toPath = null)
    {
        $this->archive->extractTo($toPath);
    }
}
