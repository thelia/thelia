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

use Thelia\Model\Template;

class TemplateDeleteFeatureEvent extends TemplateEvent
{
    public function __construct(Template $template, protected $feature_id)
    {
        parent::__construct($template);
    }

    public function getFeatureId()
    {
        return $this->feature_id;
    }

    public function setFeatureId($feature_id): static
    {
        $this->feature_id = $feature_id;

        return $this;
    }
}
