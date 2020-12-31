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

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\CustomerLogin as BaseCustomerLogin;
use Symfony\Component\Validator\Constraints;

/**
 * Customer login form for the API.
 *
 * Class CustomerLogin
 * @package Thelia\Form\Api\Customer
 * @author Baptiste Cabarrou <bcabarrou@openstudio.fr>
 */
class CustomerLogin extends BaseCustomerLogin
{
    public function buildForm()
    {
        parent::buildForm();

        $this->formBuilder->remove('remember_me');
        $this->formBuilder->remove('account');

        $this->formBuilder->add("account", TextType::class, array(
            "constraints" => array(
                new Constraints\Callback(array($this, "verifyAccount"))),
            "label_attr" => array(
                "for" => "account",
            ),
            "empty_data" => 1,
            "required"    => false,
        ));
    }

    public function getName()
    {
        return '';
    }
}
