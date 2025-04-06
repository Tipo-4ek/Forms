<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Проверка авторизации
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

// Проверка наличия ID ответа
if (!isset($_GET['response_id'])) {
    header("Location: index.php");
    exit;
}

$response_id = (int)$_GET['response_id'];
$user_id = (int)$_COOKIE['user_id'];

// Проверяем, принадлежит ли ответ текущему пользователю
$stmt = $conn->prepare("SELECT ur.*, f.title as form_title 
                         FROM user_responses ur 
                         JOIN forms f ON ur.form_id = f.id 
                         WHERE ur.id = ? AND ur.user_id = ?");
$stmt->bind_param("ii", $response_id, $user_id);
$stmt->execute();
$response_data = $stmt->get_result()->fetch_assoc();

// Если ответ не найден или не принадлежит текущему пользователю
if (!$response_data) {
    header("Location: index.php");
    exit;
}

// Получаем все результаты по этому ответу
$stmt = $conn->prepare("SELECT * FROM results WHERE response_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $response_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Получаем общий балл
$total_score = $response_data['total_score'] ?? 0;

// Получаем баллы по страницам
$stmt = $conn->prepare("SELECT ps.*, p.title as page_title 
                         FROM page_scores ps 
                         JOIN pages p ON ps.page_id = p.id 
                         WHERE ps.response_id = ? 
                         ORDER BY p.page_number ASC");
$stmt->bind_param("i", $response_id);
$stmt->execute();
$page_scores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Получаем детали всех ответов пользователя
$stmt = $conn->prepare("
    SELECT rd.*, q.text as question_text, q.type as question_type, 
           a.text as answer_text, a.score as answer_score,
           p.title as page_title, p.page_number
    FROM responses_details rd
    JOIN questions q ON rd.question_id = q.id
    LEFT JOIN answers a ON rd.answer_id = a.id
    JOIN pages p ON q.page_id = p.id
    WHERE rd.response_id = ?
    ORDER BY p.page_number ASC, q.id ASC
");
$stmt->bind_param("i", $response_id);
$stmt->execute();
$answers_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Группируем ответы по страницам
$answers_by_page = [];
foreach ($answers_details as $answer) {
    $page_number = $answer['page_number'];
    if (!isset($answers_by_page[$page_number])) {
        $answers_by_page[$page_number] = [
            'title' => $answer['page_title'],
            'answers' => []
        ];
    }
    $answers_by_page[$page_number]['answers'][] = $answer;
}

include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2>Результаты формы "<?php echo htmlspecialchars($response_data['form_title']); ?>"</h2>
                </div>
                <div class="card-body">
                    <!-- Отображение общего балла -->
                    <div class="alert alert-info">
                        <h4>Общий балл: <?php echo $total_score; ?></h4>
                    </div>

                    <!-- Отображение результатов -->
                    <?php if (count($results) > 0): ?>
                        <div class="results-section mb-4">
                            <h3>Ваши результаты</h3>
                            <?php foreach ($results as $result): ?>
                                <div class="result-card p-3 mb-3 border rounded">
                                    <?php echo nl2br(htmlspecialchars($result['result_text'])); ?>
                                </div>
                            <?php endforeach; ?>
                        </div> <?php endif; ?>

<!-- Отображение баллов по страницам -->
<?php if (count($page_scores) > 0): ?>
    <div class="page-scores-section mb-4">
        <h3>Баллы по разделам</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Раздел</th>
                    <th>Баллы</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($page_scores as $page_score): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($page_score['page_title'] ?? "Страница {$page_score['page_id']}"); ?></td>
                        <td><?php echo $page_score['score']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Детальное отображение всех ответов -->
<div class="answers-section">
    <h3>Ваши ответы</h3>
    <div class="accordion" id="answersAccordion">
        <?php foreach ($answers_by_page as $page_number => $page_data): ?>
            <div class="card">
                <div class="card-header" id="heading<?php echo $page_number; ?>">
                    <h2 class="mb-0">
                        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapse<?php echo $page_number; ?>" aria-expanded="true" aria-controls="collapse<?php echo $page_number; ?>">
                            <?php echo htmlspecialchars($page_data['title'] ?? "Страница {$page_number}"); ?>
                        </button>
                    </h2>
                </div>

                <div id="collapse<?php echo $page_number; ?>" class="collapse" aria-labelledby="heading<?php echo $page_number; ?>" data-parent="#answersAccordion">
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Вопрос</th>
                                    <th>Ответ</th>
                                    <th>Баллы</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_data['answers'] as $answer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($answer['question_text']); ?></td>
                                        <td>
                                            <?php if ($answer['question_type'] == 'text' || $answer['question_type'] == 'textarea'): ?>
                                                <?php echo nl2br(htmlspecialchars($answer['answer_value'])); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($answer['answer_text'] ?? $answer['answer_value']); ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo $answer['answer_score'] ?? 0; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">Вернуться на главную</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>