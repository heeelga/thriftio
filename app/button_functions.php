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
                document.getElementById("edit-single-rebooking-id").value = entryData.id;
                document.getElementById("edit-single-source-account").value = entryData.source_account || "main";
                document.getElementById("edit-single-target-account").value = entryData.target_account || "main";
                document.getElementById("edit-single-amount").value = entryData.amount || "";
                document.getElementById("edit-single-description").value = entryData.description || "";
                document.getElementById("edit-single-entry-month").value = entryData.entry_month || "";
                document.getElementById("edit-single-entry-year").value = entryData.entry_year || "";
            } catch (error) {
                console.error("Fehler beim Laden der Umbuchungsdaten:", error);
                alert("Fehler beim Laden der Umbuchungsdaten.");
            }
        });
    });
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

document.addEventListener('keydown', async (e) => {
    if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault(); // Standardbrowserverhalten (Seite speichern) verhindern

        // Prüfen, ob ein Overlay geöffnet ist und ein Formular hat
        const overlays = [
            { id: 'overlay', formId: 'overlay-form' }, // Bewegung hinzufügen
            { id: 'rebooking-overlay', formId: 'rebooking-form' }, // Umbuchung hinzufügen
        ];

        for (const overlay of overlays) {
            const overlayElement = document.getElementById(overlay.id);
            if (overlayElement && overlayElement.style.display === 'flex') {
                const form = document.getElementById(overlay.formId);
                if (form) {
                    try {
                        // Formulardaten sammeln
                        const formData = new FormData(form);
                        const actionUrl = form.action;

                        // Daten an den Server senden
                        const response = await fetch(actionUrl, {
                            method: 'POST',
                            body: formData,
                        });

                        // Serverantwort prüfen
                        if (response.ok) {
                            try {
                                const result = await response.json();
                                if (result.success) {
                                    // Erfolgreiches Speichern -> Seite neu laden
                                    window.location.reload();
                                } else {
                                    alert(
                                        'Fehler: ' +
                                            (result.message || 'Daten konnten nicht gespeichert werden.')
                                    );
                                }
                            } catch (jsonError) {
                                // Falls die Rückgabe kein JSON ist, einfach neu laden
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
                return; // Sobald ein Overlay behandelt wurde, Abbruch
            }
        }
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
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
                const type = entry.dataset.type; // "income" oder "expense"
                if (!isNaN(amount)) {
                    totalSum += (type === 'income' ? amount : -amount);
                }
            }
        });
        totalSumElement.textContent = totalSum.toFixed(2) + ' €';

        const totalSumContainer = document.getElementById('total-sum-container');
        if (selectedEntries.size > 0) {
            totalSumContainer.style.display = 'block';
        } else {
            totalSumContainer.style.display = 'none';
        }
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
            alert('Bitte wähle mindestens einen Eintrag aus.');
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
                alert(result.message || 'Ein Fehler ist aufgetreten.');
                return;
            }
            entries.forEach(entry => {
                if (selectedEntries.has(entry.dataset.id)) {
                    entry.remove();
                }
            });
            cancelBulkMode();
        })
        .catch(error => alert('Ein Fehler ist aufgetreten: ' + error.message));
    };

    // Funktion: Alle ausgewählten Einträge löschen
    const bulkDelete = () => {
        if (selectedEntries.size === 0) {
            alert('Bitte wähle mindestens einen Eintrag aus.');
            return;
        }
        if (!confirm('Möchten Sie die ausgewählten Einträge wirklich löschen?')) return;

        const ids = Array.from(selectedEntries);
        fetch('bulk_delete_entries.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids }),
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                alert(result.message || 'Ein Fehler ist aufgetreten.');
                return;
            }
            alert(result.message || 'Die Einträge wurden erfolgreich gelöscht.');
            window.location.reload();
        })
        .catch(error => alert('Ein Fehler ist aufgetreten: ' + error.message));
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
document.addEventListener('keydown', function (event) {
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
        document.getElementById('overlay').style.display = 'flex';
    } else if (isCtrlOrCmd && event.key === 'u') {
        event.preventDefault();
        document.getElementById('rebooking-overlay').style.display = 'flex';
    } else if (isCtrlOrCmd && event.key === 'ArrowRight') {
        event.preventDefault();
        navigateMonth(1); // Zum nächsten Monat
    } else if (isCtrlOrCmd && event.key === 'ArrowLeft') {
        event.preventDefault();
        navigateMonth(-1); // Zum vorherigen Monat
    }
});

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

    mainFab.addEventListener('click', () => {
        fabOptions.classList.toggle('hidden');
        fabOptions.classList.toggle('show');
    });

    // Aktionen für die Buttons
    document.getElementById('add-entry').addEventListener('click', () => {
        document.getElementById('overlay').style.display = 'flex'; // Overlay für neue Bewegung öffnen
    });

    document.getElementById('add-rebooking').addEventListener('click', () => {
        document.getElementById('rebooking-overlay').style.display = 'flex'; // Overlay für neue Umbuchung öffnen
    });

    document.getElementById('add-savings').addEventListener('click', () => {
        document.getElementById('add-savings-overlay').style.display = 'flex'; // Overlay für neues Sparkonto öffnen
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Hilfsfunktion zum Auslesen von URL-Parametern
    function getQueryParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }
    // Verwende die URL-Parameter "month" und "year", falls vorhanden, ansonsten Fallback auf Systemdatum
    const currentViewMonth = parseInt(getQueryParameter("month")) || (new Date().getMonth() + 1);
    const currentViewYear  = parseInt(getQueryParameter("year"))  || (new Date().getFullYear());

    // Felder des Overlays initialisieren
    const idField               = document.getElementById('edit-id');
    const entryTypeField        = document.getElementById('entry-type');   // gleiche ID wie im HTML
    const amountField           = document.getElementById('amount');
    const descriptionField      = document.getElementById('description');
    const recurringField        = document.getElementById('recurring');
    const repeatUntilMonthField = document.getElementById('repeat_until_month');
    const repeatUntilYearField  = document.getElementById('repeat_until_year');
    const repeatUntilFields     = document.getElementById('repeat-until-fields');
    const categoryField         = document.getElementById('category');
    const overlay               = document.getElementById('overlay');
    const overlayTitle          = document.getElementById('overlay-title');
    const overlayForm           = document.getElementById('overlay-form');
    const addButton             = document.getElementById('add-entry');

    // Datumsfeld für Buchungsdatum
    const bookingDateField = document.getElementById('booking_date');

    // Übersetzungen (Beispiel, ggf. anpassen)
    const translations = {
        edit_movement_title: 'Bewegung bearbeiten',
        edit_single_entry_title: 'Einzelnen Serieneintrag bearbeiten',
    };

    // Funktion: Felder zurücksetzen (Standardwerte für "Neuen Eintrag")
    function resetOverlayFields() {
        idField.value               = '';
        entryTypeField.value        = 'expense';   // Standard: Ausgabe
        amountField.value           = '';
        descriptionField.value      = '';
        recurringField.value        = 'no';
        repeatUntilMonthField.value = '';
        repeatUntilYearField.value  = '';
        categoryField.value         = '';
        bookingDateField.value      = ''; // Datumsfeld leeren

        // Overlay-UI anpassen
        toggleRepeatUntilFields('no'); 
        setEntryType('expense'); // Button-Auswahl auf "expense" setzen (vorausgesetzt, diese Funktion ist definiert)
        recurringField.disabled = false;
    }

    // Felder für Wiederholungsende anzeigen/ausblenden
    function toggleRepeatUntilFields(recurringValue) {
        if (recurringValue === 'no') {
            repeatUntilFields.style.display = 'none';
        } else {
            repeatUntilFields.style.display = 'block';
        }
    }

    // Event, wenn das Dropdown (Regelmäßig) geändert wird
    recurringField.addEventListener('change', function () {
        toggleRepeatUntilFields(this.value);
    });

    // Button "Neuen Eintrag" -> Overlay öffnen
    addButton.addEventListener('click', function () {
        resetOverlayFields();
        overlayTitle.textContent = 'Neue Bewegung hinzufügen';
        overlayForm.action = 'add_entry.php';
        overlayForm.dataset.override = '0';
        overlay.style.display = 'flex';
    });

    // Eintragsdaten via AJAX abrufen
    async function fetchEntryData(id, editMode) {
        const response = await fetch(`get_entry.php?id=${id}&edit_mode=${editMode}`);
        if (!response.ok) {
            console.error('Fehler beim Abrufen der Eintragsdaten:', response.status);
            throw new Error('Fehler beim Abrufen der Eintragsdaten');
        }
        const data = await response.json();
        return data;
    }

    // Bearbeiten-Buttons (gesamte Serie)
    function initEditButtons() {
        const editButtons = document.querySelectorAll('.edit-button');
        editButtons.forEach(button => {
            button.addEventListener('click', async function () {
                const entryId = this.dataset.id;
                resetOverlayFields();

                try {
                    // Daten mit Modus "series" laden
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

                        overlayTitle.textContent = translations.edit_movement_title;
                        overlayForm.action       = 'edit_entry.php';
                        overlayForm.dataset.override = '0';
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

    // Bearbeiten-Buttons (einzelner Serieneintrag / Override)
    function initEditSingleButtons() {
        const editSingleButtons = document.querySelectorAll('.edit-single-button');
        editSingleButtons.forEach(button => {
            button.addEventListener('click', async function () {
                const entryId = this.dataset.id;
                resetOverlayFields();

                try {
                    // Daten mit Modus "single" laden
                    const entryData = await fetchEntryData(entryId, 'single');
                    if (entryData && typeof entryData.id !== 'undefined') {
                        idField.value          = entryData.id;
                        amountField.value      = entryData.amount;
                        descriptionField.value = entryData.description;
                        categoryField.value    = entryData.category || '';

                        // Hier setzen wir das Buchungsdatum so, dass der Tag aus dem Serien-Buchungsdatum
                        // übernommen wird, aber Monat und Jahr aus den URL-Parametern (aktueller View) verwendet werden.
                        if (entryData.booking_date) {
                            let seriesDate = new Date(entryData.booking_date);
                            let day = seriesDate.getDate();
                            let monthStr = ('0' + currentViewMonth).slice(-2);
                            let dayStr = ('0' + day).slice(-2);
                            bookingDateField.value = `${currentViewYear}-${monthStr}-${dayStr}`;
                        } else {
                            bookingDateField.value = new Date().toISOString().slice(0,10);
                        }

                        if (entryData.type) {
                            entryTypeField.value = entryData.type;
                            setEntryType(entryData.type);
                        }

                        // Bei Einzelbearbeitung: recurring deaktivieren
                        recurringField.value    = 'no';
                        recurringField.disabled = true;

                        // (Optional) Falls Du month/year-Werte in weiteren Feldern benötigst:
                        repeatUntilMonthField.value = entryData.entry_month || '';
                        repeatUntilYearField.value  = entryData.entry_year  || '';

                        overlayTitle.textContent  = translations.edit_single_entry_title;
                        overlayForm.action        = 'edit_entry.php';
                        overlayForm.dataset.override = '1';
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

    // Formular abschicken
    overlayForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const formData = new FormData(overlayForm);
        formData.append('override', overlayForm.dataset.override || '0');

        try {
            const response = await fetch(overlayForm.action, {
                method: 'POST',
                body: formData,
            });
            const result = await response.json();
            if (result.success) {
                window.location.reload();
            } else {
                alert('Fehler: ' + (result.message || 'Änderungen konnten nicht gespeichert werden.'));
            }
        } catch (error) {
            console.error('Fehler beim Speichern der Änderungen:', error);
            alert('Fehler beim Speichern der Änderungen.');
        }
    });

    // Overlay schließen, wenn außerhalb geklickt wird
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            overlay.style.display = 'none';
        }
    });

    // Buttons initialisieren
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
                        //alert('Eintrag erfolgreich gelöscht.');
                        location.reload(); // Seite neu laden, um die Änderungen anzuzeigen
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

<!--Umbuchung hinzufügen Button-->
<script>
document.getElementById('add-rebooking').addEventListener('click', function () {
    document.getElementById('rebooking-overlay').style.display = 'flex';
});
</script>