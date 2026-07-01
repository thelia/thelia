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

namespace Thelia\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderQuery;

#[AsCommand(name: 'pdf:render', description: 'Render an order PDF (invoice or delivery) to a file without the HTTP flow, to preview it or work on its template')]
class PdfRender extends ContainerAwareCommand
{
    private const DOCUMENTS = ['invoice', 'delivery'];

    public function __construct(
        private readonly ParserResolver $parserResolver,
        private readonly TemplateHelperInterface $templateHelper,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('document', InputArgument::REQUIRED, 'Document to render: invoice or delivery')
            ->addOption('order', null, InputOption::VALUE_REQUIRED, 'Order id to render the document for')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale to render in, e.g. fr_FR (default: store default language)')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output file path, e.g. invoice.pdf (default: <document>-<order_ref>.pdf)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $document = (string) $input->getArgument('document');

        if (!\in_array($document, self::DOCUMENTS, true)) {
            $output->writeln(\sprintf('<error>Unknown document "%s". Expected one of: %s.</error>', $document, implode(', ', self::DOCUMENTS)));

            return Command::FAILURE;
        }

        $orderId = $input->getOption('order');

        if (null === $orderId || null === $order = OrderQuery::create()->findPk((int) $orderId)) {
            $output->writeln(\sprintf('<error>Order %s not found.</error>', $orderId ?? '(missing --order)'));

            return Command::FAILURE;
        }

        $locale = $input->getOption('locale');
        $lang = null !== $locale ? LangQuery::create()->findOneByLocale($locale) : null;

        // A PDF is generated outside an HTTP request; push a request with a session so the
        // parser and the formatting helpers have a locale and a currency to work with.
        $this->initRequest($lang);

        try {
            $html = $this->renderDocumentHtml($document, (int) $order->getId());

            $pdfEvent = new PdfEvent($html);
            $pdfEvent->setTemplateName($document);
            $pdfEvent->setFileName($order->getRef());
            $pdfEvent->setObject($order);

            if (null !== $lang) {
                $pdfEvent->setLang($lang->getCode());
            }

            $this->eventDispatcher->dispatch($pdfEvent, TheliaEvents::GENERATE_PDF);
        } catch (\Exception $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        if (!$pdfEvent->hasPdf()) {
            $output->writeln('<error>PDF generation produced no output.</error>');

            return Command::FAILURE;
        }

        $out = $input->getOption('out') ?? \sprintf('%s-%s.pdf', $document, $order->getRef());
        file_put_contents($out, $pdfEvent->getPdf());

        $output->writeln(\sprintf('<info>Wrote %s (%d bytes).</info>', $out, \strlen((string) $pdfEvent->getPdf())));

        return Command::SUCCESS;
    }

    private function renderDocumentHtml(string $document, int $orderId): string
    {
        $pdfTemplate = $this->templateHelper->getActivePdfTemplate();

        $parser = $this->parserResolver->getParser($pdfTemplate->getAbsolutePath(), $document);
        \assert($parser instanceof ParserInterface);
        $parser->setTemplateDefinition($pdfTemplate, true);

        return $parser->render($document, ['order_id' => $orderId]);
    }
}
