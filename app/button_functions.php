<?php
$language = getenv('LANGUAGE') ?: 'en';
$languageFile = __DIR__ . "/languages/$language.json";

if (file_exists($languageFile)) {
    $translations = json_decode(file_get_contents($languageFile), true);
} else {
    $translations = json_decode(file_get_contents(__DIR__ . "/languages/de.json"), true);
}

// Übergabe der Übersetzungen an JavaScript
echo "<script>const translations = " . json_encode($translations) . ";</script>";
?>

<!--Kostendiagramm-->
<script>
// Aktuellen Monat und Jahr berechnen
const currentMonth = new Date().getMonth() + 1; // JavaScript gibt Monate von 0-11 zurück, daher +1
const currentYear = new Date().getFullYear();


// Funktion zum Anzeigen des Kreisdiagramms
async function showCategoryChart(category = null) {
    try {
        const response = await fetch(`category_distribution.php?month=${currentMonth}&year=${currentYear}` + 
            (category ? `&category=${encodeURIComponent(category)}` : ''));
        if (!response.ok) {
            console.error("Fehler beim Laden der Daten:", response.status);
            alert("Fehler beim Laden der Kategorie-Daten.");
            return;
        }

        const data = await response.json();
        console.log("Geladene Daten:", data); // Debugging: Geladene Daten prüfen

        const labels = data.map(item => item.category || 'Ohne Kategorie');
        const values = data.map(item => item.total);

        console.log("Labels:", labels); // Debugging: Labels prüfen
        console.log("Werte:", values); // Debugging: Werte prüfen

        const chartCanvas = document.getElementById('categoryChart');
        if (!chartCanvas) {
            console.error("Canvas-Element für das Diagramm nicht gefunden.");
            return;
        }

        const ctx = chartCanvas.getContext('2d');
        if (window.categoryChartInstance) {
            window.categoryChartInstance.destroy(); // Existierendes Diagramm zerstören
        }

        window.categoryChartInstance = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        onClick: (e, legendItem, legend) => {
                            const category = legend.data.labels[legendItem.index];
                            console.log(`Kategorie geklickt: ${category}`); // Debugging
                            highlightCategory(category); // Kategorie hervorheben
                        }
                    }
                }
            }
        });

        document.getElementById('chart-overlay').style.display = 'flex';
    } catch (error) {
        console.error("Fehler beim Erstellen des Diagramms:", error);
        alert("Fehler beim Laden der Daten.");
    }
}

// Kategorie hervorheben
function highlightCategory(elementOrCategory) {
    let category;

    // Prüfen, ob ein Element oder ein Kategoriename übergeben wurde
    if (typeof elementOrCategory === 'string') {
        category = elementOrCategory; // Direkter Kategoriename
    } else if (elementOrCategory instanceof HTMLElement) {
        category = elementOrCategory.getAttribute('data-category'); // Attribut aus HTML-Element
    } else {
        console.error("Ungültiger Parameter für highlightCategory:", elementOrCategory);
        return;
    }

    console.log(`Hebe Einträge für Kategorie "${category}" hervor.`); // Debugging

    // Selektiere alle Kategorien in den Einträgen
    const entries = document.querySelectorAll('.entry-category-id');
    entries.forEach(entry => {
        if (entry.getAttribute('data-category') === category) {
            entry.closest('.entry-box').classList.add('highlight');
            setTimeout(() => entry.closest('.entry-box').classList.remove('highlight'), 1500); // Highlight entfernen
        }
    });

    // Overlay öffnen, falls nicht durch Diagramm
    if (typeof elementOrCategory !== 'string') {
        showCategoryChart(category);
    }
}
</script>

