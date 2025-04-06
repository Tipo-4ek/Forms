<?php
// Проверка авторизации
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

$user_id = $_COOKIE['user_id'];
$rule_id = isset($_GET['rule_id']) ? (int)$_GET['rule_id'] : 0;

if ($rule_id <= 0) {
    header("Location: manage_diagnoses.php");
    exit;
}

// Получение информации о правиле и его form_id
$sql = "SELECT * FROM rules WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $rule_id);
$stmt->execute();
$rule_result = $stmt->get_result();
$rule = $rule_result->fetch_assoc();

if (!$rule) {
    header("Location: manage_diagnoses.php");
    exit;
}

$form_id = $rule['form_id'];

// Проверяем права доступа к форме: доступны только 'edit' и 'owner'
$stmt = $conn->prepare("SELECT access_level FROM form_access WHERE form_id = ? AND user_id = ? AND access_level IN ('edit','owner')");
$stmt->bind_param("ii", $form_id, $user_id);
$stmt->execute();
$access_result = $stmt->get_result();
if ($access_result->num_rows === 0) {
    die('У вас нет прав для редактирования этого правила.');
}

// Обработка обновления правила
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_id = $_POST['form_id'];
    $scope = $_POST['scope'];
    $page_id = isset($_POST['page_id']) ? $_POST['page_id'] : NULL;
    $score_min = $_POST['score_min'];
    $score_max = $_POST['score_max'];
    $result_text = $_POST['result_text'];
    $priority = $_POST['priority'];
    $diagnosis_id = $_POST['diagnosis_id'];

    $stmt = $conn->prepare("UPDATE rules SET form_id = ?, scope = ?, page_id = ?, score_min = ?, score_max = ?, result_text = ?, priority = ?, diagnosis_id = ? WHERE id = ?");
    $stmt->bind_param("isiiisiii", $form_id, $scope, $page_id, $score_min, $score_max, $result_text, $priority, $diagnosis_id, $rule_id);
    $stmt->execute();
    header("Location: manage_diagnoses.php");
    exit;
}

// Получение всех форм
$forms_result = $conn->query("SELECT * FROM forms");
// Получение всех диагнозов
$diagnoses_result = $conn->query("SELECT * FROM diagnoses");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Редактирование правила</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h1>Редактирование правила</h1>
        <form method="post" action="edit_rule.php?rule_id=<?php echo $rule_id; ?>">
            <div class="form-group">
                <label>Форма:</label>
                <select name="form_id" class="form-control" required>
                    <?php while ($form = $forms_result->fetch_assoc()): ?>
                        <option value="<?php echo $form['id']; ?>" <?php if ($form['id'] == $rule['form_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($form['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <!-- ...existing поля формы правила... -->
            <div class="form-group">
                <label>Область:</label>
                <select name="scope" class="form-control" required>
                    <option value="form" <?php if ($rule['scope'] == 'form') echo 'selected'; ?>>Вся форма</option>
                    <option value="page" <?php if ($rule['scope'] == 'page') echo 'selected'; ?>>Конкретная страница</option>
                </select>
            </div>
            <div class="form-group">
                <label>Страница (если выбрана область "Конкретная страница"):</label>
                <input type="number" name="page_id" class="form-control" value="<?php echo htmlspecialchars($rule['page_id']); ?>">
            </div>
            <div class="form-group">
                <label>Диапазон баллов:</label>
                <div class="row">
                    <div class="col">
                        <label>От:</label>
                        <input type="number" name="score_min" class="form-control" value="<?php echo htmlspecialchars($rule['score_min']); ?>" required>
                    </div>
                    <div class="col">
                        <label>До:</label>
                        <input type="number" name="score_max" class="form-control" value="<?php echo htmlspecialchars($rule['score_max']); ?>" required>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Текст результата:</label>
                <textarea name="result_text" class="form-control" rows="3" required><?php echo htmlspecialchars($rule['result_text']); ?></textarea>
            </div>
            <div class="form-group">
                <label>Приоритет:</label>
                <input type="number" name="priority" class="form-control" value="<?php echo htmlspecialchars($rule['priority']); ?>" required>
            </div>
            <div class="form-group">
                <label>Диагноз:</label>
                <select name="diagnosis_id" class="form-control" required>
                    <?php while ($diagnosis = $diagnoses_result->fetch_assoc()): ?>
                        <option value="<?php echo $diagnosis['id']; ?>" <?php if ($diagnosis['id'] == $rule['diagnosis_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($diagnosis['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        </form>
    </div>
</body>
</html>
