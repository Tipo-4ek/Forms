<?php
// Проверка авторизации и роли
if (!isset($_COOKIE['user_id']) || ($_COOKIE['role'] !== 'admin' && $_COOKIE['role'] !== 'teacher')) {
    header("Location: login.php");
    exit;
}

require 'database.php';

$user_id = $_COOKIE['user_id'];

// Обработка создания диагноза
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_diagnosis'])) {
    $diagnosis_name = $_POST['diagnosis_name'];
    $stmt = $conn->prepare("INSERT INTO diagnoses (name) VALUES (?)");
    $stmt->bind_param("s", $diagnosis_name);
    $stmt->execute();
    header("Location: manage_diagnoses.php");
    exit;
}

// Обработка создания правила
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_rule'])) {
    $diagnosis_id = $_POST['diagnosis_id'];
    $form_id = $_POST['form_id'];
    $scope = $_POST['scope'];
    $page_id = (isset($_POST['page_id']) && $_POST['page_id'] !== "") ? $_POST['page_id'] : NULL;
    $score_min = $_POST['score_min'];
    $score_max = $_POST['score_max'];
    $result_text = $_POST['result_text'];
    $priority = $_POST['priority'];

    $stmt = $conn->prepare("INSERT INTO rules (diagnosis_id, form_id, scope, page_id, score_min, score_max, result_text, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiiisi", $diagnosis_id, $form_id, $scope, $page_id, $score_min, $score_max, $result_text, $priority);
    $stmt->execute();
    header("Location: manage_diagnoses.php");
    exit;
}

// Получение всех диагнозов
$diagnoses_result = $conn->query("SELECT * FROM diagnoses");

// Получение форм, к которым у пользователя есть доступ (view, edit, owner)
$stmt = $conn->prepare("SELECT * FROM forms WHERE id IN (SELECT form_id FROM form_access WHERE user_id = ? AND access_level IN ('view','edit','owner'))");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$forms_result = $stmt->get_result();

// Получение всех форм с правилами, привязанными к диагнозу и доступных пользователю
$forms_with_rules_result = $conn->query("
    SELECT DISTINCT f.id, f.name 
    FROM forms f
    JOIN rules r ON f.id = r.form_id
    JOIN diagnoses d ON r.diagnosis_id = d.id
    WHERE f.id IN (SELECT form_id FROM form_access WHERE user_id = $user_id AND access_level IN ('view','edit','owner'))
");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление диагнозами и правилами</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h1>Управление диагнозами и правилами</h1>

        <?php if ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === 'teacher'): ?>
        <form method="post" action="manage_diagnoses.php">
            <div class="form-group">
                <label>Название диагноза:</label>
                <input type="text" name="diagnosis_name" class="form-control" required>
            </div>
            <button type="submit" name="create_diagnosis" class="btn btn-primary">Создать диагноз</button>
        </form>
        <?php endif; ?>

        <h2 class="mt-4">Диагнозы</h2>
        <?php while ($diagnosis = $diagnoses_result->fetch_assoc()): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($diagnosis['name']); ?></h3>
                </div>
                <div class="card-body">
                    <h4>Правила</h4>
                    <?php
                    // Получение правил для диагноза, только для форм, к которым у пользователя есть доступ
                    $stmt = $conn->prepare("SELECT * FROM rules WHERE diagnosis_id = ? AND form_id IN (SELECT form_id FROM form_access WHERE user_id = ? AND access_level IN ('view','edit','owner'))");
                    $stmt->bind_param("ii", $diagnosis['id'], $user_id);
                    $stmt->execute();
                    $rules_result = $stmt->get_result();
                    ?>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Форма</th>
                                <th>Область</th>
                                <th>Страница</th>
                                <th>Диапазон баллов</th>
                                <th>Текст результата</th>
                                <th>Приоритет</th>
                                <?php if ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === 'teacher'): ?>
                                <th>Действия</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($rule = $rules_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rule['form_id']); ?></td>
                                    <td><?php echo htmlspecialchars($rule['scope']); ?></td>
                                    <td><?php echo htmlspecialchars($rule['page_id']); ?></td>
                                    <td><?php echo htmlspecialchars($rule['score_min'] . ' - ' . $rule['score_max']); ?></td>
                                    <td><?php echo htmlspecialchars($rule['result_text']); ?></td>
                                    <td><?php echo htmlspecialchars($rule['priority']); ?></td>
                                    <?php if ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === 'teacher'): ?>
                                    <td>
                                        <a href="edit_rule.php?rule_id=<?php echo $rule['id']; ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                        <form method="post" action="delete_rule.php" style="display:inline;">
                                            <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                            <input type="submit" value="Удалить" class="btn btn-sm btn-danger" onclick="return confirm('Вы уверены, что хотите удалить это правило?');">
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <?php if ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === 'teacher'): ?>
                    <h4>Добавить правило</h4>
                    <form method="post" action="manage_diagnoses.php">
                        <input type="hidden" name="diagnosis_id" value="<?php echo $diagnosis['id']; ?>">
                        <div class="form-group">
                            <label>Форма:</label>
                            <select name="form_id" class="form-control" required>
                                <?php
                                // Используем формы, к которым у пользователя есть доступ
                                $forms_result->data_seek(0);
                                while ($form = $forms_result->fetch_assoc()): ?>
                                    <option value="<?php echo $form['id']; ?>"><?php echo htmlspecialchars($form['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Область:</label>
                            <select name="scope" class="form-control" required>
                                <option value="form">Вся форма</option>
                                <option value="page">Конкретная страница</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Страница (если выбрана область "Конкретная страница"):</label>
                            <input type="number" name="page_id" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Диапазон баллов:</label>
                            <div class="row">
                                <div class="col">
                                    <label>От:</label>
                                    <input type="number" name="score_min" class="form-control" required>
                                </div>
                                <div class="col">
                                    <label>До:</label>
                                    <input type="number" name="score_max" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Текст результата:</label>
                            <textarea name="result_text" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Приоритет:</label>
                            <input type="number" name="priority" class="form-control" required>
                        </div>
                        <button type="submit" name="create_rule" class="btn btn-primary">Создать правило</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>

        <h2 class="mt-4">Формы с правилами, привязанными к диагнозам</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Название формы</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($form = $forms_with_rules_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($form['name']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
