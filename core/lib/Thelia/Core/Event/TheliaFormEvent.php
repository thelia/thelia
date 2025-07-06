<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Form\BaseForm;

/**
 * Class TheliaFormEvent.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TheliaFormEvent extends Event
{
    public function __construct(protected BaseForm $form)
    {
    }

    public function getForm(): BaseForm
    {
        return $this->form;
    }

    /**
     * @return $this
     */
    public function setForm(BaseForm $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function getName(): string
    {
        return $this->form::getName();
    }
}
