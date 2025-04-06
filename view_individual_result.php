<?php
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

$response_id = isset($_GET['response_id']) ? (int)$_GET['response_id'] : 0;
$current_user_id = $_COOKIE['user_id'];

// Получаем данные заполнения с именем пользователя, заполнившего форму
$sql = "SELECT ur.form_id, ur.user_id, u.full_name AS filler_name 
        FROM user_responses ur 
        JOIN users u ON ur.user_id = u.id 
        WHERE ur.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $response_id);
$stmt->execute();
$response_result = $stmt->get_result();
$response = $response_result->fetch_assoc();

if ($response) {
    $form_id = $response['form_id'];
    $filler_name = $response['filler_name'];
} else {
    // Если пользователь не заполнял этот response_id, проверяем права на форме
    $sql = "
        SELECT fa.access_level
        FROM form_access fa
        WHERE fa.form_id = (SELECT form_id FROM user_responses WHERE id = ?) AND fa.user_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $response_id, $current_user_id);
    $stmt->execute();
    $access_result = $stmt->get_result();
    $access = $access_result->fetch_assoc();

    if (!$access || !in_array($access['access_level'], ['view', 'edit', 'owner'])) {
        die('У вас нет прав для просмотра этого заполнения.');
    }

    // Получаем form_id и имя пользователя-заполнителя
    $sql = "SELECT ur.form_id, ur.user_id, u.full_name AS filler_name
            FROM user_responses ur 
            JOIN users u ON ur.user_id = u.id
            WHERE ur.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $response_id);
    $stmt->execute();
    $form_data = $stmt->get_result()->fetch_assoc();
    $form_id = $form_data['form_id'];
    $filler_name = $form_data['filler_name'];
}

// Получение информации о форме
$sql = "SELECT name FROM forms WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$form_result = $stmt->get_result();
$form = $form_result->fetch_assoc();

// Получение вопрос и ответов
$sql = "
    SELECT 
        q.question_text, 
        COALESCE(a.answer_text, rd.answer_value) AS answer_text,
        a.score,
        p.page_number
    FROM responses_details rd
    JOIN questions q ON rd.question_id = q.id
    LEFT JOIN answers a ON rd.answer_id = a.id
    JOIN pages p ON q.page_id = p.id
    WHERE rd.response_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $response_id);
$stmt->execute();
$answers_result = $stmt->get_result();

// Получение общего балла
$total_score = 0;
while ($answer = $answers_result->fetch_assoc()) {
    $total_score += $answer['score'] ?? 0;
}

// Получение правил для формы
$sql = "SELECT * FROM rules WHERE form_id = ? ORDER BY priority DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$rules_result = $stmt->get_result();
$rules = $rules_result->fetch_all(MYSQLI_ASSOC);

// Получение баллов по страницам
$sql = "
    SELECT 
        p.id AS page_id, 
        p.page_number, 
        COALESCE(SUM(a.score), 0) AS page_score
    FROM pages p
    LEFT JOIN questions q ON p.id = q.page_id
    LEFT JOIN responses_details rd ON q.id = rd.question_id
    LEFT JOIN answers a ON rd.answer_id = a.id
    WHERE p.form_id = ? AND rd.response_id = ?
    GROUP BY p.id, p.page_number
    ORDER BY p.page_number ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $form_id, $response_id);
$stmt->execute();
$page_scores_result = $stmt->get_result();
$page_scores = $page_scores_result->fetch_all(MYSQLI_ASSOC);

// Определение рекомендаций на основе правил
$recommendations = [];
foreach ($rules as $rule) {
    if ($rule['scope'] === 'form' && $total_score >= $rule['score_min'] && $total_score <= $rule['score_max']) {
        $recommendations[] = [
            'text' => $rule['result_text'],
            'diagnosis_id' => $rule['diagnosis_id']
        ];
    } elseif ($rule['scope'] === 'page') {
        foreach ($page_scores as $page_score) {
            if ($page_score['page_id'] == $rule['page_id'] && $page_score['page_score'] >= $rule['score_min'] && $page_score['page_score'] <= $rule['score_max']) {
                $recommendations[] = [
                    'text' => $rule['result_text'],
                    'diagnosis_id' => $rule['diagnosis_id']
                ];
            }
        }
    }
}

// Получение всех диагнозов
$diagnoses_result = $conn->query("SELECT * FROM diagnoses");
$diagnoses = [];
while ($diagnosis = $diagnoses_result->fetch_assoc()) {
    $diagnoses[$diagnosis['id']] = $diagnosis['name'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Заполнение формы <?php echo htmlspecialchars($form['name']); ?> пользователем <?php echo htmlspecialchars($filler_name); ?></title>
</head>
<body>
    <!-- Измененный заголовок -->
    <h1>Заполнение формы <?php echo htmlspecialchars($form['name']); ?> пользователем <?php echo htmlspecialchars($filler_name); ?></h1>
    
    <table border="1">
        <tr>
            <th>Страница</th>
            <th>Вопрос</th>
            <th>Ответ</th>
            <th>Баллы</th>
        </tr>
        <?php
        $answers_result->data_seek(0); // Сброс указателя результата
        while ($answer = $answers_result->fetch_assoc()):
        ?>
            <tr>
                <td><?php echo htmlspecialchars($answer['page_number']); ?></td>
                <td><?php echo htmlspecialchars($answer['question_text']); ?></td>
                <td><?php echo htmlspecialchars($answer['answer_text']); ?></td>
                <td><?php echo $answer['score'] ?? '-'; ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
    <h2>Общий балл: <?php echo $total_score; ?></h2>

    <!-- Отображение рекомендаций и диагнозов -->
    <?php if (!empty($recommendations)): ?>
        <h2>Рекомендации</h2>
        <?php foreach ($recommendations as $recommendation): ?>
            <div class="recommendation">
                <p><?php echo nl2br(htmlspecialchars($recommendation['text'])); ?></p>
                <p><strong>Диагноз:</strong> <?php echo htmlspecialchars($diagnoses[$recommendation['diagnosis_id']]); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if($_COOKIE['role'] === 'user'): ?>
        <a href="user_dashboard.php">Назад в панель пользователя</a>
    <?php else: ?>
        <a href="view_results.php?form_id=<?php echo $form_id; ?>">Назад к результатам формы</a>
    <?php endif; ?>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>