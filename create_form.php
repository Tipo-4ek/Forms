<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Проверка авторизации и роли
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

// Получение всех диагнозов
$diagnoses_result = $conn->query("SELECT * FROM diagnoses");

// Для отладки
// echo "<pre>"; print_r($_POST); echo "</pre>"; exit;

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $user_id = $_COOKIE['user_id'];
    
    // Начинаем транзакцию
    $conn->begin_transaction();
    
    try {
        // Сохранение формы
        $stmt = $conn->prepare("INSERT INTO forms (name, description, created_by, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $name, $description, $user_id);
        $stmt->execute();
        $form_id = $conn->insert_id;

        // Добавление уровня доступа "owner" для пользователя
        $stmt_access = $conn->prepare("INSERT INTO form_access (form_id, user_id, access_level) VALUES (?, ?, 'owner')");
        $stmt_access->bind_param("ii", $form_id, $user_id);
        $stmt_access->execute();
        
        // Создаем массив для хранения ID страниц
        $page_ids = [];
        
        // Сохранение страниц
        if (isset($_POST['pages']) && is_array($_POST['pages'])) {
            foreach ($_POST['pages'] as $page_index => $page) {
                $page_number = $page['page_number'];
                
                $stmt = $conn->prepare("INSERT INTO pages (form_id, page_number) VALUES (?, ?)");
                $stmt->bind_param("ii", $form_id, $page_number);
                $stmt->execute();
                
                $page_ids[$page_index] = $conn->insert_id;
            }
        }
        
        // Сохранение вопросов и вариантов ответов
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $page_index => $page_questions) {
                // Убедимся, что для этой страницы есть ID
                if (!isset($page_ids[$page_index])) {
                    continue;
                }
                
                $page_id = $page_ids[$page_index];
                
                foreach ($page_questions as $question_index => $question) {
                    $question_text = $question['text'];
                    $question_type = $question['type'];
                    
                    // Сохранение вопроса
                    $stmt = $conn->prepare("INSERT INTO questions (form_id, page_id, question_text, question_type) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiss", $form_id, $page_id, $question_text, $question_type);
                    $stmt->execute();
                    $question_id = $conn->insert_id;
                    
                    // Сохранение вариантов ответов для вопросов с фиксированными вариантами
                    if (in_array($question_type, ['radio', 'checkbox', 'select', 'scale'])) {
                        // Проверяем, есть ли варианты ответов
                        if (isset($question['options']) && is_array($question['options'])) {
                            foreach ($question['options'] as $option_index => $option_text) {
                                // Получаем соответствующий балл, если он есть
                                $score = 0;
                                if (isset($question['scores']) && is_array($question['scores']) && isset($question['scores'][$option_index])) {
                                    $score = (int)$question['scores'][$option_index];
                                }
                                
                                // Сохраняем вариант ответа
                                $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, score) VALUES (?, ?, ?)");
                                $stmt->bind_param("isi", $question_id, $option_text, $score);
                                $stmt->execute();
                            }
                        }
                    }
                } }
            }
            
            // Сохранение правил
            if (isset($_POST['rules']) && is_array($_POST['rules'])) {
                foreach ($_POST['rules'] as $rule_index => $rule) {
                    $scope = $rule['scope'];
                    $page_id = NULL;
                    
                    // Если правило относится к странице, найдем ID страницы
                    if ($scope == 'page' && isset($rule['page_id'])) {
                        $page_index = (int)$rule['page_id'];
                        if (isset($page_ids[$page_index])) {
                            $page_id = $page_ids[$page_index];
                        }
                    }
                    
                    $score_min = (int)$rule['score_min'];
                    $score_max = (int)$rule['score_max'];
                    $result_text = $rule['result_text'];
                    $priority = isset($rule['priority']) ? (int)$rule['priority'] : 0;
                    
                    $stmt = $conn->prepare("INSERT INTO rules (form_id, scope, page_id, score_min, score_max, result_text, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isiiisi", $form_id, $scope, $page_id, $score_min, $score_max, $result_text, $priority);
                    $stmt->execute();
                }
            }
            
            // Фиксируем транзакцию
            $conn->commit();
            
            // Перенаправление после успешного создания
            header("Location: dashboard.php");
            exit;
        } catch (Exception $e) {
            // В случае ошибки отменяем все изменения
            $conn->rollback();
            $error_message = "Произошла ошибка при создании формы: " . $e->getMessage();
            echo $error_message;
        }
    }
    ?>

