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

namespace Thelia\Core\Event;

class DocumentEvent extends CachedFileEvent
{
    protected $document_path;
    protected $document_url;

    /**
     * @return the document file path
     */
    public function getDocumentPath()
    {
        return $this->document_path;
    }

    /**
     * @param string $document_path the document file path
     */
    public function setDocumentPath($document_path)
    {
        $this->document_path = $document_path;

        return $this;
    }

    /**
     * @return the document URL
     */
    public function getDocumentUrl()
    {
        return $this->document_url;
    }

    /**
     * @param string $document_url the document URL
     */
    public function setDocumentUrl($document_url)
    {
        $this->document_url = $document_url;

        return $this;
    }

}
