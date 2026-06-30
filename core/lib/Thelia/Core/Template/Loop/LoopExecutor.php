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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\Util\PropelModelPager;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Thelia\Core\Template\Element\LoopInterface;
use Thelia\Core\Template\Element\LoopResult;

/**
 * Executes a Thelia loop by type and arguments, independently of any template engine.
 *
 * This is the template-engine-agnostic core of the historical Smarty {loop} plugin
 * (TheliaSmarty\Template\Plugins\TheliaLoop): a single entry point that resolves a loop
 * type to its class, runs it and returns the LoopResult. Any parser (Twig, Smarty, …)
 * consumes it through a thin adapter, so the same data is available behind any engine.
 *
 * The loop registry and the instantiation/initialization sequence are kept identical to
 * TheliaLoop so a given (type, args) yields the exact same rows whatever the engine.
 */
#[Autoconfigure(public: true)]
final readonly class LoopExecutor
{
    /** @var array<string, class-string<LoopInterface>> loop name (kebab-case) => loop class */
    private array $loopDefinition;

    /**
     * @param iterable<LoopInterface> $theliaLoops the services tagged "thelia.loop"
     */
    public function __construct(
        private ContainerInterface $container,
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        #[AutowireIterator('thelia.loop')]
        iterable $theliaLoops,
        private string $kernelEnvironment,
    ) {
        $this->loopDefinition = $this->buildLoopDefinition($theliaLoops);
    }

    /**
     * Run a loop and return its result rows.
     *
     * @param string               $type loop type, e.g. "order_product" or "order-product"
     * @param array<string, mixed> $args loop arguments, e.g. ['order' => 123]
     *
     * @throws ElementNotFoundException  if the loop type is not registered
     * @throws \InvalidArgumentException if a mandatory argument is missing or invalid
     */
    public function execute(string $type, array $args = []): LoopResult
    {
        $type = str_replace('_', '-', strtolower($type));

        if (!isset($this->loopDefinition[$type])) {
            throw new ElementNotFoundException($this->translator->trans("Loop type '%type' is not defined.", ['%type' => $type]));
        }

        $serviceId = $this->loopDefinition[$type];

        /** @var LoopInterface $loop */
        $loop = $this->container->has($serviceId) ? $this->container->get($serviceId) : new $serviceId();

        $loop->init(
            $this->container,
            $this->requestStack,
            $this->eventDispatcher,
            $this->securityContext,
            $this->translator,
            $this->loopDefinition,
            $this->kernelEnvironment,
        );

        $loop->initializeArgs(array_merge($args, ['type' => $type]));

        // exec() returns a cached LoopResult; clone it to avoid side effects when the same
        // argument set is executed again (mirrors TheliaLoop, see thelia/thelia#2213).
        $pagination = null;

        return $this->runExec($loop, $pagination);
    }

    /**
     * Isolated exec() call: exec() takes the pagination by reference, which a readonly
     * class cannot express inline.
     */
    private function runExec(LoopInterface $loop, ?PropelModelPager &$pagination): LoopResult
    {
        $result = clone $loop->exec($pagination);
        $result->rewind();

        return $result;
    }

    /**
     * Build the "loop name => class" registry from the tagged loop services, using the
     * exact same normalization as TheliaLoop::setLoopList() (kebab-case, collision suffix).
     *
     * @param iterable<LoopInterface> $theliaLoops
     *
     * @return array<string, class-string<LoopInterface>>
     */
    private function buildLoopDefinition(iterable $theliaLoops): array
    {
        $definition = [];

        foreach ($theliaLoops as $key => $loop) {
            $className = substr(strrchr($loop::class, '\\'), 1);
            $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
            $name = str_replace('_', '-', $name);

            if (\array_key_exists($name, $definition)) {
                $name .= '_'.$key;
            }

            $definition[$name] = $loop::class;
        }

        return $definition;
    }
}