<!--Einzelne Umbuchung innerhalb Serie bearbeiten-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1) Daten laden
    function getQueryParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }
    const currentViewMonth = parseInt(getQueryParameter("month")) || (new Date().getMonth() + 1);
    const currentViewYear  = parseInt(getQueryParameter("year"))  || (new Date().getFullYear());

    const editSingleRebookingButtons = document.querySelectorAll('.edit-single-rebooking-button');
    editSingleRebookingButtons.forEach(button => {
        button.addEventListener('click', async function () {
            const entryId = this.dataset.id;
            try {
                const response = await fetch(`get_rebooking_details.php?id=${entryId}`);
                const entryData = await response.json();

                if (entryData.error) {
                    alert(entryData.error);
                    return;
                }

                // Overlay öffnen
                const overlay = document.getElementById("edit-single-rebooking-overlay");
                overlay.style.display = "flex";

                // Felder mit Daten füllen
                document.getElementById("edit-single-rebooking-id").value   = entryData.id;
                document.getElementById("edit-single-source-account").value = entryData.source_account || "main";
                document.getElementById("edit-single-target-account").value = entryData.target_account || "main";
                document.getElementById("edit-single-amount").value         = entryData.amount || "";
                document.getElementById("edit-single-description").value    = entryData.description || "";

                // Umbuchungsmonat und -jahr anpassen:
                document.getElementById("edit-single-entry-month").value    = currentViewMonth;
                document.getElementById("edit-single-entry-year").value     = currentViewYear;

                // Wiederholungsfeld => "no"
                const rebookingRecurringField = document.getElementById("edit-single-rebooking-recurring");
                if (rebookingRecurringField) {
                    rebookingRecurringField.value = "no";
                    rebookingRecurringField.disabled = true;
                }
            } catch (error) {
                console.error("Fehler beim Laden der Umbuchungsdaten:", error);
                alert("Fehler beim Laden der Umbuchungsdaten.");
            }
        });
    });

    // 2) Neue Animation (Ausgefüllter grüner Kreis + weißer Haken)
    function showCheckAnimationAndReload() {
        // Einmalig Styles anfügen, falls nicht vorhanden
        if (!document.getElementById('check-animation-style')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'check-animation-style';
            styleEl.innerHTML = `
                @keyframes fillCircle {
                    0% { fill: transparent; }
                    100% { fill: #4CAF50; }
                }
                @keyframes fadeInCheck {
                    0% { opacity: 0; }
                    100% { opacity: 1; }
                }
                .check-animation-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.2);
                    backdrop-filter: blur(5px);
                    -webkit-backdrop-filter: blur(5px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999;
                }
                .checkmark-svg {
                    width: 100px;
                    height: 100px;
                    overflow: visible;
                }
                .checkmark__circle {
                    stroke: #4CAF50;
                    stroke-width: 4;
                    fill: transparent;
                    animation: fillCircle 0.6s ease forwards;
                }
                .checkmark__check {
                    stroke: white;
                    stroke-width: 4;
                    fill: none;
                    opacity: 0;
                    animation: fadeInCheck 0.3s ease forwards;
                    animation-delay: 0.6s;
                }
            `;
            document.head.appendChild(styleEl);
        }

        const container = document.createElement('div');
        container.className = 'check-animation-container';

        const svgNS = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(svgNS, "svg");
        svg.classList.add("checkmark-svg");
        svg.setAttribute("viewBox", "0 0 52 52");

        const circle = document.createElementNS(svgNS, "circle");
        circle.classList.add("checkmark__circle");
        circle.setAttribute("cx", "26");
        circle.setAttribute("cy", "26");
        circle.setAttribute("r", "25");

        const check = document.createElementNS(svgNS, "path");
        check.classList.add("checkmark__check");
        check.setAttribute("d", "M14 27l7 7 16-16");

        svg.appendChild(circle);
        svg.appendChild(check);
        container.appendChild(svg);
        document.body.appendChild(container);

        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    // 3) Formular: POST an update_single_rebooking.php
    const rebookingForm = document.getElementById('edit-single-rebooking-form');
    if (rebookingForm) {
        rebookingForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const formData = new FormData(rebookingForm);
            try {
                const response = await fetch(rebookingForm.action, {
                    method: 'POST',
                    body: formData,
                });
                // => update_single_rebooking.php gibt JSON zurück: {"success":true,...} o. {"success":false,...}
                const result = await response.json();
                if (result.success) {
                    // Erfolg: Animation + Reload
                    showCheckAnimationAndReload();
                } else {
                    // Misserfolg
                    alert('Fehler: ' + (result.message || 'Änderungen konnten nicht gespeichert werden.'));
                }
            } catch (error) {
                console.error('Fehler beim Speichern der Umbuchungsänderungen:', error);
                alert('Fehler beim Speichern der Umbuchungsänderungen.');
            }
        });
    }
});
</script>




<!--Einzeln bearbeitetes Serienelement ausblenden-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hideOverrideButtons = document.querySelectorAll('.hide-override-button');

    hideOverrideButtons.forEach(button => {
        button.addEventListener('click', async function () {
            const entryId = this.dataset.id;

            try {
                const response = await fetch('hide_override.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: entryId }),
                });

                const result = await response.json();
                if (result.success) {
                    window.location.reload(); // Seite neu laden
                } else {
                    console.error("Fehler: ", result.message || "Unbekannter Fehler");
                }
            } catch (error) {
                console.error("Fehler beim Ausblenden des Eintrags:", error);
            }
        });
    });
});

</script>

<!--Ausblenden Button für einzelne Serienelemente-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hideSingleButtons = document.querySelectorAll('.hide-single-button');

    hideSingleButtons.forEach(button => {
        button.addEventListener('click', async function () {
            const entryId = this.dataset.id;
            const month = this.dataset.month;
            const year = this.dataset.year;

            try {
                const response = await fetch('hide_single_entry.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: entryId, month: month, year: year }),
                });

                const result = await response.json();
                if (result.success) {
                    window.location.reload(); // Seite neu laden
                } else {
                    console.error("Fehler: ", result.message || "Das Serienelement konnte nicht ausgeblendet werden.");
                }
            } catch (error) {
                console.error("Fehler beim Ausblenden des Serienelements:", error);
            }
        });
    });
});
</script>

<!--Menubar bei Klick außerhalb schließen-->
<script>
    function toggleMenu() {
        const menuLinks = document.getElementById('menu-links');
        menuLinks.style.display = menuLinks.style.display === 'block' ? 'none' : 'block';
    }

    // Schließt die Menüleiste bei Klick außerhalb oder auf den Menüpunkt "Vorschläge oder Fehler melden"
    document.addEventListener('click', function (event) {
        const menuLinks = document.getElementById('menu-links');
        const burgerMenu = document.querySelector('.burger-menu');
        const suggestionLink = document.getElementById('suggestions-link');

        if (
            menuLinks.style.display === 'block' && // Menü ist sichtbar
            !menuLinks.contains(event.target) && // Klick ist außerhalb des Menüs
            !burgerMenu.contains(event.target) && // Klick ist nicht auf den Burger-Button
            !suggestionLink.contains(event.target) // Klick ist nicht auf den Vorschläge-Link
        ) {
            menuLinks.style.display = 'none'; // Menü schließen
        }

        if (event.target === suggestionLink) {
            menuLinks.style.display = 'none'; // Menü schließen, wenn Vorschläge-Link geklickt wird
        }
    });
</script>
<script>
    // Schließt die Menüleiste bei Klick außerhalb
    document.addEventListener('click', function (event) {
        const menuLinks = document.getElementById('menu-links');
        const burgerMenu = document.querySelector('.burger-menu');

        if (
            menuLinks.style.display === 'block' && // Menü ist sichtbar
            !menuLinks.contains(event.target) && // Klick ist außerhalb des Menüs
            !burgerMenu.contains(event.target) // Klick ist nicht auf den Burger-Button
        ) {
            menuLinks.style.display = 'none'; // Menü schließen
        }
    });
</script>


<!--Komfortfeatures PC-->
<!--Strg+H für Home und Strg+S zum Speichern-->
<script>
document.addEventListener('keydown', (e) => {
    if (e.key === 'h' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault(); // Verhindert das Standardverhalten des Browsers
        window.location.href = 'index.php'; // Weiterleitung zur index.php
    }
});

