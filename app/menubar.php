<?php
include('init.php');

// Admin-Check
$isAdmin = false;
$stmt = $conn->prepare("SELECT admin FROM user WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['admin'] == 1) {
        $isAdmin = true;
    }
}
?>
<!DOCTYPE html>
<style>
@media only screen and (max-width: 768px) {
  html { visibility: hidden; }
}

/* 
  1) Buttons (Lupe & Filter) kreisrund gestalten.
     - width/height identisch
     - border-radius: 50%
     - zentrierte Icons
*/

/* Container, damit Lupe und Filterbutton (und das Overlay-Feld) nebeneinander stehen */
.search-container {
  display: inline-flex;
  align-items: center;
  position: relative; /* wichtig für absolutes Positionieren des Filter-Containers */
}

/* Filter-Container:
   Absolut positioniert, damit er neben dem Button "schwebt",
   ohne andere Elemente zu verschieben.
*/
#filterContainer {
  display: none;
  position: absolute;
  top: 50%;
  left: calc(100% + 10px); /* 100% = Breite der search-container + 10px Abstand */
  transform: translateY(-50%); /* vertikal zentrieren relativ zur Button-Höhe */
  
  /* Optische Gestaltung */
  background: #fff;     /* heller Hintergrund */
  border: 1px solid #ccc;
  border-radius: 4px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
  padding: 5px;
  z-index: 1000;        /* Damit es oben liegt und nicht abgeschnitten wird */
  width: 200px;         /* Feste Breite, nach Bedarf anpassen */
}

/* Eingabefeld selbst */
#filterInput {
  width: 100%;
  padding: 5px;
  font-size: 14px;
  box-sizing: border-box;
}
</style>

<!-- Search-Overlay -->
<div id="search-overlay" class="search-overlay">
    <div class="search-modal">
        <!-- Schließen-Button hinzufügen -->
        <div id="close-search" class="close-button">&times;</div>

        <!-- Suchfeld -->
        <input type="text" id="search-input" placeholder='<?php echo $translations['search_for_entries'];?>'>

        <!-- Suchergebnisse -->
        <div id="search-results" class="search-results"></div>
    </div>
</div>

<!-- Menü-Bereich -->
<div class="menu-bar">
  <div class="search-container">
    <?php
    // Such-Button nur auf index.php anzeigen
    if (basename($_SERVER['SCRIPT_NAME']) == 'index.php') { ?>
      <!-- Lupe -->
      <button id="search-toggle" class="search-button" title="Suchen">
        <i class="fas fa-search"></i>
      </button>

      <!-- Filter-Button -->
      <button type="button" id="filter-toggle" class="search-button" title="Filtern">
        <i class="fas fa-filter"></i>
      </button>

      <!-- Filterfeld (erscheint rechts vom Filter-Button) -->
      <div id="filterContainer">
        <label for="filterInput" style="display: none;"></label>
        <input type="text" id="filterInput" placeholder="Einträge filtern...">
      </div>

      <script>
      // ---------------------------------------------
      // Toggle für das Filterfeld
      // ---------------------------------------------
      const filterToggleBtn = document.getElementById('filter-toggle');
      const filterContainer = document.getElementById('filterContainer');
      const filterInput     = document.getElementById('filterInput');

      if (filterToggleBtn && filterContainer && filterInput) {
        filterToggleBtn.addEventListener('click', () => {
          // Sichtbarkeit umschalten
          if (filterContainer.style.display === 'none' || filterContainer.style.display === '') {
            // Anzeigen & fokussieren
            filterContainer.style.display = 'block';
            filterInput.focus();
          } else {
            // Ausblenden, Text leeren & Filter zurücksetzen
            filterContainer.style.display = 'none';
            filterInput.value = '';
            resetFilter();
          }
        });

        // Echtzeit-Filterung per keyup
        filterInput.addEventListener('keyup', function() {
          const searchValue = filterInput.value.toLowerCase();
          const allEntries  = document.querySelectorAll('#entries .entry-box');

          allEntries.forEach(function(entry) {
            // Data-Attribut auslesen
            const filterText = entry.getAttribute('data-filter-text') || '';
            
            // Prüfen, ob die Eingabe im filterText vorkommt
            if (filterText.indexOf(searchValue) !== -1) {
                // Treffer -> Eintrag anzeigen
                entry.style.display = '';
            } else {
                // Kein Treffer -> Eintrag ausblenden
                entry.style.display = 'none';
            }
          });
        });
      }

      // ---------------------------------------------
      // Funktion, um sämtliche Einträge wieder einzublenden
      // ---------------------------------------------
      function resetFilter() {
        const allEntries = document.querySelectorAll('#entries .entry-box');
        allEntries.forEach(entry => {
          entry.style.display = '';
        });
      }

      // ---------------------------------------------
      // Toggle-Suche (bestehend)
      // ---------------------------------------------
      const searchToggle = document.getElementById("search-toggle");
      const searchOverlay = document.getElementById("search-overlay");
      const closeSearch = document.getElementById("close-search");

      if (searchToggle && searchOverlay && closeSearch) {
        searchToggle.addEventListener("click", () => {
          if (searchOverlay.style.display === "" || searchOverlay.style.display === "none") {
            searchOverlay.style.display = "flex";
          } else {
            searchOverlay.style.display = "none";
          }
        });

        closeSearch.addEventListener("click", () => {
          searchOverlay.style.display = "none";
        });

        window.addEventListener("click", (event) => {
          if (event.target === searchOverlay) {
            searchOverlay.style.display = "none";
          }
        });
      }
      </script>
    <?php } ?>
  </div>
  <div class="menu-title">
    <a href="index.php" style="text-decoration: none; color: inherit;">ThriftIO</a>
  </div>
  <div class="burger-menu" onclick="toggleMenu()">
    <i class="fas fa-bars"></i>
  </div>
