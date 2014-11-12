{*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************}

{*
This is the default TEXT mail layout. Use {$message_body} as a placeholder for
the text message defined in the 'TEXT Message' field in the back-office, or the
content of the selected template in the back-office.

Be sure to use the nofilter modifier, to prevent HTML escaping.

DO NOT DELETE THIS FILE, some plugins may use it.
*}

{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='email.default'}
{default_locale locale={$locale}}

{block name='message-body'}{$message_body nofilter}{/block}