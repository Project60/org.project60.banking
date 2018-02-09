{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
|         R. Lott (hello -at- artfulrobot.uk)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

{* this panel should display the matched rules (if any)
   AND offer to create a new one *}

{* this is just an example *}
<div class="rules-analyser-list">
{if $rules}
  <p>Rules Matched:</p>
    <table>
      <thead>
        <tr>
          <th>Rule ID</th>
          <th>Provided</th>
          <th>Edit</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$rules item=rule}
          <tr>
          <td>{$rule.id}</td>
          <td>{$rule.execution}</td>
          <td><a href="javascript:alert('not coded yet');" >Edit Rule</a></td>
          </tr>
        {/foreach}
      </tbody>
    </table>
{else}
  <p>No <em>rules</em> currently match this transaction.</p>
{/if}
</div>

<div class="rules-analyser-new">
  <a class="button rules-analyser__show-ui-btn"><span><div class="icon ui-icon-plus"></div>{ts}create new rule{/ts}</span></a>
  <input type="hidden" name="rules-analyser__create-new-rule" value="0" />
  <input type="hidden" name="rules-analyser__custom-fields-count" value="0" />

  <div class='rules-analyser__create-ui' style="display:none;">
    <a class="not-floated rules-analyser__hide-create-ui-btn button" href=""><span><div class="icon add-icon  ui-icon-circle-minus"></div>Cancel</span></a>
    <h4 class="rules-analyser__section-heading">{ts}New Rule Criteria{/ts}</h4>
    <p>{ts}What information must match to trigger this rule?{/ts}</p>
    <table id="rules-analyser__conditions">
      <thead><tr><th>Include</th><th>Match</th></tr></thead>
      <tbody>
        <tr>
          <td><input checked="" type="checkbox" id="rules-analyser__party-iban-cb" name="rules-analyser__party-iban-cb" class="rules-analyser__party-iban-cb">
 <label for="rules-analyser__party-iban-cb">{ts}Party IBAN{/ts}</label> </td>
          <td class="rules-analyser__party-iban-ui"><input name="rules-analyser__party-iban" value="{$payment_data_parsed._party_IBAN}" type="text"> </td>
        </tr>

        <tr>
          <td><input checked="" type="checkbox" id="rules-analyser__our-iban-cb" name="rules-analyser__our-iban-cb" class="rules-analyser__our-iban-cb">
 <label for="rules-analyser__our-iban-cb">{ts}Our IBAN{/ts}</label> </td>
          <td class="rules-analyser__our-iban-ui"><input name="rules-analyser__our-iban"value="{$payment_data_parsed._IBAN}" type="text"> </td>
        </tr>

        <tr>
          <td><input checked="" type="checkbox" id="rules-analyser__amount-cb" name="rules-analyser__amount-cb" class="rules-analyser__amount-cb">
 <label for="rules-analyser__amount-cb">{ts}Amount{/ts}</label> </td>
          <td class="rules-analyser__amount-ui">
            <select class="rules-analyser__amount-op" name="rules-analyser__amount-op" >
              <option value="equals">Exactly</option>
              <option>Between</option>
            </select>
            <input name="rules-analyser__amount" value="{$payment->amount}" type="text" class="rules-analyser__amount" size="8" >
            <span class="rules-analyser__amount-2-ui">
              and
              <input name="rules-analyser__amount-2" value="" type="text" class="rules-analyser__amount-2" size="8" >
            </span>
          </td>
        </tr>

        <tr>
          <td><input checked="" type="checkbox" id="rules-analyser__party-name-cb" name="rules-analyser__party-name-cb" class="rules-analyser__party-name-cb">
 <label for="rules-analyser__party-name-cb">{ts}Party Name{/ts}</label> </td>
          <td class="rules-analyser__party-name-ui"><input name="rules-analyser__party-name" value="{$payment_data_parsed.name}" type="text"> </td>
        </tr>


        <tr>
          <td><input checked="" type="checkbox" id="rules-analyser__tx-reference-cb" name="rules-analyser__tx-reference-cb" class="rules-analyser__tx-reference-cb">
 <label for="rules-analyser__tx-reference-cb">{ts}Transaction Reference{/ts}</label> </td>
          <td class="rules-analyser__tx-reference-ui"><input name="rules-analyser__tx-reference" value="{$payment_data_parsed.reference}" type="text"> </td>
        </tr>

        <tr>
          <td><input checked="" type="checkbox" id="rules-analyser__tx-purpose-cb" name="rules-analyser__tx-purpose-cb" class="rules-analyser__tx-purpose-cb">
 <label for="rules-analyser__tx-purpose-cb">{ts}Transaction Purpose{/ts}</label> </td>
          <td class="rules-analyser__tx-purpose-ui"><input name="rules-analyser__tx-purpose" value="{$payment_data_parsed.purpose}" type="text"> </td>
        </tr>
      </tbody>
    </table>
    <div class="rules-analyser__add-condition-hints" >
    <p>{ts}The following data has been extracted for this transaction and may be helpful in adding a custom field.{/ts}</p>
    <table class="rules-analyser__add-condition-hints" style="display:none;">
      <thead>
        <tr><th>Field</th><th>Value</th></tr>
      </thead>
      <tbody>
        {foreach from=$payment_data_parsed item=v key=k}
        {if not in_array($k, ['reference', 'name', 'amount_parsed', '_party_IBAN', '_IBAN', 'purpose'])}
        <tr><td>{$k}</td><td>{$v}</td></tr>
        {/if}
        {/foreach}
      </tbody>
    </table>
    </div>
    <a href class="rules-analyser__add-condition" >{ts}Add custom condition{/ts}</a>

    <h4 class="rules-analyser__section-heading">{ts}New Rule Actions{/ts}</h4>
    <p>{ts}What information is added by this rule?{/ts}</p>
    <table>
      <thead><tr><th>{ts}Field to set{/ts}</th><th>{ts}Value{/ts}</th></tr></thead>
      <tbody>
        {foreach from=$fields_to_set item=field_ui key=rule_field}
          <tr>
            <td>
              <input type="checkbox" id="rules-analyser__set-{$rule_field}-cb" name="rules-analyser__set-{$rule_field}-cb" class="rules-analyser__set-{$rule_field}-cb rules-analyser__action">
              <label for="rules-analyser__set-{$rule_field}-cb">{ts}{$field_ui->label}{/ts}</label> </td>
            <td class="rules-analyser__set-{$rule_field}-ui"><input name="rules-analyser__set-{$rule_field}" value="" type="text"> </td>
          </tr>
        {/foreach}
      </tbody>
    </table>

    <a class="button not-floated" href=""><span><div class="icon add-icon  ui-icon-circle-triangle-e"></div>{ts}Test Rule{/ts}</span></a>
    <span class="rules-analyser__status" ></span>

  </div>
