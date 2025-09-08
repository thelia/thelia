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

namespace Thelia\Core\Template\Element;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Util\PropelModelPager;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Loop\LoopExtendsArgDefinitionsEvent;
use Thelia\Core\Event\Loop\LoopExtendsBuildArrayEvent;
use Thelia\Core\Event\Loop\LoopExtendsBuildModelCriteriaEvent;
use Thelia\Core\Event\Loop\LoopExtendsInitializeArgsEvent;
use Thelia\Core\Event\Loop\LoopExtendsParseResultsEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\Exception\LoopException;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Translation\Translator;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * Class BaseLoop.
 *
 * @method string        getType()
 * @method bool          getForceReturn()
 * @method bool          getBackendContext()
 * @method int           getOffset()          available if countable is true
 * @method int           getPage()            available if countable is true
 * @method int           getLimit()           available if countable is true
 * @method bool          getReturnUrl()       false for disable the generation of urls
 * @method ModelCriteria buildModelCriteria()
 * @method array         buildArray()
 *
 * @deprecated prefer to use the the resources from API
 */
abstract class BaseLoop implements LoopInterface
{
    protected ?string $loopName = null;
    protected static ?array $loopDefinitions = null;

    /** @var ArgumentCollection[] cache for loop arguments (class => ArgumentCollection) */
    protected static array $loopDefinitionsArgs = [];

    protected ContainerInterface $container;
    protected Request $request;
    protected EventDispatcherInterface $dispatcher;
    protected SecurityContext $securityContext;
    protected ArgumentCollection $args;
    protected $countable = true;
    protected $timestampable = false;
    protected $versionable = false;
    protected Translator $translator;
    private static array $cacheLoopResult = [];
    private static array $cacheLoopPagination = [];
    private static array $cacheCount = [];

    /** @var array cache of event to dispatch */
    protected static array $dispatchCache = [];

    protected RequestStack $requestStack;
    protected array $theliaParserLoops;
    protected string $kernelEnvironment;

    public function init(
        ContainerInterface $container,
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher,
        SecurityContext $securityContext,
        TranslatorInterface $translator,
        array $theliaParserLoops,
        $kernelEnvironment,
    ): void {
        $this->translator = $translator;

        $this->checkInterface();
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->dispatcher = $eventDispatcher;
        $this->securityContext = $securityContext;
        $this->theliaParserLoops = $theliaParserLoops;
        $this->kernelEnvironment = $kernelEnvironment;

        $this->initialize();
    }

    /**
     * Initialize the loop.
     *
     * First it will get the loop name according to the loop class.
     * Then argument definitions is initialized
     */
    protected function initialize(): void
    {
        $class = static::class;

        if (null === self::$loopDefinitions) {
            self::$loopDefinitions = array_flip($this->theliaParserLoops);
        }

        if (isset(self::$loopDefinitions[$class])) {
            $this->loopName = self::$loopDefinitions[$class];
        }

        if (!isset(self::$loopDefinitionsArgs[$class])) {
            $this->args = $this->getArgDefinitions()->addArguments($this->getDefaultArgs(), false);

            $eventName = $this->getDispatchEventName(TheliaEvents::LOOP_EXTENDS_ARG_DEFINITIONS);

            if (null !== $eventName) {
                $this->dispatcher->dispatch(
                    new LoopExtendsArgDefinitionsEvent($this),
                    $eventName,
                );
            }

            self::$loopDefinitionsArgs[$class] = $this->args;
        }

        $this->args = self::$loopDefinitionsArgs[$class];

        // reset all arguments to default value, as argument list is cached in a static variable and holds
        // values defined by previous loop usage across loop instances.
        foreach ($this->args as $arg) {
            $arg->setValue($arg->default);
        }
    }

