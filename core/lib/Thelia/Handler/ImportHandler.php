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

namespace Thelia\Handler;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;

/**
 * Class ImportHandler
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ImportHandler
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface An event dispatcher interface
     */
    protected $eventDispatcher;

    /**
     * Class constructor
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *  An event dispatcher interface
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Get import model based on given identifier
     *
     * @param integer $importId          An import identifier
     * @param boolean $dispatchException Dispatch exception if model doesn't exist
     *
     * @throws \ErrorException
     *
     * @return null|\Thelia\Model\Import
     */
    public function getImport($importId, $dispatchException = false)
    {
        $import = (new ImportQuery)->findPk($importId);

        if ($import === null && $dispatchException) {
            throw new \ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%id" in the imports',
                    [
                        '%id' => $importId
                    ]
                )
            );
        }

        return $import;
    }

    /**
     * Get import category model based on given identifier
     *
     * @param integer $importCategoryId  An import category identifier
     * @param boolean $dispatchException Dispatch exception if model doesn't exist
     *
     * @throws \ErrorException
     *
     * @return null|\Thelia\Model\ImportCategory
     */
    public function getCategory($importCategoryId, $dispatchException = false)
    {
        $category = (new ImportCategoryQuery)->findPk($importCategoryId);

        if ($category === null && $dispatchException) {
            throw new \ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%id" in the import categories',
                    [
                        '%id' => $importCategoryId
                    ]
                )
            );
        }

        return $category;
    }
}
