<?php
include('init.php');
include('dbconnection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $savingsName = $data['savingsName'] ?? null;
    $username = $_SESSION['username'] ?? null;

    if (!$username || !$savingsName) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage.']);
        exit;
    }

    try {
        global $pdo;

        // Tabelle des Sparkontos löschen
        $tableName = "{$username}_" . $savingsName;
        $stmt = $pdo->prepare("DROP TABLE IF EXISTS `$tableName`;");
        $stmt->execute();

        // Eintrag in der separaten Zinstabelle löschen
        $interestStmt = $pdo->prepare("DELETE FROM savings_interest_rates WHERE savings_name = :savings_name");
        $interestStmt->execute(['savings_name' => $tableName]);

        // Tabelle für Kontostand löschen
        $balanceTableName = "Kontostand_" . $tableName;
        $balanceStmt = $pdo->prepare("DROP TABLE IF EXISTS `$balanceTableName`;");
        $balanceStmt->execute();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>