function showCheckAnimationAndReload() {
    // Füge einmalig einen Style-Block für das neue Animations-Overlay hinzu, falls noch nicht vorhanden
    if (!document.getElementById('check-animation-style')) {
        const styleEl = document.createElement('style');
        styleEl.id = 'check-animation-style';
        styleEl.innerHTML = `
            @keyframes fillCircle {
                0% { fill: transparent; }
                100% { fill: #4CAF50; }
            }
            @keyframes fadeInCheck {
                0% { opacity: 0; }
                100% { opacity: 1; }
            }
            .check-animation-container {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.2);
                backdrop-filter: blur(5px);
                -webkit-backdrop-filter: blur(5px);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 99999;
            }
            .checkmark-svg {
                width: 100px;
                height: 100px;
                overflow: visible;
            }
            .checkmark__circle {
                stroke: #4CAF50;
                stroke-width: 4;
                fill: transparent;
                animation: fillCircle 0.6s ease forwards;
            }
            .checkmark__check {
                stroke: white;
                stroke-width: 4;
                fill: none;
                opacity: 0;
                animation: fadeInCheck 0.3s ease forwards;
                animation-delay: 0.6s;
            }
        `;
        document.head.appendChild(styleEl);
    }
  
    // Erstelle den Container für das Overlay
    const container = document.createElement('div');
    container.className = 'check-animation-container';
  
    // Erstelle das SVG-Element
    const svgNS = "http://www.w3.org/2000/svg";
    const svg = document.createElementNS(svgNS, "svg");
    svg.classList.add("checkmark-svg");
    svg.setAttribute("viewBox", "0 0 52 52");
  
    // Erstelle den Kreis, der sich füllt
    const circle = document.createElementNS(svgNS, "circle");
    circle.classList.add("checkmark__circle");
    circle.setAttribute("cx", "26");
    circle.setAttribute("cy", "26");
    circle.setAttribute("r", "25");
  
    // Erstelle den Haken, der eingeblendet wird
    const check = document.createElementNS(svgNS, "path");
    check.classList.add("checkmark__check");
    check.setAttribute("d", "M14 27l7 7 16-16");
  
    svg.appendChild(circle);
    svg.appendChild(check);
    container.appendChild(svg);
    document.body.appendChild(container);
  
    // Nach Abschluss der Animation (ca. 1000ms) Seite neu laden
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}
  
