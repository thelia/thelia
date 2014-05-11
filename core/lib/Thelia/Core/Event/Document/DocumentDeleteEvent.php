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
