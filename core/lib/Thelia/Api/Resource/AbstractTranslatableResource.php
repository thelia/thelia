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

namespace Thelia\Api\Resource;

abstract class AbstractTranslatableResource implements PropelResourceInterface, TranslatableResourceInterface
{
    use PropelResourceTrait;

    public I18nCollection $i18ns;

    public function __construct()
    {
        $this->i18ns = new I18nCollection();
    }

    public function setI18ns(array $i18ns): self
    {
        foreach ($i18ns as $locale => $i18n) {
            $i18nClass = $this->getI18nResourceClass();
            $this->i18ns->add(new $i18nClass($i18n), $locale);
        }

        return $this;
    }

    public function addI18n(I18n $i18n, string $locale): self
    {
        $this->i18ns->add($i18n, $locale);

        return $this;
    }

    public function getI18ns(): I18nCollection
    {
        return $this->i18ns;
    }
}
