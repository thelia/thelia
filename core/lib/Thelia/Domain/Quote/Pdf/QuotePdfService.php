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

namespace Thelia\Domain\Quote\Pdf;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Quote\Document\QuoteDocumentContext;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Exception\QuoteDocumentUnavailableException;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Quote;
use Thelia\Model\QuoteRevisionQuery;

/**
 * Prints a revision of a quote from the `quote` document of the active PDF template, through the
 * chain the other documents use (the PDF event).
 *
 * Only a revision that was sent is printed, never a draft: the buyer and the shop read what was
 * sent. Nothing is kept, the document is made again at each call, in the language frozen on the
 * quote. The caller decides who may read the quote: this service does not know who asks.
 */
final readonly class QuotePdfService
{
    public const string TEMPLATE_NAME = 'quote';

    public function __construct(
        private ParserResolver $parserResolver,
        private TemplateHelperInterface $templateHelper,
        private EventDispatcherInterface $eventDispatcher,
        private QuoteDocumentContext $context,
    ) {
    }

    /**
     * @return string the content of the PDF file
     *
     * @throws QuoteDocumentUnavailableException
     */
    public function render(Quote $quote, int $revisionNumber): string
    {
        $revision = QuoteRevisionQuery::create()
            ->filterByQuoteId($quote->getId())
            ->filterByNumber($revisionNumber)
            ->orderById(Criteria::DESC)
            ->findOne() ?? throw QuoteDocumentUnavailableException::revisionNotFound();

        if (QuoteRevisionState::Sent->value !== $revision->getState()) {
            throw QuoteDocumentUnavailableException::revisionNotSent();
        }

        $pdfTemplate = $this->templateHelper->getActivePdfTemplate();

        if (!is_file($pdfTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.self::TEMPLATE_NAME.'.html.twig')) {
            throw QuoteDocumentUnavailableException::notRendered();
        }

        /** @var Customer|null $customer Propel finds none for an orphan id. */
        $customer = $quote->getCustomer();
        $locale = (string) ($quote->getLocale() ?: $customer?->getCustomerLang()->getLocale() ?: Lang::getDefaultLanguage()->getLocale());

        $parser = $this->parserResolver->getParser($pdfTemplate->getAbsolutePath(), self::TEMPLATE_NAME);
        $parser->setTemplateDefinition($pdfTemplate, true);
        $html = $parser->render(self::TEMPLATE_NAME, $this->context->build($quote, $revision, $locale));

        $pdfEvent = new PdfEvent($html);
        $pdfEvent->setTemplateName(self::TEMPLATE_NAME);
        $pdfEvent->setFileName((string) $quote->getRef());
        $pdfEvent->setObject($quote);

        $this->eventDispatcher->dispatch($pdfEvent, TheliaEvents::GENERATE_PDF);

        if (!$pdfEvent->hasPdf()) {
            throw QuoteDocumentUnavailableException::notRendered();
        }

        return (string) $pdfEvent->getPdf();
    }
}
