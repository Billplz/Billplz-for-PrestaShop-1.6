{if isset($status) && $status} <br class="clear" />
	<p class="alert alert-error">Sorry, there is an error processing your order. <strong><u>Error: {$status|escape:'htmlall':'UTF-8'}</u></strong></p>
	<p>Please report this to our <a href="{$link->getPagelink('contact')|escape:'htmlall':'UTF-8'}">customer support</a></p>
{elseif isset($unsuccessful) && $unsuccessful}<br class="clear" />
	<p class="alert alert-error">{$unsuccessful|escape:'htmlall':'UTF-8'}</p>
{elseif isset($mismatched) && $mismatched}<br class="clear" />
	<p class="alert alert-error">{$mismatched|escape:'htmlall':'UTF-8'}</p>
{/if}