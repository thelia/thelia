{if $step eq "cart"}
    {assign var="step1" value=" active"}
    {assign var="step2" value=" disabled"}
    {assign var="step3" value=" disabled"}
    {assign var="step4" value=" disabled"}
{elseif $step eq "delivery"}
    {assign var="step1" value=""}
    {assign var="step2" value=" active"}
    {assign var="step3" value=" disabled"}
    {assign var="step4" value=" disabled"}
{elseif $step eq "invoice"}
    {assign var="step1" value=""}
    {assign var="step2" value=""}
    {assign var="step3" value=" active"}
    {assign var="step4" value=" disabled"}
{elseif $step eq "last"}
    {assign var="step1" value=" disabled"}
    {assign var="step2" value=" disabled"}
    {assign var="step3" value=" disabled"}
    {assign var="step4" value=" active"}
{/if}

<div class="btn-group checkout-progress">
    <a class="btn btn-step{$step1}" href="{url path="/cart"}" role="button"><span class="step-nb">1</span> <span class="step-label">{intl l="Your Cart"}</span></a>
    <a class="btn btn-step{$step2}" href="{url path="/order/delivery"}" role="button"><span class="step-nb">2</span> <span class="step-label">{intl l="Billing and delivery"}</span></a>
    <a class="btn btn-step{$step3}" href="{url path="/order/invoice"}" role="button"><span class="step-nb">3</span> <span class="step-label">{intl l="Check my order"}</span></a>
    <a class="btn btn-step{$step4}" href="{url path="/order/placed"}" role="button"><span class="step-nb">4</span> <span class="step-label">{intl l="Secure payment"}</span></a>
</div>
