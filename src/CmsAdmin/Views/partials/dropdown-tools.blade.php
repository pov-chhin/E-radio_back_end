{{-- Shared by the admin forms that use the dropdown look (the box with an arrow, a search box and a list under it). Include it before the
     form's own script. It defines two things on window:
       dropdownShell(box, { toggle, panel, search, onOpen, onReset }) — the opening and closing: the button opens the panel with its
         search box focused; a click outside, Escape or tabbing away closes it, and the search is cleared on the way out (onReset puts
         the list back). Enter in the search box doesn't send the form. Returns close(focusButton).
       selectDropdown(select, { searchPlaceholder, searchLabel, emptyText }) — turns a plain <select> into a dropdown that picks one,
         under a search box. The <select> stays in the form and does the work (the form posts it, the scripts read it): this hides it,
         lists its options as radio buttons and, when one is picked, sets it and tells it so (a change event). An option with no value is
         the "nothing picked" text: the button shows it, but it is not in the list (one picked can't be taken back to none). --}}
<script>
    window.dropdownShell = (box, { toggle, panel, search, onOpen, onReset }) => {
        const close = focus => {
            if (panel.hidden) return;
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            if (search.value) { search.value = ''; onReset?.(); }
            if (focus) toggle.focus();
        };

        toggle.addEventListener('click', () => {
            if (!panel.hidden) { close(false); return; }
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            onOpen?.();
            search.focus();
        });
        search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
        document.addEventListener('click', event => { if (!box.contains(event.target)) close(false); });
        box.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) close(true); });
        box.addEventListener('focusout', event => { if (event.relatedTarget && !box.contains(event.relatedTarget)) close(false); });
        return close;
    };

    window.selectDropdown = (select, { searchPlaceholder, searchLabel, emptyText }) => {
        if (!select || select.disabled) return;
        const field = select.closest('label');
        const make = (tag, className, attributes = {}) => {
            const element = document.createElement(tag);
            if (className) element.className = className;
            Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));
            return element;
        };

        const box = make('div', 'tag-dropdown');
        const toggle = make('button', 'tag-dropdown-toggle', { type: 'button', 'aria-haspopup': 'true', 'aria-expanded': 'false' });
        const label = make('span');
        const panel = make('div', 'tag-dropdown-panel');
        const searchBox = make('div', 'tag-dropdown-search');
        const search = make('input', '', { type: 'search', placeholder: searchPlaceholder, 'aria-label': searchLabel, autocomplete: 'off' });
        const list = make('div', 'tag-tree', { style: 'max-height:164px' });
        const empty = make('p', 'muted', { style: 'margin:0;padding:4px 14px 12px;font-size:13px' });
        empty.textContent = emptyText;
        empty.hidden = true;
        panel.hidden = true;
        toggle.append(label);
        searchBox.append(search);
        panel.append(searchBox, list, empty);
        box.append(toggle, panel);

        // An option with no value is the "nothing picked" text: shown on the button, not something to pick.
        const rows = [...select.options].filter(option => option.value !== '').map(option => {
            const row = make('label', 'tag-tree-leaf');
            const input = make('input', '', { type: 'radio' });
            const name = make('span');
            name.textContent = option.textContent.trim();
            row.append(input, name);
            list.append(row);
            return { option, row, input };
        });

        const filter = () => {
            const needle = search.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach(({ option, row }) => {
                const show = !option.hidden && !option.disabled && (!needle || row.textContent.toLowerCase().includes(needle));
                row.style.display = show ? '' : 'none';
                if (show) shown++;
            });
            empty.hidden = shown > 0;
        };

        const refresh = () => {
            rows.forEach(({ option, input }) => { input.checked = option.selected; });
            label.textContent = select.selectedOptions[0]?.textContent.trim() ?? '';
            filter();
        };

        const close = window.dropdownShell(box, { toggle, panel, search, onReset: filter });
        rows.forEach(({ option, input }) => input.addEventListener('click', () => {
            if (select.value !== option.value) {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            refresh();
            close(true);
        }));
        search.addEventListener('input', () => { list.scrollTop = 0; filter(); });
        select.addEventListener('change', refresh);

        // The box sits under the label (a click inside a label would reach the select), which keeps its caption.
        select.style.display = 'none';
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;
        field.style.marginBottom = '6px';
        box.style.marginBottom = '12px';
        field.after(box);
        refresh();
    };
</script>
