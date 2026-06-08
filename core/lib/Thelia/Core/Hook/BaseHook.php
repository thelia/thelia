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

namespace Thelia\Core\Hook;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Module\BaseModule;

/**
 * The base class for hook. If you provide hooks in your module you have to extends
 * this class.
 *
 * These class provides some helper functions to retrieve object from the current session
 * of the current user. It also provides a render function that allows you to get the right
 * template file from different locations and allows you to override templates in your current
 * template.
 *
 * Class BaseHook
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
abstract class BaseHook implements BaseHookInterface
{
    public const INJECT_TEMPLATE_METHOD_NAME = 'insertTemplate';

    public ?BaseModule $module = null;
    protected array $templates = [];
    public TranslatorInterface $translator;
    protected ?Request $request = null;
    protected ?Session $session = null;
    protected ?Customer $customer = null;
    protected ?Cart $cart = null;
    protected ?Order $order = null;
    protected ?Lang $lang = null;
    protected ?Currency $currency = null;

    #[Required]
    public ContainerInterface $container;

    public ?EventDispatcherInterface $dispatcher = null;
    public ?ParserResolver $parserResolver = null;
    public ?ParserInterface $parser = null;
    public ?AssetResolverInterface $assetsResolver = null;

    public function __construct(
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        if ($dispatcher instanceof EventDispatcherInterface) {
            $this->dispatcher = $dispatcher;
        }

        if ($parserResolver instanceof ParserResolver) {
            $this->parserResolver = $parserResolver;
        }

        $moduleCode = explode('\\', static::class)[0];

        $moduleDatabase = ModuleQuery::create()
            ->findOneByCode($moduleCode);

        if ($moduleDatabase instanceof Module) {
            $moduleClass = $moduleDatabase->getFullNamespace();
            $this->module = new $moduleClass();
        }

        $this->translator = Translator::getInstance();
    }

    /**
     * This function is called when hook uses the automatic insert template.
     */
    public function insertTemplate(HookRenderEvent $event, string $code): void
    {
        if (\array_key_exists($code, $this->templates)) {
            $templates = explode(';', (string) $this->templates[$code]);

            // Concatenate arguments and template variables,
            // giving the precedence to arguments.
            $allArguments = $event->getTemplateVars() + $event->getArguments();

            foreach ($templates as $template) {
                [$type, $filepath] = $this->getTemplateParams($template);

                if ('render' === $type) {
                    $event->add($this->render($filepath, $allArguments));
                    continue;
                }

                if ('dump' === $type) {
                    $event->add($this->render($filepath));
                    continue;
                }

                if ('css' === $type) {
                    $event->add($this->addCSS($filepath));
                    continue;
                }

                if ('js' === $type) {
                    $event->add($this->addJS($filepath));
                    continue;
                }

                if (method_exists($this, $type)) {
                    $this->{$type}($filepath, $allArguments);
                }
            }
        }
    }

    public function render(string $templateName, array $parameters = []): string
    {
        // When a parser is already current (Smarty page, PDF or email pipeline), keep the
        // existing behaviour untouched: render from the resolved absolute source path.
        $currentParser = ParserResolver::getCurrentParser();

        if ($currentParser instanceof ParserInterface) {
            $templateDir = $this->getParserResolver()->getAssetResolver($currentParser)
                ->resolveAssetSourcePath($this->module->getCode(), '', $templateName, $currentParser);

            return null !== $templateDir
                ? $currentParser->render($templateDir.DS.$templateName, $parameters)
                : \sprintf('ERR: Unknown template %s for module %s', $templateName, $this->module->getCode());
        }

        // Twig back-office: pages are rendered by Symfony controllers that bypass the Thelia
        // parser pipeline, so no parser is current. Resolve it from the template; its loader
        // already knows the module template directories, so render the relative name.
        $parser = $this->resolveTemplateParser($templateName);

        if (!$parser instanceof ParserInterface) {
            return \sprintf('ERR: Unknown template %s for module %s', $templateName, $this->module->getCode());
        }

        return $parser->render($templateName, $parameters);
    }

    private function resolveTemplateParser(string $templateName): ?ParserInterface
    {
        $resolver = $this->getParserResolver();
        $templateDefinition = $resolver->getCurrentTemplateDefinition();

        // Parsers are iterated by descending priority (Twig before Smarty): the first one
        // that owns the file within the active template wins, so a module shipping both a
        // .html (Smarty) and a .html.twig picks the Twig one in the Twig back-office.
        foreach ($resolver->getParsers() as $parser) {
            // Only configure a parser that is idle; the one already rendering the current
            // page keeps its template definition (re-setting it would re-init its Twig env).
            if ($templateDefinition instanceof TemplateDefinition
                && !$parser->getTemplateDefinition() instanceof TemplateDefinition) {
                $parser->setTemplateDefinition($templateDefinition, true);
            }

            try {
                $templateDir = $resolver->getAssetResolver($parser)
                    ->resolveAssetSourcePath($this->module->getCode(), '', $templateName, $parser);
            } catch (\Throwable) {
                continue;
            }

            if (null !== $templateDir && is_file($templateDir.DS.$templateName)) {
                return $parser;
            }
        }

        return null;
    }

    private function getParserResolver(): ParserResolver
    {
        return $this->parserResolver ??= $this->container->get('thelia.parser.resolver');
    }

    public function dump(string $fileName): string
    {
        $fileDir = $this->getAssetsResolver()->resolveAssetSourcePath($this->module->getCode(), '', $fileName, $this->getParser());

        if (null !== $fileDir) {
            $content = file_get_contents($fileDir.DS.$fileName);

            if (false === $content) {
                $content = '';
            }
        } else {
            $content = \sprintf('ERR: Unknown file %s for module %s', $fileName, $this->module->getCode());
        }

        return $content;
    }

    public function addCSS(string $fileName, array $attributes = [], array $filters = []): string
    {
        $tag = '';
        $url = $this->getAssetsResolver()->resolveAssetURL($this->module->getCode(), $fileName, 'css', $this->getParser(), $filters);

        if ('' !== $url) {
            $tags = [];
            $tags[] = '<link rel="stylesheet" type="text/css" ';
            $tags[] = ' href="'.$url.'" ';

            foreach ($attributes as $name => $val) {
                if (\is_string($name) && !\in_array($name, ['href', 'rel', 'type'], true)) {
                    $tags[] = $name.'="'.$val.'" ';
                }
            }

            $tags[] = '/>';
            $tag = implode('', $tags);
        }

        return $tag;
    }

    public function addJS(string $fileName, array $attributes = [], array $filters = []): string
    {
        $tag = '';
        $url = $this->getAssetsResolver()->resolveAssetURL($this->module->getCode(), $fileName, 'js', $this->getParser(), $filters);

        if ('' !== $url) {
            $tags = [];
            $tags[] = '<script';
            $tags[] = ' src="'.$url.'" ';

            foreach ($attributes as $name => $val) {
                if (\is_string($name) && !\in_array($name, ['src', 'type'], true)) {
                    $tags[] = $name.'="'.$val.'" ';
                }
            }

            $tags[] = '></script>';
            $tag = implode('', $tags);
        }

        return $tag;
    }

    public function setModule(?BaseModule $module): void
    {
        $this->module = $module;
    }

    public function getModule(): ?BaseModule
    {
        return $this->module;
    }

    public function getParser(): ParserInterface
    {
        $current = ParserResolver::getCurrentParser();

        if ($current instanceof ParserInterface) {
            return $current;
        }

        // Twig back-office (Symfony-rendered page): no parser is current. Use the highest-priority
        // parser and give it the active template definition so asset resolution (addCSS/addJS/dump)
        // has the template context it needs.
        $resolver = $this->getParserResolver();
        $parser = $resolver->getDefaultParser();
        $definition = $resolver->getCurrentTemplateDefinition();

        if ($definition instanceof TemplateDefinition && !$parser->getTemplateDefinition() instanceof TemplateDefinition) {
            $parser->setTemplateDefinition($definition, true);
        }

        return $parser;
    }

    protected function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    protected function getRequest(): ?Request
    {
        if (!$this->request instanceof Request) {
            $this->request = $this->getParser()->getRequest();
        }

        return $this->request;
    }

    protected function getSession(): Session
    {
        if (!$this->session instanceof Session && $this->getRequest() instanceof Request) {
            $this->session = $this->getRequest()?->getSession();
        }

        return $this->session;
    }

    protected function getView(): string
    {
        $ret = '';

        if ($this->getRequest() instanceof Request) {
            $ret = $this->getRequest()->attributes->get('_view', '');
        }

        return $ret;
    }

    protected function getCart(): ?Cart
    {
        if (!$this->cart instanceof Cart) {
            $this->cart = $this->getSession() ? $this->getSession()->getSessionCart($this->dispatcher) : null;
        }

        return $this->cart;
    }

    protected function getOrder(): ?Order
    {
        if (!$this->order instanceof Order) {
            $this->order = $this->getSession() ? $this->getSession()->getOrder() : null;
        }

        return $this->order;
    }

    protected function getCurrency(): ?Currency
    {
        if (!$this->currency instanceof Currency) {
            $this->currency = $this->getSession() ? $this->getSession()->getCurrency(true) : Currency::getDefaultCurrency();
        }

        return $this->currency;
    }

    protected function getCustomer(): ?Customer
    {
        if (!$this->customer instanceof Customer) {
            $this->customer = $this->getSession() ? $this->getSession()->getCustomerUser() : null;
        }

        return $this->customer;
    }

    protected function getLang(): Lang
    {
        if (!$this->lang instanceof Lang) {
            $this->lang = $this->getSession()
                ? $this->getSession()->getLang()
                : Lang::getDefaultLanguage();
        }

        return $this->lang;
    }

    /**
     * Add a new template for automatic render.
     *
     * @param string $hookCode the code of the hook (the name of the event used to render) : 'hook.{type}.{hook code}'
     * @param string $value    list of the template to render or add.
     *                         eg: 'render:mytemplate.html;css:assets/css/mycss.css;js:assets/js/myjs.js'
     */
    public function addTemplate(string $hookCode, string $value): void
    {
        if (\array_key_exists($hookCode, $this->templates)) {
            throw new \InvalidArgumentException(\sprintf("The hook '%s' is already used in this class.", $hookCode));
        }

        $this->templates[$hookCode] = $value;
    }

    public function getTemplates(): array
    {
        return $this->templates;
    }

    protected function getTemplateParams($template): array
    {
        $templateParams = explode(':', (string) $template);

        if (\count($templateParams) > 1) {
            return $templateParams;
        }

        return ['render', $templateParams[0]];
    }

    /**
     *  A hook is basically an Event, this function returns an array of hooks this hook subscriber wants to listen to.
     *
     *  Example:
     *  [
     *      'hook.event.name' => [
     *          [
     *              type => "back",
     *              method => "onModuleConfiguration"
     *          ],
     *          [
     *              type => "front",
     *              template => "render:module_configuration.html"
     *          ],
     *          [
     *              type => "front",
     *              method => "displaySomething"
     *          ],
     *      ],
     *      'another.hook' => [[...]]
     *  ]
     */
    public static function getSubscribedHooks(): array
    {
        return [];
    }

    /**
     * @throws \Exception
     */
    public function getAssetsResolver(): AssetResolverInterface
    {
        return $this->parserResolver->getAssetResolver($this->getParser());
    }
}
