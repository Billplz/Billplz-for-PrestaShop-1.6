{* This file is never used in Billplz for PrestaShop 1.6 *}

{if isset($error) && $error}
	<h3 class="alert alert-error">{$error}</h3>
{else}
	<h3 class="alert alert-success">Your purchase has been successfully processed.</h3>
	<p>Kindly check for the email notification for the details of your order or look into your <a href="{$orderHistory}">Order History</a></p>
{/if}