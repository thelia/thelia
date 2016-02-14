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

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProfileQuery;

/**
 * Class ProfileModificationForm
 * @package Thelia\Form
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ProfileModificationForm extends ProfileCreationForm
{
    protected function buildForm()
    {
        parent::buildForm(true);

        $this->formBuilder
            ->add("id", "hidden", array(
                "required" => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                    new Constraints\Callback(
                        array(
                            "methods" => array(
                                array($this, "verifyProfileId"),
                            ),
                        )
                    ),
                ),
            ))
        ;

        $this->formBuilder->remove("code");
    }

    public function getName()
    {
        return "thelia_profile_modification";
    }

    public function verifyProfileId($value, ExecutionContextInterface $context)
    {
        $profile = ProfileQuery::create()
            ->findPk($value);

        if (null === $profile) {
            $context->addViolation(Translator::getInstance()->trans("Profile ID not found"));
        }
    }
}
