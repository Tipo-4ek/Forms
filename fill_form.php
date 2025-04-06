<?php
// Буферизация вывода помогает избежать проблем с заголовками
ob_start();

// Включаем отображение ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Проверка авторизации
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_COOKIE['user_id'];

// Создаем лог-файл для отладки
// $debug_log = fopen('form_debug.log', 'a');
// function debug_log($message) {
//     global $debug_log;
//     fwrite($debug_log, date('[Y-m-d H:i:s] ') . $message . "\n");
// }

// debug_log("Начало выполнения скрипта");
// debug_log("User ID: " . $user_id);
// debug_log("GET данные: " . print_r($_GET, true));
// debug_log("POST данные: " . print_r($_POST, true));
// debug_log("COOKIE данные: " . print_r($_COOKIE, true));

require 'database.php';
// debug_log("База данных подключена");

// Проверка наличия form_id
if (!isset($_GET['form_id'])) {
    // debug_log("Отсутствует form_id");
    die('Необходимо указать ID формы');
}

$form_id = (int)$_GET['form_id'];
// debug_log("Form ID: " . $form_id);

// Получаем response_id из URL если есть
$response_id = isset($_GET['response_id']) ? (int)$_GET['response_id'] : null;
// debug_log("Response ID из URL: " . ($response_id ? $response_id : "отсутствует"));

// Проверка response_id, если он передан
if ($response_id) {
    $sql = "SELECT id FROM user_responses WHERE id = ? AND user_id = ? AND form_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $response_id, $user_id, $form_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // debug_log("Ошибка! response_id не найден в базе: " . $response_id);
        die('Ошибка: Данные формы не найдены. Попробуйте начать сначала.');
    }
    
    // debug_log("Response ID подтвержден: " . $response_id);
}

// Получение информации о форме
try {
    $sql = "SELECT name, description FROM forms WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $form_id);
    $stmt->execute();
    $form_result = $stmt->get_result();

    if ($form_result->num_rows === 0) {
        // debug_log("Форма не найдена: " . $form_id);
        die('Форма не найдена');
    }

    $form = $form_result->fetch_assoc();
    // debug_log("Форма найдена: " . $form['name']);
} catch (Exception $e) {
    // debug_log("Ошибка при получении информации о форме: " . $e->getMessage());
    die('Ошибка: ' . $e->getMessage());
}

// // Если response_id не передан, проверяем, не заполнял ли пользователь уже эту форму
// if (!$response_id) {
//     try {
//         $sql = "SELECT id FROM user_responses WHERE user_id = ? AND form_id = ?";
//         $stmt = $conn->prepare($sql);
//         $stmt->bind_param("ii", $user_id, $form_id);
//         $stmt->execute();
//         $existing_response = $stmt->get_result();

//         if ($existing_response->num_rows > 0) {
//             $response_id = $existing_response->fetch_assoc()['id'];
//             // debug_log("Пользователь уже заполнил форму. Перенаправление на результат.");
//             header("Location: view_individual_result.php?response_id=" . $response_id);
//             exit;
//         }
//     } catch (Exception $e) {
//         //debug_log("Ошибка при проверке существующих ответов: " . $e->getMessage());
//         die('Ошибка: ' . $e->getMessage());
//     }
// }

// Получение всех страниц формы
try {
    $sql = "SELECT id, page_number FROM pages WHERE form_id = ? ORDER BY page_number";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $form_id);
    $stmt->execute();
    $pages_result = $stmt->get_result();
    $pages = [];

    while ($page = $pages_result->fetch_assoc()) {
        $pages[] = $page;
    }

    if (count($pages) === 0) {
        // debug_log("У формы нет страниц");
        die('У формы нет страниц');
    }
    
    // debug_log("Количество страниц формы: " . count($pages));
} catch (Exception $e) {
    // debug_log("Ошибка при получении страниц формы: " . $e->getMessage());
    die('Ошибка: ' . $e->getMessage());
}

// Получаем текущую страницу (по умолчанию первая)
$current_page_index = isset($_GET['page']) ? (int)$_GET['page'] : 0;
if ($current_page_index < 0 || $current_page_index >= count($pages)) {
    $current_page_index = 0;
}

