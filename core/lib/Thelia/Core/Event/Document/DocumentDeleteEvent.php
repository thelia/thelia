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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CategoryDocument;
use Thelia\Model\ContentDocument;
use Thelia\Model\FolderDocument;
use Thelia\Model\ProductDocument;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Occurring when a Document is about to be deleted
 *
 * @package Document
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class DocumentDeleteEvent extends ActionEvent
{
    /** @var string Document type */
    protected $documentType = null;

    /** @var CategoryDocument|ProductDocument|ContentDocument|FolderDocument Document about to be deleted */
    protected $documentToDelete = null;

    /**
     * Constructor
     *
     * @param CategoryDocument|ProductDocument|ContentDocument|FolderDocument $documentToDelete Document about to be deleted
     * @param string                                                          $documentType     Document type
     *                                                                                          ex : FileManager::TYPE_CATEGORY
     */
    public function __construct($documentToDelete, $documentType)
    {
        $this->documentToDelete = $documentToDelete;
        $this->documentType = $documentType;
    }

    /**
     * Set picture type
     *
     * @param string $documentType Document type
     *
     * @return $this
     */
    public function setDocumentType($documentType)
    {
        $this->documentType = $documentType;

        return $this;
    }

    /**
     * Get picture type
     *
     * @return string
     */
    public function getDocumentType()
    {
        return $this->documentType;
    }

    /**
     * Set Document about to be deleted
     *
     * @param CategoryDocument|ProductDocument|ContentDocument|FolderDocument $documentToDelete Document about to be deleted
     *
     * @return $this
     */
    public function setDocumentToDelete($documentToDelete)
    {
        $this->documentToDelete = $documentToDelete;

        return $this;
    }

    /**
     * Get Document about to be deleted
     *
     * @return CategoryDocument|ProductDocument|ContentDocument|FolderDocument
     */
    public function getDocumentToDelete()
    {
        return $this->documentToDelete;
    }

}
