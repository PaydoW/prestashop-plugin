<form method="post" action="{$action|escape:'htmlall':'UTF-8'}">
	{if $description}
		<p>{$description|escape:'htmlall':'UTF-8'}</p>
	{/if}

	{if $methodsMode}
		<fieldset class="paydo-payment-methods" style="margin-top: 12px;">
			<legend style="font-size: 1rem; font-weight: 600;">
				{l s='Choose PayDo method:' mod='paydo'}
			</legend>

			{if $hostedPageEnabled}
				<label style="display:flex; align-items:center; gap:10px; padding:8px 10px; margin:6px 0; border:1px solid #ddd; border-radius:8px; cursor:pointer;">
					<input type="radio" name="paydo_method" value="hosted" required checked style="margin:0;">
					<span>
						<strong>{l s='PayDo Hosted Page' mod='paydo'}</strong><br>
						<small>{l s='Choose from all payment methods available on the secure PayDo page' mod='paydo'}</small>
					</span>
				</label>
			{/if}

			{foreach from=$paymentMethods item=method name=paydoMethods}
				<label style="display:flex; align-items:center; gap:10px; padding:8px 10px; margin:6px 0; border:1px solid #ddd; border-radius:8px; cursor:pointer;">
					<input
						type="radio"
						name="paydo_method"
						value="{$method.identifier|escape:'htmlall':'UTF-8'}"
						required
						{if !$hostedPageEnabled && $smarty.foreach.paydoMethods.first}checked{/if}
						style="margin:0;"
					>
					{if $method.logo}
						<img
							src="{$method.logo|escape:'htmlall':'UTF-8'}"
							alt=""
							loading="lazy"
							style="height:22px; width:auto; max-width:60px; object-fit:contain;"
						>
					{/if}
					<span>{$method.title|escape:'htmlall':'UTF-8'}</span>
				</label>
			{/foreach}
		</fieldset>
	{/if}
</form>
