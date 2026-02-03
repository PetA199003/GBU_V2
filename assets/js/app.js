/**
 * Gefährdungsbeurteilung - JavaScript
 */

// CSRF Token für AJAX Requests
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// API Basis-URL
const API_URL = '/gefaehrdungsbeurteilung/api';

/**
 * AJAX Request Wrapper
 */
async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    if (csrfToken) {
        defaultOptions.headers['X-CSRF-Token'] = csrfToken;
    }

    const response = await fetch(`${API_URL}/${endpoint}`, {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    return response.json();
}

/**
 * Risikobewertung berechnen
 */
function calculateRisk(schadenschwere, wahrscheinlichkeit) {
    return (schadenschwere * schadenschwere) * wahrscheinlichkeit;
}

/**
 * Risikofarbe ermitteln
 */
function getRiskColor(score) {
    if (score <= 2) return '#92D050'; // Grün
    if (score <= 4) return '#FFFF00'; // Gelb
    if (score <= 8) return '#FFC000'; // Orange
    return '#FF0000'; // Rot
}

/**
 * Risikoklasse ermitteln
 */
function getRiskClass(score) {
    return `risk-${score}`;
}

/**
 * Formular-Validierung
 */
function validateForm(form) {
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    return true;
}

/**
 * Toast-Benachrichtigung anzeigen
 */
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();

    const toastHTML = `
        <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type}" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toast = toastContainer.lastElementChild;
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

/**
 * Bestätigungsdialog
 */
function confirmAction(message) {
    return new Promise((resolve) => {
        const result = confirm(message);
        resolve(result);
    });
}

/**
 * Ladeindikator
 */
function showLoading(element) {
    element.dataset.originalContent = element.innerHTML;
    element.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Laden...';
    element.disabled = true;
}

function hideLoading(element) {
    if (element.dataset.originalContent) {
        element.innerHTML = element.dataset.originalContent;
        element.disabled = false;
    }
}

/**
 * Gefährdungsbeurteilung Editor
 */
class GBEditor {
    constructor(containerId, gbId) {
        this.container = document.getElementById(containerId);
        this.gbId = gbId;
        this.data = null;
        this.init();
    }

    async init() {
        try {
            await this.loadData();
            this.render();
            this.bindEvents();
        } catch (error) {
            console.error('Fehler beim Laden der Daten:', error);
            showToast('Fehler beim Laden der Daten', 'error');
        }
    }

    async loadData() {
        this.data = await apiRequest(`beurteilungen/${this.gbId}`);
    }

    render() {
        // Implementierung des Renderings
    }

    bindEvents() {
        // Event-Listener binden
    }

    async saveVorgang(vorgangData) {
        try {
            const response = await apiRequest('vorgaenge', {
                method: vorgangData.id ? 'PUT' : 'POST',
                body: JSON.stringify(vorgangData)
            });
            showToast('Gespeichert');
            return response;
        } catch (error) {
            showToast('Fehler beim Speichern', 'error');
            throw error;
        }
    }

    async deleteVorgang(vorgangId) {
        if (!await confirmAction('Möchten Sie diesen Eintrag wirklich löschen?')) {
            return;
        }

        try {
            await apiRequest(`vorgaenge/${vorgangId}`, { method: 'DELETE' });
            showToast('Gelöscht');
            await this.loadData();
            this.render();
        } catch (error) {
            showToast('Fehler beim Löschen', 'error');
        }
    }
}

/**
 * Bibliothek-Suche
 */
class LibrarySearch {
    constructor(inputId, resultsId, type) {
        this.input = document.getElementById(inputId);
        this.results = document.getElementById(resultsId);
        this.type = type;
        this.selectedCallback = null;

        if (this.input) {
            this.init();
        }
    }

    init() {
        let timeout;
        this.input.addEventListener('input', (e) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => this.search(e.target.value), 300);
        });

        // Außerhalb klicken schließt Ergebnisse
        document.addEventListener('click', (e) => {
            if (!this.input.contains(e.target) && !this.results.contains(e.target)) {
                this.hideResults();
            }
        });
    }

    async search(query) {
        if (query.length < 2) {
            this.hideResults();
            return;
        }

        try {
            const data = await apiRequest(`bibliothek/${this.type}?q=${encodeURIComponent(query)}`);
            this.showResults(data);
        } catch (error) {
            console.error('Suchfehler:', error);
        }
    }

    showResults(items) {
        if (items.length === 0) {
            this.results.innerHTML = '<div class="p-3 text-muted">Keine Ergebnisse gefunden</div>';
        } else {
            this.results.innerHTML = items.map(item => `
                <div class="autocomplete-suggestion" data-id="${item.id}">
                    <strong>${item.titel}</strong>
                    <small class="d-block text-muted">${item.beschreibung?.substring(0, 100) || ''}...</small>
                </div>
            `).join('');
        }

        this.results.style.display = 'block';

        // Click Handler für Ergebnisse
        this.results.querySelectorAll('.autocomplete-suggestion').forEach(el => {
            el.addEventListener('click', () => {
                const item = items.find(i => i.id == el.dataset.id);
                if (this.selectedCallback) {
                    this.selectedCallback(item);
                }
                this.hideResults();
            });
        });
    }

    hideResults() {
        this.results.style.display = 'none';
    }

    onSelect(callback) {
        this.selectedCallback = callback;
    }
}

/**
 * Risiko-Matrix
 */
function updateRiskMatrix() {
    const schadenschwere = parseInt(document.getElementById('schadenschwere')?.value || 1);
    const wahrscheinlichkeit = parseInt(document.getElementById('wahrscheinlichkeit')?.value || 1);
    const risk = calculateRisk(schadenschwere, wahrscheinlichkeit);

    const riskDisplay = document.getElementById('risk-display');
    if (riskDisplay) {
        riskDisplay.textContent = risk;
        riskDisplay.style.backgroundColor = getRiskColor(risk);
        riskDisplay.style.color = risk >= 9 ? 'white' : 'black';
    }
}

/**
 * STOP-Prinzip Checkbox Handler
 */
function initStopCheckboxes() {
    document.querySelectorAll('.stop-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const badge = this.closest('.stop-badge');
            if (badge) {
                badge.classList.toggle('inactive', !this.checked);
            }
        });
    });
}

/**
 * Sortierbare Listen
 */
function initSortable(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let draggedItem = null;

    container.querySelectorAll('.draggable').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            draggedItem = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            container.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        });

        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });

        item.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        item.addEventListener('drop', function(e) {
            e.preventDefault();
            if (draggedItem !== this) {
                const allItems = [...container.querySelectorAll('.draggable')];
                const draggedIndex = allItems.indexOf(draggedItem);
                const dropIndex = allItems.indexOf(this);

                if (draggedIndex < dropIndex) {
                    this.parentNode.insertBefore(draggedItem, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedItem, this);
                }

                // Sortierung speichern
                saveSortOrder(containerId);
            }
        });
    });
}

async function saveSortOrder(containerId) {
    const container = document.getElementById(containerId);
    const items = [...container.querySelectorAll('.draggable')].map((el, index) => ({
        id: el.dataset.id,
        sortierung: index
    }));

    try {
        await apiRequest('sortierung', {
            method: 'POST',
            body: JSON.stringify({
                type: container.dataset.type,
                items: items
            })
        });
    } catch (error) {
        console.error('Fehler beim Speichern der Sortierung:', error);
    }
}

/**
 * Export-Funktionen
 */
async function exportPDF(gbId) {
    showToast('PDF wird erstellt...');
    window.open(`${API_URL}/export/pdf/${gbId}`, '_blank');
}

async function exportExcel(gbId) {
    showToast('Excel-Datei wird erstellt...');
    window.open(`${API_URL}/export/excel/${gbId}`, '_blank');
}

/**
 * Initialisierung beim Laden der Seite
 */
document.addEventListener('DOMContentLoaded', function() {
    // Risiko-Matrix initialisieren
    const riskInputs = document.querySelectorAll('#schadenschwere, #wahrscheinlichkeit');
    riskInputs.forEach(input => {
        input.addEventListener('change', updateRiskMatrix);
    });
    updateRiskMatrix();

    // STOP-Checkboxen initialisieren
    initStopCheckboxes();

    // Sortierbare Listen initialisieren
    document.querySelectorAll('[data-sortable]').forEach(container => {
        initSortable(container.id);
    });

    // Bootstrap Tooltips aktivieren
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Formular-Validierung
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
});