    /**
     * Define common loop arguments.
     *
     * @return Argument[]
     */
    protected function getDefaultArgs(): array
    {
        $defaultArgs = [
            Argument::createBooleanTypeArgument('backend_context', false),
            Argument::createBooleanTypeArgument('force_return', false),
            Argument::createAnyTypeArgument('type'),
            Argument::createBooleanTypeArgument('no-cache', false),
            Argument::createBooleanTypeArgument('return_url', true),
        ];

        if (true === $this->countable) {
            $defaultArgs[] = Argument::createIntTypeArgument('offset', 0);
            $defaultArgs[] = Argument::createIntTypeArgument('page');
            $defaultArgs[] = Argument::createIntTypeArgument('limit', \PHP_INT_MAX);
        }

        if ($this instanceof SearchLoopInterface) {
            $defaultArgs[] = Argument::createAnyTypeArgument('search_term');
            $defaultArgs[] = new Argument(
                'search_in',
                new TypeCollection(
                    new EnumListType($this->getSearchIn()),
                ),
            );
            $defaultArgs[] = new Argument(
                'search_mode',
                new TypeCollection(
                    new EnumType(
                        [
                            SearchLoopInterface::MODE_ANY_WORD,
                            SearchLoopInterface::MODE_SENTENCE,
                            SearchLoopInterface::MODE_STRICT_SENTENCE,
                        ],
                    ),
                ),
                SearchLoopInterface::MODE_STRICT_SENTENCE,
            );
        }

        return $defaultArgs;
    }

    /**
     * Provides a getter to loop parameter values.
     *
     * @param string $name      the method name (only getArgname is supported)
     * @param mixed  $arguments this parameter is ignored
     *
     * @return mixed the argument value
     *
     * @throws \InvalidArgumentException if the parameter is unknown or the method name is not supported
     */
    public function __call(string $name, mixed $arguments): mixed
    {
        if (str_starts_with($name, 'get')) {
            // camelCase to underscore: getNotEmpty -> not_empty
            $argName = strtolower((string) preg_replace('/([^A-Z])([A-Z])/', '$1_$2', substr($name, 3)));

            return $this->getArgValue($argName);
        }

        throw new \InvalidArgumentException($this->translator->trans('Unsupported magic method %name. only getArgname() is supported.', ['%name' => $name]));
    }

    /**
     * Initialize the loop arguments.
     *
     * @param array $nameValuePairs a array of name => value pairs. The name is the name of the argument.
     *
     * @throws \InvalidArgumentException if some argument values are missing, or invalid
     */
    public function initializeArgs(array $nameValuePairs): void
    {
        $faultActor = [];
        $faultDetails = [];

        if (null !== $eventName = $this->getDispatchEventName(TheliaEvents::LOOP_EXTENDS_INITIALIZE_ARGS)) {
            $event = new LoopExtendsInitializeArgsEvent($this, $nameValuePairs);
            $this->dispatcher->dispatch($event, $eventName);
            $nameValuePairs = $event->getLoopParameters();
        }

        $loopType = $nameValuePairs['type'] ?? 'undefined';
        $loopName = $nameValuePairs['name'] ?? 'undefined';

        $this->args->rewind();

        while (($argument = $this->args->current()) !== false) {
            $this->args->next();

            $value = $nameValuePairs[$argument->name] ?? null;

            /* check if mandatory */
            if (null === $value && $argument->mandatory) {
                $faultActor[] = $argument->name;
                $faultDetails[] = $this->translator->trans(
                    '"%param" parameter is missing in loop type: %type, name: %name',
                    [
                        '%param' => $argument->name,
                        '%type' => $loopType,
                        '%name' => $loopName,
                    ],
                );
            } elseif ('' === $value) {
                if (!$argument->empty) {
                    /* check if empty */
                    $faultActor[] = $argument->name;
                    $faultDetails[] = $this->translator->trans(
                        '"%param" parameter cannot be empty in loop type: %type, name: %name',
                        [
                            '%param' => $argument->name,
                            '%type' => $loopType,
                            '%name' => $loopName,
                        ],
                    );
                }
            } elseif (null !== $value && !$argument->type->isValid($value)) {
                /* check type */
                $faultActor[] = $argument->name;
                $faultDetails[] = $this->translator->trans(
                    'Invalid value "%value" for "%param" parameter in loop type: %type, name: %name',
                    [
                        '%value' => $value,
                        '%param' => $argument->name,
                        '%type' => $loopType,
                        '%name' => $loopName,
                    ],
                );
            } else {
                /* set default value */
                /* did it as last checking for we consider default value is acceptable no matter type or empty restriction */
                if (null === $value) {
                    $value = $argument->default;
                }

                $argument->setValue($value);
            }
        }

        if ([] !== $faultActor) {
            $complement = \sprintf('[%s]', implode(', ', $faultDetails));

            throw new \InvalidArgumentException($complement);
        }
    }

