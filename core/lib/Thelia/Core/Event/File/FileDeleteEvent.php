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
namespace Thelia\Core\Event\File;

use Thelia\Core\Event\ActionEvent;
use Thelia\Files\FileModelInterface;

/**
 * Event fired when a file is about to be deleted.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class FileDeleteEvent extends ActionEvent
{
    /**
     * Constructor.
     *
     * @param FileModelInterface $fileToDelete Image about to be deleted
     */
    public function __construct(protected $fileToDelete)
    {
    }

    /**
     * Set Image about to be deleted.
     *
     * @param FileModelInterface $fileToDelete Image about to be deleted
     *
     * @return $this
     */
    public function setFileToDelete($fileToDelete): static
    {
        $this->fileToDelete = $fileToDelete;

        return $this;
    }

    /**
     * Get Image about to be deleted.
     *
     * @return FileModelInterface
     */
    public function getFileToDelete()
    {
        return $this->fileToDelete;
    }
}
