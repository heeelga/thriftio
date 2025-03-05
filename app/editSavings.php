<?php
include('init.php');
include('dbconnection.php');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $savingsName = $data['savingsName'] ?? null;
    $interestRate = $data['interestRate'] ?? null;
    $username = $_SESSION['username'] ?? null;

    if (!$username || !$savingsName || $interestRate === null || $interestRate < 0 || $interestRate > 100) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Eingabedaten.']);
        exit;
    }

    try {
        global $pdo;

        // Sparkonto in der separaten Zinstabelle aktualisieren
        $stmt = $pdo->prepare("
            UPDATE savings_interest_rates
            SET interest_rate = :interest_rate
            WHERE savings_name = :savings_name
        ");
        $stmt->execute([
            'interest_rate' => $interestRate,
            'savings_name' => "{$username}_{$savingsName}",
        ]);

        echo json_encode(['success' => true, 'message' => 'Änderungen erfolgreich gespeichert.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>
