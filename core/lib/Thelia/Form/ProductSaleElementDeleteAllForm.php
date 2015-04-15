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

namespace Thelia\Form;

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Model\Currency;
use Thelia\Core\Translation\Translator;

class ProductSaleElementDeleteAllForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "product_id",
                "integer",
                array(
                    "constraints" => array(new GreaterThan(array('value' => 0))),
                )
            )
        ;
    }
}