// Tastenkombination: Strg+S (oder Cmd+S) zum Speichern – wenn ein Overlay mit Formular sichtbar ist, werden die Formulardaten per AJAX gesendet
document.addEventListener('keydown', async (e) => {
    if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault(); // Standardbrowserverhalten verhindern
  
        // Definierte Overlays mit zugehörigen Formularen
        const overlays = [
            { id: 'overlay', formId: 'overlay-form' },         // Beispiel: Bewegung hinzufügen
            { id: 'rebooking-overlay', formId: 'rebooking-form' }  // Beispiel: Umbuchung hinzufügen
        ];
  
        for (const overlay of overlays) {
            const overlayElement = document.getElementById(overlay.id);
            if (overlayElement && overlayElement.style.display === 'flex') {
                const form = document.getElementById(overlay.formId);
                if (form) {
                    try {
                        // Formulardaten sammeln und an den Server senden
                        const formData = new FormData(form);
                        const actionUrl = form.action;
                        const response = await fetch(actionUrl, {
                            method: 'POST',
                            body: formData,
                        });
                        if (response.ok) {
                            try {
                                const result = await response.json();
                                if (result.success) {
                                    // Erfolgreiches Speichern -> Neues Animations-Overlay anzeigen und danach neu laden
                                    showCheckAnimationAndReload();
                                } else {
                                    alert('Fehler: ' + (result.message || 'Daten konnten nicht gespeichert werden.'));
                                }
                            } catch (jsonError) {
                                // Falls keine JSON-Antwort vorliegt, einfach neu laden
                                window.location.reload();
                            }
                        } else {
                            alert('Fehler: Server konnte die Anfrage nicht verarbeiten.');
                        }
                    } catch (error) {
                        console.error('Fehler beim Speichern der Daten:', error);
                        alert('Fehler beim Speichern der Daten.');
                    }
                }
                return; // Sobald ein Overlay behandelt wurde, beenden
            }
        }
    }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {
    // Neue Funktion: Animation eines gefüllten grünen Kreises mit weißem Haken und Hintergrund-Ausblurrung
    function showCheckAnimationAndReload() {
        // Füge einmalig einen Style-Block für das neue Animations-Overlay hinzu, falls noch nicht vorhanden
        if (!document.getElementById('check-animation-style')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'check-animation-style';
            styleEl.innerHTML = `
                @keyframes fillCircle {
                    0% { fill: transparent; }
                    100% { fill: #4CAF50; }
                }
                @keyframes fadeInCheck {
                    0% { opacity: 0; }
                    100% { opacity: 1; }
                }
                .check-animation-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.2);
                    backdrop-filter: blur(5px);
                    -webkit-backdrop-filter: blur(5px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999;
                }
                .checkmark-svg {
                    width: 100px;
                    height: 100px;
                    overflow: visible;
                }
                .checkmark__circle {
                    stroke: #4CAF50;
                    stroke-width: 4;
                    fill: transparent;
                    animation: fillCircle 0.6s ease forwards;
                }
                .checkmark__check {
                    stroke: white;
                    stroke-width: 4;
                    fill: none;
                    opacity: 0;
                    animation: fadeInCheck 0.3s ease forwards;
                    animation-delay: 0.6s;
                }
            `;
            document.head.appendChild(styleEl);
        }
  
        // Erstelle den Container für das Overlay
        const container = document.createElement('div');
        container.className = 'check-animation-container';
  
        // Erstelle das SVG-Element
        const svgNS = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(svgNS, "svg");
        svg.classList.add("checkmark-svg");
        svg.setAttribute("viewBox", "0 0 52 52");
  
        // Erstelle den Kreis, der sich füllt
        const circle = document.createElementNS(svgNS, "circle");
        circle.classList.add("checkmark__circle");
        circle.setAttribute("cx", "26");
        circle.setAttribute("cy", "26");
        circle.setAttribute("r", "25");
  
        // Erstelle den Haken, der eingeblendet wird
        const check = document.createElementNS(svgNS, "path");
        check.classList.add("checkmark__check");
        check.setAttribute("d", "M14 27l7 7 16-16");
  
        svg.appendChild(circle);
        svg.appendChild(check);
        container.appendChild(svg);
        document.body.appendChild(container);
  
        // Nach Abschluss der Animation (ca. 1000ms) Seite neu laden
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
  
    // Globale Variable initialisieren
    window.bulkMode = false;
    const selectedEntries = new Set();
    const bulkActionContainer = document.querySelector('.bulk-action-container');
    const bulkDeleteButton = document.getElementById('bulk-delete');
    const bulkHideButton = document.getElementById('bulk-hide');
    const bulkCancelButton = document.getElementById('bulk-cancel');
    const entries = document.querySelectorAll('.entry-box');
  
    // Funktion: Eintrag auswählen bzw. deselektieren
    const selectEntry = (entry) => {
        const id = entry.dataset.id;
        if (selectedEntries.has(id)) {
            selectedEntries.delete(id);
            entry.classList.remove('selected');
        } else {
            selectedEntries.add(id);
            entry.classList.add('selected');
        }
        console.log(`Amount: ${entry.dataset.amount}, Type: ${entry.dataset.type}`);
        updateTotalSum();
    };
  
    const totalSumElement = document.getElementById('total-sum');
    const updateTotalSum = () => {
        let totalSum = 0;
        selectedEntries.forEach(id => {
            const entry = document.querySelector(`.entry-box[data-id="${id}"]`);
            if (entry) {
                const amount = parseFloat(entry.dataset.amount);
                const type = entry.dataset.type;
                const rebooking = entry.dataset.rebooking;
                if (!isNaN(amount)) {
                    if (rebooking) {
                        totalSum += (rebooking === 'from' ? amount : -amount);
                    } else {
                        totalSum += (type === 'income' ? amount : -amount);
                    }
                }
            }
        });
        totalSumElement.textContent = totalSum.toFixed(2) + ' €';
  
        const totalSumContainer = document.getElementById('total-sum-container');
        totalSumContainer.style.display = selectedEntries.size > 0 ? 'block' : 'none';
    };
  
    // Funktion: Bulk-Mode starten
    const startBulkMode = () => {
        window.bulkMode = true;
        bulkActionContainer.classList.remove('hidden');
    };
  
    // Funktion: Bulk-Mode abbrechen
    const cancelBulkMode = () => {
        window.bulkMode = false;
        bulkActionContainer.classList.add('hidden');
        selectedEntries.clear();
        entries.forEach(entry => entry.classList.remove('selected'));
  
        const totalSumContainer = document.getElementById('total-sum-container');
        totalSumContainer.style.display = 'none';
    };
  
    // Funktion: Alle ausgewählten Einträge ausblenden
    const bulkHide = () => {
        if (selectedEntries.size === 0) {
            alert(translations.please_select_entry);
            return;
        }
  
        const ids = Array.from(selectedEntries);
        fetch('bulk_hide_entries.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids }),
        })
        .then(response => response.json())
        .then((result) => {
            if (!result.success) {
                alert(result.message || translations.error_occurred);
                return;
            }
            // Erfolgreicher Bulk-Hide: Zeige das neue Animations-Overlay und lade danach die Seite neu
            showCheckAnimationAndReload();
        })
        .catch(error => alert(translations.error_occurred_prefix + error.message));
    };
  
    // Funktion: Alle ausgewählten Einträge löschen
    const bulkDelete = () => {
        if (selectedEntries.size === 0) {
            alert(translations.please_select_entry);
            return;
        }
        if (!confirm(translations.confirm_delete_entries)) return;
  
        const ids = Array.from(selectedEntries);
        fetch('bulk_delete_entries.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids }),
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                alert(result.message || translations.error_occurred);
                return;
            }
            // Erfolgreicher Bulk-Delete: Zeige das neue Animations-Overlay und lade danach die Seite neu
            showCheckAnimationAndReload();
        })
        .catch(error => alert(translations.error_occurred_prefix + error.message));
    };
  
    // Event-Listener für die einzelnen Einträge
    entries.forEach(entry => {
        let pressTimer;
  
        // Maus-Events
        entry.addEventListener('mousedown', () => {
            pressTimer = setTimeout(() => {
                startBulkMode();
                selectEntry(entry);
            }, 500); // 500ms langes Drücken
        });
        entry.addEventListener('mouseup', () => clearTimeout(pressTimer));
        entry.addEventListener('mouseleave', () => clearTimeout(pressTimer));
  
        // Touch-Events für mobile Geräte
        entry.addEventListener('touchstart', () => {
            pressTimer = setTimeout(() => {
                startBulkMode();
                selectEntry(entry);
            }, 500);
        });
        entry.addEventListener('touchend', () => clearTimeout(pressTimer));
        entry.addEventListener('touchcancel', () => clearTimeout(pressTimer));
  
        // Klick zum Auswählen im Bulk-Mode
        entry.addEventListener('click', () => {
            if (window.bulkMode === true) {
                selectEntry(entry);
            }
        });
    });
  
    // Event-Listener für Bulk-Buttons
    bulkDeleteButton.addEventListener('click', bulkDelete);
    bulkHideButton.addEventListener('click', bulkHide);
    bulkCancelButton.addEventListener('click', cancelBulkMode);
  
    // Tastenkombinationen
    document.addEventListener('keydown', (e) => {
        // Strg+A: Im Bulk-Mode alle ausgewählten Einträge ausblenden
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            if (window.bulkMode === true) {
                bulkHide();
            }
        }
        // Delete: Im Bulk-Mode alle ausgewählten Einträge löschen
        if (e.key === 'Delete') {
            if (window.bulkMode === true) {
                bulkDelete();
            }
        }
        // Escape: Bulk-Mode abbrechen
        if (e.key === 'Escape') {
            cancelBulkMode();
        }
        // Strg + Leertaste: Bulk-Mode starten
        if (e.ctrlKey && e.code === 'Space') {
            e.preventDefault();
            if (window.bulkMode !== true) {
                startBulkMode();
            }
        }
    });
});
</script>




