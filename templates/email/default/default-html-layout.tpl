{*
This is the default HTML mail layout. Use {$message_body} as a placeholder for
the HTML message defined in the 'HTML Message' field in the back-office, or the
content of the selected template in the back-office.

Be sure to use the nofilter modifier, to prevent HTML escaping.

DO NOT DELETE THIS FILE, some plugins may use it.
*}
{block name='message-body'}{$message_body nofilter}{/block}