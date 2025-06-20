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

class TemplateDuplicateEvent extends TemplateEvent
{
    /**
     * TemplateCreateEvent constructor.
     *
     * @param int $sourceTemplateId
     * @param string $locale
     */
    public function __construct(protected $sourceTemplateId, protected $locale)
    {
        parent::__construct();
    }

    /**
     * @return int
     */
    public function getSourceTemplateId()
    {
        return $this->sourceTemplateId;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }
}
