<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Проверка авторизации
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

$form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 0;
$user_id = $_COOKIE['user_id'];

// Проверка прав доступа к форме
$sql = "
    SELECT fa.access_level
    FROM form_access fa
    WHERE fa.form_id = ? AND fa.user_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $form_id, $user_id);
$stmt->execute();
$access_result = $stmt->get_result();
$access = $access_result->fetch_assoc();

if (!$access || !in_array($access['access_level'], ['view', 'edit', 'owner'])) {
    die('У вас нет прав для просмотра заполнений этой формы.');
}

// Получение информации о форме
$sql = "SELECT name FROM forms WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$form_result = $stmt->get_result();
$form = $form_result->fetch_assoc();

// Получение всех записей ответов
$sql = "
    SELECT 
        ur.id, 
        ur.user_id, 
        u.full_name, 
        ur.created_at,
        (
            SELECT SUM(a.score)
            FROM responses_details AS rd
            JOIN answers a ON rd.answer_id = a.id
            WHERE rd.response_id = ur.id
        ) AS total_score
    FROM 
        user_responses AS ur
    JOIN 
        users AS u ON ur.user_id = u.id
    WHERE 
        ur.form_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$responses_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Результаты формы <?php echo htmlspecialchars($form['name']); ?></title>
</head>
<body>
    <h1>Результаты формы: <?php echo htmlspecialchars($form['name']); ?></h1>
    <table border="1">
        <tr>
            <th>Пользователь</th>
            <th>Дата заполнения</th>
            <th>Результаты</th>
            <th>Оценка</th>
        </tr>
        <?php while ($response = $responses_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($response['full_name']); ?></td>
                <td><?php echo $response['created_at']; ?></td>
                <td>
                    <a href="view_individual_result.php?response_id=<?php echo $response['id']; ?>">Просмотр</a>
                </td>
                <td>
                    <?php echo $response['result_text'] ?? 'Не оценено'; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    <a href="dashboard.php">Назад к панели администратора</a>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>