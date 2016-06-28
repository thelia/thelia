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
use Thelia\Form\CustomerCreateForm as BaseCustomerCreateForm;

/**
 * Class CustomerCreateForm
 * @package Thelia\Form\Api\Customer
 * @author manuel raynaud <manu@raynaud.io>
 */
class CustomerCreateForm extends BaseCustomerCreateForm
{
    public function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->remove('email_confirm')
            ->remove('password_confirm')
            ->remove('agreed')
            ->add('lang_id', 'integer', [
                'constraints' => [
                    new NotBlank(),
                ]
            ]);
    }

    public function getName()
    {
        return '';
    }
}