<!DOCTYPE html>
<html>
<head>
    <title>Создание многостраничной формы</title>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        h1, h2, h3 {
            color: #333;
        }
        .page-card, .question-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .question-card {
            background-color: #fff;
            margin-top: 10px;
        }
        .add-btn, input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px 0;
        }
        .answer-item {
            margin: 5px 0;
            padding: 5px;
            background-color: #f1f1f1;
            border-radius: 3px;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            padding: 5px 10px;
        }
        .rules-section {
            background-color: #e9f7ef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>

</head>
<body>
    <h1>Создание многостраничной формы</h1>
    <form method="post" action="create_form.php">
        <div class="form-group">
            <label for="name">Название формы:</label>
            <input type="text" id="name" name="name" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="description">Описание формы:</label>
            <textarea id="description" name="description" class="form-control"></textarea>
        </div>
        
        <div id="pages-container">
            <h2>Страницы формы</h2>
            
            <div class="page-card" id="page-0">
                <h3>Страница 1</h3>
                <input type="hidden" name="pages[0][page_number]" value="1">
                
                <div class="form-group">
                    <label>Вопросы на этой странице:</label>
                    <div class="questions-container" id="questions-container-0">
                        <!-- Здесь будут добавляться вопросы для этой страницы -->
                    </div>
                    <button type="button" class="add-btn" onclick="addQuestion(0)">+ Добавить вопрос</button>
                </div>
            </div>
        </div>
        
        <button type="button" class="add-btn" onclick="addPage()">+ Добавить страницу</button>
        
        <div class="rules-section">
            <h2>Правила результатов</h2>
            <div id="rules-container">
                <!-- Здесь будут добавляться правила для оценки результатов -->
            </div>
            <button type="button" class="add-btn" onclick="addRule()">+ Добавить правило</button>
        </div>
        
        <input type="submit" value="Создать форму" style="margin-top: 20px;">
    </form>

    
    <script>
    let pageCount = 1;
    let questionCountPerPage = {};
    questionCountPerPage[0] = 0;
    
    // Функция добавления новой страницы
    function addPage() {
        const pageIndex = pageCount;
        pageCount++;
        
        // Создаем новый контейнер для страницы
        const pageCard = document.createElement('div');
        pageCard.className = 'page-card';
        pageCard.id = `page-${pageIndex}`;
        
        // Формируем HTML для новой страницы
        pageCard.innerHTML = `
            <h3>Страница ${pageCount}</h3>
            <input type="hidden" name="pages[${pageIndex}][page_number]" value="${pageCount}">
            
            <div class="form-group">
                <label>Вопросы на этой странице:</label>
                <div class="questions-container" id="questions-container-${pageIndex}">
                    <!-- Здесь будут добавляться вопросы для этой страницы -->
                </div>
                <button type="button" class="add-btn" onclick="addQuestion(${pageIndex})">+ Добавить вопрос</button>
            </div>
            <button type="button" class="delete-btn" onclick="deletePage(${pageIndex})">Удалить страницу</button>
        `;
        
        // Добавляем страницу в контейнер
        document.getElementById('pages-container').appendChild(pageCard);
        
        // Инициализируем счетчик вопросов для новой страницы
        questionCountPerPage[pageIndex] = 0;
    }
    
    // Функция удаления страницы
    function deletePage(pageIndex) {
        const pageElement = document.getElementById(`page-${pageIndex}`);
        if (pageElement) {
            pageElement.remove();
        }
    }
    
    // Функция добавления нового вопроса на страницу
    function addQuestion(pageIndex) {
        const questionIndex = questionCountPerPage[pageIndex];
        questionCountPerPage[pageIndex]++;
        
        // Создаем новый контейнер для вопроса
        const questionCard = document.createElement('div');
        questionCard.className = 'question-card';
        questionCard.id = `question-${pageIndex}-${questionIndex}`;
        
        // Формируем HTML для нового вопроса
        questionCard.innerHTML = `
            <div class="form-group">
                <label>Текст вопроса:</label>
                <input type="text" name="questions[${pageIndex}][${questionIndex}][text]" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Тип вопроса:</label>
                <select name="questions[${pageIndex}][${questionIndex}][type]" class="form-control" onchange="handleQuestionTypeChange(this, ${pageIndex}, ${questionIndex})">
                    <option value="text">Текстовый ответ</option>
                    <option value="textarea">Многострочный текстовый ответ</option>
                    <option value="radio">Один из вариантов</option>
                    <option value="checkbox">Множественный выбор</option>
                    <option value="select">Выпадающий список</option>
                    <option value="scale">Шкала</option>
                </select>
            </div>
            
            <div class="question-options" id="question-options-${pageIndex}-${questionIndex}">
                <!-- Здесь будут дополнительные опции в зависимости от типа вопроса -->
            </div>
            
            <button type="button" class="delete-btn" onclick="deleteQuestion(${pageIndex}, ${questionIndex})">Удалить вопрос</button>
        `;
        
        // Добавляем вопрос в контейнер
        document.getElementById(`questions-container-${pageIndex}`).appendChild(questionCard);
    }
    
    // Функция удаления вопроса
    function deleteQuestion(pageIndex, questionIndex) {
        const questionElement = document.getElementById(`question-${pageIndex}-${questionIndex}`);
        if (questionElement) {
            questionElement.remove();
        }
    }
    

    
    // Обработка изменения типа вопроса
    function handleQuestionTypeChange(selectElement, pageIndex, questionIndex) {
        const questionType = selectElement.value;
        const optionsContainer = document.getElementById(`question-options-${pageIndex}-${questionIndex}`);
        
        // Очищаем контейнер опций
        optionsContainer.innerHTML = '';
        
        // В зависимости от типа вопроса добавляем различные опции
        switch (questionType) {
            case 'radio':
            case 'checkbox':
            case 'select':
                // Для вопросов с вариантами ответов
                optionsContainer.innerHTML = `
                    <div class="form-group">
                        <label>Варианты ответов:</label>
                        <div class="options-list" id="options-list-${pageIndex}-${questionIndex}">
                            <div class="option-item">
                                <input type="text" name="questions[${pageIndex}][${questionIndex}][options][]" class="form-control" placeholder="Вариант ответа" required>
                                <input type="number" name="questions[${pageIndex}][${questionIndex}][scores][]" class="form-control score-input" placeholder="Баллы" value="0">
                                <button type="button" class="delete-btn small" onclick="this.parentElement.remove()">×</button>
                            </div>
                        </div>
                        <button type="button" class="add-btn small" onclick="addOption(${pageIndex}, ${questionIndex})">+ Добавить вариант</button>
                    </div>
                `;
                break;
                
            case 'scale':
                // Для вопросов со шкалой
                optionsContainer.innerHTML = `
                    <div class="form-group">
                        <label>Настройки шкалы:</label>
                        <div class="row">
                            <div class="col">
                                <label>Минимальное значение:</label>
                                <input type="number" name="questions[${pageIndex}][${questionIndex}][scale_min]" class="form-control" value="1" required>
                            </div>
                            <div class="col">
                                <label>Максимальное значение:</label>
                                <input type="number" name="questions[${pageIndex}][${questionIndex}][scale_max]" class="form-control" value="5" required>
                            </div>
                        </div>
                    </div>
                `;
                break;
        }
    }

    // Добавление варианта ответа с полем для баллов
    function addOption(pageIndex, questionIndex) {
        const optionsList = document.getElementById(`options-list-${pageIndex}-${questionIndex}`);
        const optionItem = document.createElement('div');
        optionItem.className = 'option-item';
        
        optionItem.innerHTML = `
            <input type="text" name="questions[${pageIndex}][${questionIndex}][options][]" class="form-control" placeholder="Вариант ответа" required>
            <input type="number" name="questions[${pageIndex}][${questionIndex}][scores][]" class="form-control score-input" placeholder="Баллы" value="0">
            <button type="button" class="delete-btn small" onclick="this.parentElement.remove()">×</button>
        `;
        
        optionsList.appendChild(optionItem);
    }


// Обработка изменения типа правила
function handleRuleTypeChange(selectElement, ruleIndex) {
    const ruleType = selectElement.value;
    const conditionContainer = document.getElementById(`rule-condition-${ruleIndex}`);
    
    if (ruleType === 'score') {
        conditionContainer.innerHTML = `
            <div class="form-group">
                <label>Диапазон баллов:</label>
                <div class="row">
                    <div class="col">
                        <label>От:</label>
                        <input type="number" name="rules[${ruleIndex}][score_min]" class="form-control" value="0" required>
                    </div>
                    <div class="col">
                        <label>До:</label>
                        <input type="number" name="rules[${ruleIndex}][score_max]" class="form-control" value="100" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Учитывать баллы:</label>
                <select name="rules[${ruleIndex}][score_source]" class="form-control" required>
                    <option value="total">За всю форму</option>
                    <option value="page">За конкретную страницу</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Страница для подсчета баллов:</label>
                <select name="rules[${ruleIndex}][score_page]" class="form-control">
                    <option value="">Выберите страницу</option>
                    <!-- Здесь будут добавляться страницы динамически -->
                </select>
            </div>
        `;
    } else {
        conditionContainer.innerHTML = `
            <div class="form-group">
                <label>Если в вопросе:</label>
                <select name="rules[${ruleIndex}][if_question]" class="form-control" required>
                    <option value="">Выберите вопрос</option>
                    <!-- Здесь будут добавляться вопросы динамически -->
                </select>
            </div>
            
            <div class="form-group">
                <label>Выбран ответ:</label>
                <select name="rules[${ruleIndex}][if_answer]" class="form-control" required>
                    <option value="">Выберите ответ</option>
                    <!-- Здесь будут добавляться варианты ответов динамически -->
                </select>
            </div>
        `;
    }
    
    updateRuleOptions();
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
            <select name="rules[${ruleIndex}][scope]" class="form-control" onchange="handleRuleScopeChange(this, ${ruleIndex})" required>
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
                    <input type="number" name="rules[${ruleIndex}][score_min]" class="form-control" value="0" required>
                </div>
                <div class="col">
                    <label>До:</label>
                    <input type="number" name="rules[${ruleIndex}][score_max]" class="form-control" value="10" required>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label>Текст результата:</label>
            <textarea name="rules[${ruleIndex}][result_text]" class="form-control" rows="3" placeholder="Текст, который увидит пользователь при попадании в диапазон баллов" required></textarea>
        </div>
        
        <div class="form-group">
            <label>Диагноз:</label>
            <select name="rules[${ruleIndex}][diagnosis_id]" class="form-control" required>
                <?php while ($diagnosis = $diagnoses_result->fetch_assoc()): ?>
                    <option value="<?php echo $diagnosis['id']; ?>"><?php echo htmlspecialchars($diagnosis['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <button type="button" class="delete-btn" onclick="deleteRule(${ruleIndex})">Удалить правило</button>
    `;
    
    rulesContainer.appendChild(ruleCard);
    handleRuleScopeChange(ruleCard.querySelector('select[name$="[scope]"]'), ruleIndex);
    updateRuleOptions();
}


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
                </select>
            </div>
        `;
        
        // Находим селектор страниц
        const pageSelect = scopeContainer.querySelector(`select[name="rules[${ruleIndex}][page_id]"]`);
        
        // Заполняем селектор доступными страницами
        for (let i = 0; i < pageCount; i++) {
            const pageElement = document.getElementById(`page-${i}`);
            if (pageElement) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = `Страница ${i+1}`;
                pageSelect.appendChild(option);
            }
        }
        updateRuleOptions();
    } else {
        scopeContainer.innerHTML = ''; // Для всей формы не нужно выбирать страницу
        updateRuleOptions();
    }
}

// Удаление правила логики
function deleteRule(ruleIndex) {
    console.log("delete rule");
    const ruleElement = document.getElementById(`rule-${ruleIndex}`);
    if (ruleElement) {
        ruleElement.remove();
    }
    updateRuleOptions();
}




// Обновление опций в правилах при изменении структуры формы
function updateRuleOptions() {
    // Выбираем все селекторы страниц в правилах
    const pageSelects = document.querySelectorAll('select[name$="[page_id]"]');
    
    pageSelects.forEach(select => {
        const currentValue = select.value; // Сохраняем текущее выбранное значение
        
        // Очищаем селектор и добавляем пустой вариант
        select.innerHTML = '<option value="">-- Выберите страницу --</option>';
        
        // Заполняем селектор доступными страницами
        for (let i = 0; i < pageCount; i++) {
            const pageElement = document.getElementById(`page-${i}`);
            if (pageElement) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = `Страница ${i+1}`;
                
                // Если это был выбранный вариант, выбираем его снова
                if (currentValue == i) {
                    option.selected = true;
                }
                
                select.appendChild(option);
            }
        }
    });
    
    console.log("Обновление опций правил. Найдено селекторов: " + pageSelects.length);
}


    // Инициализация первой страницы при загрузке
    window.onload = function() {
        addPage();
    };
</script>
</body>
</html>