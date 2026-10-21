{{-- Shared by the admin lists that are a DataTable with a strip of filters over it (Content, Radio Channels). Include it after the
     table with ['tableId' => the table's id]. It brings the styles of the strip, of DataTables' own controls and of the responsive
     table, jQuery and DataTables from the same CDN as the icon font, and attachTableFilters(table, { filtersId, dateKey, searchLabel }):
     the page makes its DataTable, then calls it. The strip is a .table-filters element holding labelled .filter-field boxes whose
     control carries data-filter="..." (a select matches the row's data-<name> attribute, which may hold several values joined by |; "from" and "to" are days, compared with the
     row's data-<dateKey>), and a [data-filter-clear] button. --}}
<style>
        /* The filter bar: a tinted strip across the top of the card. Each control has an icon at its left and its own arrow; one
           that is set turns pink, so what narrows the list is easy to spot. */
        .table-filters { display: flex; flex-wrap: nowrap; align-items: flex-end; gap: 12px; padding: 16px 18px; background: #fafafd; border-bottom: 1px solid var(--line); }
        .filter-field { flex: 1 1 160px; min-width: 150px; max-width: 220px; display: flex; flex-direction: column; gap: 6px; margin: 0; }
        .filter-field > span:first-child { font-size: 11px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; color: #747785; }
        .filter-control { position: relative; display: block; }
        .filter-control > i { position: absolute; z-index: 1; left: 12px; top: 50%; transform: translateY(-50%); font-size: 15px; color: #9598a6; pointer-events: none; }
        .filter-control select, .filter-control input[type=date] { width: 100%; min-width: 0; height: 38px; padding: 0 12px 0 38px; border: 1px solid var(--line); border-radius: 10px; background-color: #fff; font-size: 15px; font-weight: 600; color: #3d3f4d; box-shadow: 0 1px 2px rgba(25,25,50,.03); transition: border-color .15s, box-shadow .15s, background-color .15s; }
        .filter-control select { appearance: none; -webkit-appearance: none; padding-right: 34px; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%233d3f4d' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 13px center; }
        .filter-control input[type=date] { position: relative; }
        /* The whole date box opens its calendar, not just the little icon at its right. */
        .filter-control input[type=date]::-webkit-calendar-picker-indicator { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .filter-control select:hover, .filter-control input[type=date]:hover { border-color: #d3d5df; }
        .filter-control select:focus, .filter-control input[type=date]:focus { outline: 0; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(55,140,240,.18); }
        .filter-control.is-active select, .filter-control.is-active input[type=date] { border-color: var(--pink); background-color: #fff7f9; color: #c9304a; }
        .filter-control.is-active > i { color: var(--pink); }
        .table-filters .filter-clear { flex: none; width: 38px; height: 38px; padding: 0; }
        /* DataTables' search box and "per page" are moved onto this row by the script: the search first, "per page" at the right end. */
        .table-filters-tools { display: flex; flex-wrap: nowrap; align-items: flex-end; gap: 12px; flex: 0 1 auto; min-width: 0; margin-left: auto; }
        .table-filters .dt-length { flex: none; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #747785; }
        .table-filters .dt-length label { margin: 0; font-size: 13px; font-weight: 600; color: #747785; }
        .table-filters .dt-length select, .table-filters .dt-search input { height: 38px; padding: 0 12px; border: 1px solid var(--line); border-radius: 10px; background-color: #fff; font-size: 15px; font-weight: 600; color: #3d3f4d; box-shadow: 0 1px 2px rgba(25,25,50,.03); transition: border-color .15s, box-shadow .15s; }
        .table-filters .dt-length select { cursor: pointer; }
        .table-filters .filter-search { flex: 1.3 1 190px; max-width: 280px; }
        .table-filters .dt-search { display: block; }
        .table-filters .dt-search label { display: none; }
        .table-filters .dt-search input { width: 100%; min-width: 0; padding-left: 36px; font-weight: 500; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%239598a6' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='M20 20l-3.5-3.5'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: 13px center; }
        .table-filters .dt-length select:hover, .table-filters .dt-search input:hover { border-color: #d3d5df; }
        @media (max-width: 1320px) {
            .table-filters, .table-filters-tools { flex-wrap: wrap; }
            .table-filters-tools { flex: 1 1 100%; margin-left: 0; }
            .table-filters .filter-search { flex: 1 1 100%; max-width: none; }
        }
        .table-filters .dt-length select:focus, .table-filters .dt-search input:focus { outline: 0; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(55,140,240,.18); }
        #{{ $tableId }}_wrapper .dt-layout-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px 16px; padding: 12px 18px; }
        #{{ $tableId }}_wrapper .dt-layout-row.dt-layout-table { display: block; padding: 0; overflow-x: auto; }
        #{{ $tableId }}_wrapper .dt-layout-cell { display: flex; align-items: center; gap: 8px; }
        #{{ $tableId }}_wrapper .dt-layout-table .dt-layout-cell { display: block; }
        #{{ $tableId }}_wrapper label { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 13px; font-weight: 600; color: #747785; }
        #{{ $tableId }}_wrapper .dt-search input, #{{ $tableId }}_wrapper .dt-length select { height: 34px; padding: 0 12px; border: 1px solid var(--line); border-radius: 9px; background: #fff; font-size: 15px; }
        #{{ $tableId }}_wrapper .dt-search input { min-width: 220px; }
        #{{ $tableId }}_wrapper .dt-info { font-size: 13px; color: #8b8fa3; }
        #{{ $tableId }}_wrapper .dt-paging { display: flex; gap: 4px; }
        #{{ $tableId }}_wrapper .dt-paging-button { min-width: 30px; height: 30px; padding: 0 9px; border: 1px solid var(--line); border-radius: 8px; background: #fff; font-size: 13px; font-weight: 700; color: #3d3f4d; cursor: pointer; }
        #{{ $tableId }}_wrapper .dt-paging-button.current { background: var(--pink); border-color: var(--pink); color: #fff; }
        #{{ $tableId }}_wrapper .dt-paging-button.disabled { opacity: .4; cursor: default; }
        #{{ $tableId }} th.dt-orderable-asc, #{{ $tableId }} th.dt-orderable-desc { cursor: pointer; }
        #{{ $tableId }} th .dt-column-header { display: inline-flex; align-items: center; gap: 6px; }
        #{{ $tableId }} th .dt-column-order::after { content: '\2195'; opacity: .35; font-size: 13px; }
        #{{ $tableId }} th.dt-ordering-asc .dt-column-order::after { content: '\25B2'; opacity: 1; }
        #{{ $tableId }} th.dt-ordering-desc .dt-column-order::after { content: '\25BC'; opacity: 1; }
        #{{ $tableId }} th.dt-orderable-none .dt-column-order { display: none; }
        /* DataTables' own "nothing here" cell: centred across the table, like the empty row it replaces. */
        #{{ $tableId }} td.dt-empty { text-align: center; padding: 54px 18px; color: var(--muted); }
        /* Type scale: 15px for what is read first (a row's title, the values in the filter boxes), 13px for the rest of the table and
           its controls, 11px for captions, column heads, badges and the small lines under a title or a time. */
        #{{ $tableId }} { font-size: 13px; }
        #{{ $tableId }} th { font-size: 11px; }
        #{{ $tableId }} .badge { font-size: 11px; }
        /* Responsive. Wide and medium screens: the columns keep their width (a cell never wraps, the Actions buttons stay on one
           line) and the table scrolls sideways inside its card when it doesn't fit. Phones (760px and under): the head row goes and
           each row becomes a card, every value labelled by its column. */
        #{{ $tableId }} { min-width: 1000px; }
        #{{ $tableId }} th, #{{ $tableId }} td { white-space: nowrap; padding-left: 14px; padding-right: 14px; }
        #{{ $tableId }} td:first-child { white-space: normal; }
        #{{ $tableId }} .table-actions { flex-wrap: nowrap; }
        @media (max-width: 760px) {
            .table-filters { gap: 10px; padding: 14px; }
            .filter-field { flex: 1 1 calc(50% - 10px); min-width: 0; max-width: none; }
            .table-filters .filter-search, .table-filters-tools { flex: 1 1 100%; }
            .table-filters-tools { justify-content: flex-end; }
            #{{ $tableId }}_wrapper .dt-layout-row { padding: 10px 14px; }
            #{{ $tableId }}_wrapper .dt-layout-row.dt-layout-table { overflow-x: visible; padding: 0; }
            #{{ $tableId }} { min-width: 0; }
            #{{ $tableId }} thead { display: none; }
            #{{ $tableId }}, #{{ $tableId }} tbody { display: block; width: 100%; }
            #{{ $tableId }} tbody tr { display: block; margin: 12px 14px; padding: 6px 0; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
            #{{ $tableId }} tbody tr:hover td { background: transparent; }
            #{{ $tableId }} td { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 14px; border-top: 0; text-align: right; white-space: normal; }
            #{{ $tableId }} td::before { content: attr(data-label); flex: none; font-size: 11px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; color: #999ca9; text-align: left; }
            #{{ $tableId }} td:not([data-label])::before { display: none; }
            #{{ $tableId }} td:first-child { display: block; text-align: left; padding-top: 12px; }
            #{{ $tableId }} td:last-child { justify-content: flex-start; padding-bottom: 12px; }
            #{{ $tableId }} td.dt-empty { display: block; text-align: center; }
        }
        @media (max-width: 480px) {
            .filter-field { flex-basis: 100%; }
        }
        #{{ $tableId }} .secondary-button.compact, #{{ $tableId }} .danger-button.compact { font-size: 13px; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables.net/2.1.8/dataTables.min.js"></script>
<script>
    // The boxes in the strip narrow the rows on top of the search box, and take effect as soon as one is changed. A row with no day
    // drops out as soon as a day is set. DataTables' search box (the first column) and "per page" move onto the strip: one row.
    window.attachTableFilters = (table, { filtersId, dateKey, searchLabel }) => {
        const filters = document.getElementById(filtersId);
        if (!filters) return;

        // One row, in the table's own order: DataTables' search box comes first, then the filters, and "per page" ends it. They move
        // onto the filter strip, and the row they leave is dropped.
        const wrapper = table.table().container();
        const searchField = document.createElement('div');
        const caption = document.createElement('span');
        const tools = document.createElement('div');
        searchField.className = 'filter-field filter-search';
        caption.textContent = searchLabel;
        searchField.append(caption, wrapper.querySelector('.dt-search'));
        filters.prepend(searchField);
        tools.className = 'table-filters-tools';
        tools.append(wrapper.querySelector('.dt-length'));
        filters.append(tools);
        wrapper.querySelector('.dt-layout-row')?.remove();

        const controls = [...filters.querySelectorAll('[data-filter]')];   // not the search box and "per page" that share the strip
        const clear = filters.querySelector('[data-filter-clear]');

        jQuery.fn.dataTable.ext.search.push((settings, data, index) => {
            // Another table's search is none of ours. The row's own <tr> (rows filtered out are off the page, so the
            // page can't be asked) carries what is filtered by.
            if (settings.nTable && settings.nTable !== table.table().node()) return true;
            const node = table.row(index).node();
            if (!node) return true;
            const row = node.dataset;
            return controls.every(control => {
                const name = control.dataset.filter;
                const value = control.value;
                if (!value) return true;
                if (name === 'from') return !!row[dateKey] && row[dateKey] >= value;
                if (name === 'to') return !!row[dateKey] && row[dateKey] <= value;
                // A row may carry several values for one filter (a user's roles), told apart by | : any of them matches.
                return (row[name] ?? '').split('|').includes(value);
            });
        });

        // Changing a box filters at once.
        const apply = () => {
            controls.forEach(control => control.closest('.filter-control').classList.toggle('is-active', control.value !== ''));
            clear.hidden = !controls.some(control => control.value);
            table.draw();
        };
        filters.addEventListener('change', event => { if (event.target.matches('[data-filter]')) apply(); });
        clear.addEventListener('click', () => {
            controls.forEach(control => { control.value = ''; });
            apply();
        });
    };
</script>