$current_page = $pages[$current_page_index];
// debug_log("Текущая страница: " . ($current_page_index + 1) . " (ID: " . $current_page['id'] . ")");

// Получение вопросов текущей страницы
try {
    $sql = "SELECT id, question_text, question_type FROM questions WHERE form_id = ? AND page_id = ? ORDER BY id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $form_id, $current_page['id']);
    $stmt->execute();
    $questions_result = $stmt->get_result();
    $questions = [];

    while ($question = $questions_result->fetch_assoc()) {
        // Если тип вопроса предполагает выбор из вариантов, загрузим варианты ответов
        if (in_array($question['question_type'], ['radio', 'checkbox', 'select', 'scale'])) {
            $sql = "SELECT id, answer_text, score FROM answers WHERE question_id = ? ORDER BY id";
            $answers_stmt = $conn->prepare($sql);
            $answers_stmt->bind_param("i", $question['id']);
            $answers_stmt->execute();
            $answers_result = $answers_stmt->get_result();
            $question['answers'] = [];

            while ($answer = $answers_result->fetch_assoc()) {
                $question['answers'][] = $answer;
            }
        }
        
        $questions[] = $question;
    }
    
    // debug_log("Количество вопросов на текущей странице: " . count($questions));
} catch (Exception $e) {
    // debug_log("Ошибка при получении вопросов: " . $e->getMessage());
    die('Ошибка: ' . $e->getMessage());
}

// Получение всех диагнозов
$diagnoses_result = $conn->query("SELECT * FROM diagnoses");

