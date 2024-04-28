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

namespace Thelia\Core\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\BaseLoop;

class DataAccessService
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ContainerInterface $container,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SecurityContext $securityContext,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function datas(string $path, array $params = [], $locale = null, $cache = true): mixed
    {
        return ['test' => 'success'];
    }

    /** @deprecated use new data access layer */
    public function loop(string $loopName, array $params = []): mixed
    {
        $loopNamespace = 'Thelia\\Core\\Template\\Loop\\';
        $className = ucfirst(strtolower($loopName));
        if (!class_exists($loopNamespace.$className)) {
            throw new \RuntimeException('Loop '.$className.' not found');
        }
        $fullClassName = $loopNamespace.$className;

        /** @var BaseLoop $instance */
        $instance = new $fullClassName();

        $instance->init(
            $this->container,
            $this->requestStack,
            $this->eventDispatcher,
            $this->securityContext,
            $this->translator,
            $this->parameterBag->get('Thelia.parser.loops'),
            $this->parameterBag->get('kernel.environment')
        );
        $instance->initializeArgs($params);
        $loopResults = $instance->exec($pagination);

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
}
