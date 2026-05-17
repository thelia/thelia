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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/advanced', name: 'admin.configuration.advanced')]
final class AdvancedConfigurationController
{
    private const RESOURCE = AdminResources::ADVANCED_CONFIGURATION;
    private const REDIRECT_ROUTE = 'admin.configuration.advanced';

    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly EventDispatcherInterface $events,
        private readonly TokenProvider $tokens,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.cache_dir%')]
        private readonly string $cacheDir,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/configuration/advanced/index.html.twig'));
    }

    #[Route('/flush-cache', name: '.flush-cache', methods: ['POST', 'GET'])]
    public function flushCache(Request $request): RedirectResponse
    {
        return $this->dispatchCacheClear($request, $this->cacheDir, 'Application cache cleared.');
    }

    #[Route('/flush-assets', name: '.flush-assets', methods: ['POST', 'GET'])]
    public function flushAssets(Request $request): RedirectResponse
    {
        return $this->dispatchCacheClear(
            $request,
            $this->webDir().'assets',
            'Web assets cache cleared.',
        );
    }

    #[Route('/flush-images-and-documents', name: '.flush-images-and-documents', methods: ['POST', 'GET'])]
    public function flushImagesAndDocuments(Request $request): RedirectResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return new RedirectResponse($this->urls->generate(self::REDIRECT_ROUTE));
        }

        try {
            $this->tokens->checkToken((string) $request->query->get('_token'));

            $imagesDir = $this->webDir().ConfigQuery::read('image_cache_dir_from_web_root', 'cache'.\DIRECTORY_SEPARATOR.'images');
            $this->events->dispatch(new CacheEvent($imagesDir), TheliaEvents::CACHE_CLEAR);

            $docsDir = $this->webDir().ConfigQuery::read('document_cache_dir_from_web_root', 'cache'.\DIRECTORY_SEPARATOR.'documents');
            $this->events->dispatch(new CacheEvent($docsDir), TheliaEvents::CACHE_CLEAR);

            $this->flash($request, 'success', 'Images and documents cache cleared.');
        } catch (\Throwable $exception) {
            $this->flash($request, 'danger', $exception->getMessage());
        }

        return new RedirectResponse($this->urls->generate(self::REDIRECT_ROUTE));
    }

    private function dispatchCacheClear(Request $request, string $dir, string $successMessage): RedirectResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return new RedirectResponse($this->urls->generate(self::REDIRECT_ROUTE));
        }

        try {
            $this->tokens->checkToken((string) $request->query->get('_token'));
            $this->events->dispatch(new CacheEvent($dir), TheliaEvents::CACHE_CLEAR);
            $this->flash($request, 'success', $successMessage);
        } catch (\Throwable $exception) {
            $this->flash($request, 'danger', $exception->getMessage());
        }

        return new RedirectResponse($this->urls->generate(self::REDIRECT_ROUTE));
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add($type, $this->translator->trans($message));
        }
    }

    private function webDir(): string
    {
        return $this->projectDir.\DIRECTORY_SEPARATOR.'web'.\DIRECTORY_SEPARATOR;
    }
}
