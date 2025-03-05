<?php
include('init.php');
include 'dbconnection.php';

$response = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $notes = $_POST['release_notes'];
    
    // Release Notes in Textdatei speichern
    file_put_contents('release_notes.txt', $notes);

    // Setze alle User als "Release Notes verfügbar" und "ungelesen"
    $updateQuery = "UPDATE user SET release_notes_available = TRUE, release_notes_read = FALSE";
    if ($conn->query($updateQuery) === TRUE) {
        $response = "Release Notes gespeichert und an alle User verteilt!";
    } else {
        $response = "Fehler beim Aktualisieren der User: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Release Notes Verwaltung</title>
    <style>
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .overlay.active {
            display: flex;
        }

        .modal {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
        }

        .success-message {
            color: #28a745;
            text-align: center;
            margin-top: 10px;
        }

        .close-button {
            background-color: #ccc;
            color: white;
            font-size: 18px;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <button id="open-overlay" class="add-button">+</button>

    <div class="overlay" id="notes-overlay">
        <div class="modal">
            <form method="POST">
                <h2>Neue Release Notes eingeben</h2>
                <textarea name="release_notes" rows="10" cols="50" placeholder="Schreibe hier die Release Notes..."></textarea><br>
                <button type="submit">Speichern</button>
                <?php if ($response): ?>
                    <div class="success-message"><?= $response ?></div>
                <?php endif; ?>
                <button type="button" class="close-button" id="close-overlay">Schließen</button>
            </form>
        </div>
    </div>

    <script>
        const openOverlay = document.getElementById('open-overlay');
        const closeOverlay = document.getElementById('close-overlay');
        const overlay = document.getElementById('notes-overlay');

        openOverlay.addEventListener('click', () => {
            overlay.classList.add('active');
        });

        closeOverlay.addEventListener('click', () => {
            overlay.classList.remove('active');
        });
    </script>
</body>
</html>
