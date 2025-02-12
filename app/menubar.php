<?php
ob_start(); // Output Buffering starten
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
ob_end_clean(); // Buffer leeren, ohne den Inhalt auszugeben
?>

<!DOCTYPE html>
<style>
@media only screen and (max-width: 768px) {
  html { visibility: hidden; }
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
      <button id="search-toggle" class="search-button" title="Suchen">
        <i class="fas fa-search"></i>
      </button>
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
  
<script>
document.getElementById('createBackupBtn').addEventListener('click', () => {
    fetch('create_backup.php', { method: 'POST' })
        .then(response => response.json())
        .then(data => alert(data.message))
        .catch(err => console.error('Fehler:', err));
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

<script>
document.getElementById('listBackupsBtn').addEventListener('click', () => {
    fetch('list_backups.php')
        .then(response => response.json())
        .then(backups => {
            const backupList = document.getElementById('backupList');
            backupList.innerHTML = backups.map(backup => {
                return `
                    <div class="backup-item">
                        <span>${backup.display}</span>
                        <div class="backup-actions">
                            <button class="restore-btn" onclick="restoreBackup('${backup.file}')"><?php echo $translations['restore']?></button>
                            <button class="delete-btn" onclick="deleteBackup('${backup.file}')"><?php echo $translations['delete']?></button>
                        </div>
                    </div>
                `;
            }).join('');
        })
        .catch(err => console.error('Fehler:', err));
});


function restoreBackup(backupFile) {
    fetch('restore_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ backup_file: backupFile })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message); // Zeigt die Erfolgsmeldung oder Fehlermeldung an
        if (data.success) {
            window.location.reload(); // Lädt die aktuelle Seite neu
        }
    })
    .catch(err => {
        console.error('Fehler:', err);
        alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
    });
}


function deleteBackup(backupFile) {
    if (confirm(`Möchtest Du das Backup '${backupFile}' wirklich löschen?`)) {
        fetch('delete_backup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ backup_file: backupFile })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            document.getElementById('listBackupsBtn').click(); // Liste aktualisieren
        })
        .catch(err => console.error('Fehler:', err));
    }
}
</script>



<script>
    function toggleDangerzone() {
        const dangerzoneEntries = document.getElementById("dangerzone-entries");
        if (dangerzoneEntries.style.display === "none") {
            dangerzoneEntries.style.display = "block";
        } else {
            dangerzoneEntries.style.display = "none";
        }
    }
</script>


<!-- Overlay hinzufügen-->

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
