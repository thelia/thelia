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

namespace Thelia\Form\State;

use Symfony\Component\Validator\Constraints\GreaterThan;
use Thelia\Form\StandardDescriptionFieldsTrait;

/**
 * Class StateModificationForm
 * @package Thelia\Form\State
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateModificationForm extends StateCreationForm
{
    use StandardDescriptionFieldsTrait;

    protected function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add('id', 'hidden', ['constraints' => [new GreaterThan(['value' => 0])]])
        ;
    }

    public function getName()
    {
        return "thelia_state_modification";
    }
}
