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
use Thelia\Files\FileModelInterface;

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

    /** @var FileModelInterface Document about to be deleted */
    protected $documentToDelete = null;

    /**
     * Constructor
     *
     * @param FileModelInterface $documentToDelete Document about to be deleted
     */
    public function __construct($documentToDelete)
    {
        $this->documentToDelete = $documentToDelete;
    }

    /**
     * Set Document about to be deleted
     *
     * @param FileModelInterface $documentToDelete Document about to be deleted
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
     * @return FileModelInterface
     */
    public function getDocumentToDelete()
    {
        return $this->documentToDelete;
    }

}
