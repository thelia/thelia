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

namespace BackOfficeDefaultTwigBundle\Twig;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\FragmentBag;
use Thelia\Core\Template\TemplateDefinition;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig counterpart for the Smarty `{hookblock}`/`{forhook}`/`{ifhook}` plugins, plus a
 * tolerant `safe_hook` that swallows listener errors when a module ships only Smarty
 * templates during the back-office cohabitation phase.
 *
 *     {% if has_hook('product.tab') %}
 *       <ul>
 *         {% for block in hook_block('product.tab', { product_id: product.id }) %}
 *           <li><a href="{{ block.href }}">{{ block.title }}</a></li>
 *         {% endfor %}
 *       </ul>
 *     {% endif %}
 */
final class HookExtension extends AbstractExtension
{
    private const HOOK_TYPE = TemplateDefinition::BACK_OFFICE;
    private const HOOK_TYPE_PDF = TemplateDefinition::PDF;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hook_block', $this->renderHookBlock(...)),
            new TwigFunction('has_hook', $this->hasActiveHook(...)),
            new TwigFunction('safe_hook', $this->safeHook(...), ['is_safe' => ['html']]),
            new TwigFunction('pdf_hook', $this->pdfHook(...), ['is_safe' => ['html']]),
            new TwigFunction('pdf_hook_block', $this->renderPdfHookBlock(...)),
            new TwigFunction('has_pdf_hook', $this->hasActivePdfHook(...)),
        ];
    }

    public function safeHook(string $name, array $parameters = []): string
    {
        return $this->dispatchHookRender($name, $parameters, self::HOOK_TYPE);
    }

    public function pdfHook(string $name, array $parameters = []): string
    {
        return $this->dispatchHookRender($name, $parameters, self::HOOK_TYPE_PDF);
    }

    public function renderHookBlock(string $name, array $parameters = []): FragmentBag
    {
        return $this->safelyRenderHookBlock($name, $parameters, self::HOOK_TYPE);
    }

    public function renderPdfHookBlock(string $name, array $parameters = []): FragmentBag
    {
        return $this->safelyRenderHookBlock($name, $parameters, self::HOOK_TYPE_PDF);
    }

    public function hasActiveHook(string $name, array $parameters = []): bool
    {
        return !$this->renderHookBlock($name, $parameters)->isEmpty();
    }

    public function hasActivePdfHook(string $name, array $parameters = []): bool
    {
        return !$this->renderPdfHookBlock($name, $parameters)->isEmpty();
    }

    private function dispatchHookRender(string $name, array $parameters, int $type): string
    {
        $event = new HookRenderEvent($name, $parameters);

        try {
            $this->dispatcher->dispatch(
                $event,
                \sprintf('hook.%s.%s', $type, $name),
            );

            return $event->dump();
        } catch (\Throwable $exception) {
            $this->logger->warning(
                \sprintf('hook(%s) caught a listener error: %s', $name, $exception->getMessage()),
                ['exception' => $exception],
            );

            return '';
        }
    }

    private function safelyRenderHookBlock(string $name, array $parameters, int $type): FragmentBag
    {
        try {
            return $this->dispatchHookBlock($name, $parameters, $type)->getFragmentBag();
        } catch (\Throwable $exception) {
            $this->logger->warning(
                \sprintf('hook_block(%s) caught a listener error: %s', $name, $exception->getMessage()),
                ['exception' => $exception],
            );

            return new FragmentBag();
        }
    }

    private function dispatchHookBlock(string $name, array $parameters, int $type): HookRenderBlockEvent
    {
        $event = new HookRenderBlockEvent($name, $parameters);

        $this->dispatcher->dispatch(
            $event,
            \sprintf('hook.%s.%s', $type, $name),
        );

        return $event;
    }
}
