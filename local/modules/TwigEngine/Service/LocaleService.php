<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TwigEngine\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Lang;

class LocaleService
{
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public function getLocale(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof \Thelia\Core\HttpFoundation\Request) {
            return null;
        }

        $locale = $request->getSession()?->getLang()->getLocale();

        return $locale ?? Lang::getDefaultLanguage()?->getLocale();
    }
}
