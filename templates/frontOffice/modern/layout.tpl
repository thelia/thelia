<!doctype html>



{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='fo.modern'}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{block name="init"}{/block}
{block name="no-return-functions"}{/block}
{assign var="store_name" value={config key="store_name"}}
{assign var="store_description" value={config key="store_description"}}
{assign var="lang_code" value={lang attr="code"}}
{assign var="lang_locale" value={lang attr="locale"}}
{if not $store_name}{assign var="store_name" value={intl l='Thelia V2'}}{/if}
{if not $store_description}{assign var="store_description" value={$store_name}}{/if}

{loop type="auth" name="isConnected.check" role="CUSTOMER"}
{assign var="isConnected" value=true}
{/loop}

{* Build a clean page title *}
{$seoPageTitle = ""}
{if $page_title|default:null}
  {$seoPageTitle = $page_title}
{elseif $breadcrumbs|default:null}
  {foreach from=$breadcrumbs|array_reverse item=breadcrumb}
    {$seoPageTitle = $seoPageTitle|cat: $breadcrumb.title|cat:" - "}
  {/foreach}
  {$seoPageTitle = $seoPageTitle|cat: $store_name}
{else}
  {$seoPageTitle = $store_name}
{/if}

{* Build a clean page descritpion *}


<html lang="{$lang_locale|replace:'_':'-'}" class="no-js">

<head>
  <meta charset="utf-8">

  {store_seo_meta locale=$lang_locale}

  {* Page Title *}
  {strip}
    <title>
      {block name="page-title"}
        {$seoPageTitle}{$page_info|default:null}
      {/block}
    </title>
  {/strip}

  {block name="prefetch-js"}
    {encore_entry_prefetch_script_tags entry="app"}
  {/block}

  {* Meta Tags *}
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="generator" content="{intl l='Thelia V2'}">
  <meta name="format-detection" content="telephone=no">

  <meta property="og:url" content="{navigate to='current'}">
  <meta property="og:type" content="website">
  <meta property="og:title" content="{$seoPageTitle}">
  <meta property="og:description"
    content="{$page_description|default:$store_description|default:{config key="store_description"}|default:""}">
  <meta property="og:site_name" content="{$store_name}">
  <meta property="og:locale" content="{lang attr="locale"}">

  <meta name="twitter:card" content="summary">
  <meta name="twitter:url" content="{navigate to='current'}">
  <meta name="twitter:title" content="{$seoPageTitle}">
  <meta name="twitter:description"
    content="{$page_description|default:$store_description|default:{config key="store_description"}|default:""}">

  {block name="meta"}
    <meta name="description"
      content="{$page_description|default:$store_description|default:{config key="store_description"}|default:""}">

    {if isset($page_keywords)}
      <meta name="keywords" content="{$page_keywords}">
    {else}
      <meta name="keywords" content="{$default_keywords}">
    {/if}

  {/block}

  {block name="share-meta"}
    {* Share meta *}
    <meta property="og:image" content="{encore_manifest_file file="dist/images/default-social-thumbnail.webp"}">
    <meta property="og:image:secure_url" content="{encore_manifest_file file="dist/images/default-social-thumbnail.webp"}">
    <meta property="og:image:width" content="450">
    <meta property="og:image:height" content="450">
    <meta name="twitter:image" content="{encore_manifest_file file="dist/images/default-social-thumbnail.webp"}">
  {/block}

  {hook name="main.head-top"}

  {* CSS *}
  {block name="css"}
    {encore_entry_link_tags entry="print" attributes=["media" => "print"]}
    {encore_entry_link_tags entry="app"}
  {/block}

  {* FAVICON *}
  <link rel="apple-touch-icon" sizes="180x180" href="{encore_manifest_file file="dist/favicons/apple-touch-icon.png"}">
  <link rel="icon" type="image/png" sizes="32x32" href="{encore_manifest_file file="dist/favicons/favicon-32x32.png"}">
  <link rel="icon" type="image/png" sizes="16x16" href="{encore_manifest_file file="dist/favicons/favicon-16x16.png"}">
  <link rel="manifest" href="{encore_manifest_file file="dist/favicons/site.webmanifest"}">
  <link rel="mask-icon" href="{encore_manifest_file file="dist/favicons/safari-pinned-tab.svg"}" color="#5bbad5">
  <meta name="msapplication-TileColor" content="#ffffff">
  <meta name="theme-color" content="#ffffff">


  {* Favicon *}
  {* <link rel="shortcut icon" type="image/x-icon" href="{encore_manifest_file file="favicon.ico"}"> *}

  {* Feeds *}
  <link rel="alternate" type="application/rss+xml" title="{intl l='All products'}"
    href="{url path="/feed/catalog/%lang" lang=$lang_locale}">
  <link rel="alternate" type="application/rss+xml" title="{intl l='All contents'}"
    href="{url path="/feed/content/%lang" lang=$lang_locale}">
  <link rel="alternate" type="application/rss+xml" title="{intl l='All brands'}"
    href="{url path="/feed/brand/%lang" lang=$lang_locale}">
  {block name="feeds"}{/block}

  {hook name="main.head-bottom"}
  {block name="structured-data"}{/block}
</head>

<body class="font-body no-js {block name="body-class"}{/block}" itemscope itemtype="http://schema.org/WebPage">

  <!-- Accessibility -->
  <a class="sr-only" href="#content" {if $smarty.server.REQUEST_URI === "/"}tabindex="1"
    {/if}>{intl l="Skip to content"}</a>
  <a class="sr-only" href="#MainNavigation" {if $smarty.server.REQUEST_URI === "/"}tabindex="1"
    {/if}>{intl l="Skip to main navigation"}</a>

  {$currentTabIndex = null}
  {if $smarty.server.REQUEST_URI === "/"}
    {$currentTabIndex = "1"}
  {/if}

  {hook name="main.body-top"}
  {block name="header"}
    {include file="components/smarty/Header/Header.html" tabindex=$currentTabIndex}
  {/block}

  {block name="navigation"}
    {include file="components/smarty/Navigation/Navigation.html" tabindex=$currentTabIndex}
  {/block}

  <main>
    {hook name="main.content-top"}
    <div id="content">
      {block name="main-content"}{/block}
    </div>
    {hook name="main.content-bottom"}
  </main>

  {block name="footer"}
    {include file="components/smarty/Footer/Footer.html"}
  {/block}

  {block name="javascript-data"}{/block}

  {block name="minicart"}{include file="components/React/MiniCart/MiniCart.html" }{/block}
  {block name="minilogin"}{include file="components/React/MiniLogin/MiniLogin.html"}{/block}
  {block name="modals"}{/block}

  {strip}
  <script>
    window.DEFAULT_CURRENCY_CODE = "{currency attr="code"}";
    window.DEFAULT_CURRENCY_SYMBOL = "{currency attr="symbol"}";
    window.SVG_SPRITE_URL = "{encore_manifest_file file="dist/sprite.svg"}";
    window.PLACEHOLDER_IMAGE = "{encore_manifest_file file="dist/images/placeholder.webp"}";
  </script>
  {/strip}

  {block name="javascript-initialization"}{/block}

  {block name="javascript"}
    {encore_entry_script_tags entry="app"}
  {/block}

  {block name="after-javascript-include"}{/block}

  {hook name="main.after-javascript-include"}

  {hook name="main.javascript-initialization"}

  {hook name="main.body-bottom"}
</body>

</html>
