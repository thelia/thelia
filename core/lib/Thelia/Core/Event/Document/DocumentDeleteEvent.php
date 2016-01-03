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

use Thelia\Core\Event\File\FileDeleteEvent;
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
 * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
 */
class DocumentDeleteEvent extends FileDeleteEvent
{
    /**
     * Constructor
     *
     * @param CategoryDocument|ProductDocument|ContentDocument|FolderDocument $documentToDelete Document about to be deleted
     * @param string                                                          $documentType     Document type
     *                                                                                          ex : FileManager::TYPE_CATEGORY
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function __construct($documentToDelete, $documentType)
    {
        parent::__construct($documentToDelete);
    }

    /**
     * Set picture type
     *
     * @param string $documentType Document type
     *
     * @return $this
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function setDocumentType($documentType)
    {
        return $this;
    }

    /**
     * Get picture type
     *
     * @return string
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function getDocumentType()
    {
        throw new \RuntimeException("getDocumentType() is deprecated and no longer supported");
    }

    /**
     * Set Document about to be deleted
     *
     * @param CategoryDocument|ProductDocument|ContentDocument|FolderDocument $documentToDelete Document about to be deleted
     *
     * @return $this
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function setDocumentToDelete($documentToDelete)
    {
        parent::setFileToDelete($documentToDelete);

        return $this;
    }

    /**
     * Get Document about to be deleted
     *
     * @return CategoryDocument|ProductDocument|ContentDocument|FolderDocument
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function getDocumentToDelete()
    {
        return parent::getFileToDelete();
    }
}
