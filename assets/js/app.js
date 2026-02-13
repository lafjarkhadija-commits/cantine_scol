const toggleBtn = document.getElementById('sidebar-toggle');
const sidebar = document.querySelector('.sidebar');
if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
}

document.addEventListener('click', (e) => {
    if (!sidebar || !sidebar.classList.contains('open')) return;
    const isToggle = e.target.closest('#sidebar-toggle');
    const isSidebar = e.target.closest('.sidebar');
    if (!isToggle && !isSidebar) {
        sidebar.classList.remove('open');
    }
});

const confirmForms = document.querySelectorAll('[data-confirm]');
confirmForms.forEach((el) => {
    el.addEventListener('submit', (e) => {
        const message = el.getAttribute('data-confirm') || 'Confirmer l\'action ?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});

const cmdSearch = document.getElementById('cmd-search');
const cmdTable = document.getElementById('cmd-table');
const cmdStatus = document.getElementById('cmd-status');
function applyCmdFilters() {
    if (!cmdTable) return;
    const q = (cmdSearch?.value || '').toLowerCase();
    const val = (cmdStatus?.value || '').toLowerCase();
    const rows = Array.from(cmdTable.querySelectorAll('tr')).slice(1);
    rows.forEach(tr => {
        const text = tr.innerText.toLowerCase();
        const statut = (tr.children[4]?.innerText || '').toLowerCase();
        const matchQ = text.includes(q);
        const matchS = val ? statut.includes(val) : true;
        tr.style.display = matchQ && matchS ? '' : 'none';
    });
}
if (cmdSearch) cmdSearch.addEventListener('input', applyCmdFilters);
if (cmdStatus) cmdStatus.addEventListener('change', applyCmdFilters);

const menusGrid = document.getElementById('menus-grid');
const menusSearch = document.getElementById('menus-search');
const menusType = document.getElementById('menus-type');
function filterMenus() {
    if (!menusGrid) return;
    const q = (menusSearch?.value || '').toLowerCase();
    const type = (menusType?.value || '').toLowerCase();
    menusGrid.querySelectorAll('.menu-card').forEach(card => {
        const txt = card.innerText.toLowerCase();
        const t = (card.dataset.type || '').toLowerCase();
        const matchTxt = txt.includes(q);
        const matchType = type ? t === type : true;
        card.style.display = matchTxt && matchType ? '' : 'none';
    });
}
if (menusSearch) menusSearch.addEventListener('input', filterMenus);
if (menusType) menusType.addEventListener('change', filterMenus);
