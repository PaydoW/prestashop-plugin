{if isset($confirmation)}
  <div class="alert alert-success">{l s='Settings updated' mod='paydo'}</div>
{/if}
{if isset($methodSyncSuccess)}
  <div class="alert alert-success">{$methodSyncSuccess|escape:'htmlall':'UTF-8'}</div>
{/if}
{if isset($methodSyncError)}
  <div class="alert alert-danger">{$methodSyncError|escape:'htmlall':'UTF-8'}</div>
{/if}
{if isset($configurationError)}
  <div class="alert alert-danger">{$configurationError|escape:'htmlall':'UTF-8'}</div>
{/if}

<div class="panel" id="fieldset_0">
  <div class="panel-heading">
    <i class="icon-cogs"></i>
    {l s='Paydo configuration' mod='paydo'}
  </div>

  <div class="form-wrapper">
    <form id="paydo_form" action="" method="post" class="form-horizontal">
      {* Enable Paydo payments *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Enable Paydo payments' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <span class="radio">
            <label for="enablePayments_1">
              <input type="radio" id="enablePayments_1" name="enablePayments" value="1" {if isset($enablePayments) && $enablePayments eq '1'}checked{/if}>
              {l s='Yes' mod='paydo'}
            </label>
          </span>
          <span class="radio">
            <label for="enablePayments_0">
              <input type="radio" id="enablePayments_0" name="enablePayments" value="0" {if !isset($enablePayments) || $enablePayments eq '0'}checked{/if}>
              {l s='No' mod='paydo'}
            </label>
          </span>
        </div>
      </div>

      {* Display Name *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Display Name' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <input type="text" id="displayName" name="displayName" value="{if isset($displayName)}{$displayName|escape:'htmlall':'UTF-8'}{/if}" class="form-control" placeholder="{l s='Payment method displayed name' mod='paydo'}">
        </div>
      </div>

      {* Description *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Description' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <input type="text" id="description" name="description" value="{if isset($description)}{$description|escape:'htmlall':'UTF-8'}{/if}" class="form-control" placeholder="{l s='Payment method description' mod='paydo'}">
        </div>
      </div>

      {* Public Key *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Public Key' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <input type="text" id="publicKey" name="publicKey" value="{if isset($publicKey)}{$publicKey|escape:'htmlall':'UTF-8'}{/if}" class="form-control" autocomplete="off" placeholder="{l s='Issued in project settings' mod='paydo'}">
        </div>
      </div>

      {* Secret Key *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Secret Key' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <input type="password" id="secretKey" name="secretKey" value="" class="form-control" autocomplete="new-password" placeholder="{if $hasSecretKey}{l s='Saved — leave blank to keep the current key' mod='paydo'}{else}{l s='Issued in project settings' mod='paydo'}{/if}">
        </div>
      </div>

      <hr>

      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Internet Acquiring payment methods' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <span class="radio">
            <label for="methodsMode_1">
              <input type="radio" id="methodsMode_1" name="methodsMode" value="1" {if isset($methodsMode) && $methodsMode eq '1'}checked{/if}>
              {l s='Let customers choose a PayDo payment method on checkout' mod='paydo'}
            </label>
          </span>
          <span class="radio">
            <label for="methodsMode_0">
              <input type="radio" id="methodsMode_0" name="methodsMode" value="0" {if !isset($methodsMode) || $methodsMode eq '0'}checked{/if}>
              {l s='Use the standard PayDo checkout without a predefined method' mod='paydo'}
            </label>
          </span>
          <p class="help-block">
            {l s='When enabled, only the methods selected below are offered and the chosen method is sent to PayDo.' mod='paydo'}
          </p>
        </div>
      </div>

      <div id="paydo-methods-settings">
		{if $methodsMode && !$hostedPageEnabled && !$hasEnabledMethods}
		  <div class="alert alert-warning">
			{l s='Enable PayDo Hosted Page or at least one synchronized payment method before using this mode.' mod='paydo'}
		  </div>
		{/if}

        <div class="form-group">
          <label class="control-label col-lg-4">
            {l s='PayDo Hosted Page' mod='paydo'}
          </label>
          <div class="col-lg-8">
            <span class="radio">
              <label for="hostedPageEnabled_1">
                <input type="radio" id="hostedPageEnabled_1" name="hostedPageEnabled" value="1" {if $hostedPageEnabled}checked{/if}>
                {l s='Show the common PayDo payment page' mod='paydo'}
              </label>
            </span>
            <span class="radio">
              <label for="hostedPageEnabled_0">
                <input type="radio" id="hostedPageEnabled_0" name="hostedPageEnabled" value="0" {if !$hostedPageEnabled}checked{/if}>
                {l s='Hide Hosted Page and show only enabled methods' mod='paydo'}
              </label>
            </span>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="projectId">
            {l s='Project ID' mod='paydo'}
          </label>
          <div class="col-lg-8">
            <input type="text" id="projectId" name="projectId" value="{if isset($projectId)}{$projectId|escape:'htmlall':'UTF-8'}{/if}" class="form-control" autocomplete="off">
            <p class="help-block">{l s='Used to load available payment methods from PayDo.' mod='paydo'}</p>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="jwtToken">
            {l s='JWT Token' mod='paydo'}
          </label>
          <div class="col-lg-8">
            <textarea id="jwtToken" name="jwtToken" class="form-control" rows="3" autocomplete="off">{if isset($jwtToken)}{$jwtToken|escape:'htmlall':'UTF-8'}{/if}</textarea>
            <p class="help-block">{l s='Bearer token used only to synchronize the method list.' mod='paydo'}</p>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4">
            {l s='Synchronize payment methods' mod='paydo'}
          </label>
          <div class="col-lg-8">
            <button type="submit" name="paydo_sync_methods" value="1" class="btn btn-default">
              <i class="icon-refresh"></i> {l s='Sync from PayDo' mod='paydo'}
            </button>
            <p class="help-block">{l s='Project ID is saved when synchronization starts. A blank JWT field reuses the saved token.' mod='paydo'}</p>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label col-lg-4" for="paydo-methods-search">
            {l s='Enabled PayDo methods' mod='paydo'}
          </label>
          <div class="col-lg-8">
            {if $paymentMethods|@count > 0}
              <input type="text" id="paydo-methods-search" class="form-control" placeholder="{l s='Search payment method' mod='paydo'}" style="margin-bottom:10px;">
              <div id="paydo-methods-box" style="display:grid; gap:8px; max-height:320px; overflow:auto; padding:10px; border:1px solid #ddd; border-radius:4px;">
                {foreach from=$paymentMethods item=method}
                  <label class="paydo-method-row" data-title="{$method.title|escape:'htmlall':'UTF-8'} {$method.identifier|escape:'htmlall':'UTF-8'}" style="display:flex; align-items:center; gap:10px; margin:0; padding:6px; border-radius:4px;">
                    <input type="checkbox" name="enabledMethods[]" value="{$method.identifier|escape:'htmlall':'UTF-8'}" {if $method.enabled}checked{/if}>
                    {if $method.logo}
                      <img src="{$method.logo|escape:'htmlall':'UTF-8'}" alt="" style="height:22px; width:auto; max-width:60px; object-fit:contain;">
                    {/if}
                    <span>
                      <strong>{$method.title|escape:'htmlall':'UTF-8'}</strong><br>
                      <small>#{$method.identifier|escape:'htmlall':'UTF-8'}</small>
                    </span>
                  </label>
                {/foreach}
              </div>
            {else}
              <div class="alert alert-info" style="margin-bottom:0;">
                {l s='No methods loaded yet. Fill in Project ID and JWT Token, then click Sync from PayDo.' mod='paydo'}
              </div>
            {/if}
            <p class="help-block">{l s='Synchronized methods are not enabled automatically. Select the methods to show on checkout and save settings.' mod='paydo'}</p>
          </div>
        </div>
      </div>

      {* IPN URL (read-only + Copy) *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='IPN URL' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <div class="input-group">
            <input type="text" id="ipnUrl" value="{$ipnUrl|escape:'htmlall':'UTF-8'}" class="form-control" readonly>
            <span class="input-group-btn">
              <button type="button" class="btn btn-default" id="paydo-copy-ipn">
                {l s='Copy' mod='paydo'}
              </button>
            </span>
          </div>
          <p class="help-block">
            {l s='Use this URL in your Paydo dashboard (IPN settings) to receive payment status updates.' mod='paydo'}
          </p>
        </div>
      </div>

      <div class="panel-footer">
        <button type="submit" name="pc_form" class="btn btn-primary pull-right">
          <i class="process-icon-save"></i> {l s='Save' mod='paydo'}
        </button>
      </div>
    </form>
  </div>
</div>

{literal}
<script>
(function () {
  var btn = document.getElementById('paydo-copy-ipn');
  if (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById('ipnUrl');
      if (!input) return;
      var oldText = btn.innerText;

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(function () {
          btn.innerText = '✔ Copied!';
          setTimeout(function(){ btn.innerText = oldText; }, 1500);
        });
        return;
      }

      input.select();
      try {
        document.execCommand('copy');
        btn.innerText = '✔ Copied!';
        setTimeout(function(){ btn.innerText = oldText; }, 1500);
      } catch (err) {}
      window.getSelection && window.getSelection().removeAllRanges && window.getSelection().removeAllRanges();
    });
  }

  var modeInputs = document.querySelectorAll('input[name="methodsMode"]');
  var methodsSettings = document.getElementById('paydo-methods-settings');
  function toggleMethodsSettings() {
    if (!methodsSettings) return;
    var enabled = document.getElementById('methodsMode_1');
    methodsSettings.style.display = enabled && enabled.checked ? '' : 'none';
  }
  for (var i = 0; i < modeInputs.length; i++) {
    modeInputs[i].addEventListener('change', toggleMethodsSettings);
  }
  toggleMethodsSettings();

  var search = document.getElementById('paydo-methods-search');
  var rows = document.querySelectorAll('.paydo-method-row');
  if (search && rows.length) {
    search.addEventListener('input', function () {
      var query = (search.value || '').trim().toLowerCase();
      for (var index = 0; index < rows.length; index++) {
        var title = (rows[index].getAttribute('data-title') || '').toLowerCase();
        rows[index].style.display = !query || title.indexOf(query) !== -1 ? '' : 'none';
      }
    });
  }
})();
</script>
{/literal}