<script>
// Funktion zum Navigieren zwischen Monaten
function navigateMonth(offset) {
    const urlParams = new URLSearchParams(window.location.search);
    let month = parseInt(urlParams.get('month') || new Date().getMonth() + 1, 10);
    let year = parseInt(urlParams.get('year') || new Date().getFullYear(), 10);

    month += offset;

    if (month > 12) {
        month = 1;
        year++;
    } else if (month < 1) {
        month = 12;
        year--;
    }

    // URL aktualisieren und Seite neu laden
    window.location.href = `?month=${month}&year=${year}`;
}


document.addEventListener('DOMContentLoaded', () => {
    // Schließen mit Escape
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            // Suche nach offenen Overlays und schließe das erste gefundene
            const openOverlays = document.querySelectorAll('.overlay[style*="display: flex"]');
            if (openOverlays.length > 0) {
                openOverlays[0].style.display = 'none'; // Schließt das erste gefundene Overlay
            }
        }
    });

    // Schließen durch Klicken außerhalb des Overlays
    document.querySelectorAll('.overlay').forEach(overlay => {
        overlay.addEventListener('click', function (event) {
            if (event.target === this) {
                this.style.display = 'none'; // Schließt das Overlay
            }
        });
    });

    // Schließen mit dem "X"-Button
    document.querySelectorAll('.close-button').forEach(button => {
        button.addEventListener('click', function () {
            const overlay = this.closest('.overlay'); // Finde das zugehörige Overlay
            if (overlay) {
                overlay.style.display = 'none'; // Schließt das Overlay
            }
        });
    });
});



</script>

<!--Neuer Plus Button-->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mainFab = document.getElementById('main-fab');
    const fabOptions = document.getElementById('fab-options');
    const background = document.getElementById('background');

    mainFab.addEventListener('click', () => {
        fabOptions.classList.toggle('hidden');
        fabOptions.classList.toggle('show');

        if (fabOptions.classList.contains('show')) {
            background.classList.add('blurred');
        } else {
            background.classList.remove('blurred');
        }
    });

    // Overlay für neue Bewegung
    document.getElementById('add-entry').addEventListener('click', () => {
        document.getElementById('overlay-form').style.display = 'flex';
        background.classList.remove('blurred');
        fabOptions.classList.add('hidden');
        fabOptions.classList.remove('show');
    });

// Overlay für Umbuchungen
document.getElementById('add-rebooking').addEventListener('click', () => {
    document.getElementById('rebooking-overlay').style.display = 'flex';
    background.classList.remove('blurred');
    fabOptions.classList.add('hidden');
    fabOptions.classList.remove('show');
});    

    // Overlay für neues Sparkonto
    document.getElementById('add-savings').addEventListener('click', () => {
        document.getElementById('savings-form').style.display = 'flex';
        background.classList.remove('blurred');
        fabOptions.classList.add('hidden');
        fabOptions.classList.remove('show');
    });
});
</script>





