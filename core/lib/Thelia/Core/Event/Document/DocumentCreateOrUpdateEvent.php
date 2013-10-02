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
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
 * Occurring when a Document is saved
 *
 * @package Document
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class DocumentCreateOrUpdateEvent extends ActionEvent
{

    /** @var CategoryDocument|ProductDocument|ContentDocument|FolderDocument model to save */
    protected $modelDocument = array();

    /** @var CategoryDocument|ProductDocument|ContentDocument|FolderDocument model to save */
    protected $oldModelDocument = array();

    /** @var UploadedFile Document file to save */
    protected $uploadedFile = null;

    /** @var int Document parent id */
    protected $parentId = null;

    /** @var string Document type */
    protected $documentType = null;

    /** @var string Parent name */
    protected $parentName = null;

    /**
     * Constructor
     *
     * @param string $documentType Document type
     *                             ex : FileManager::TYPE_CATEGORY
     * @param int $parentId Document parent id
     */
    public function __construct($documentType, $parentId)
    {
        $this->documentType = $documentType;
        $this->parentId  = $parentId;
    }

    /**
     * Set Document to save
     *
     * @param CategoryDocument|ProductDocument|ContentDocument|FolderDocument $document Document to save
     *
     * @return $this
     */
    public function setModelDocument($document)
    {
        $this->modelDocument = $document;

        return $this;
    }

    /**
     * Get Document being saved
     *
     * @return CategoryDocument|ProductDocument|ContentDocument|FolderDocument
     */
    public function getModelDocument()
    {
        return $this->modelDocument;
    }

    /**
     * Set document type
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
     * Get document type
     *
     * @return string
     */
    public function getDocumentType()
    {
        return $this->documentType;
    }

    /**
     * Set Document parent id
     *
     * @param int $parentId Document parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * Get Document parent id
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * Set uploaded file
     *
     * @param UploadedFile $uploadedFile File being uploaded
     *
     * @return $this
     */
    public function setUploadedFile($uploadedFile)
    {
        $this->uploadedFile = $uploadedFile;

        return $this;
    }

    /**
     * Get uploaded file
     *
     * @return UploadedFile
     */
    public function getUploadedFile()
    {
        return $this->uploadedFile;
    }

    /**
     * Set parent name
     *
     * @param string $parentName Parent name
     *
     * @return $this
     */
    public function setParentName($parentName)
    {
        $this->parentName = $parentName;

        return $this;
    }

    /**
     * Get parent name
     *
     * @return string
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * Set old model value
     *
     * @param CategoryDocument|ContentDocument|FolderDocument|ProductDocument $oldModelDocument
     */
    public function setOldModelDocument($oldModelDocument)
    {
        $this->oldModelDocument = $oldModelDocument;
    }

    /**
     * Get old model value
     *
     * @return CategoryDocument|ContentDocument|FolderDocument|ProductDocument
     */
    public function getOldModelDocument()
    {
        return $this->oldModelDocument;
    }

}
