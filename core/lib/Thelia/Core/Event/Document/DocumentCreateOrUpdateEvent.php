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

use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Files\FileModelInterface;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Occurring when an Document is saved
 *
 * @package Document
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
 */
class DocumentCreateOrUpdateEvent extends FileCreateOrUpdateEvent
{
    /**
     * Constructor
     *
     * @param string $documentType Document type
     *                             ex : FileManager::TYPE_CATEGORY
     * @param int    $parentId     Document parent id
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function __construct($documentType, $parentId)
    {
        parent::__construct($parentId);
    }

    /**
     * @param mixed $locale
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function setLocale($locale)
    {
        return $this;
    }

    /**
     * @return mixed
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function getLocale()
    {
        throw new \RuntimeException("getLocale() is deprecated and no longer supported");
    }

    /**
     * Set Document to save
     *
     * @param $document FileModelInterface
     *
     * @return $this
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function setModelDocument($document)
    {
        parent::setModel($document);
    }

    /**
     * Get Document being saved
     *
     * @return FileModelInterface
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function getModelDocument()
    {
        return parent::getModel();
    }

    /**
     * Set picture type
     *
     * @param string $documentType Document type
     *
     * @return $this
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function setDocumentType($documentType)
    {
        return $this;
    }

    /**
     * Get picture type
     *
     * @return string
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function getDocumentType()
    {
        throw new \RuntimeException("getDocumentType() is deprecated and no longer supported");
    }

    /**
     * Set old model value
     *
     * @param FileModelInterface $oldModelDocument
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function setOldModelDocument($oldModelDocument)
    {
        parent::setOldModel($oldModelDocument);
    }

    /**
     * Get old model value
     *
     * @return FileModelInterface
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function getOldModelDocument()
    {
        return parent::getOldModel();
    }
}
