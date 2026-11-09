(() => {
  const initialize = () => {
    document.querySelectorAll('.ee-app').forEach(app => {
      app.classList.add('ee-enhanced');
      app.querySelector('[data-ee-menu]')?.addEventListener('click', event => {
        const button = event.currentTarget;
        const open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        app.classList.toggle('ee-menu-open', open);
      });

      app.querySelectorAll('.ee-chip-group input, .ee-search-bar select').forEach(control => {
        control.addEventListener('change', () => control.form.requestSubmit());
      });

      app.querySelectorAll('[data-ee-tabs]').forEach(group => {
        const tabs = [...group.querySelectorAll('[role="tab"]')];
        const panels = [...group.querySelectorAll('[role="tabpanel"]')];
        const activate = (tab, updateHash = false) => {
          tabs.forEach(item => {
            const selected = item === tab;
            item.setAttribute('aria-selected', String(selected));
            item.tabIndex = selected ? 0 : -1;
          });
          panels.forEach(panel => { panel.hidden = panel.id !== tab.getAttribute('aria-controls'); });
          if (updateHash) history.replaceState(history.state, '', `#${tab.getAttribute('aria-controls')}`);
        };
        const fromHash = tabs.find(tab => `#${tab.getAttribute('aria-controls')}` === location.hash);
        activate(fromHash || tabs[0]);
        window.addEventListener('hashchange', () => {
          const tab = tabs.find(item => `#${item.getAttribute('aria-controls')}` === location.hash);
          if (tab) activate(tab);
        });
        tabs.forEach((tab, index) => {
          tab.addEventListener('click', () => activate(tab, true));
          tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next !== undefined) { event.preventDefault(); activate(tabs[next], true); tabs[next].focus(); }
          });
        });
      });

      const search = app.querySelector('[data-ee-registration-search]');
      const status = app.querySelector('[data-ee-registration-status]');
      const rows = [...app.querySelectorAll('[data-ee-registration-row]')];
      const filterRows = () => {
        let visible = 0;
        rows.forEach(row => {
          row.hidden = Boolean((search?.value && !row.textContent.toLowerCase().includes(search.value.toLowerCase())) || (status?.value && row.dataset.status !== status.value));
          if (!row.hidden) visible++;
        });
        const empty = app.querySelector('[data-ee-filter-empty]');
        if (empty) empty.hidden = visible > 0;
      };
      search?.addEventListener('input', filterRows);
      status?.addEventListener('change', filterRows);

      app.querySelector('[data-ee-logo-input]')?.addEventListener('change', event => {
        const file = event.target.files[0];
        const preview = app.querySelector('[data-ee-logo-preview]');
        if (!file || !file.type.startsWith('image/') || !preview) return;
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.alt = 'Selected company logo';
        image.onload = () => URL.revokeObjectURL(url);
        image.onerror = () => URL.revokeObjectURL(url);
        image.src = url;
        preview.replaceChildren(image);
      });
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
  else initialize();
})();
