<?php
// Проверка авторизации и роли
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

$form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 0;
$user_id = $_COOKIE['user_id'];

// Проверка прав доступа к форме
$sql = "
    SELECT 1 
    FROM forms f
    LEFT JOIN form_access fa ON f.id = fa.form_id
    WHERE f.id = ? AND (f.created_by = ? OR fa.user_id = ?)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $form_id, $user_id, $user_id);
$stmt->execute();
$access_result = $stmt->get_result();

if ($access_result->num_rows === 0) {
    die('У вас нет доступа к этой форме');
}

// Включение логирования ошибок
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

if ($form_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// Получение информации о форме
$sql = "SELECT name, description FROM forms WHERE id = ? AND created_by = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $form_id, $_COOKIE['user_id']);
$stmt->execute();
$form_result = $stmt->get_result();
$form = $form_result->fetch_assoc();

if (!$form) {
    header("Location: dashboard.php");
    exit;
}

// Проверка структуры таблицы questions
$questions_columns = [];
$result = $conn->query("DESCRIBE questions");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $questions_columns[$row['Field']] = true;
    }
}

// Проверка структуры таблицы answers
$answers_columns = [];
$result = $conn->query("DESCRIBE answers");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $answers_columns[$row['Field']] = true;
    }
}

// Определяем имена полей в таблице questions
$question_text_field = isset($questions_columns['question_text']) ? 'question_text' : 'text';
$question_type_field = isset($questions_columns['question_type']) ? 'question_type' : 'type';

// Определяем имена полей в таблице answers
$answer_text_field = isset($answers_columns['answer_text']) ? 'answer_text' : 'text';
$answer_score_field = isset($answers_columns['score']) ? 'score' : 'value';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];

    // Обновление формы
    $stmt = $conn->prepare("UPDATE forms SET name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $description, $form_id);
    if (!$stmt->execute()) {
        error_log("Ошибка при обновлении формы: " . $stmt->error);
    }

    // Обработка обновления вопросов
    if (isset($_POST['questions'])) {
        foreach ($_POST['questions'] as $question_id => $question) {
            $question_text = $question['text'];
            $question_type = $question['type'];

            // Обновление вопроса - используем правильные имена полей
            $update_question_sql = "UPDATE questions SET $question_text_field = ?, $question_type_field = ? WHERE id = ?";
            $stmt = $conn->prepare($update_question_sql);
            $stmt->bind_param("ssi", $question_text, $question_type, $question_id);
            if (!$stmt->execute()) {
                error_log("Ошибка при обновлении вопроса ID $question_id: " . $stmt->error);
            }
            
            // Обработка обновления ответов
            if (isset($question['answers'])) {
                foreach ($question['answers'] as $answer_id => $answer) {
                    $answer_text = $answer['text'];
                    $score = $answer['score'];

                    // Обновление ответа - используем правильные имена полей
                    $update_answer_sql = "UPDATE answers SET $answer_text_field = ?, $answer_score_field = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_answer_sql);
                    $stmt->bind_param("sii", $answer_text, $score, $answer_id);
                    if (!$stmt->execute()) {
                        error_log("Ошибка при обновлении ответа ID $answer_id: " . $stmt->error);
                    }
                }
            }
        }
    }

    // Обработка обновления правил
    if (isset($_POST['rules'])) {
        foreach ($_POST['rules'] as $rule_id => $rule) {
            $scope = $rule['scope'];
            $page_id = isset($rule['page_id']) ? $rule['page_id'] : NULL;
            $score_min = $rule['score_min'];
            $score_max = $rule['score_max'];
            $result_text = $rule['result_text'];
            $priority = $rule['priority'];
            $diagnosis_id = $rule['diagnosis_id'];

            // Обновление правила
            $update_rule_sql = "UPDATE rules SET scope = ?, page_id = ?, score_min = ?, score_max = ?, result_text = ?, priority = ?, diagnosis_id = ? WHERE id = ?";
            $stmt = $conn->prepare($update_rule_sql);
            $stmt->bind_param("siiisiii", $scope, $page_id, $score_min, $score_max, $result_text, $priority, $diagnosis_id, $rule_id);
            if (!$stmt->execute()) {
                error_log("Ошибка при обновлении правила ID $rule_id: " . $stmt->error);
            }
        }
    }

    header("Location: dashboard.php");
    exit;
}

// Получение вопросов
$sql = "SELECT * FROM questions WHERE form_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$questions_result = $stmt->get_result();

// Проверка на существование таблицы rules
$check_rules_table = $conn->query("SHOW TABLES LIKE 'rules'");
$rules_table_exists = $check_rules_table->num_rows > 0;

$rules = [];
// Только если таблица существует
if ($rules_table_exists) {
    // Получение правил формы
    $sql = "SELECT * FROM rules WHERE form_id = ? ORDER BY priority DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $form_id);
    $stmt->execute();
    $rules_result = $stmt->get_result();
    
    while ($rule = $rules_result->fetch_assoc()) {
        $rules[] = $rule;
    }
}

