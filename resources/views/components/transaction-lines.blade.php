@props(['mode' => 'deposit', 'categories', 'prices' => [], 'stock' => [], 'initial' => [], 'dateInput' => 'date'])

@php
    $lineErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => $key === 'lines' || str_starts_with($key, 'lines.'))
        ->flatten()
        ->unique();
@endphp

<div class="mb-3">
    <label class="form-label">{{ __('Items') }} <span class="text-danger">*</span></label>

    @if ($lineErrors->isNotEmpty())
        <div class="alert alert-danger py-2">
            <ul class="mb-0">
                @foreach ($lineErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="tx-lines" data-mode="{{ $mode }}" data-date-input="{{ $dateInput }}"
         data-placeholder="{{ __('Select a category') }}" data-no-price="{{ __('No price') }}"
         data-stock-label="{{ __('Stock:') }}" data-remove-label="{{ __('Remove') }}"
         data-prices="{{ json_encode($prices) }}" data-stock="{{ json_encode($stock) }}"
         data-categories="{{ json_encode($categories) }}" data-initial="{{ json_encode($initial) }}">
        <div class="table-responsive">
            <table class="table align-middle mb-2">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">{{ __('Category') }}</th>
                        <th style="min-width: 150px;">{{ __('Weight') }}</th>
                        <th class="text-end" style="min-width: 150px;">{{ __('Price per unit') }}</th>
                        <th class="text-end">{{ __('Subtotal') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-lines-body></tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>{{ __('Total') }}</td>
                        <td data-total-weight>0</td>
                        <td></td>
                        <td class="text-end text-nowrap" data-total-amount>Rp 0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-add-line>{{ __('Add line') }}</button>
    </div>
</div>

@once
    @push('scripts')
        <script>
(function () {
    var root = document.getElementById('tx-lines');
    if (!root) { return; }

    var mode = root.dataset.mode;                       // 'deposit' | 'sale'
    var prices = JSON.parse(root.dataset.prices || '{}');
    var stock = JSON.parse(root.dataset.stock || '{}');
    var categories = JSON.parse(root.dataset.categories || '[]');
    var initial = JSON.parse(root.dataset.initial || '[]');
    var dateInput = document.getElementById(root.dataset.dateInput);
    var body = root.querySelector('[data-lines-body]');
    var totalWeightEl = root.querySelector('[data-total-weight]');
    var totalAmountEl = root.querySelector('[data-total-amount]');
    var rows = [];
    var nextIndex = 0;

    var nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });
    var mf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

    function selectedDay() {
        return dateInput && dateInput.value ? dateInput.value : new Date().toISOString().slice(0, 10);
    }

    // Latest price row whose date is on or before the chosen day (rows come sorted by date).
    function priceFor(categoryId) {
        var list = prices[categoryId] || [];
        var day = selectedDay();
        var found = null;
        for (var i = 0; i < list.length; i++) {
            if (list[i].date <= day) { found = list[i]; }
        }
        return found;
    }

    function el(tag, className) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        return node;
    }

    function addRow(line) {
        line = line || {};
        var i = nextIndex++;
        var tr = el('tr');
        var row = { tr: tr, touched: false };

        // Category
        var tdCategory = el('td');
        row.select = el('select', 'form-select');
        row.select.name = 'lines[' + i + '][waste_category_id]';
        row.select.required = true;
        var blank = el('option');
        blank.value = '';
        blank.textContent = root.dataset.placeholder || '';
        row.select.appendChild(blank);
        categories.forEach(function (c) {
            var option = el('option');
            option.value = c.id;
            option.textContent = c.label;
            if (line.waste_category_id !== undefined && String(line.waste_category_id) === String(c.id)) {
                option.selected = true;
            }
            row.select.appendChild(option);
        });
        tdCategory.appendChild(row.select);
        tr.appendChild(tdCategory);

        // Weight (+ stock hint for sales)
        var tdWeight = el('td');
        row.weight = el('input', 'form-control');
        row.weight.type = 'number';
        row.weight.name = 'lines[' + i + '][weight]';
        row.weight.step = '0.001';
        row.weight.min = '0.001';
        row.weight.required = true;
        if (line.weight !== undefined && line.weight !== null) { row.weight.value = line.weight; }
        tdWeight.appendChild(row.weight);
        if (mode === 'sale') {
            row.hint = el('div', 'form-text');
            tdWeight.appendChild(row.hint);
        }
        tr.appendChild(tdWeight);

        // Price: read-only estimate for deposits, editable input for sales
        var tdPrice = el('td', 'text-end');
        if (mode === 'sale') {
            row.priceInput = el('input', 'form-control text-end');
            row.priceInput.type = 'number';
            row.priceInput.name = 'lines[' + i + '][price_per_unit]';
            row.priceInput.step = 'any';
            row.priceInput.min = '0';
            row.priceInput.required = true;
            if (line.price_per_unit !== undefined && line.price_per_unit !== null && line.price_per_unit !== '') {
                row.priceInput.value = line.price_per_unit;
                row.touched = true;                       // keep a value the user (or a failed submit) already set
            }
            tdPrice.appendChild(row.priceInput);
        } else {
            row.priceCell = el('span');
            tdPrice.appendChild(row.priceCell);
        }
        tr.appendChild(tdPrice);

        // Subtotal + remove
        row.subtotalCell = el('td', 'text-end text-nowrap');
        tr.appendChild(row.subtotalCell);

        var tdRemove = el('td', 'text-end');
        var remove = el('button', 'btn btn-sm btn-outline-danger');
        remove.type = 'button';
        remove.textContent = '\u00d7';
        remove.setAttribute('aria-label', root.dataset.removeLabel || 'Remove');
        remove.addEventListener('click', function () { removeRow(row); });
        tdRemove.appendChild(remove);
        tr.appendChild(tdRemove);

        row.select.addEventListener('change', function () { refreshRow(row, true); updateTotals(); });
        row.weight.addEventListener('input', function () { refreshRow(row, false); updateTotals(); });
        if (row.priceInput) {
            row.priceInput.addEventListener('input', function () { row.touched = true; refreshRow(row, false); updateTotals(); });
        }

        body.appendChild(tr);
        rows.push(row);
        refreshRow(row, true);
        updateTotals();
        return row;
    }

    function removeRow(row) {
        if (rows.length === 1) {                           // keep one empty row instead of none
            row.select.value = '';
            row.weight.value = '';
            if (row.priceInput) { row.priceInput.value = ''; row.touched = false; }
            refreshRow(row, true);
        } else {
            body.removeChild(row.tr);
            rows.splice(rows.indexOf(row), 1);
        }
        updateTotals();
    }

    function refreshRow(row, categoryChanged) {
        var id = row.select.value;
        var price = id ? priceFor(id) : null;
        var weight = parseFloat(row.weight.value);
        if (isNaN(weight)) { weight = 0; }
        var unit = null;

        if (mode === 'deposit') {
            unit = price ? price.buy : null;
            if (!id) {
                row.priceCell.textContent = '-';
            } else if (price) {
                row.priceCell.textContent = mf.format(price.buy);
            } else {
                row.priceCell.textContent = root.dataset.noPrice || '!';
            }
            row.priceCell.className = id && !price ? 'text-danger' : '';
        } else {
            // Suggest the reference selling price until the user types their own.
            if (!row.touched && (categoryChanged || row.priceInput.value === '')) {
                row.priceInput.value = price && price.sell !== null ? price.sell : '';
            }
            var typed = parseFloat(row.priceInput.value);
            unit = isNaN(typed) ? null : typed;

            var available = id && stock[id] !== undefined ? stock[id] : 0;
            row.hint.textContent = id ? (root.dataset.stockLabel || '') + ' ' + nf.format(available) : '';
            row.hint.className = 'form-text' + (id && weight > available ? ' text-danger' : '');
        }

        row.weightValue = weight;
        row.subtotal = unit !== null && weight > 0 ? Math.round(weight * unit * 100) / 100 : 0;
        row.subtotalCell.textContent = unit !== null && weight > 0 ? mf.format(row.subtotal) : '-';
    }

    function updateTotals() {
        var weight = 0, amount = 0;
        rows.forEach(function (r) { weight += r.weightValue || 0; amount += r.subtotal || 0; });
        totalWeightEl.textContent = nf.format(Math.round(weight * 1000) / 1000);
        totalAmountEl.textContent = 'Rp ' + mf.format(Math.round(amount * 100) / 100);
    }

    function refreshAll(categoryChanged) {
        rows.forEach(function (r) { refreshRow(r, categoryChanged); });
        updateTotals();
    }

    // A different date can change which price applies.
    if (dateInput) {
        dateInput.addEventListener('change', function () { refreshAll(true); });
        dateInput.addEventListener('input', function () { refreshAll(true); });
    }

    root.querySelector('[data-add-line]').addEventListener('click', function () { addRow({}); });

    (initial.length ? initial : [{}]).forEach(function (line) { addRow(line); });
})();
        </script>
    @endpush
@endonce