    /**
     * Return a loop argument.
     *
     * @param string $argumentName the argument name
     *
     * @return Argument the loop argument
     *
     * @throws \InvalidArgumentException if argument is not found in loop argument list
     */
    protected function getArg(string $argumentName): Argument
    {
        $arg = $this->args->get($argumentName);

        if (!$arg instanceof Argument) {
            throw new \InvalidArgumentException($this->translator->trans('Undefined loop argument "%name"', ['%name' => $argumentName]));
        }

        return $arg;
    }

    /**
     * Return a loop argument value.
     *
     * @param string $argumentName the argument name
     *
     * @return mixed the loop argument value
     *
     * @throws \InvalidArgumentException if argument is not found in loop argument list
     */
    protected function getArgValue(string $argumentName): mixed
    {
        return $this->getArg($argumentName)->getValue();
    }

    /**
     * @param ModelCriteria         $search     the search request
     * @param PropelModelPager|null $pagination the pagination part
     *
     * @throws \InvalidArgumentException if the search mode is undefined
     */
    protected function search(ModelCriteria $search, ?PropelModelPager &$pagination = null): array|PropelModelPager|ObjectCollection
    {
        if (false === $this->countable) {
            return $search->find();
        }

        $this->setupSearchContext($search);

        if (null !== $this->getArgValue('page')) {
            return $this->searchWithPagination($search, $pagination);
        }

        return $this->searchWithOffset($search);
    }

    protected function setupSearchContext(ModelCriteria $search): void
    {
        if ($this instanceof SearchLoopInterface) {
            $searchTerm = $this->getArgValue('search_term');
            $searchIn = $this->getArgValue('search_in');
            $searchMode = $this->getArgValue('search_mode');

            if (null !== $searchTerm && null !== $searchIn) {
                switch ($searchMode) {
                    case SearchLoopInterface::MODE_ANY_WORD:
                        $searchCriteria = Criteria::IN;
                        $searchTerm = explode(' ', (string) $searchTerm);
                        break;
                    case SearchLoopInterface::MODE_SENTENCE:
                        $searchCriteria = Criteria::LIKE;
                        $searchTerm = '%'.$searchTerm.'%';
                        break;
                    case SearchLoopInterface::MODE_STRICT_SENTENCE:
                        $searchCriteria = Criteria::EQUAL;
                        break;
                    default:
                        throw new \InvalidArgumentException($this->translator->trans("Undefined search mode '%mode'", ['%mode' => $searchMode]));
                }

                $this->doSearch($search, $searchTerm, $searchIn, $searchCriteria);
            }
        }
    }

    protected function searchArray(array $search)
    {
        if (false === $this->countable) {
            return $search;
        }

        $limit = (int) $this->getArgValue('limit');
        $offset = (int) $this->getArgValue('offset');

        if (null !== $this->getArgValue('page')) {
            $pageNum = (int) $this->getArgValue('page');

            $totalPageCount = ceil(\count($search) / $limit);

            if ($pageNum > $totalPageCount || $pageNum <= 0) {
                return [];
            }

            $firstItem = ($pageNum - 1) * $limit + 1;

            return \array_slice($search, $firstItem, $firstItem + $limit, false);
        }

        return \array_slice($search, $offset, $limit, false);
    }

