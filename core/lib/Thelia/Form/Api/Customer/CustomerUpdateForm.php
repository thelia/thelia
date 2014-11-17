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

namespace Thelia\Form\Api\Customer;

use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\CustomerUpdateForm as BaseCustomerUpdateForm;

/**
 * Class CustomerUpdateForm
 * @package Thelia\Form\Api\Customer
 * @author Manuel Raynaud <manu@thelia.net>
 */
class CustomerUpdateForm extends BaseCustomerUpdateForm
{
    public function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add('lang', 'lang_id')
            ->add('id', 'customer_id')
        ;
    }

    public function getName()
    {
        return '';
    }
}
