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

namespace TheliaSmarty\Template\Plugins;

use Propel\Runtime\Util\PropelModelPager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Thelia\Core\Template\Element\LoopInterface;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class TheliaLoop extends AbstractSmartyPlugin
{
    /** @var PropelModelPager[] */
    protected static $pagination;

    protected $loopDefinition = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var RequestStack */
    protected $requestStack;

    /** @var Request */
    protected $request;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var SecurityContext */
    protected $securityContext;

    /** @var Translator */
    protected $translator;

    /** @var LoopResult[] */
    protected $loopstack = [];

    protected $varstack = [];

    /** @var bool */
    protected $isDebugActive;

    /** @var array */
    protected $theliaParserLoops;

    /** @var string */
    protected $kernelEnvironment;

    /**
     * TheliaLoop constructor.
     */
    public function __construct(
        ContainerInterface $container,
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher,
        SecurityContext $securityContext,
        TranslatorInterface $translator,
        bool $kernelDebug,
        array $theliaParserLoops,
        string $kernelEnvironment
    ) {
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->request = $requestStack->getCurrentRequest();
        $this->dispatcher = $eventDispatcher;
        $this->securityContext = $securityContext;
        $this->translator = $translator;
        $this->isDebugActive = $kernelDebug;
        $this->theliaParserLoops = $theliaParserLoops;
        $this->setLoopList($theliaParserLoops);
        $this->kernelEnvironment = $kernelEnvironment;
    }

    /**
     * @param string $loopName
     *
     * @throws \InvalidArgumentException if no pagination was found for loop
     *
     * @return PropelModelPager
     */
    public static function getPagination($loopName)
    {
        if (\array_key_exists($loopName, self::$pagination)) {
            return self::$pagination[$loopName];
        }
        throw new \InvalidArgumentException(
            Translator::getInstance()->trans("No pagination currently defined for loop name '%name'", ['%name' => $loopName])
        );
    }

    /**
     * Process the count function: executes a loop and return the number of items found.
     *
     * @param array                     $params   parameters array
     * @param \Smarty_Internal_Template $template
     *
     * @throws \InvalidArgumentException if a parameter is missing
     *
     * @return int the item count
     */
    public function theliaCount($params, /* @noinspection PhpUnusedParameterInspection */ $template)
    {
        $type = $this->getParam($params, 'type');

        if (null == $type) {
            throw new \InvalidArgumentException(
                $this->translator->trans("Missing 'type' parameter in {count} loop arguments")
            );
        }

        try {
            $loop = $this->createLoopInstance($params);

            return $loop->count();
        } catch (ElementNotFoundException $ex) {
            // If loop is not found, when in development mode, rethrow the exception to make it visible
            //            if ($this->isDebugActive) {
            //                throw $ex;
            //            }

            // Otherwise, log the problem and return a count of 0
            Tlog::getInstance()->error($ex->getMessage());

            return 0;
        }
    }

    /**
     * Process {loop name="loop name" type="loop type" ... } ... {/loop} block.
     *
     * @param array                     $params
     * @param string                    $content
     * @param \Smarty_Internal_Template $template
     * @param bool                      $repeat
     *
     * @throws \InvalidArgumentException
     *
     * @return void|string
     */
    public function theliaLoop($params, $content, $template, &$repeat)
    {
        $name = $this->getParam($params, 'name');

        if (null == $name) {
            throw new \InvalidArgumentException(
                $this->translator->trans("Missing 'name' parameter in loop arguments")
            );
        }

        $type = $this->getParam($params, 'type');

        if (null == $type) {
            throw new \InvalidArgumentException(
                $this->translator->trans("Missing 'type' parameter in loop arguments")
            );
        }

        if ($content === null) {
            // Check if a loop with the same name exists in the current scope, and abort if it's the case.
            if (\array_key_exists($name, $this->varstack)) {
                throw new \InvalidArgumentException(
                    $this->translator->trans("A loop named '%name' already exists in the current scope.", ['%name' => $name])
                );
            }

            try {
                $loop = $this->createLoopInstance($params);

                self::$pagination[$name] = null;

                // We have to clone the result, as exec() returns a cached LoopResult object, which may cause side effects
                // if loops with the same argument set are nested (see https://github.com/thelia/thelia/issues/2213)
                $loopResults = clone $loop->exec(self::$pagination[$name]);

                $loopResults->rewind();
            } catch (ElementNotFoundException $ex) {
                // If loop is not found, when in development mode, rethrow the exception to make it visible
                //                if ($this->isDebugActive) {
                //                    throw $ex;
                //                }

                // Otherwise, log the problem and simulate an empty result.
                Tlog::getInstance()->error($ex->getMessage());

                // Provide an empty result
                $loopResults = new LoopResult(null);
            }

            $this->loopstack[$name] = $loopResults;

            // No results ? The loop is terminated, do not evaluate loop text.
            if ($loopResults->isEmpty()) {
                $repeat = false;
            }
        } else {
            $loopResults = $this->loopstack[$name];

            $loopResults->next();
        }

        if ($loopResults->valid()) {
            $loopResultRow = $loopResults->current();

            // On first iteration, save variables that may be overwritten by this loop
            if (!isset($this->varstack[$name])) {
                $saved_vars = [];

                $varlist = $loopResultRow->getVars();

                foreach ($varlist as $var) {
                    $saved_vars[$var] = $template->getTemplateVars($var);
                }

                $this->varstack[$name] = $saved_vars;
            }

            foreach ($loopResultRow->getVarVal() as $var => $val) {
                $template->assign($var, $val);
            }

            $repeat = true;
        }

        // Loop is terminated. Cleanup.
        if (!$repeat) {
            // Restore previous variables values before terminating
            if (isset($this->varstack[$name])) {
                foreach ($this->varstack[$name] as $var => $value) {
                    $template->assign($var, $value);
                }

                unset($this->varstack[$name]);
            }
        }

        if ($content !== null) {
            if ($loopResults->isEmpty()) {
                $content = '';
            }

            return $content;
        }

        return '';
    }

    /**
     * Process {elseloop rel="loopname"} ... {/elseloop} block.
     *
     * @param array                     $params   loop parameters
     * @param string                    $content  loop text content
     * @param \Smarty_Internal_Template $template the Smarty object
     * @param bool                      $repeat   repeat indicator (see Smarty doc.)
     *
     * @return string the loop output
     */
    public function theliaElseloop($params, $content, /* @noinspection PhpUnusedParameterInspection */ $template, &$repeat)
    {
        // Block the smarty interpretation in the elseloop
        if ($content === null) {
            if (!$this->checkEmptyLoop($params)) {
                $repeat = false;

                return '';
            }
        }

        return $content;
    }

    /**
     * Process {ifloop rel="loopname"} ... {/ifloop} block.
     *
     * @param array                     $params   loop parameters
     * @param string                    $content  loop text content
     * @param \Smarty_Internal_Template $template the Smarty object
     * @param bool                      $repeat   repeat indicator (see Smarty doc.)
     *
     * @return string the loop output
     */
    public function theliaIfLoop($params, $content, /* @noinspection PhpUnusedParameterInspection */ $template, &$repeat)
    {
        // When encountering close tag, check if loop has results.
        if ($repeat === false) {
            return $this->checkEmptyLoop($params) ? '' : $content;
        }

        return '';
    }

    /**
     * Process {pageloop rel="loopname"} ... {/pageloop} block.
     *
     * @param array                     $params   loop parameters
     * @param string                    $content  loop text content
     * @param \Smarty_Internal_Template $template the Smarty object
     * @param bool                      $repeat   repeat indicator (see Smarty doc.)
     *
     * @throws \InvalidArgumentException
     *
     * @return string the loop output
     */
    public function theliaPageLoop($params, $content, $template, &$repeat)
    {
        $loopName = $this->getParam($params, 'rel');

        if (null == $loopName) {
            throw new \InvalidArgumentException($this->translator->trans("Missing 'rel' parameter in page loop"));
        }

        // To be sure all variables was set
        $varsToSet = ['START', 'PREV', 'NEXT', 'END', 'LAST', 'PAGE', 'CURRENT'];
        array_walk($varsToSet, function ($varName) use ($template): void {
            if (!isset($template->getTemplateVars()[$varName])) {
                $template->assign($varName, null);
            }
        });

        // Find pagination
        $pagination = self::getPagination($loopName);

        if ($pagination === null || $pagination->getNbResults() == 0) {
            // No need to paginate
            return '';
        }

        $startPage = (int) $this->getParam($params, 'start-page', 1);
        $displayedPageCount = (int) $this->getParam($params, 'limit', 10);

        if ((int) $displayedPageCount == 0) {
            $displayedPageCount = \PHP_INT_MAX;
        }

        $totalPageCount = $pagination->getLastPage();

        if ($content === null) {
            // The current page
            $currentPage = $pagination->getPage();

            // Get the start page.
            if ($totalPageCount > $displayedPageCount) {
                $startPage = $currentPage - round($displayedPageCount / 2);

                if ($startPage <= 0) {
                    $startPage = 1;
                }
            }

            // This is the iterative page number, the one we're going to increment in this loop
            $iterationPage = $startPage;

            // The last displayed page number
            $endPage = $startPage + $displayedPageCount - 1;

            if ($endPage > $totalPageCount) {
                $endPage = $totalPageCount;
            }

            // The first displayed page number
            $template->assign('START', $startPage);
            // The previous page number
            $template->assign('PREV', $currentPage > 1 ? $currentPage - 1 : $currentPage);
            // The next page number
            $template->assign('NEXT', $currentPage < $totalPageCount ? $currentPage + 1 : $totalPageCount);
            // The last displayed page number
            $template->assign('END', $endPage);
            // The overall last page
            $template->assign('LAST', $totalPageCount);
        } else {
            $iterationPage = $template->getTemplateVars('PAGE');

            ++$iterationPage;
        }

        if ($iterationPage <= $template->getTemplateVars('END')) {
            // The iterative page number
            $template->assign('PAGE', $iterationPage);

            // The overall current page number
            $template->assign('CURRENT', $pagination->getPage());

            $repeat = true;
        }

        if ($content !== null) {
            return $content;
        }

        return '';
    }

    /**
     * Check if a loop has returned results. The loop shoud have been executed before, or an
     * InvalidArgumentException is thrown.
     *
     * @param array $params
     *
     * @throws \InvalidArgumentException
     *
     * @return bool true if the loop is empty
     */
    protected function checkEmptyLoop($params)
    {
        $loopName = $this->getParam($params, 'rel');

        if (null == $loopName) {
            throw new \InvalidArgumentException(
                $this->translator->trans("Missing 'rel' parameter in ifloop/elseloop arguments")
            );
        }

        if (!isset($this->loopstack[$loopName])) {
            throw new \InvalidArgumentException(
                $this->translator->trans("Related loop name '%name'' is not defined.", ['%name' => $loopName])
            );
        }

        return $this->loopstack[$loopName]->isEmpty();
    }

    protected function createLoopInstance($smartyParams): LoopInterface
    {
        $type = strtolower($smartyParams['type']);

        if (!isset($this->loopDefinition[$type])) {
            throw new ElementNotFoundException(
                $this->translator->trans("Loop type '%type' is not defined.", ['%type' => $type])
            );
        }

        $serviceId = $this->loopDefinition[$type];

        /** @var LoopInterface $loop */
        $loop = $this->container->has($serviceId) ? $this->container->get($serviceId) : new $serviceId();
        $loop->init(
            $this->container,
            $this->requestStack,
            $this->dispatcher,
            $this->securityContext,
            $this->translator,
            $this->theliaParserLoops,
            $this->kernelEnvironment
        );

        $loop->initializeArgs($smartyParams);

        return $loop;
    }

    /**
     * Injects an associative array containing information for loop execution.
     *
     * key is loop name
     * value is the class implementing/extending base loop classes
     *
     * ex :
     *
     * $loop = array(
     *  "product" => "Thelia\Loop\Product",
     *  "category" => "Thelia\Loop\Category",
     *  "myLoop" => "My\Own\Loop"
     * );
     *
     * @throws \InvalidArgumentException if loop name already exists
     */
    public function setLoopList(array $loopDefinition): void
    {
        foreach ($loopDefinition as $name => $className) {
            $this->registerLoop($className, $name);
        }
    }

    public function getLoopList()
    {
        return $this->loopDefinition;
    }

    public function registerLoop($className, $name = null): void
    {
        if (\array_key_exists($name, $this->loopDefinition)) {
            throw new \InvalidArgumentException(
                $this->translator->trans("The loop name '%name' is already defined in %className class", [
                    '%name' => $name,
                    '%className' => $className,
                ])
            );
        }

        $this->loopDefinition[$name] = $className;
    }

    /**
     * Defines the various smarty plugins hendled by this class.
     *
     * @return \TheliaSmarty\Template\SmartyPluginDescriptor[] smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'count', $this, 'theliaCount'),
            new SmartyPluginDescriptor('block', 'loop', $this, 'theliaLoop'),
            new SmartyPluginDescriptor('block', 'elseloop', $this, 'theliaElseloop'),
            new SmartyPluginDescriptor('block', 'ifloop', $this, 'theliaIfLoop'),
            new SmartyPluginDescriptor('block', 'pageloop', $this, 'theliaPageLoop'),
        ];
    }
}
