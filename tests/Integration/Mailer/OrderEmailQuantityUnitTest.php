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

namespace Thelia\Tests\Integration\Mailer;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Email;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The order e-mails write the quantity of a line with the decimals and the unit the order
 * froze, in the language of the mail: "1,25 kg", "2" for two bottles of 0.75 l, and "2" again
 * for a line placed before quantities had decimals.
 *
 * The templates belong to the e-mail package, released on its own cycle: a package that does
 * not call `format_quantity` yet is reported as skipped rather than failed.
 */
final class OrderEmailQuantityUnitTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function messages(): iterable
    {
        yield 'confirmation' => ['order_confirmation'];
        yield 'notification' => ['order_notification'];
    }

    #[DataProvider('messages')]
    public function testAQuantitySoldByMeasureReadsWithItsUnitInTheLanguageOfTheMail(string $message): void
    {
        $this->skipUnlessTheMessageFormatsQuantities($message);

        $french = $this->render($message, 'fr_FR');
        $english = $this->render($message, 'en_US');

        self::assertStringContainsString('1,25 kg x', (string) $french->getHtmlBody());
        self::assertMatchesRegularExpression('/Quantit\S+ : 1,25 kg\s/', (string) $french->getTextBody());
        self::assertStringContainsString('1.25 kg x', (string) $english->getHtmlBody());
        self::assertMatchesRegularExpression('/Quantit\S+ : 1\.25 kg\s/', (string) $english->getTextBody());
    }

    #[DataProvider('messages')]
    public function testAQuantitySoldByThePieceAndALegacyLineReadAsWholeNumbers(string $message): void
    {
        $this->skipUnlessTheMessageFormatsQuantities($message);

        $email = $this->render($message, 'fr_FR');
        $html = (string) $email->getHtmlBody();
        $text = (string) $email->getTextBody();

        self::assertMatchesRegularExpression('/\s2 x /', $html);
        self::assertMatchesRegularExpression('/\s3 x /', $html);
        self::assertMatchesRegularExpression('/Quantit\S+ : 2\s/', $text);
        self::assertMatchesRegularExpression('/Quantit\S+ : 3\s/', $text);
        self::assertStringNotContainsString('2 l', $html.$text);
    }

    private function render(string $message, string $locale): Email
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order($factory->customer($factory->customerTitle()), ['statusCode' => OrderStatus::CODE_PAID]);

        $this->addLine($order->getId(), 'Weighed product', 1.25, 2, 'kg');
        $this->addLine($order->getId(), 'Bottle of 0.75 l', 2.0, 0, 'l');
        $this->addLine($order->getId(), 'Legacy product', 3.0, 0, null);

        $mailerFactory = new MailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
            new NullTransport(),
        );

        return $mailerFactory->createEmailMessage(
            $message,
            ['sender@example.com' => 'Sender'],
            ['recipient@example.com' => 'Recipient'],
            ['order_id' => $order->getId(), 'order_ref' => $order->getRef()],
            $locale,
        );
    }

    private function addLine(int $orderId, string $title, float $quantity, int $decimals, ?string $unitCode): void
    {
        (new OrderProduct())
            ->setOrderId($orderId)
            ->setProductRef('REF-'.md5($title))
            ->setProductSaleElementsRef('PSE-'.md5($title))
            ->setTitle($title)
            ->setQuantity($quantity)
            ->setQuantityDecimals($decimals)
            ->setUnitCode($unitCode)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());
    }

    private function skipUnlessTheMessageFormatsQuantities(string $message): void
    {
        $directory = $this->getService(TemplateHelperInterface::class)->getActiveMailTemplate()->getAbsolutePath();

        foreach (['html', 'txt'] as $format) {
            $file = $directory.\DIRECTORY_SEPARATOR.$message.'.'.$format.'.twig';

            if (!file_exists($file) || !str_contains((string) file_get_contents($file), 'format_quantity')) {
                self::markTestSkipped(\sprintf('The installed e-mail template does not format the quantities of %s yet.', $file));
            }
        }
    }
}