<script>
document.addEventListener('DOMContentLoaded', () => {

    // ---------------------------------------
    // Hilfsfunktionen und Grundkonfiguration:
    // ---------------------------------------

    function getQueryParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }
    function formatLocalDate(date) {
        const year  = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day   = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    const currentViewMonth = parseInt(getQueryParameter("month")) || (new Date().getMonth() + 1);
    const currentViewYear  = parseInt(getQueryParameter("year"))  || (new Date().getFullYear());

    // Felder / Elemente fürs Overlay
    const overlay       = document.getElementById('overlay');
    const overlayForm   = document.getElementById('overlay-form');
    const overlayTitle  = document.getElementById('overlay-title');
    const addButton     = document.getElementById('add-entry');
    const bookingDateField = document.getElementById('booking_date');
    const idField               = document.getElementById('edit-id');
    const entryTypeField        = document.getElementById('entry-type');
    const amountField           = document.getElementById('amount');
    const descriptionField      = document.getElementById('description');
    const recurringField        = document.getElementById('recurring');
    const repeatUntilMonthField = document.getElementById('repeat_until_month');
    const repeatUntilYearField  = document.getElementById('repeat_until_year');
    const repeatUntilFields     = document.getElementById('repeat-until-fields');
    const categoryField         = document.getElementById('category');

    // Dieses Objekt enthält Strings für Übersetzungen usw.
    const translations = <?php echo json_encode($translations); ?>;

    // ---------------------------------------
    // Overlay-Felder zurücksetzen
    // ---------------------------------------
    function resetOverlayFields() {
        idField.value               = '';
        entryTypeField.value        = 'expense';
        amountField.value           = '';
        descriptionField.value      = '';
        recurringField.value        = 'no';
        repeatUntilMonthField.value = '';
        repeatUntilYearField.value  = '';
        categoryField.value         = '';
        bookingDateField.value      = '';

        toggleRepeatUntilFields('no');
        setEntryType('expense');  // Annahme: diese Funktion existiert bereits
        recurringField.disabled = false;
    }

    function toggleRepeatUntilFields(recurringValue) {
        if (recurringValue === 'no') {
            repeatUntilFields.style.display = 'none';
        } else {
            repeatUntilFields.style.display = 'block';
        }
    }
    recurringField.addEventListener('change', function() {
        toggleRepeatUntilFields(this.value);
    });

    // ---------------------------------------
    // Neues Overlay öffnen
    // ---------------------------------------
    function openNewEntryOverlay() {
        resetOverlayFields();
        const today = new Date();
        if (today.getMonth() + 1 === currentViewMonth && today.getFullYear() === currentViewYear) {
            bookingDateField.value = formatLocalDate(today);
        } else {
            const firstDay = new Date(currentViewYear, currentViewMonth - 1, 1);
            bookingDateField.value = formatLocalDate(firstDay);
        }
        overlayTitle.textContent = translations.add_entry;
        overlayForm.action = 'add_entry.php';
        overlayForm.dataset.override = '0'; // default
        overlay.style.display = 'flex';
    }

    // ---------------------------------------
    // Klick auf Plus-Button => Overlay öffnen
    // ---------------------------------------
    addButton.addEventListener('click', () => {
        openNewEntryOverlay();
    });

    // ---------------------------------------
    // fetchEntryData: Eintragsdaten per AJAX laden
    // ---------------------------------------
    async function fetchEntryData(id, editMode) {
        const response = await fetch(`get_entry.php?id=${id}&edit_mode=${editMode}`);
        if (!response.ok) {
            console.error('Fehler beim Abrufen der Eintragsdaten:', response.status);
            throw new Error('Fehler beim Abrufen der Eintragsdaten');
        }
        return await response.json();
    }

    // ---------------------------------------
    // Edit Buttons für komplette Serie
    // ---------------------------------------
    function initEditButtons() {
        const editButtons = document.querySelectorAll('.edit-button');
        editButtons.forEach(button => {
            button.addEventListener('click', async function() {
                const entryId = this.dataset.id;
                resetOverlayFields();
                try {
                    const entryData = await fetchEntryData(entryId, 'series');
                    if (entryData && typeof entryData.id !== 'undefined') {
                        idField.value               = entryData.id;
                        amountField.value           = entryData.amount;
                        descriptionField.value      = entryData.description;
                        recurringField.value        = entryData.recurring;
                        repeatUntilMonthField.value = entryData.repeat_until_month || '';
                        repeatUntilYearField.value  = entryData.repeat_until_year  || '';
                        categoryField.value         = entryData.category || '';
                        bookingDateField.value      = entryData.booking_date || '';
                        toggleRepeatUntilFields(entryData.recurring);
                        if (entryData.type) {
                            entryTypeField.value = entryData.type;
                            setEntryType(entryData.type);
                        }
                        overlayTitle.textContent  = translations.edit_movement_title;
                        overlayForm.action        = 'edit_entry.php';
                        overlayForm.dataset.override = '0';
                        overlay.style.display = 'flex';
                    } else {
                        alert(translations.error_loading_entry);
                    }
                } catch (error) {
                    console.error(translations.error_loading_entry_console, error);
                    alert(translations.error_loading_entry);
                }
            });
        });
    }

    // ---------------------------------------
    // Edit Buttons für einzelnen Serieneintrag (Override)
    // ---------------------------------------
    function initEditSingleButtons() {
        const editSingleButtons = document.querySelectorAll('.edit-single-button');
        editSingleButtons.forEach(button => {
            button.addEventListener('click', async function() {
                const entryId = this.dataset.id;
                resetOverlayFields();
                try {
                    const entryData = await fetchEntryData(entryId, 'single');
                    if (entryData && typeof entryData.id !== 'undefined') {
                        idField.value          = entryData.id;
                        amountField.value      = entryData.amount;
                        descriptionField.value = entryData.description;
                        categoryField.value    = entryData.category || '';
                        if (entryData.booking_date) {
                            let seriesDate = new Date(entryData.booking_date);
                            let day   = seriesDate.getDate();
                            let dayStr   = String(day).padStart(2, '0');
                            let monthStr = String(currentViewMonth).padStart(2, '0');
                            bookingDateField.value = `${currentViewYear}-${monthStr}-${dayStr}`;
                        } else {
                            bookingDateField.value = formatLocalDate(new Date());
                        }
                        if (entryData.type) {
                            entryTypeField.value = entryData.type;
                            setEntryType(entryData.type);
                        }
                        recurringField.value    = 'no';
                        recurringField.disabled = true;
                        repeatUntilMonthField.value = entryData.entry_month || '';
                        repeatUntilYearField.value  = entryData.entry_year  || '';
                        overlayTitle.textContent  = translations.edit_single_entry_title;
                        overlayForm.action        = 'edit_entry.php';
                        overlayForm.dataset.override = '1'; // override
                        overlay.style.display = 'flex';
                    } else {
                        alert('Fehler: Eintragsdaten konnten nicht geladen werden.');
                    }
                } catch (error) {
                    console.error('Fehler beim Laden der Eintragsdaten:', error);
                    alert('Fehler beim Laden der Eintragsdaten.');
                }
            });
        });
    }

    // ---------------------------------------
    // Neue Funktion: Ausgefüllter grüner Kreis + weißer Haken
    // ---------------------------------------
    function showCheckAnimationAndReload() {
        // Einmalig Styles anfügen, falls nicht vorhanden
        if (!document.getElementById('check-animation-style')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'check-animation-style';
            styleEl.innerHTML = `
                @keyframes fillCircle {
                    0% { fill: transparent; }
                    100% { fill: #4CAF50; }
                }
                @keyframes fadeInCheck {
                    0% { opacity: 0; }
                    100% { opacity: 1; }
                }
                .check-animation-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.2);
                    backdrop-filter: blur(5px);
                    -webkit-backdrop-filter: blur(5px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999;
                }
                .checkmark-svg {
                    width: 100px;
                    height: 100px;
                    overflow: visible;
                }
                .checkmark__circle {
                    stroke: #4CAF50;
                    stroke-width: 4;
                    fill: transparent;
                    animation: fillCircle 0.6s ease forwards;
                }
                .checkmark__check {
                    stroke: white;
                    stroke-width: 4;
                    fill: none;
                    opacity: 0;
                    animation: fadeInCheck 0.3s ease forwards;
                    animation-delay: 0.6s;
                }
            `;
            document.head.appendChild(styleEl);
        }

        // Container + SVG erstellen
        const container = document.createElement('div');
        container.className = 'check-animation-container';

        const svgNS = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(svgNS, "svg");
        svg.classList.add("checkmark-svg");
        svg.setAttribute("viewBox", "0 0 52 52");

        const circle = document.createElementNS(svgNS, "circle");
        circle.classList.add("checkmark__circle");
        circle.setAttribute("cx", "26");
        circle.setAttribute("cy", "26");
        circle.setAttribute("r", "25");

        const check = document.createElementNS(svgNS, "path");
        check.classList.add("checkmark__check");
        check.setAttribute("d", "M14 27l7 7 16-16");

        svg.appendChild(circle);
        svg.appendChild(check);
        container.appendChild(svg);
        document.body.appendChild(container);

        // Nach kurzer Zeit Seite neu laden
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    // ---------------------------------------
    // Formular per AJAX absenden
    // ---------------------------------------
    overlayForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        const formData = new FormData(overlayForm);
        formData.append('override', overlayForm.dataset.override || '0');

        try {
            const response = await fetch(overlayForm.action, {
                method: 'POST',
                body: formData,
            });
            // Hier wird JSON erwartet (s. add_entry.php und edit_entry.php)
            const result = await response.json();
            if (result.success) {
                // Bei Erfolg: Neue Animation anzeigen -> Reload
                showCheckAnimationAndReload();
            } else {
                alert('Fehler: ' + (result.message || 'Daten konnten nicht gespeichert werden.'));
            }
        } catch (error) {
            console.error('Fehler beim Speichern der Änderungen:', error);
            alert('Fehler beim Speichern der Änderungen.');
        }
    });

    // ---------------------------------------
    // Overlay schließen bei Klick außerhalb
    // ---------------------------------------
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.style.display = 'none';
        }
    });

    // ---------------------------------------
    // Tastenkombinationen
    // ---------------------------------------
    document.addEventListener('keydown', (event) => {
        const isCtrlOrCmd = event.ctrlKey || event.metaKey;
        if (event.key === 'Escape') {
            const openOverlays = document.querySelectorAll('.overlay[style*="display: flex"]');
            if (openOverlays.length > 0) {
                openOverlays[0].style.display = 'none';
            }
        } else if (isCtrlOrCmd && event.key === 'f') {
            event.preventDefault();
            document.getElementById('search-overlay').style.display = 'flex';
            document.getElementById('search-input').focus();
        } else if (isCtrlOrCmd && event.key === 'b') {
            event.preventDefault();
            openNewEntryOverlay();
        } else if (isCtrlOrCmd && event.key === 'u') {
            event.preventDefault();
            document.getElementById('rebooking-overlay').style.display = 'flex';
        } else if (isCtrlOrCmd && event.key === 'ArrowRight') {
            event.preventDefault();
            navigateMonth(1);
        } else if (isCtrlOrCmd && event.key === 'ArrowLeft') {
            event.preventDefault();
            navigateMonth(-1);
        }
    });

    // ---------------------------------------
    // Edit Buttons initialisieren
    // ---------------------------------------
    initEditButtons();
    initEditSingleButtons();
});
</script>









