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

namespace Thelia\Form\OrderStatus;

use Symfony\Component\Validator\Constraints\GreaterThan;

/**
 * Class OrderStatusModificationForm
 * @package Thelia\Form\OrderStatus
 * @author  Gilles Bourgeat <gbourgeat@openstudio.fr>
 * @since 2.4
 */
class OrderStatusModificationForm extends OrderStatusCreationForm
{
    protected function buildForm()
    {
        $this->formBuilder->add("id", "hidden", [
            'required'    => true,
            "constraints" => [
                new GreaterThan(['value' => 0])
            ]
        ]);

        parent::buildForm();

        $this->addStandardDescFields();
    }

    public function getName()
    {
        return 'thelia_order_status_modification';
    }
}
