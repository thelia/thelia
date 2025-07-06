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
namespace Thelia\Core\Event\Template;

class TemplateCreateEvent extends TemplateEvent
{
    protected $template_name;

    protected $locale;

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTemplateName()
    {
        return $this->template_name;
    }

    public function setTemplateName($template_name): static
    {
        $this->template_name = $template_name;

        return $this;
    }
}