<!-- Zusammengeführtes Such-Skript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    console.log("Such-Skript gestartet.");

    // Elemente
    const searchToggleButton = document.getElementById('search-toggle');
    const searchOverlay      = document.getElementById('search-overlay');  // Falls Du ein Overlay verwendest
    const closeSearchButton  = document.getElementById('close-search');
    const searchBar          = document.getElementById('search-bar');      // Falls Du eine extra Suchleiste togglest
    const searchInput        = document.getElementById('search-input');
    const searchResults      = document.getElementById('search-results');

    // --- TEIL A: Suchleiste / Overlay ein-/ausblenden ---

    // Funktion Overlay öffnen
    const openSearchOverlay = () => {
        if (searchOverlay) {
            searchOverlay.style.display = 'flex';
        }
        if (searchBar) {
            searchBar.classList.add('visible');
        }
        searchInput.value = '';
        searchResults.innerHTML = '';
        searchInput.focus();
    };

    // Funktion Overlay schließen
    const closeSearchOverlay = () => {
        if (searchOverlay) {
            searchOverlay.style.display = 'none';
        }
        if (searchBar) {
            searchBar.classList.remove('visible');
        }
    };

    // Klick auf Lupe/Toggle
    if (searchToggleButton) {
        searchToggleButton.addEventListener('click', () => {
            // Entweder direkt öffnen:
            // openSearchOverlay();

            // Oder toggeln:
            if (searchBar) {
                searchBar.classList.toggle('visible');
                if (searchBar.classList.contains('visible')) {
                    searchInput.focus();
                }
            } else {
                // Falls kein searchBar-Element da ist, nimm Overlay
                if (searchOverlay) {
                    openSearchOverlay();
                }
            }
        });
    }

    // Klick auf "Schließen"-Button
    if (closeSearchButton) {
        closeSearchButton.addEventListener('click', closeSearchOverlay);
    }

    // --- TEIL B: AJAX-Suche bei Eingabe ---

    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim();
        if (query.length === 0) {
            searchResults.innerHTML = '';
            return;
        }

        console.log("Suche nach:", query);

        // AJAX-Anfrage an search_entries.php
        fetch(`search_entries.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                console.log("Erhaltene Daten:", data);
                searchResults.innerHTML = '';

                if (!Array.isArray(data) || data.length === 0) {
                    searchResults.innerHTML = '<p>Keine Ergebnisse gefunden.</p>';
                    return;
                }

                // Ergebnisse anzeigen
                data.forEach(entry => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'search-result-item';
                    
                    resultItem.innerHTML = `
                        <p><strong>${entry.description}</strong></p>
                        <p>Betrag: ${parseFloat(entry.amount).toFixed(2)} €</p>
                        <p>Wiederholung: ${entry.recurring}</p>
                        <p>Datum: ${entry.date}</p>
                    `;

                    // Klick => Mit #entry-ID weiterleiten + sessionStorage-Fallback
                    resultItem.addEventListener('click', () => {
                        console.log("Klick auf Suchergebnis:", entry.id, entry.description);

                        // 1) SessionStorage: Fürs nachträgliche Highlight in index.php
                        sessionStorage.setItem('highlightId', entry.id);
                        sessionStorage.setItem('highlightYear', entry.entry_year);
                        sessionStorage.setItem('highlightMonth', entry.entry_month);

                        // 2) Per URL mit Hash => automatisches Springen
                        const url = `index.php?year=${entry.entry_year}&month=${entry.entry_month}#entry-${entry.id}`;
                        console.log("Weiterleitung zu:", url);

                        window.location.href = url;
                    });

                    searchResults.appendChild(resultItem);
                });
            })
            .catch(error => {
                console.error('Fehler bei der Suche:', error);
                searchResults.innerHTML = '<p>Fehler bei der Suche. Bitte versuche es später erneut.</p>';
            });
    });
});
</script>




