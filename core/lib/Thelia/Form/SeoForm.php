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

/**
 * Class SeoForm
 * @package Thelia\Form
 * @author Christophe Laffont <claffont@openstudio.fr>
 */
class SeoForm extends BaseForm
{
    use SeoFieldsTrait;

    /**
     * @inheritdoc
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add("id", "hidden", array(
                'required' => true,
                "constraints" => array(
                    new GreaterThan(array('value' => 0)),
                ),
            ))
            ->add("locale", "hidden", array(
                'required' => true,
                "constraints" => array(
                    new NotBlank(),
                ),
            ))
        ;

        // Add SEO Fields
        $this->addSeoFields();
    }

    public function getName()
    {
        return "thelia_seo";
    }
}
