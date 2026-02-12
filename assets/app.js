import './styles/app.css';

const GERMAN_WEEKDAYS = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
const numberFmt = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 });

function getDateLabel(dateStr) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const date = new Date(dateStr + 'T00:00:00');

    const diffMs = date.getTime() - today.getTime();
    const diffDays = Math.max(0, Math.round(diffMs / 86_400_000));

    if (diffDays === 0) return 'Heute';
    if (diffDays === 1) return 'Morgen';
    if (diffDays <= 6) {
        // JS getDay(): 0=Sun, 1=Mon, ... 6=Sat -> map to ISO 1=Mon ... 7=Sun
        const isoDay = date.getDay() === 0 ? 7 : date.getDay();
        return GERMAN_WEEKDAYS[isoDay];
    }

    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    return `${dd}.${mm}.`;
}

function groupTasksByDate(tasks) {
    const groups = new Map();
    for (const task of tasks) {
        const label = getDateLabel(task.dueDate);
        if (!groups.has(label)) groups.set(label, []);
        groups.get(label).push(task);
    }
    return groups;
}

function sortTasks(tasks) {
    return tasks.sort((a, b) => {
        const dateCmp = a.dueDate.localeCompare(b.dueDate);
        if (dateCmp !== 0) return dateCmp;
        // Import before Export
        return (a.type === 'Import' ? 0 : 1) - (b.type === 'Import' ? 0 : 1);
    });
}

function buildXitAct(task) {
    return JSON.stringify({
        actions: [
            {
                group: 'A1',
                exchange: 'AI1',
                priceLimits: {},
                buyPartial: false,
                useCXInv: true,
                name: 'BuyItems',
                type: 'CX Buy',
            },
            {
                type: 'MTRA',
                name: 'TransferAction',
                group: 'A1',
                origin: 'Antares Station Warehouse',
                dest: 'Configure on Execution',
            },
        ],
        global: { name: `Import to ${task.planetName}` },
        groups: [{ type: 'Manual', name: 'A1', materials: task.materials }],
    });
}

function calculateTotalPrice(task, prices) {
    let total = 0;
    for (const [ticker, amount] of Object.entries(task.materials)) {
        const p = prices[ticker];
        if (!p) continue;
        if (task.type === 'Import') total += amount * p.ask;
        else total += amount * p.bid;
    }
    return total;
}

function renderTasks(tasks, prices) {
    const container = document.getElementById('task-container');

    if (tasks.length === 0) {
        container.innerHTML = '<p class="empty">No shipping tasks scheduled.</p>';
        return;
    }

    const sorted = sortTasks(tasks);
    const grouped = groupTasksByDate(sorted);

    let html = '';
    for (const [label, groupTasks] of grouped) {
        html += `<section class="day-group"><h2 class="day-label">${label}</h2><ul class="task-list">`;
        for (const task of groupTasks) {
            const typeLower = task.type.toLowerCase();
            const isImport = task.type === 'Import';
            const xitAttr = isImport ? ` data-xit-act="${buildXitAct(task).replace(/"/g, '&quot;')}"` : '';
            const totalPrice = calculateTotalPrice(task, prices);

            const materialsStr = Object.entries(task.materials)
                .map(([ticker, amount]) => `${numberFmt.format(amount)}&nbsp;${ticker}`)
                .join(', ');

            const direction = isImport ? 'to' : 'from';
            const priceHtml = totalPrice > 0
                ? `<span class="task__price">~${numberFmt.format(totalPrice)}</span>`
                : '';

            html += `<li class="task task--${typeLower}"${xitAttr}>`;
            html += `<span class="task__ship">[${task.shipClass}]</span> `;
            html += `<span class="task__type">${task.type}</span> `;
            html += `<span class="task__materials">${materialsStr}</span> `;
            html += `<span class="task__direction">${direction}</span> `;
            html += `<span class="task__planet">${task.planetName}</span>`;
            html += priceHtml;
            html += `</li>`;
        }
        html += `</ul></section>`;
    }

    container.innerHTML = html;
    attachXitActListeners();
}

function attachXitActListeners() {
    document.querySelectorAll('.task[data-xit-act]').forEach(task => {
        task.addEventListener('click', () => {
            const json = task.dataset.xitAct;
            const formatted = JSON.stringify(JSON.parse(json), null, 2);
            document.getElementById('xit-act-json').textContent = formatted;
            document.getElementById('xit-act-modal').classList.remove('modal--hidden');
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('xit-act-modal');
    const copyBtn = document.getElementById('xit-act-copy');
    const loadingEl = document.getElementById('loading');
    const loadingText = loadingEl.querySelector('.loading__text');

    // Modal controls
    const closeModal = () => modal.classList.add('modal--hidden');

    modal.querySelector('.modal__close').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('modal--hidden')) {
            closeModal();
        }
    });

    copyBtn.addEventListener('click', () => {
        navigator.clipboard.writeText(document.getElementById('xit-act-json').textContent).then(() => {
            copyBtn.textContent = 'Copied!';
            setTimeout(() => { copyBtn.textContent = 'Copy to Clipboard'; }, 2000);
        });
    });

    // SSE connection
    const allTasks = [];
    let prices = {};

    const source = new EventSource('/api/dashboard/stream');

    source.addEventListener('progress', (e) => {
        loadingText.textContent = JSON.parse(e.data);
    });

    source.addEventListener('tasks', (e) => {
        const tasks = JSON.parse(e.data);
        allTasks.push(...tasks);
    });

    source.addEventListener('prices', (e) => {
        prices = JSON.parse(e.data);
    });

    source.addEventListener('done', () => {
        source.close();
        loadingEl.remove();
        renderTasks(allTasks, prices);
    });

    source.addEventListener('error', (e) => {
        source.close();
        // SSE error event may have data (our custom error) or not (connection error)
        if (e.data) {
            loadingText.textContent = 'Fehler: ' + JSON.parse(e.data);
        } else {
            loadingText.textContent = 'Verbindungsfehler';
        }
        loadingEl.querySelector('.loading__spinner').remove();
    });
});
