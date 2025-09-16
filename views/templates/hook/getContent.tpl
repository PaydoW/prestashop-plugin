{if isset($confirmation)}
  <div class="alert alert-success">{l s='Settings updated' mod='paydo'}</div>
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
          <input type="text" id="displayName" name="displayName" value="{if isset($displayName)}{$displayName}{/if}" class="form-control" placeholder="{l s='Payment method displayed name' mod='paydo'}">
        </div>
      </div>

      {* Description *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Description' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <input type="text" id="description" name="description" value="{if isset($description)}{$description}{/if}" class="form-control" placeholder="{l s='Payment method description' mod='paydo'}">
        </div>
      </div>

      {* Public Key *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Public Key' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <input type="text" id="publicKey" name="publicKey" value="{if isset($publicKey)}{$publicKey}{/if}" class="form-control" placeholder="{l s='Issued in project settings' mod='paydo'}">
        </div>
      </div>

      {* Secret Key *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='Secret Key' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <input type="text" id="secretKey" name="secretKey" value="{if isset($secretKey)}{$secretKey}{/if}" class="form-control" placeholder="{l s='Issued in project settings' mod='paydo'}">
        </div>
      </div>

      {* IPN URL (read-only + Copy) *}
      <div class="form-group">
        <label class="control-label col-lg-4">
          {l s='IPN URL' mod='paydo'}
        </label>
        <div class="col-lg-8">
          <div class="input-group">
            <input type="text" id="ipnUrl" value="{$ipnUrl}" class="form-control" readonly>
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
  if (!btn) return;
  btn.addEventListener('click', function (e) {
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
})();
</script>
{/literal}
