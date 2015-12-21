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

namespace Thelia\Form\Area;

use Symfony\Component\Validator\Constraints\GreaterThan;

/**
 * Class AreaModificationForm
 * @package Thelia\Form\Shipping
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaModificationForm extends AreaCreateForm
{
    public function buildForm()
    {
        parent::buildForm();

        $this->formBuilder
            ->add(
                "area_id",
                "hidden",
                [
                    "constraints" => [
                        new GreaterThan([ 'value' => 0 ])
                    ]
                ]
            )
        ;
    }

    public function getName()
    {
        return 'thelia_area_modification';
    }
}
