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

namespace Thelia\Core\Event\Document;

use Thelia\Core\Event\CachedFileEvent;

/**
 * Class DocumentEvent
 *
 * @package Thelia\Core\Event
 */
class DocumentEvent extends CachedFileEvent
{
    protected $documentPath;
    protected $documentUrl;

    /**
     * Get Document path
     *
     * @return string The document file path
     */
    public function getDocumentPath()
    {
        return $this->documentPath;
    }

    /**
     * Set Document path
     *
     * @param string $documentPath the document file path
     *
     * @return $this
     */
    public function setDocumentPath($documentPath)
    {
        $this->documentPath = $documentPath;

        return $this;
    }

    /**
     * Get Document URL
     *
     * @return string The document URL
     */
    public function getDocumentUrl()
    {
        return $this->documentUrl;
    }

    /**
     * Set Document URL
     *
     * @param string $documentUrl the document URL
     *
     * @return $this
     */
    public function setDocumentUrl($documentUrl)
    {
        $this->documentUrl = $documentUrl;

        return $this;
    }
}
