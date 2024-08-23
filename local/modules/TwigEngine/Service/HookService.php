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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Model\ModuleQuery;
use TwigEngine\Template\TwigParser;

readonly class HookService
{
    public function __construct(
        private bool $kernelDebug,
        private EventDispatcherInterface $dispatcher,
        private TwigParser $twigParser,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function processHookFunction(
        string $hookName,
        array $parameters,
    ): string {
        $module = $parameters['module'] ?? 0;
        $moduleCode = $parameters['modulecode'] ?? '';

        $type = $this->twigParser->getTemplateDefinition()?->getType();
        $event = new HookRenderEvent($hookName, $parameters, $this->twigParser->getTwig()->getGlobals());

        $event->setArguments($this->getArgumentsFromParams($parameters));

        $eventName = sprintf('hook.%s.%s', $type, $hookName);

        // this is a hook specific to a module
        if (0 === $module
            && '' !== $moduleCode
            && null !== $mod = ModuleQuery::create()->findOneByCode($moduleCode)) {
            $module = $mod->getId();
        }
        if (0 !== $module) {
            $eventName .= '.'.$module;
        }

        $this->dispatcher->dispatch($event, $eventName);
        $content = trim($event->dump());
        if ($this->kernelDebug && $this->twigParser->getRequest()?->get('SHOW_HOOK')) {
            $content = $this->showHook(
                $hookName,
                $parameters,
                $this->twigParser->getTwig()->getGlobals()
            ).$content;
        }

        return $content;
    }

    protected function showHook(string $hookName, array $parameters, array $templateVars): string
    {
        if (!class_exists('\Symfony\Component\VarDumper\VarDumper')) {
            throw new \RuntimeException('For use SHOW_HOOK, you can install dependency symfony/var-dumper');
        }

        ob_start();

        \Symfony\Component\VarDumper\VarDumper::dump([
            'hook name' => $hookName,
            'hook parameters' => $parameters,
            'hook external variables' => $templateVars,
        ]);

        $content = ob_get_clean();

        return <<<HTML
<div style="background-color: #C82D26; color: #fff; border-color: #000000; border: solid;">
{$hookName}
 <a onclick="this.parentNode.querySelector('.hook-details').style.display = 'block'">Show details</a>
<div class="hook-details" style="display: none; cursor: pointer;">
{$content}
</div>
</div>
HTML;
    }

    protected function getArgumentsFromParams(array $params): array
    {
        $args = [];
        $excludes = ['name', 'before', 'separator', 'after', 'fields'];

        foreach ($params as $key => $value) {
            if (!\in_array($key, $excludes, true)) {
                $args[$key] = $value;
            }
        }

        return $args;
    }
}
