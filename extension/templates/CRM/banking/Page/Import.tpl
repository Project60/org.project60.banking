
<div class="crm-block crm-form-block crm-import-datasource-form-block" id="choose-data-source">
  <h3>Select Import Method</h3>
    <table class="form-layout">
      <tbody>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td class="label"><label for="dataSource">Import Plugin<span title="This field is required." class="crm-marker">*</span></label>
          </td>
          <td>
            <select class="form-select required" id="banking-importer-plugin" name="importer-plugin" onchange="todo(this.value);">
              <option selected="selected" value="CSV Importer">CSV Importer</option>
              <option value="SEPA XML">SEPA XML</option>
            </select>
          </td>
        </tr>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td class="label"><label for="dataSource">Plugin Configuration<span title="This field is required." class="crm-marker">*</span></label>
          </td>
          <td>
            <select class="form-select required" id="banking-importer-plugin-configuration" name="importer-plugin" onchange="todo(this.value);">
              <option selected="selected" value="Default">Default</option>
              <option value="GLS Online Banking">GLS Online Banking</option>
            </select>
          </td>
        </tr>
    </tbody>
  </table>
</div>

<div class="crm-block crm-form-block crm-import-datasource-form-block">
  <h3>Upload File</h3>
  <table class="form-layout">
    <tbody>
      <tr>
        <td class="label">
          <label for="uploadFile">  Import Data File<span title="This field is required." class="crm-marker">*</span></label>
        </td>
        <td>
          <input type="file" class="form-file required" id="uploadFile" name="uploadFile" maxlength="255" size="30"><br>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<div class="crm-block crm-form-block crm-import-datasource-form-block" id="import options">
  <h3>Import Options</h3>
    <table class="form-layout">
      <tbody>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td>
            <input type="checkbox" class="form-checkbox" value="1" selected="selected">First row contains column headers</input>
          </td>
        </tr>
        <tr class="crm-import-datasource-form-block-dataSource">
          <td>
            <input type="checkbox" class="form-checkbox" value="1" selected="selected">Process payments right away</input>
          </td>
        </tr>
    </tbody>
  </table>
</div>
  
<div class="crm-submit-buttons">
  <span class="crm-button crm-button-type-upload crm-button_qf_DataSource_upload">
    <input type="submit" id="_qf_DataSource_upload-bottom" value="Continue &gt;&gt;" name="_qf_DataSource_upload" class="validate form-submit default">
  </span>
  <span class="crm-button crm-button-type-cancel crm-button_qf_DataSource_cancel">
    <input type="submit" id="_qf_DataSource_cancel-bottom" value="Cancel" name="_qf_DataSource_cancel" class="cancel form-submit">
  </span>
</div>
