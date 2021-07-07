<div class="row">
	<div class="col-xs-12 col-md-6 col-lg-12">
        <p class="payment_module billplz">
			<a title="Pay with Billplz" id="BillplzSubmitBtn" href="#">
				<span class="logo"><img style="height:64px;" alt="Pay with Billplz" src="{$logo_url}"></span><span class="text">Pay with Billplz</span>
				<span class="text_support">Billplz supports the payment options as below:</span>
				<span class="bank_card"><img alt="Pay with Billplz" src="{$payment_logo_url}"></span>
			</a>
		</p>
    </div>
</div>

<form action="{$process_url}" method="post" name="ePayment" id="billplzform">
	<input type="hidden" name="cart_id" value="{$cart_id}" />
</form>
<script type="text/javascript">
	{literal}
		$('#BillplzSubmitBtn').on('click',function(e){
			e.preventDefault();
			$('#billplzform').submit();
			return false;
		});
	{/literal}
</script>	

<style type="text/css">
p.payment_module.billplz a {
	padding-left: 17px;
}
p.payment_module.billplz a span.logo {
	display: inline-block;
	margin-left: 13px;
	margin-right: 10px;
	text-align: left;
}
p.payment_module.billplz a span.logo img {
	width: auto;
	max-width: 95%;
}
p.payment_module.billplz a span.text {
	color: #333;
}
p.payment_module.billplz a span.text_support {
	display: block;
	font-size: 13px;
	font-style: italic;
	margin-top: 15px;
	padding-left: 14px;
}
p.payment_module.billplz a span.bank_card img {
	width: auto;
	max-width: 95%;
	display: block;
}
</style>
