<?php
include('init.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unbekannt';
    $suggestionText = $data['suggestion_text'] ?? '';
    $feedbackType = $data['feedback_type'] ?? 'Unbekannt';

    // API-Details
    $giteaUrl = 'https://zipfl.revier.beer/api/v1/repos/markus/finance_docker/issues';
    $accessToken = '45deeceb2f62849644ddf1859fee3eda44c2384a';

    // Issue-Daten
    $issueData = [
        'title' => "[$feedbackType] von Benutzer: $username",
        'body' => $suggestionText,
    ];

    // API-Aufruf vorbereiten
    $options = [
        'http' => [
            'header' => implode("\r\n", [
                "Content-Type: application/json",
                "Authorization: token $accessToken"
            ]),
            'method' => 'POST',
            'content' => json_encode($issueData),
        ],
    ];

    $context = stream_context_create($options);

    // API-Aufruf ausführen
    $response = @file_get_contents($giteaUrl, false, $context);
    $httpCode = $http_response_header[0] ?? '';

    if ($response === false) {
        $error = error_get_last();
        echo json_encode([
            'success' => false,
            'error' => 'Fehler beim Erstellen des Issues.',
            'details' => $error['message'] ?? 'Unbekannter Fehler'
        ]);
    } else {
        // HTTP-Status prüfen
        if (strpos($httpCode, '200') !== false || strpos($httpCode, '201') !== false) {
            echo json_encode(['success' => true, 'message' => 'Issue erfolgreich erstellt.']);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Fehler beim Erstellen des Issues.',
                'http_code' => $httpCode,
                'response' => $response,
            ]);
        }
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Ungültige Anforderung.']);
}
?>