// Проверка на существование таблицы pages
$check_pages_table = $conn->query("SHOW TABLES LIKE 'pages'");
$pages_table_exists = $check_pages_table->num_rows > 0;

$pages = [];
// Только если таблица существует
if ($pages_table_exists) {
    // Получение страниц формы
    $sql = "SELECT * FROM pages WHERE form_id = ? ORDER BY page_number ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $form_id);
    $stmt->execute();
    $pages_result = $stmt->get_result();
    
    while ($page = $pages_result->fetch_assoc()) {
        $pages[] = $page;
    }
}

// Получение всех диагнозов
$diagnoses_result = $conn->query("SELECT * FROM diagnoses");
$diagnoses = $diagnoses_result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Редактирование формы <?php echo htmlspecialchars($form['name']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .rule-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .delete-btn {
            color: #dc3545;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Редактирование формы: <?php echo htmlspecialchars($form['name']); ?></h1>
        
        <form method="post" action="edit_form.php?form_id=<?php echo $form_id; ?>">
            <div class="form-group">
                <label>Название формы:</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($form['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Описание формы:</label>
                <textarea name="description" class="form-control"><?php echo htmlspecialchars($form['description']); ?></textarea>
            </div>
            
            <h2 class="mt-4">Вопросы</h2>
            
            <?php 
            // Сбрасываем позицию результата, чтобы начать сначала
            $questions_result->data_seek(0);
            
            while ($question = $questions_result->fetch_assoc()): 
                // Получаем текст вопроса из нужного поля
                $question_text = isset($question[$question_text_field]) ? $question[$question_text_field] : '';
                $question_type = isset($question[$question_type_field]) ? $question[$question_type_field] : '';
            ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Вопрос</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Текст вопроса:</label>
                            <input type="text" name="questions[<?php echo $question['id']; ?>][text]" class="form-control" value="<?php echo htmlspecialchars($question_text); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Тип вопроса:</label>
                            <select name="questions[<?php echo $question['id']; ?>][type]" class="form-control">
                                <option value="short_text" <?php if ($question_type == 'short_text') echo 'selected'; ?>>Короткий текст</option>
                                <option value="long_text" <?php if ($question_type == 'long_text') echo 'selected'; ?>>Длинный текст</option>
                                <option value="radio" <?php if ($question_type == 'radio') echo 'selected'; ?>>Один вариант</option>
                                <option value="checkbox" <?php if ($question_type == 'checkbox') echo 'selected'; ?>>Несколько вариантов</option>
                                <option value="select" <?php if ($question_type == 'select') echo 'selected'; ?>>Выпадающий список</option>
                                <option value="scale" <?php if ($question_type == 'scale') echo 'selected'; ?>>Шкала</option>
                            </select>
                        </div>
                        
                        <?php
                        // Получение ответов
                        $sql = "SELECT * FROM answers WHERE question_id = ?";
                        $stmt_answers = $conn->prepare($sql);
                        $stmt_answers->bind_param("i", $question['id']);
                        $stmt_answers->execute();
                        $answers_result = $stmt_answers->get_result();
                        
                        if ($answers_result->num_rows > 0):
                        ?>
                        <h4>Ответы</h4>
                        
                        <div class="answers-container">
                            <?php while ($answer = $answers_result->fetch_assoc()): 
                                // Получаем текст ответа и баллы из нужных полей
                                $answer_text = isset($answer[$answer_text_field]) ? $answer[$answer_text_field] : '';
                                $answer_score = isset($answer[$answer_score_field]) ? $answer[$answer_score_field] : 0;
                            ?>
                                <div class="row mb-2">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label>Текст ответа:</label>
                                            <input type="text" name="questions[<?php echo $question['id']; ?>][answers][<?php echo $answer['id']; ?>][text]" class="form-control" value="<?php echo htmlspecialchars($answer_text); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Баллы:</label>
                                            <input type="number" name="questions[<?php echo $question['id']; ?>][answers][<?php echo $answer['id']; ?>][score]" class="form-control" value="<?php echo $answer_score; ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                            <div class="alert alert-info">Для этого вопроса нет вариантов ответов.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
            
            <?php if ($rules_table_exists && $pages_table_exists): ?>
            <h2 class="mt-4">Правила логики</h2>
            
            <div id="rules-container">
                <?php foreach ($rules as $index => $rule): ?>
                    <div class="rule-card" id="rule-<?php echo $index; ?>">
                        <h3>Правило <?php echo $index + 1; ?></h3>
                        
                        <div class="form-group">
                            <label>Применить правило к:</label>
                            <select name="rules[<?php echo $rule['id']; ?>][scope]" class="form-control" onchange="handleRuleScopeChange(this, <?php echo $index; ?>)" required>
                                <option value="form" <?php if ($rule['scope'] == 'form') echo 'selected'; ?>>Всей форме</option>
                                <option value="page" <?php if ($rule['scope'] == 'page') echo 'selected'; ?>>Конкретной странице</option>
                            </select>
                        </div>
                        <div id="rule-scope-container-<?php echo $index; ?>">
                            <?php if ($rule['scope'] == 'page'): ?>
                                <div class="form-group">
                                    <label>Выберите страницу:</label>
                                    <select name="rules[<?php echo $rule['id']; ?>][page_id]" class="form-control" required>
                                        <option value="">-- Выберите страницу --</option>
                                        <?php foreach ($pages as $page): ?>
                                            <option value="<?php echo $page['id']; ?>" <?php if ($rule['page_id'] == $page['id']) echo 'selected'; ?>>
                                                Страница <?php echo $page['page_number']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label>Диапазон баллов:</label>
                            <div class="row">
                                <div class="col">
                                    <label>От:</label>
                                    <input type="number" name="rules[<?php echo $rule['id']; ?>][score_min]" class="form-control" value="<?php echo $rule['score_min']; ?>" required>
                                </div>
                                <div class="col">
                                    <label>До:</label>
                                    <input type="number" name="rules[<?php echo $rule['id']; ?>][score_max]" class="form-control" value="<?php echo $rule['score_max']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Текст результата:</label>
                            <textarea name="rules[<?php echo $rule['id']; ?>][result_text]" class="form-control" rows="3" required><?php echo htmlspecialchars($rule['result_text']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Приоритет:</label>
                            <input type="number" name="rules[<?php echo $rule['id']; ?>][priority]" class="form-control" value="<?php echo $rule['priority']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Диагноз:</label>
                            <select name="rules[<?php echo $rule['id']; ?>][diagnosis_id]" class="form-control" required>
                                <?php foreach ($diagnoses as $diagnosis): ?>
                                    <option value="<?php echo $diagnosis['id']; ?>" <?php if ($diagnosis['id'] == $rule['diagnosis_id']) echo 'selected'; ?>><?php echo htmlspecialchars($diagnosis['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="button" class="delete-btn" onclick="deleteRule(<?php echo $index; ?>)">Удалить правило</button>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="btn btn-info mt-3 mb-4" onclick="addRule()">Добавить правило</button>
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary mt-4">Сохранить изменения</button>
        </form>
    </div>

    <script>
    // Обработка изменения области применения правила (вся форма или конкретная страница)
    function handleRuleScopeChange(selectElement, ruleIndex) {
        const scope = selectElement.value;
        const scopeContainer = document.getElementById(`rule-scope-container-${ruleIndex}`);
        
        if (scope === 'page') {
            // Создаем HTML для выбора страницы
            scopeContainer.innerHTML = `
                <div class="form-group">
                    <label>Выберите страницу:</label>
                    <select name="rules[${ruleIndex}][page_id]" class="form-control" required>
                        <option value="">-- Выберите страницу --</option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo $page['id']; ?>">Страница <?php echo $page['page_number']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            `;
        } else {
            scopeContainer.innerHTML = ''; // Для всей формы не нужно выбирать страницу
        }
    }

    // Удаление правила логики
    function deleteRule(ruleIndex) {
        const ruleElement = document.getElementById(`rule-${ruleIndex}`);
        if (ruleElement) {
            ruleElement.remove();
        }
    }

    // Добавление правила логики
    function addRule() {
        const rulesContainer = document.getElementById('rules-container');
        const ruleIndex = rulesContainer.children.length;
        
        const ruleCard = document.createElement('div');
        ruleCard.className = 'rule-card';
        ruleCard.id = `rule-${ruleIndex}`;
        
        ruleCard.innerHTML = `
            <h3>Правило ${ruleIndex + 1}</h3>
            
            <div class="form-group">
                <label>Применить правило к:</label>
                <select name="rules[new_${ruleIndex}][scope]" class="form-control" onchange="handleRuleScopeChange(this, ${ruleIndex})" required>
                    <option value="form">Всей форме</option>
                    <option value="page">Конкретной странице</option>
                </select>
            </div>
            
            <div id="rule-scope-container-${ruleIndex}">
                <!-- Здесь будет выбор страницы, если выбран scope = page -->
            </div>
            
            <div class="form-group">
                <label>Диапазон баллов:</label>
                <div class="row">
                    <div class="col">
                        <label>От:</label>
                        <input type="number" name="rules[new_${ruleIndex}][score_min]" class="form-control" value="0" required>
                    </div>
                    <div class="col">
                        <label>До:</label>
                        <input type="number" name="rules[new_${ruleIndex}][score_max]" class="form-control" value="10" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Текст результата:</label>
                <textarea name="rules[new_${ruleIndex}][result_text]" class="form-control" rows="3" placeholder="Текст, который увидит пользователь при попадании в диапазон баллов" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Диагноз:</label>
                <select name="rules[new_${ruleIndex}][diagnosis_id]" class="form-control" required>
                    <?php foreach ($diagnoses as $diagnosis): ?>
                        <option value="<?php echo $diagnosis['id']; ?>"><?php echo htmlspecialchars($diagnosis['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Приоритет:</label>
                <input type="number" name="rules[new_${ruleIndex}][priority]" class="form-control" value="1" required>
            </div>
            
            <button type="button" class="delete-btn" onclick="deleteRule(${ruleIndex})">Удалить правило</button>
        `;
        
        rulesContainer.appendChild(ruleCard);
    }
    </script>
</body>
</html>