</div>

<!-- Container für die Seiten-Links und Aktionen -->
<div id="menu-links" class="menu-links">
  <!-- Gemeinsame Navigations-Links -->
  <a href="index.php"><?php echo $translations['start']; ?></a>
  <a href="account.php"><?php echo $translations['account']; ?></a>
  <?php if ($isAdmin) { ?>
    <a href="admin.php"><?php echo $translations['admin']; ?></a>
  <?php } ?>
  <!-- <a href="invite.php"><?php echo $translations['invite']; ?></a> -->
  <a href="#" id="suggestions-link"><?php echo $translations['make_suggestion']; ?></a>
  <a href="logout.php">Logout</a>

  <!-- Gemeinsame Action-Elemente, z.B. Theme Toggle -->
  <div class="action-bar" style="display: flex; align-items: center; justify-content: flex-end;">
      <?php if (basename($_SERVER['SCRIPT_NAME']) == 'account.php') { ?>
      <div class="backup-actions" style="margin-left: 10px;">
        <button id="createBackupBtn" class="action-btn" title="<?php echo $translations['create_backup']; ?>">
          <i class="fas fa-save"></i>
        </button>
        <button id="listBackupsBtn" class="action-btn" title="<?php echo $translations['show_backups']; ?>">
          <i class="fas fa-list"></i>
        </button>
      </div>
      <?php } ?>
      <div class="theme-toggle-container">
        <label class="switch">
          <input type="checkbox" id="themeToggle">
          <span class="slider round"></span>
        </label>
        <i class="fas fa-moon"></i>
      </div>
  </div>

  <?php if (basename($_SERVER['SCRIPT_NAME']) == 'account.php') { ?>
    <!-- Weitere Admin-spezifische Elemente -->

    <!-- Backup-Liste -->
    <div id="backupList" style="margin-top: 20px;"></div>

    <!-- Dangerzone Hinweis -->
    <div class="dangerzone" onclick="toggleDangerzone()"
         style="background-color: #dc3545; color: white; text-align: center; padding: 10px; margin: 10px 0; cursor: pointer;">
      Dangerzone!
    </div>
    <div id="dangerzone-entries" style="display: none; text-align: center;">
      <a href="#" onclick="deleteAllEntries()"><?php echo $translations['delete_all_entries_this_month']; ?></a><br>
      <a href="#" onclick="deleteAllUserEntries()"><?php echo $translations['delete_all_entries']; ?></a>
    </div>
  <?php } ?>