// Обработка отправки формы
// debug_log("Проверка отправки формы");
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    echo "<script>console.log('Пришел POST');</script>";
    // debug_log("Форма была отправлена");
    
    // Проверяем, была ли отправлена последняя страница
    $is_last_page = $current_page_index === count($pages) - 1;
    // debug_log("Последняя страница: " . ($is_last_page ? "Да" : "Нет"));
    
    try {
        // Если response_id еще не создан (первая страница)
        if (!$response_id) {
            // debug_log("Создание новой записи user_responses");
            $sql = "INSERT INTO user_responses (user_id, form_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $form_id);
            $stmt->execute();
            $response_id = $conn->insert_id;
            
            error_log('Создана запись response_id: ' . $response_id, 3, 'error_log.txt');
        }

        // Обрабатываем ответы на текущей странице
        error_log('Начало сохранения ответов для response_id: ' . $response_id, 3, 'error_log.txt');
        foreach ($questions as $question) {
            $question_id = $question['id'];
            error_log('Обработка вопроса ID: ' . $question_id . ', тип: ' . $question['question_type'], 3, 'error_log.txt');
            
            switch (strtolower(trim($question['question_type']))) {
                case 'short_text':
                case 'textarea': 
                    if (isset($_POST['question_' . $question_id])) {
                        $answer_value = $_POST['question_' . $question_id];
                        error_log('Сохранение текстового ответа: ' . $answer_value, 3, 'error_log.txt');
                        
                        $sql = "INSERT INTO responses_details (response_id, question_id, answer_id, answer_value) 
                                VALUES (?, ?, 0, ?)";
                        $stmt = $conn->prepare($sql);
                        error_log('AFTER 1 тесктового ответа: response_id -> ' . $response_id . " question_id -> " . $question_id . " asnwer_id -> " . $answer_id . " answer_value -> " . $answer_value , 3, 'error_log.txt');
                        error_log('AFTER 2 тесктового ответа: ' . var_dump($stmt), 3, 'error_log.txt');
                        $stmt->bind_param("iis", $response_id, $question_id, $answer_value);
                        $stmt->execute();
                    }
                    break;
                    
                case 'radio':
                case 'select':
                    if (isset($_POST['question_' . $question_id])) {
                        $answer_id = (int)$_POST['question_' . $question_id];
                        // debug_log("Сохранение radio/select ответа, answer_id: " . $answer_id);
                        
                        $sql = "INSERT INTO responses_details (response_id, question_id, answer_id) 
                                VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iii", $response_id, $question_id, $answer_id);
                        $stmt->execute();
                    }
                    break;
                    
                    case 'checkbox':
                        if (isset($_POST['question_' . $question_id]) && is_array($_POST['question_' . $question_id])) {
                            // debug_log("Сохранение checkbox ответов: " . implode(", ", $_POST['question_' . $question_id]));
                            
                            foreach ($_POST['question_' . $question_id] as $answer_id) {
                                $answer_id = (int)$answer_id;
                                
                                $sql = "INSERT INTO responses_details (response_id, question_id, answer_id) 
                                        VALUES (?, ?, ?)";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("iii", $response_id, $question_id, $answer_id);
                                $stmt->execute();
                            }
                        }
                        break;
                        
                    case 'scale':
                        if (isset($_POST['question_' . $question_id])) {
                            $answer_id = (int)$_POST['question_' . $question_id];
                            // debug_log("Сохранение scale ответа, answer_id: " . $answer_id);
                            
                            $sql = "INSERT INTO responses_details (response_id, question_id, answer_id) 
                                    VALUES (?, ?, ?)";
                            $stmt->prepare($sql);
                            $stmt->bind_param("iii", $response_id, $question_id, $answer_id);
                            $stmt->execute();
                        }
                        break;
                }
            }
            error_log('Ответы сохранены', 3, 'error_log.txt');
    
        // Если это последняя страница, вычисляем и сохраняем общий балл
        if ($is_last_page) {
            error_log('Подсчет общего балла', 3, 'error_log.txt');
            // Вычисляем общий балл
            $sql = "SELECT COALESCE(SUM(a.score), 0) as total_score
                    FROM responses_details rd
                    LEFT JOIN answers a ON rd.answer_id = a.id
                    WHERE rd.response_id = ? AND rd.answer_id > 0";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $response_id);
            $stmt->execute();
            $score_result = $stmt->get_result();
            $total_score = $score_result->fetch_assoc()['total_score'] ?? 0;
            
            error_log('Общий балл: ' . $total_score, 3, 'error_log.txt');
            
            // Обновляем запись в user_responses
            $sql = "UPDATE user_responses SET total_score = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $total_score, $response_id);
            $stmt->execute();
            
            // Находим соответствующее правило
            $sql = "SELECT result_text FROM rules 
                    WHERE form_id = ? AND scope = 'form' 
                    AND ? BETWEEN score_min AND score_max
                    ORDER BY priority DESC
                    LIMIT 1";
            $stmt->prepare($sql);
            $stmt->bind_param("ii", $form_id, $total_score);
            $stmt->execute();
            $rule_result = $stmt->get_result();
            
            if ($rule_result->num_rows > 0) {
                $result_text = $rule_result->fetch_assoc()['result_text'];
                error_log('Найдено правило с текстом: ' . $result_text, 3, 'error_log.txt');
            }

            // Перенаправляем на страницу с результатом
            error_log('Перенаправление на страницу результата', 3, 'error_log.txt');
            header("Location: view_individual_result.php?response_id=" . $response_id);
            exit;
        } else {
            // Если это не последняя страница, переходим к следующей
            error_log('Переход на следующую страницу: ' . ($current_page_index + 1), 3, 'error_log.txt');
            header("Location: fill_form.php?form_id=" . $form_id . "&page=" . ($current_page_index + 1) . "&response_id=" . $response_id);
            exit;
        }
    } catch (Exception $e) {
        error_log('Ошибка при обработке формы: ' . $e->getMessage(), 3, 'error_log.txt');
        die('Ошибка: ' . $e->getMessage());
    }
}

