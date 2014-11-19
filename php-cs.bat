@echo off
echo Running php-cs-fixer...
call php-cs-fixer fix core\lib\Thelia\Action --level=psr2
call php-cs-fixer fix core\lib\Thelia\Command --level=psr2
call php-cs-fixer fix core\lib\Thelia\Condition --level=psr2
call php-cs-fixer fix core\lib\Thelia\Config --level=psr2
call php-cs-fixer fix core\lib\Thelia\Controller --level=psr2
call php-cs-fixer fix core\lib\Thelia\Core --level=psr2
call php-cs-fixer fix core\lib\Thelia\Coupon --level=psr2
call php-cs-fixer fix core\lib\Thelia\Exception --level=psr2
call php-cs-fixer fix core\lib\Thelia\Files --level=psr2
call php-cs-fixer fix core\lib\Thelia\Form --level=psr2
call php-cs-fixer fix core\lib\Thelia\ImportExport --level=psr2
call php-cs-fixer fix core\lib\Thelia\Install --level=psr2
call php-cs-fixer fix core\lib\Thelia\Log --level=psr2
call php-cs-fixer fix core\lib\Thelia\Mailer --level=psr2
for %%F in (core\lib\Thelia\Model\*.php) DO call php-cs-fixer fix %%F --level=psr2
call php-cs-fixer fix core\lib\Thelia\Model\Breadcrumb --level=psr2
call php-cs-fixer fix core\lib\Thelia\Model\Exception --level=psr2
call php-cs-fixer fix core\lib\Thelia\Model\Tools --level=psr2
call php-cs-fixer fix core\lib\Thelia\Module --level=psr2
call php-cs-fixer fix core\lib\Thelia\Rewriting --level=psr2
call php-cs-fixer fix core\lib\Thelia\TaxEngine --level=psr2
call php-cs-fixer fix tests\phpunit\Thelia\Tests --level=psr2
call php-cs-fixer fix core\lib\Thelia\Tools --level=psr2
call php-cs-fixer fix core\lib\Thelia\Type --level=psr2
echo Done.