</div>
</div>

<script>
  window.addEventListener("load", function() {
    document.documentElement.style.visibility = "visible";
  });
</script>

<div id="backupList" style="margin-top: 20px;"></div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const themeToggle = document.getElementById("themeToggle");

  // Prüfen, ob eine Einstellung im Local Storage gespeichert ist
  const currentTheme = localStorage.getItem("theme");
  if (currentTheme) {
    document.body.classList.add(currentTheme);
    themeToggle.checked = currentTheme === "dark-theme";
  }

  // Event Listener für den Schieberegler
  themeToggle.addEventListener("change", () => {
    if (themeToggle.checked) {
      document.body.classList.add("dark-theme");
      localStorage.setItem("theme", "dark-theme");
    } else {
      document.body.classList.remove("dark-theme");
      localStorage.setItem("theme", "");
    }
  });
});
</script>

<!-- Overlay hinzufügen -->
<div id="suggestion-overlay" class="suggestion-overlay">
    <div class="suggestion-modal">
        <!-- Schließen-Button -->
        <button class="close-button" id="close-suggestion-overlay">&times;</button>
        
        <!-- Titel -->
        <h2><?php echo $translations['make_suggestion'];?></h2>
        
        <!-- Formular -->
        <form id="suggestion-form">
            <label for="feedback-type">Typ:</label>
            <select id="feedback-type" name="feedback_type" required>
                <option value="Vorschlag"><?php echo $translations['suggestion'];?></option>
                <option value="Fehlermeldung"><?php echo $translations['error_report'];?></option>
            </select>

            <label for="suggestion-text"><?php echo $translations['suggestion_headline'];?></label>
            <textarea id="suggestion-text" name="suggestion_text" rows="5" required></textarea>
            
            <button type="button" id="submit-suggestion"><?php echo $translations['send'];?></button>
        </form>
        
        <!-- Feedback-Meldung -->
        <div id="suggestion-feedback" style="display:none; margin-top: 10px; color: green;"></div>
    </div>
</div>

<!-- Javascript zur Steuerung des Overlays und des AJAX-Versands-->
<script>
    const suggestionLink = document.getElementById('suggestions-link');
    const suggestionOverlay = document.getElementById('suggestion-overlay');
    const closeSuggestionOverlay = document.getElementById('close-suggestion-overlay');
    const suggestionForm = document.getElementById('suggestion-form');
    const submitSuggestion = document.getElementById('submit-suggestion');
    const suggestionFeedback = document.getElementById('suggestion-feedback');

    suggestionLink.addEventListener('click', (event) => {
        event.preventDefault();
        suggestionOverlay.style.display = 'flex';
    });

    closeSuggestionOverlay.addEventListener('click', () => {
        suggestionOverlay.style.display = 'none';
        suggestionFeedback.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === suggestionOverlay) {
            suggestionOverlay.style.display = 'none';
            suggestionFeedback.style.display = 'none';
        }
    });

    submitSuggestion.addEventListener('click', () => {
        const suggestionText = document.getElementById('suggestion-text').value;
        const feedbackType = document.getElementById('feedback-type').value;

        if (!suggestionText) {
            suggestionFeedback.textContent = 'Bitte gib einen Vorschlag oder Fehlerbericht ein.';
            suggestionFeedback.style.color = 'red';
            suggestionFeedback.style.display = 'block';
            return;
        }

        fetch('send_suggestion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ suggestion_text: suggestionText, feedback_type: feedbackType }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                suggestionFeedback.textContent = 'Vielen Dank! Ich sehe es mir an!';
                suggestionFeedback.style.color = 'green';
                suggestionFeedback.style.display = 'block';
                suggestionForm.reset();
            } else {
                suggestionFeedback.textContent = 'Fehler beim Versenden: ' + data.error;
                suggestionFeedback.style.color = 'red';
                suggestionFeedback.style.display = 'block';
            }
        })
        .catch(error => {
            suggestionFeedback.textContent = 'Ein unerwarteter Fehler ist aufgetreten.';
            suggestionFeedback.style.color = 'red';
            suggestionFeedback.style.display = 'block';
        });
    });
</script>
