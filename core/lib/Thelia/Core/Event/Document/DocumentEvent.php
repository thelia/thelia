<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
