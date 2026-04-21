(function (CRM) {

  class CiviBankingImport extends HTMLElement {

    connectedCallback() {
      this.loadStart();
    }

    loadStart() {
      this.apiParams = {};
      this.renderStart();
    }

    renderStart() {
      this.innerHTML = `
        <div class="description"></div>
        <div class="form-group">
          <label for="civiBankingImportFile"></label>
          <input id="civiBankingImportFile" type="file" accept=".csv,.txt,.xml">
        </div>
        <div class="form-group">
          <label for="civiBankingImportUrl"></label>
          <input id="civiBankingImportUrl">
        </div>
      `;

      this.querySelector('.description').innerText = ts('Select a file or paste a URL');

      this.querySelector('label[for=civiBankingImportFile]').innerText = ts('Upload a file');
      this.querySelector('input#civiBankingImportFile').onchange = (e) => this.loadFile(e.target.files[0]);

      this.querySelector('label[for=civiBankingImportUrl]').innerText = ts('Paste a URL');
      this.querySelector('input#civiBankingImportUrl').onpaste = (e) => this.loadUrl(e.clipboardData.getData('text'));
    }

//    loadAccountOptions(input) {
//      CRM.api4('BankAccount', 'get')
//      .then((results) => results.forEach((record) => {
//        const option = document.createElement('option');
//        option.value = record.id;
//        option.innerText = record.description;
//        input.append(option);
//      }))
//    }

    loadFile(file) {
      console.log('file value', file)

      const reader = new FileReader();
      reader.onload = () => this.loadPreviewPage(reader.result.split('\n'));

      reader.readAsText(file);
    }

    loadUrl(url) {
      // get a csv from Google Sheet URL
      if (url.startsWith('https://docs.google.com/spreadsheets/d/')) {
        url = new URL(url);
        const sheetId = url.pathname.split('/')[3];
        const tabId = url.searchParams.get('gid')

        url = `https://docs.google.com/spreadsheets/d/${sheetId}/export?format=csv${tabId ? ('&gid=' + tabId) : ''}`;
      }

     fetch(url)
        .then((response) => {
          if (response.status === '403') {
            throw new Error('access denied');
          }
          if (!response.ok) {
            const message = response.text()
            throw new Error(message);
          }
          return response.text();
        })
        .then((text) => this.loadPreviewPage(text.split('\n')))
        .catch((error) => CRM.alert(`Error fetching ${url}: ${error}`, null, 'error'))
    }

    loadPreviewPage(content) {
      this.apiParams.content = content;

      this.renderPreviewPage();
      this.loadPreview();
    }

    loadPreview() {
      CRM.api4('BankTransactionBatch', 'preview', this.apiParams)
        .then((result) => {
          this.previewResult = result;
          Object.assign(this.apiParams, result.params);
        })
        .catch((error) => this.handleError(error, ts('Error loading preview'), 'warning'))
        .then(() => this.renderPreview());
    }


    loadPreviewSoon() {
      this.querySelector('.preview').innerHTML = '<div class="crm-loading-spinner"></div>';
      clearTimeout(this.nextPreviewLoad);
      this.nextPreviewLoad = setTimeout(() => this.loadPreview(), 1000);
    }


    buildOptionSelector(param, labelText, options) {
      const select = document.createElement('select');

      select.append(...options.map((o) => {
        const option = document.createElement('option');
        option.value = o.value;
        option.innerText = o.label;
        option.selected = (o.value === this.apiParams[param])
        return option;
      }));

      return this.wrapInput(select, param, labelText);
    }

    buildTextInput(param, labelText) {
      const input = document.createElement('input');
      return this.wrapInput(input, param, labelText);
    }

    wrapInput(input, param, labelText) {
      input.onchange = () => {
        this.apiParams[param] = input.value;
        this.loadPreviewSoon();
      };

      const label = document.createElement('label');
      label.innerText = labelText;
      label.append(input);

      return label;
    }

    renderParamInputs() {
      const columnOptions = [
        {
          label: '- auto -',
          value: '',
        },
        ...this.previewResult.headerColumns.map((col) => ({
          label: col,
          value: col
        }))
      ];

      const dateFormatOptions = ['Y-m-d', 'm/d/Y', 'd/m/Y'].map((format) => ({
        label: format,
        value: format
      }));

      this.querySelector('fieldset').replaceChildren(
        this.buildOptionSelector('dateColumn', ts('Date Column'), columnOptions),
        this.buildOptionSelector('dateFormat', ts('Date Format'), dateFormatOptions),
        this.buildOptionSelector('amountColumn', ts('Amount Column'), columnOptions),
        this.buildOptionSelector('referenceColumn', ts('Reference Column'), columnOptions),
    //    this.buildTextInput('statementTitle', ts('Statement Title'))
      );
    }

    renderPreviewPage() {
      this.innerHTML = `
        <strong></strong>

        <fieldset class="crm-flex-justify-between">
        </fieldset>

        <div class="preview">
          <div class="crm-loading-spinner"></div>
        </div>

        <div class="crm-buttons">
          <button type="button" id="cancel"></button>
          <button type="button" id="runImport"></button>
        </div>
      `;
      this.querySelector('strong').innerText = ts('Preview');

      this.querySelector('#cancel').innerText = ts('Back');
      this.querySelector('#cancel').onclick = () => this.loadStart();

      this.querySelector('#runImport').innerText = ts('Run import');
      this.querySelector('#runImport').onclick = () => this.runImport();
    }

    renderPreview() {
      this.renderParamInputs();

      this.querySelector('strong').innerText = ts('Preview: %1', {1: this.previewResult.statement.reference});

      this.querySelector('.preview').innerHTML = '';
      this.querySelector('.preview').append(this.buildTransactionTable(this.previewResult.transactions));

      const invalidRows = this.previewResult.skipped?.invalid;
      if (invalidRows) {
        const invalidWarning = document.createElement('div');
        invalidWarning.classList.add('status', 'status-warning');
        invalidWarning.append(
          ts("%1 invalid rows will be skipped", {1: invalidRows.length}),
          this.buildTransactionTable(invalidRows)
        )
        this.querySelector('.preview').prepend(invalidWarning);
      }
    }

    tabulateTransactionData(transactions) {
      const includeErrorColumn = transactions.some((row) => row.error);
      const dataColumns = Array.from(new Set([].concat(...transactions.map((row) => Object.keys(row.data_parsed)))));

      const header = [ts('Date'), ts('Amount'), ts('Reference'), ...dataColumns];
      if (includeErrorColumn) header.unshift(ts('Error'));

      return [header, ...transactions.map((tx) => {
        const row = [tx.booking_date, tx.amount, tx.bank_reference, ...dataColumns.map((key) => tx.data_parsed[key])];
        if (includeErrorColumn) row.unshift(tx.error);
        return row;
      })];
    }

    buildTransactionTable(transactions) {
      const data = this.tabulateTransactionData(transactions);
      return this.buildTable(data);
    }

    buildTable(data) {
      const table = document.createElement('table');

      table.append(...data.map((rowData, index) => {
        const row = document.createElement('tr');
        row.append(...rowData.map((cellData) => {
          const cell = document.createElement(index === 0 ? 'th' : 'td');
          cell.innerText = cellData;
          return cell;
        }));
        return row;
      }));

      return table;
    }

    runImport() {
      this.innerHTML = '<div class="crm-loading-spinner"></div>';

      CRM.api4('BankTransactionBatch', 'import', this.apiParams)
      .then((result) => {
        if (!result?.statement?.id) {
          // successful import should return a statement id
          throw new Error(result);
        }
        this.runResult = result;
        CRM.alert('Import complete', null, 'success');
      })
      .catch((error) => {
        this.handleError(error, ts('Import failed'), 'error');
        this.loadStart();
        throw error;
      })
      .then(() => this.renderResult());
    }

    renderResult() {
      this.innerHTML = `
        <strong></strong>
        <div class="results">
        </div>
        <div class="crm-buttons">
          <a class="btn"></a>
        </div>
      `;

      this.querySelector('strong').innerText = ts('Import result');

      const errorRows = this.runResult.skipped?.error;
      if (errorRows) {
        const errors = document.createElement('div');
        errors.classList.add('status', 'status-error');
        errors.append(
          ts('%1 rows with errors!', {1: errorRows.length}),
          this.buildTransactionTable(errorRows)
        );
        this.querySelector('.results').append(errors);
      }

      const invalidRows = this.runResult.skipped?.invalid;
      if (invalidRows) {
        const invalid = document.createElement('div');
        invalid.classList.add('status', 'status-warning');
        invalid.append(
          ts('%1 invalid rows were skipped', {1: invalidRows.length}),
          this.buildTransactionTable(invalidRows)
        );
        this.querySelector('.results').append(invalid)
      }

      const success = document.createElement('div');
      success.classList.add('status', 'status-success');
      success.append(
        ts('%1 transactions imported', {1: this.runResult.transactions.length}),
        this.buildTransactionTable(this.runResult.transactions)
      );
      this.querySelector('.results').append(success);

      this.querySelector('a').innerText = ts('Go to statement');
      this.querySelector('a').href = CRM.url(`civicrm/banking/statements/lines?s_id=${this.runResult.statement.id}&reset=1`);
    }

    handleError(error, title, type) {
      console.log(title, error);

      const error_message = error?.error_message ?? error?.message ?? 'Unknown error';
      CRM.alert(error_message, title, type);
    }

  }

  window.customElements.define('civi-banking-import', CiviBankingImport);

})(CRM);





