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

namespace Thelia\Core\FileFormat\Formatter;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Translation\Translator;

/**
 * Class FormatterData
 * @package Thelia\Core\FileFormat\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FormatterData
{
    /** @var array */
    protected $data;

    /** @var Translator */
    protected $translator;

    public function __construct()
    {
        $this->translator = Translator::getInstance();
    }

    public function loadModelCriteria(ModelCriteria $criteria)
    {

        $propelData =
            $criteria
                ->find()
        ;

        if (empty($propelData)) {
            return null;
        }

    }
}