    protected function searchWithOffset(ModelCriteria $search): ObjectCollection
    {
        $limit = (int) $this->getArgValue('limit');

        if ($limit >= 0) {
            $search->limit($limit);
        }

        $search->offset((int) $this->getArgValue('offset'));

        return $search->find();
    }

    protected function searchWithPagination(ModelCriteria $search, ?PropelModelPager &$pagination): array|PropelModelPager
    {
        $page = (int) $this->getArgValue('page');
        $limit = (int) $this->getArgValue('limit');

        $pagination = $search->paginate($page, $limit);

        if ($page > $pagination->getLastPage()) {
            return [];
        }

        return $pagination;
    }

    public function count()
    {
        $hash = $this->args->getHash();

        if (($isCaching = $this->isCaching()) && isset(self::$cacheCount[$hash])) {
            return self::$cacheCount[$hash];
        }

        $count = 0;

        if ($this instanceof PropelSearchLoopInterface) {
            $searchModelCriteria = $this->extendsBuildModelCriteria($this->buildModelCriteria());

            if (!$searchModelCriteria instanceof ModelCriteria) {
                $count = 0;
            } else {
                $this->setupSearchContext($searchModelCriteria);

                $count = $searchModelCriteria->count();
            }
        } elseif ($this instanceof ArraySearchLoopInterface) {
            $searchArray = $this->extendsBuildArray($this->buildArray());
            $count = null === $searchArray ? 0 : \count($searchArray);
        }

        if ($isCaching) {
            self::$cacheCount[$hash] = $count;
        }

        return $count;
    }

    public function exec(?PropelModelPager $pagination): LoopResult
    {
        $hash = $this->args->getHash();

        if (($isCaching = $this->isCaching()) && isset(self::$cacheLoopResult[$hash])) {
            if (isset(self::$cacheLoopPagination[$hash])) {
                $pagination = self::$cacheLoopPagination[$hash];
            }

            return self::$cacheLoopResult[$hash];
        }

        $results = [];

        if ($this instanceof PropelSearchLoopInterface) {
            $searchModelCriteria = $this->extendsBuildModelCriteria($this->buildModelCriteria());

            $results = $this->search(
                $searchModelCriteria,
                $pagination,
            );
        } elseif ($this instanceof ArraySearchLoopInterface) {
            $searchArray = $this->extendsBuildArray($this->buildArray());

            $results = $this->searchArray($searchArray);
        }

        $loopResult = new LoopResult($results);

        if (true === $this->countable) {
            $loopResult->setCountable();
        }

        if (true === $this->timestampable) {
            $loopResult->setTimestamped();
        }

        if (true === $this->versionable) {
            $loopResult->setVersioned();
        }

        $parsedResults = $this->extendsParseResults($this->parseResults($loopResult));

        $loopResult->finalizeRows();

        if ($isCaching) {
            self::$cacheLoopResult[$hash] = $parsedResults;

            if ($pagination instanceof PropelModelPager) {
                self::$cacheLoopPagination[$hash] = clone $pagination;
            }
        }

        return $parsedResults;
    }

    protected function checkInterface(): void
    {
        /* Must implement either :
         *  - PropelSearchLoopInterface
         *  - ArraySearchLoopInterface
        */
        $searchInterface = false;

        if ($this instanceof PropelSearchLoopInterface) {
            if ($searchInterface) {
                throw new LoopException($this->translator->trans('Loop cannot implements multiple Search Interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`'), LoopException::MULTIPLE_SEARCH_INTERFACE);
            }

            $searchInterface = true;
        }

        if ($this instanceof ArraySearchLoopInterface) {
            if ($searchInterface) {
                throw new LoopException($this->translator->trans('Loop cannot implements multiple Search Interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`'), LoopException::MULTIPLE_SEARCH_INTERFACE);
            }

            $searchInterface = true;
        }

        if (false === $searchInterface) {
            throw new LoopException($this->translator->trans('Loop must implements one of the following interfaces : `PropelSearchLoopInterface`, `ArraySearchLoopInterface`'), LoopException::SEARCH_INTERFACE_NOT_FOUND);
        }

        /* Only PropelSearch allows timestamp and version */
        if (!$this instanceof PropelSearchLoopInterface) {
            if (true === $this->timestampable) {
                throw new LoopException($this->translator->trans("Loop must implements 'PropelSearchLoopInterface' to be timestampable"), LoopException::NOT_TIMESTAMPED);
            }

            if (true === $this->versionable) {
                throw new LoopException($this->translator->trans("Loop must implements 'PropelSearchLoopInterface' to be versionable"), LoopException::NOT_VERSIONED);
            }
        }
    }

