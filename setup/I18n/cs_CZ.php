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

return [
    'Update on your return {{ return_ref }}' => 'Aktualizace k vašemu vrácení {{ return_ref }}',
    'A {{ config("store_name") }} account has been created for you' => 'Pro Vás byl vytvořen účet v obchodě {{ config("store_name") }}',
    'Address' => 'Adresa',
    'Category' => 'Kategorie',
    'Confirm your %store account' => 'Potvrďte svůj účet v obchodě {{ config("store_name") }}',
    'Content' => 'Obsah',
    'New order {{ order_ref }} placed on {{ config("store_name") }}' => 'Nová objednávka {{ order_ref }} v obchodě {{ config("store_name") }}',
    'New password request on %store' => 'Žádost o nové heslo v obchodě {{ config("store_name") }}',
    'New York' => 'New York',
    'Order status transition override' => 'Vynucení přechodu stavu objednávky',
    'Order payment capture' => 'Stržení platby objednávky',
    'Awaiting capture' => 'Čeká na stržení platby',
    'Reset your password on {{ config("store_name") }}' => 'Obnovte své heslo v obchodě {{ config("store_name") }}',
    'Search' => 'Vyhledávání',
    'Your %store activation code' => 'Váš aktivační kód pro {{ config("store_name") }}',
    'Your account information on {{ config("store_name") }} has been changed.' => 'Informace o účtu v obchodě {{ config("store_name") }} byly změněny.',
    'Your order {{ order_ref }} at {{ config("store_name") }}' => 'Vaše objednávka {{ order_ref }} v obchodě {{ config("store_name") }}',
    'Your subscription to %store newsletter' => 'Váš odběr novinek z obchodu {{ config("store_name") }}',
    'Promotion' => 'Akce',
    'Newness' => 'Novinka',
    'Your order {{ order_ref }} has been shipped' => 'Vaše objednávka {{ order_ref }} byla odeslána',
    'Partially shipped' => 'Částečně odesláno',
    'Availability' => 'Dostupnost',
    'Customer rating' => 'Hodnocení zákazníků',
    'Price' => 'Cena',
    'Configuration background jobs' => 'Konfigurace úloh na pozadí',
    'Low stock on {{ config("store_name") }}' => 'Nízký stav zásob na {{ config("store_name") }}',
    '{{ report_title }}: {{ period_start }} to {{ period_end }}' => '{{ report_title }}: od {{ period_start }} do {{ period_end }}',
    'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}' => 'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}',
    '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}' => '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}',
];
