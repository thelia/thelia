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

namespace Thelia\Form\Lang;

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class LangUpdateForm
 * @package Thelia\Form\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangUpdateForm extends LangCreateForm
{
    public function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add('id', 'hidden', array(
                'constraints' => array(
                    new NotBlank(),
                    new GreaterThan(array('value' => 0)),
                ),
            ));
    }

    public function getName()
    {
        return 'thelia_lang_update';
    }
}