</div>
{literal}
<script>
if (!rulesAnalyser) {
  // Define class.
  var rulesAnalyser = function($el) {
    this.$el = $el;
    this.$ui = $el.find('.rules-analyser__create-ui');

    // Bind UI.

    // Open UI btn.
    this.$open_btn = $el.find('.rules-analyser__show-ui-btn').on('click', this.toggleNewRuleUi.bind(this));

    // Close UI btn.
    $el.find('.rules-analyser__hide-create-ui-btn').on('click', this.toggleNewRuleUi.bind(this));

    // Hidden input remembers whether we want to add a rule.
    this.$new_rule = this.$el.find('input[name="rules-analyser__create-new-rule"]');

    // Checkboxes to enable/disable match fields.
    var updateUi = this.updateUi.bind(this);
    CRM._.each(this.toggleableFields, function(v) {
      $el.find('.rules-analyser__' + v + '-cb').on('click', updateUi);
    });

    // Changing amount operator needs to update UI.
    $el.find('.rules-analyser__amount-op').on('click', updateUi);

    // Allow adding custom conditions.
    this.custom_count = 0;
    $el.find('.rules-analyser__add-condition').on('click', this.addCustomCondition.bind(this));

    updateUi();

    return this;
  };
  CRM._.extend(rulesAnalyser.prototype, {

    toggleableFields: [
      // Fields to set, from config.
      {/literal}{foreach from=$fields_to_set item=field_ui key=rule_field}
      'set-{$rule_field}',
      {/foreach}{literal}
      // Filter fields.
      'party-iban',
      'party-name',
      'our-iban',
      'tx-reference',
      'tx-purpose',
      'amount',
    ],
    toggleNewRuleUi: function(e) {
      e.preventDefault();
      this.$new_rule.val( parseInt(this.$new_rule.val()) ? 0 : 1 );
      this.updateUi();
    },
    updateUi: function() {

      var errors = [];

      if (parseInt(this.$new_rule.val())) {
        this.$ui.show();
        this.$open_btn.hide();
      }
      else {
        this.$ui.hide();
        this.$open_btn.show();
      }

      var self = this;
      CRM._.each(this.toggleableFields, function(v) {
        var isChecked = self.$el.find('.rules-analyser__' + v + '-cb').prop('checked');
        var fieldUi = self.$el.find('.rules-analyser__' + v + '-ui');
        if (isChecked) {
          fieldUi.show();
        }
        else {
          fieldUi.hide();
        }
      });

      var v = this.$el.find('.rules-analyser__amount-op').val();
      var $amount2 = this.$el.find('.rules-analyser__amount-2-ui');
      if (v == 'equals') {
        $amount2.hide();
      }
      else {
        $amount2.show();
      }

      // Custom fields.
      var custom_fields = [];
      for (var i=1; i<=this.custom_count; i++) {
        var ccName = this.$el.find('input[name="rules-analyser__custom-name-' + i + '"]');
        if (ccName.length == 1) {

          if (ccName.val() == '') {
            // Remove unused custom field.
            ccName.closest('tr').remove();
          }
          else if (!ccName.val().match(/^[a-zA-Z0-9_-]+$/)) {
            ccName.css({color: 'red'});
            errors.push("Custom fieldnames are only made up of upper, lowercase letters, numbers and underscores.")
          }
          else if (custom_fields.indexOf(ccName.val()) > -1) {
            ccName.css({color: 'red'});
            errors.push("Do not specify the same custom field twice.");
          }
          else {
            ccName.css({color: ''});
            custom_fields.push(ccName.val());
          }
        }
      }
      if (custom_fields.length == 0) {
        this.$el.find('.rules-analyser__add-condition-hints').hide();
      }

      // If no actions are selected this is not going to be useful.
      if (this.$el.find('.rules-analyser__action:checked').length == 0) {
        errors.push("{/literal}{ts}You must select at least one action.{/ts}{literal}");
      }

      if (errors.length) {
        this.$el.find('.rules-analyser__status').addClass('error').text(errors.join(' '));
      }
      else {
        this.$el.find('.rules-analyser__status').removeClass('error').text('');
      }

    },
    addCustomCondition: function(e) {
      e.preventDefault();
      this.custom_count++;
      this.$el.find('input[name="rules-analyser__custom-fields-count"]').val(this.custom_count);
      var updateUi = this.updateUi.bind(this);
      var ccName = CRM.$('<input placeholder="custom_field_name">')
        .attr('name', 'rules-analyser__custom-name-' + this.custom_count)
        .on('change', updateUi);
      var ccValue = CRM.$('<input placeholder="(match string)">')
        .attr('name', 'rules-analyser__custom-value-' + this.custom_count);

      this.$el.find('#rules-analyser__conditions tr').last().after(
        CRM.$('<tr/>')
        .append(CRM.$('<td>').append(ccName))
        .append(CRM.$('<td>').append(ccValue))
      );
      ccName.focus();
      this.$el.find('.rules-analyser__add-condition-hints').show();
    }
  });
}
// Instantiate UI class.
CRM.$('.rules-analyser-new').not('.processed').each(function() {
  this.rulesAnalyser = new rulesAnalyser(CRM.$(this).addClass('processed'));
});
</script>
{/literal}{* I don't know if you have a better place for a module's CSS? *}{literal}
<style>
  .rules-analyser__party-iban-ui input,
  .rules-analyser__party-name-ui input,
  .rules-analyser__our-iban-ui input,
  .rules-analyser__tx-reference-ui input,
  .rules-analyser__tx-purpose-ui input
  {
    box-sizing: border-box;
    width: 100%;
  }
  .crm-container a.button.not-floated {
    float:none;
    display: inline-block;
  }
  td.suggest h4.rules-analyser__section-heading {
    margin-top: 0.5rem;
    padding-left: 0;
  }
</style>
{/literal}
