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

namespace Thelia\Core\Event;

use Symfony\Component\EventDispatcher\Event;
use Thelia\Form\BaseForm;

/**
 * Class TheliaFormEvent
 * @package Thelia\Core\Event
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TheliaFormEvent extends Event
{
    /**
     * @var BaseForm
     */
    protected $form;

    public function __construct(BaseForm $form)
    {
        $this->form = $form;
    }

    /**
     * @return BaseForm
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param BaseForm $form
     * @return $this
     */
    public function setForm(BaseForm $form)
    {
        $this->form = $form;

        return $this;
    }

    public function getName()
    {
        return $this->form->getName();
    }
}
