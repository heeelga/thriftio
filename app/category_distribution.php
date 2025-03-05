<?php
include('init.php');
include 'dbconnection.php';

$username = $_SESSION['username'];
$table_name = $conn->real_escape_string($username);

$month = intval($_GET['month'] ?? date('n'));
$year = intval($_GET['year'] ?? date('Y'));

$sqlCategoryDistribution = "
SELECT category, SUM(amount) AS total
FROM `$table_name`
WHERE 
    type = 'expense'
    AND entry_month = ?
    AND entry_year = ?
GROUP BY category
ORDER BY total DESC
";

$stmt = $conn->prepare($sqlCategoryDistribution);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$categoryData = [];
while ($row = $result->fetch_assoc()) {
    $categoryData[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($categoryData);
