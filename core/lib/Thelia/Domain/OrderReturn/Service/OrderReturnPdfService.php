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

namespace Thelia\Domain\OrderReturn\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderReturn;

/**
 * Renders the printable return document from the active PDF template, on the
 * model of the delivery slip. The return data is passed to the template as
 * plain variables (there is no order_return loop), then dompdf turns the HTML
 * into a PDF through the GENERATE_PDF event.
 */
final readonly class OrderReturnPdfService
{
    private const TEMPLATE_NAME = 'order_return';

    public function __construct(
        private ParserResolver $parserResolver,
        private TemplateHelperInterface $templateHelper,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Return the PDF bytes of the return document, or null when no renderer answered.
     */
    public function render(OrderReturn $return): ?string
    {
        $pdfTemplate = $this->templateHelper->getActivePdfTemplate();
        $parser = $this->parserResolver->getParser($pdfTemplate->getAbsolutePath(), self::TEMPLATE_NAME);
        $parser->setTemplateDefinition($pdfTemplate, true);

        $html = $parser->render(self::TEMPLATE_NAME, $this->buildContext($return));

        $pdfEvent = new PdfEvent($html);
        $pdfEvent->setTemplateName(self::TEMPLATE_NAME);
        $pdfEvent->setFileName((string) $return->getRef());
        $pdfEvent->setObject($return);

        $this->eventDispatcher->dispatch($pdfEvent, TheliaEvents::GENERATE_PDF);

        return $pdfEvent->hasPdf() ? $pdfEvent->getPdf() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(OrderReturn $return): array
    {
        $order = $return->getOrder();
        $locale = $order?->getLang()?->getLocale() ?? 'en_US';

        $status = $return->getOrderReturnStatus();
        $status?->setLocale($locale);

        $symbol = (string) ($order?->getCurrency()?->getSymbol() ?? '');

        $lines = [];
        foreach ($return->getOrderReturnLines() as $line) {
            $orderProduct = $line->getOrderProduct();
            $lines[] = [
                'title' => (string) $orderProduct?->getTitle(),
                'ref' => (string) $orderProduct?->getProductRef(),
                'quantity' => (float) $line->getQuantity(),
                'refund' => $this->formatAmount((float) $line->getRefundAmount(), $symbol),
            ];
        }

        return [
            'locale' => $locale,
            'return_ref' => (string) $return->getRef(),
            'order_ref' => (string) $order?->getRef(),
            'status' => (string) ($status?->getTitle() ?? ''),
            'created_at' => $return->getCreatedAt(),
            'customer_name' => trim(\sprintf(
                '%s %s',
                (string) $return->getCustomer()?->getFirstname(),
                (string) $return->getCustomer()?->getLastname(),
            )),
            'reason' => (string) $return->getReasonTitle(),
            'refund' => $this->formatAmount((float) $return->getRefundAmount(), $symbol),
            'lines' => $lines,
            'store_name' => (string) ConfigQuery::read('store_name', ''),
        ];
    }

    private function formatAmount(float $amount, string $symbol): string
    {
        $formatted = number_format($amount, 2, '.', ' ');

        return '' === $symbol ? $formatted : $formatted.' '.$symbol;
    }
}
