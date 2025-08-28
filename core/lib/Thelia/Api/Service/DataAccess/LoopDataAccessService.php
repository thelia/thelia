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

namespace Thelia\Api\Service\DataAccess;

use Propel\Runtime\Util\PropelModelPager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Thelia\Core\Template\Element\LoopInterface;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Log\Tlog;

class LoopDataAccessService
{
    /** @var PropelModelPager[] */
    protected static array $pagination;
    protected array $loopDefinition = [];
    protected ?Request $request = null;

    /** @var LoopResult[] */
    protected array $loopStack = [];
    protected array $variableStack = [];

    public function __construct(
        protected ContainerInterface $container,
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $eventDispatcher,
        protected SecurityContext $securityContext,
        protected TranslatorInterface $translator,
        protected bool $kernelDebug,
        protected array $theliaParserLoops,
        protected string $kernelEnvironment,
    ) {
        $this->request = $requestStack->getMainRequest();
        $this->setLoopList($theliaParserLoops);
    }

    public function theliaCount(
        string $loopType,
        array $params,
    ): int {
        try {
            return $this->createLoopInstance($loopType, $params)->count();
        } catch (ElementNotFoundException $ex) {
            Tlog::getInstance()->error($ex->getMessage());

            return 0;
        }
    }

    public function theliaLoop(
        string $loopName,
        string $loopType,
        array $params,
    ): array {
        // Check if a loop with the same name exists in the current scope, and abort if it's the case.
        if (\array_key_exists($loopName, $this->variableStack)) {
            throw new \InvalidArgumentException($this->translator->trans("A loop named '%name' already exists in the current scope.", ['%name' => $loopName]));
        }

        try {
            $loop = $this->createLoopInstance($loopType, $params);

            self::$pagination[$loopName] = null;

            // We have to clone the result, as exec() returns a cached LoopResult object, which may cause side effects
            // if loops with the same argument set are nested (see https://github.com/thelia/thelia/issues/2213)
            $loopResults = clone $loop->exec(self::$pagination[$loopName]);

            $loopResults->rewind();
        } catch (ElementNotFoundException $ex) {
            Tlog::getInstance()->error($ex->getMessage());
            $loopResults = new LoopResult(null);
        }

        $this->loopStack[$loopName] = $loopResults;

        $datas = [];
        $count = 0;
        for ($loopResults->rewind(); $loopResults->valid(); $loopResults->next()) {
            $loopResult = $loopResults->current();

            foreach ($loopResult->getVars() as $key) {
                $datas[$count][$key] = $loopResult->get($key);
            }
            ++$count;
        }

        return $datas;
    }

    protected function createLoopInstance(string $loopType, array $params): LoopInterface
    {
        if (!isset($this->loopDefinition[$loopType])) {
            throw new ElementNotFoundException($this->translator->trans("Loop type '%type' is not defined.", ['%type' => $loopType]));
        }

        $serviceId = $this->loopDefinition[$loopType];

        /** @var LoopInterface $loop */
        $loop = $this->container->has($serviceId) ? $this->container->get($serviceId) : new $serviceId();
        $loop->init(
            $this->container,
            $this->requestStack,
            $this->eventDispatcher,
            $this->securityContext,
            $this->translator,
            $this->theliaParserLoops,
            $this->kernelEnvironment
        );

        $loop->initializeArgs($params);

        return $loop;
    }

    public function setLoopList(array $loopDefinition): void
    {
        foreach ($loopDefinition as $name => $className) {
            $this->registerLoop($className, $name);
        }
    }

    public function registerLoop($className, $name = null): void
    {
        if (\array_key_exists($name, $this->loopDefinition)) {
            throw new \InvalidArgumentException($this->translator->trans("The loop name '%name' is already defined in %className class", ['%name' => $name, '%className' => $className]));
        }

        $this->loopDefinition[$name] = $className;
    }
}
