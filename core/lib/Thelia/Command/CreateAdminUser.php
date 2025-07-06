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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;

#[AsCommand(name: 'admin:create', description: 'Create a new administrator user')]
class CreateAdminUser extends ContainerAwareCommand
{
    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this
            ->setHelp('The <info>admin:create</info> command create a new administration user.')
            ->addOption(
                'login_name',
                null,
                InputOption::VALUE_OPTIONAL,
                'Admin login name',
                null
            )
            ->addOption(
                'first_name',
                null,
                InputOption::VALUE_OPTIONAL,
                'User first name',
                null
            )
            ->addOption(
                'last_name',
                null,
                InputOption::VALUE_OPTIONAL,
                'User last name',
                null
            )
            ->addOption(
                'email',
                null,
                InputOption::VALUE_OPTIONAL,
                'Admin email address',
                null
            )
            ->addOption(
                'locale',
                null,
                InputOption::VALUE_OPTIONAL,
                'Preferred locale (default: en_US)',
                null
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                'Password',
                null
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Admin $admin */
        $admin = $this->getAdminInfo($input, $output);

        $admin->save();

        $output->writeln([
            '',
            '<info>User '.$admin->getLogin().' successfully created.</info>',
            '',
        ]);

        return 0;
    }

    protected function enterData(
        QuestionHelper $helper,
        InputInterface $input,
        OutputInterface $output,
        $label,
        $errorMessage,
        $hidden = false,
    ): mixed {
        $question = new Question($this->decorateInfo($label));

        if ($hidden) {
            $question->setHidden(true);
            $question->setHiddenFallback(false);
        }

        $question->setValidator(function ($value) use (&$errorMessage) {
            if (trim($value) === '') {
                throw new \Exception($errorMessage);
            }

            return $value;
        });

        return $helper->ask($input, $output, $question);
    }

    /**
     * Ask to user all needed information.
     *
     * @return array
     */
    protected function getAdminInfo(InputInterface $input, OutputInterface $output): Admin
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $admin = new Admin();

        $admin->setLogin($input->getOption('login_name') ?: $this->enterLogin($helper, $input, $output));
        $admin->setFirstname($input->getOption('first_name') ?: $this->enterData($helper, $input, $output, 'User first name : ', 'Please enter user first name.'));
        $admin->setLastname($input->getOption('last_name') ?: $this->enterData($helper, $input, $output, 'User last name : ', 'Please enter user last name.'));

        $admin->setLocale($input->getOption('locale') ?: 'en_US');
        $admin->setEmail($input->getOption('email') ?: $this->enterEmail($helper, $input, $output));

        while (true) {
            $password = $input->getOption('password') ?: $this->enterData($helper, $input, $output, 'Password : ', 'Please enter a password.', true);
            $password_again = $input->getOption('password') ?: $this->enterData($helper, $input, $output, 'Password (again): ', 'Please enter the password again.', true);

            if (!empty($password) && $password == $password_again) {
                $admin->setPassword($password);

                break;
            }

            $output->writeln('Passwords are different, please try again.');
        }

        $admin->setProfile(null);

        return $admin;
    }

    protected function decorateInfo($text): string
    {
        return \sprintf('<info>%s</info>', $text);
    }

    protected function enterLogin(QuestionHelper $helper, InputInterface $input, OutputInterface $output): mixed
    {
        $question = new Question($this->decorateInfo('Admin login name : '));

        $question->setValidator(function ($answer): string {
            $answer = trim($answer);
            if ($answer === '' || $answer === '0') {
                throw new \RuntimeException('Please enter a login name.');
            }

            if (AdminQuery::create()->findOneByLogin($answer)) {
                throw new \RuntimeException('An administrator with this login already exists.');
            }

            return $answer;
        });

        return $helper->ask($input, $output, $question);
    }

    protected function enterEmail(QuestionHelper $helper, InputInterface $input, OutputInterface $output): mixed
    {
        $question = new Question($this->decorateInfo('Admin email or empty value : '));

        $question->setValidator(function ($answer): string {
            $answer = trim($answer);
            if ($answer !== '' && $answer !== '0' && !filter_var($answer, \FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Please enter an email or an empty value.');
            }

            if (AdminQuery::create()->findOneByEmail($answer)) {
                throw new \RuntimeException('An administrator with this email already exists.');
            }

            return $answer === '' || $answer === '0' ? uniqid('CHANGE_ME_') : $answer;
        });

        return $helper->ask($input, $output, $question);
    }
}
