{extends file='page.tpl'}

{block name='page_content'}
	<div class="alert alert-info">
		<h2>{l s='Your payment is being confirmed' mod='paydo'}</h2>
		<p>{l s='We are waiting for secure confirmation from PayDo. You can safely return to your account and review the order status there.' mod='paydo'}</p>
	</div>
{/block}
