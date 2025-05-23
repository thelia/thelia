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

namespace Thelia\Service\Model;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Lang;

readonly class LangService
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    public function getLang(): ?Lang
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        return $request->getSession()?->getLang();
    }

    public function getLocale(): ?string
    {
        $locale = $this->getLang()?->getLocale();

        return $locale ?? Lang::getDefaultLanguage()?->getLocale();
    }
}