// Определяем, является ли текущая страница последней
$is_last_page = $current_page_index === count($pages) - 1;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['name']); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <header class="mb-4">
                    <h1 class="text-center mb-3"><?php echo htmlspecialchars($form['name']); ?></h1>
                    <p class="lead text-center mb-4"><?php echo htmlspecialchars($form['description']); ?></p>
                    <div class="progress mb-3">
                        <div class="progress-bar bg-primary" role="progressbar" 
                             style="width: <?php echo (($current_page_index + 1) / count($pages)) * 100; ?>%" 
                             aria-valuenow="<?php echo (($current_page_index + 1) / count($pages)) * 100; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                    <p class="text-center text-muted small">Страница <?php echo $current_page_index + 1; ?> из <?php echo count($pages); ?></p>
                </header>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                    <?php foreach ($questions as $question): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><?php echo htmlspecialchars($question['question_text']); ?></h5>
                            </div>
                            <div class="card-body">
                                <?php switch ($question['question_type']):
                                    case 'text': ?>
                                        <div class="form-group">
                                            <input type="text" class="form-control" name="question_<?php echo $question['id']; ?>" required>
                                        </div>
                                        <?php break;
                                        
                                    case 'textarea':
                                    case 'short_text' ?>
                                        <div class="form-group">
                                            <textarea class="form-control" name="question_<?php echo $question['id']; ?>" rows="4" required></textarea>
                                        </div>
                                        <?php break;
                                        
                                    case 'radio': ?>
                                        <div class="form-group">
                                            <?php foreach ($question['answers'] as $answer): ?>
                                                <div class="custom-control custom-radio mb-2">
                                                    <input type="radio" id="radio_<?php echo $question['id']; ?>_<?php echo $answer['id']; ?>" 
                                                           name="question_<?php echo $question['id']; ?>" 
                                                           value="<?php echo $answer['id']; ?>" 
                                                           class="custom-control-input" required>
                                                    <label class="custom-control-label" for="radio_<?php echo $question['id']; ?>_<?php echo $answer['id']; ?>">
                                                        <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                    </label>
                                                </div><?php endforeach; ?>
                                        </div>
                                        <?php break;
                                        
                                    case 'checkbox': ?>
                                        <div class="form-group">
                                            <?php foreach ($question['answers'] as $answer): ?>
                                                <div class="custom-control custom-checkbox mb-2">
                                                    <input type="checkbox" id="checkbox_<?php echo $question['id']; ?>_<?php echo $answer['id']; ?>" 
                                                           name="question_<?php echo $question['id']; ?>[]" 
                                                           value="<?php echo $answer['id']; ?>" 
                                                           class="custom-control-input">
                                                    <label class="custom-control-label" for="checkbox_<?php echo $question['id']; ?>_<?php echo $answer['id']; ?>">
                                                        <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php break;
                                        
                                    case 'select': ?>
                                        <div class="form-group">
                                            <select name="question_<?php echo $question['id']; ?>" class="custom-select" required>
                                                <option value="">Выберите вариант</option>
                                                <?php foreach ($question['answers'] as $answer): ?>
                                                    <option value="<?php echo $answer['id']; ?>">
                                                        <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php break;
                                        
                                    case 'scale': ?>
                                        <div class="form-group scale-options">
                                            <div class="d-flex justify-content-between flex-wrap">
                                                <?php foreach ($question['answers'] as $answer): ?>
                                                    <div class="text-center mx-2 mb-3">
                                                        <div class="custom-control custom-radio">
                                                            <input type="radio" id="scale_<?php echo $question['id']; ?>_<?php echo $answer['id']; ?>" 
                                                                   name="question_<?php echo $question['id']; ?>" 
                                                                   value="<?php echo $answer['id']; ?>" 
                                                                   class="custom-control-input" required>
                                                            <label class="custom-control-label" for="scale_<?php echo $question['id']; ?>_<?php echo $answer['id']; ?>">
                                                                <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                            </label>
                                                        </div> </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php break;
                                endswitch; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    

                    <div class="d-flex justify-content-between mt-4">
                        <?php if ($current_page_index > 0): ?>
                            <a href="fill_form.php?form_id=<?php echo $form_id; ?>&page=<?php echo $current_page_index - 1; ?><?php echo $response_id ? '&response_id=' . $response_id : ''; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>Назад
                            </a>
                        <?php else: ?>
                            <div></div> <!-- Пустой div для сохранения выравнивания -->
                        <?php endif; ?>
                        
                        <button type="submit" name="submit" class="btn btn-primary px-4">
                            <?php echo $is_last_page ? 'Завершить' : 'Далее'; ?>
                            <?php if (!$is_last_page): ?>
                                <i class="fas fa-arrow-right ml-2"></i>
                            <?php endif; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-3 text-muted small">
            <p>&copy; <?php echo date('Y'); ?> Система тестирования</p>
        </div>
    </div>

    <!-- Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Bootstrap JS, Popper.js, и jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <?php // Закрываем файл логов
    // fclose($debug_log); ?>
</body>
</html>

<?php
// Завершаем буферизацию вывода
ob_end_flush();
?>