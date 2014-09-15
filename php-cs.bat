@echo off
echo "Running php-cs-fixer..."
call php-cs-fixer fix core\lib\Thelia\Action --level=all
call php-cs-fixer fix core\lib\Thelia\Cart --level=all
call php-cs-fixer fix core\lib\Thelia\Command --level=all
call php-cs-fixer fix core\lib\Thelia\Condition --level=all
call php-cs-fixer fix core\lib\Thelia\Config --level=all
call php-cs-fixer fix core\lib\Thelia\Controller --level=all
call php-cs-fixer fix core\lib\Thelia\Core --level=all
call php-cs-fixer fix core\lib\Thelia\Coupon --level=all
call php-cs-fixer fix core\lib\Thelia\Exception --level=all
call php-cs-fixer fix core\lib\Thelia\Form --level=all
call php-cs-fixer fix core\lib\Thelia\Install --level=all
call php-cs-fixer fix core\lib\Thelia\Log --level=all
call php-cs-fixer fix core\lib\Thelia\Mailer --level=all
for %%F in (core\lib\Thelia\Model\*.php) DO call php-cs-fixer fix %%F --level=all
call php-cs-fixer fix core\lib\Thelia\Model\Exception --level=all
call php-cs-fixer fix core\lib\Thelia\Model\Tools --level=all
call php-cs-fixer fix core\lib\Thelia\Module --level=all
call php-cs-fixer fix core\lib\Thelia\Rewriting --level=all
call php-cs-fixer fix core\lib\Thelia\TaxEngine --level=all
call php-cs-fixer fix core\lib\Thelia\Tests --level=all
call php-cs-fixer fix core\lib\Thelia\Tools --level=all
call php-cs-fixer fix core\lib\Thelia\Type --level=all
echo "Done."
