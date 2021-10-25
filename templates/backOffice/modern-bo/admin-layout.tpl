{block name="check-auth"}
{check_auth role="ADMIN" resource="{block name="check-resource"}{/block}" module="{block name="check-module"}{/block}" access="{block name="check-access"}{/block}" login_tpl="/admin/login"}
{/block}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'}


{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='bo.modern'}

{block name="no-return-functions"}{/block}

<!DOCTYPE html>
<html lang="{$lang_code}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{block name="page-title"}Default Page Title{/block} - {intl l='Thelia Back Office'}</title>

  {block name="css"}
    {getAssetsFromEntrypoints entry="app" type="css"}
  {/block}

</head>

<body>
  <aside></aside>
  <main>Hello</main>

  {block name="javascript"}
    {getAssetsFromEntrypoints entry="app" type="js"}
  {/block}

</body>
</html>