<script>
document.addEventListener('DOMContentLoaded', function () {
    // Neue Funktion: Animation eines gefüllten grünen Kreises mit weißem Haken und Hintergrund-Ausblurrung
    function showCheckAnimationAndReload() {
        // Füge einmalig einen Style-Block für das neue Animations-Overlay hinzu, falls noch nicht vorhanden
        if (!document.getElementById('check-animation-style')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'check-animation-style';
            styleEl.innerHTML = `
                @keyframes fillCircle {
                    0% { fill: transparent; }
                    100% { fill: #4CAF50; }
                }
                @keyframes fadeInCheck {
                    0% { opacity: 0; }
                    100% { opacity: 1; }
                }
                .check-animation-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.2);
                    backdrop-filter: blur(5px);
                    -webkit-backdrop-filter: blur(5px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999;
                }
                .checkmark-svg {
                    width: 100px;
                    height: 100px;
                    overflow: visible;
                }
                .checkmark__circle {
                    stroke: #4CAF50;
                    stroke-width: 4;
                    fill: transparent;
                    animation: fillCircle 0.6s ease forwards;
                }
                .checkmark__check {
                    stroke: white;
                    stroke-width: 4;
                    fill: none;
                    opacity: 0;
                    animation: fadeInCheck 0.3s ease forwards;
                    animation-delay: 0.6s;
                }
            `;
            document.head.appendChild(styleEl);
        }
  
        // Erstelle den Container für das Overlay
        const container = document.createElement('div');
        container.className = 'check-animation-container';
  
        // Erstelle das SVG-Element
        const svgNS = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(svgNS, "svg");
        svg.classList.add("checkmark-svg");
        svg.setAttribute("viewBox", "0 0 52 52");
  
        // Erstelle den Kreis, der sich füllt
        const circle = document.createElementNS(svgNS, "circle");
        circle.classList.add("checkmark__circle");
        circle.setAttribute("cx", "26");
        circle.setAttribute("cy", "26");
        circle.setAttribute("r", "25");
  
        // Erstelle den Haken, der eingeblendet wird
        const check = document.createElementNS(svgNS, "path");
        check.classList.add("checkmark__check");
        check.setAttribute("d", "M14 27l7 7 16-16");
  
        svg.appendChild(circle);
        svg.appendChild(check);
        container.appendChild(svg);
        document.body.appendChild(container);
  
        // Nach Abschluss der Animation (ca. 1000ms) Seite neu laden
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
  
    // Event-Listener für Löschen
    document.querySelectorAll('.delete-button').forEach(button => {
        button.addEventListener('click', function () {
            const entryId = this.dataset.id;
            const tableName = this.dataset.table; // Table-Name aus dem Button holen
  
            // Bestätigungsdialog anzeigen
            if (confirm('Eintrag wirklich löschen?')) {
                fetch('delete_entry.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${entryId}&table_name=${encodeURIComponent(tableName)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Fehler beim Löschen des Eintrags.');
                    }
                    return response.text();
                })
                .then(responseText => {
                    if (responseText.includes('success')) {
                        // Statt direkt die Seite neu zu laden, Animation anzeigen und danach neu laden
                        showCheckAnimationAndReload();
                    } else {
                        throw new Error(responseText);
                    }
                })
                .catch(error => {
                    console.error('Fehler:', error.message);
                    alert('Fehler beim Löschen des Eintrags.');
                });
            }
        });
    });
});
</script>




<!--Ausblenden Button -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Event-Listener für Ausblenden
    document.querySelectorAll('.hide-button').forEach(button => {
        button.addEventListener('click', function () {
            const entryId = this.dataset.id;

            fetch('hide_entry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${entryId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Fehler beim Ausblenden des Eintrags.');
                }
                location.reload(); // Seite neu laden, um die Änderungen anzuzeigen
            })
            .catch(error => {
                console.error(error.message);
            });
        });
    });
});
</script>

<!--Alle Einträge Einblenden Button -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const unhideAllButton = document.getElementById('unhide-all');

    // Event-Listener für "Alle Einträge einblenden"
    unhideAllButton.addEventListener('click', function () {
        fetch('unhide_all_entries.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `month=${<?= $month ?>}&year=${<?= $year ?>}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Fehler beim Wieder-Einblenden der Einträge.');
            }
            location.reload(); // Seite neu laden, um die Änderungen anzuzeigen
        })
        .catch(error => {
            console.error(error.message);
        });
    });
});
</script>


<!--Zurück Scroll to Top Button-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scrollToTopButton = document.getElementById('scroll-to-top');

    // Zeige den Button an, wenn der Benutzer scrollt
    window.addEventListener('scroll', function () {
        if (window.scrollY > 200) {
            scrollToTopButton.classList.add('visible');
        } else {
            scrollToTopButton.classList.remove('visible');
        }
    });

    // Scrollt zurück nach oben, wenn der Button geklickt wird
    scrollToTopButton.addEventListener('click', function () {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});
</script>
