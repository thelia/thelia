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
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderQuery;

#[AsCommand(name: 'mail:render', description: 'Render a mail message to a file without sending it, to preview it or work on its template')]
class MailRender extends ContainerAwareCommand
{
    public function __construct(
        private readonly MailerFactory $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('message-code', InputArgument::REQUIRED, 'Mail message code, e.g. order_confirmation')
            ->addOption('order', null, InputOption::VALUE_REQUIRED, 'Order id, to provide the order context (order_id, order_ref, customer)')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale to render in, e.g. fr_FR (default: store default language)')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output file prefix: writes <prefix>.html and <prefix>.txt (default: standard output)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locale = $input->getOption('locale');
        $lang = null !== $locale ? LangQuery::create()->findOneByLocale($locale) : null;

        // A mail is rendered outside an HTTP request; push a request with a session so the
        // parser and the formatting helpers have a locale and a currency to work with.
        $this->initRequest($lang);

        $parameters = [];
        $to = ['recipient@example.com' => 'Recipient'];

        if (null !== $orderId = $input->getOption('order')) {
            $order = OrderQuery::create()->findPk((int) $orderId);

            if (null === $order) {
                $output->writeln(\sprintf('<error>Order %s not found.</error>', $orderId));

                return Command::FAILURE;
            }

            $parameters['order_id'] = $order->getId();
            $parameters['order_ref'] = $order->getRef();

            if (null !== $customer = $order->getCustomer()) {
                $parameters['customer_id'] = $customer->getId();
                $to = [($customer->getEmail() ?: 'customer@example.com') => trim($customer->getFirstname().' '.$customer->getLastname())];
            }
        }

        $from = [(ConfigQuery::getStoreEmail() ?: 'store@example.com') => ConfigQuery::getStoreName() ?: 'Store'];

        try {
            $email = $this->mailer->createEmailMessage((string) $input->getArgument('message-code'), $from, $to, $parameters, $locale);
        } catch (\Exception $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $html = (string) $email->getHtmlBody();
        $text = (string) $email->getTextBody();

        $out = $input->getOption('out');

        if (null !== $out) {
            file_put_contents($out.'.html', $html);
            file_put_contents($out.'.txt', $text);
            $output->writeln(\sprintf('<info>Wrote %s.html (%d bytes) and %s.txt (%d bytes).</info>', $out, \strlen($html), $out, \strlen($text)));

            return Command::SUCCESS;
        }

        $output->writeln($html);

        return Command::SUCCESS;
    }
}