    protected function isCaching(): bool
    {
        return !$this->getArg('no-cache')->getValue() && 'test' !== $this->kernelEnvironment;
    }

    abstract public function parseResults(LoopResult $loopResult): LoopResult;

    /**
     * Definition of loop arguments.
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     */
    abstract protected function getArgDefinitions(): ArgumentCollection;

    /**
     * Use this method in order to add fields in sub-classes.
     */
    protected function addOutputFields(LoopResultRow $loopResultRow, object|array $item): void
    {
    }

    /**
     * Get the event name for the loop depending of the event name and the loop name.
     *
     * This function also checks if there are services that listen to this event.
     * If not the function returns null.
     *
     * @param string $eventName the event name (`TheliaEvents::LOOP_EXTENDS_ARG_DEFINITIONS`,
     *                          `TheliaEvents::LOOP_EXTENDS_INITIALIZE_ARGS`, ...)
     *
     * @return string|null The event name for the loop if listeners exist, otherwise null is returned
     */
    protected function getDispatchEventName(string $eventName): ?string
    {
        $customEventName = TheliaEvents::getLoopExtendsEvent($eventName, $this->loopName);

        if (!isset(self::$dispatchCache[$customEventName])) {
            self::$dispatchCache[$customEventName] = $this->dispatcher->hasListeners($customEventName);
        }

        return self::$dispatchCache[$customEventName]
            ? $customEventName
            : null;
    }

    /**
     * Dispatch an event to extend the BuildModelCriteria.
     */
    protected function extendsBuildModelCriteria(?ModelCriteria $search = null): ?ModelCriteria
    {
        if (!$search instanceof ModelCriteria) {
            return null;
        }

        $eventName = $this->getDispatchEventName(TheliaEvents::LOOP_EXTENDS_BUILD_MODEL_CRITERIA);

        if (null !== $eventName) {
            $this->dispatcher->dispatch(
                new LoopExtendsBuildModelCriteriaEvent($this, $search),
                $eventName,
            );
        }

        return $search;
    }

    /**
     * Dispatch an event to extend the BuildArray.
     */
    protected function extendsBuildArray(?array $search = null): array
    {
        if (null === $search) {
            return [];
        }

        $eventName = $this->getDispatchEventName(TheliaEvents::LOOP_EXTENDS_BUILD_ARRAY);

        if (null !== $eventName) {
            $event = new LoopExtendsBuildArrayEvent($this, $search);

            $this->dispatcher->dispatch($event, $eventName);

            $search = $event->getArray();
        }

        return $search;
    }

    /**
     * Dispatch an event to extend the ParseResults.
     */
    protected function extendsParseResults(LoopResult $loopResult): LoopResult
    {
        $eventName = $this->getDispatchEventName(TheliaEvents::LOOP_EXTENDS_PARSE_RESULTS);

        if (null !== $eventName) {
            $this->dispatcher->dispatch(
                new LoopExtendsParseResultsEvent($this, $loopResult),
                $eventName,
            );
        }

        return $loopResult;
    }

    /**
     * Get the argument collection.
     */
    public function getArgumentCollection(): ArgumentCollection
    {
        return $this->args;
    }

    /**
     * Get the loop name.
     */
    public function getLoopName(): ?string
    {
        return $this->loopName;
    }

    protected function getMainRequest(): \Symfony\Component\HttpFoundation\Request
    {
        return $this->requestStack->getMainRequest();
    }

    public function getCurrentRequest(): \Symfony\Component\HttpFoundation\Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}
