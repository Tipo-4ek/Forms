<?php
// Проверка авторизации
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

$user_id = $_COOKIE['user_id'];

// Получение всех заполнений пользователя
$sql = "
    SELECT ur.id AS response_id, f.name AS form_name, ur.created_at AS response_date
    FROM user_responses ur
    JOIN forms f ON ur.form_id = f.id
    WHERE ur.user_id = ?
    ORDER BY ur.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$responses_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Мои заполнения</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid black;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>Мои заполнения</h1>
    <table>
        <tr>
            <th>Название формы</th>
            <th>Дата заполнения</th>
            <th>Действия</th>
        </tr>
        <?php while ($response = $responses_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($response['form_name']); ?></td>
                <td><?php echo htmlspecialchars($response['response_date']); ?></td>
                <td>
                    <a href="view_individual_result.php?response_id=<?php echo $response['response_id']; ?>">Просмотреть</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    <br>
    <a href="dashboard.php">Вернуться на главную</a>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
