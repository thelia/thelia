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

namespace BackOfficeDefaultTwigBundle\Controller\Configuration;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Twig\Environment;

final class ConfigurationController
{
    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/admin/configuration', name: 'admin.configuration.index', methods: ['GET'])]
    public function index(): Response
    {
        if ($denied = $this->access->check(AdminResources::CONFIG, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/configuration/index.html.twig'));
    }
}
