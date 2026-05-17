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

namespace BackOfficeDefaultTwigBundle\Controller\Module;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleQuery;
use Twig\Environment;

final class ModuleConfigureController
{
    private const TEMPLATE = '@BackOfficeDefaultTwig/module/configure.html.twig';

    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/admin/module/{module_code}', name: 'admin.module.configure', methods: ['GET'])]
    public function configure(string $module_code): Response
    {
        $module = ModuleQuery::create()->findOneByCode($module_code);
        if ($module === null) {
            throw new \InvalidArgumentException(\sprintf('Module `%s` does not exists', $module_code));
        }

        if ($denied = $this->access->check([], $module_code, AccessManager::VIEW)) {
            return $denied;
        }

        $defaultLang = LangQuery::create()->findOneByByDefault(true);
        $locale = $defaultLang?->getLocale() ?? 'en_US';
        $module->setLocale($locale);

        return new Response($this->twig->render(self::TEMPLATE, [
            'module' => $module,
            'module_code' => $module_code,
        ]));
    }
}
