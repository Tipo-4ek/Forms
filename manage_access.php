<?php
// Проверка авторизации
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

// Проверка наличия form_id
if (!isset($_GET['form_id'])) {
    die('Необходимо указать ID формы');
}

$form_id = (int)$_GET['form_id'];
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

if (!$access || ($access['access_level'] !== 'edit' && $access['access_level'] !== 'owner')) {
    die('У вас нет прав для управления доступами к этой форме.');
}

// Получение информации о форме и её владельце
$sql = "
    SELECT f.name, u.full_name AS owner_name, u.email AS owner_email
    FROM forms f
    JOIN form_access fa ON f.id = fa.form_id AND fa.access_level = 'owner'
    JOIN users u ON fa.user_id = u.id
    WHERE f.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$form_result = $stmt->get_result();
$form = $form_result->fetch_assoc();

if (!$form) {
    die('Форма не найдена');
}

// Обработка добавления доступа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['access_level'])) {
    $user_id = (int)$_POST['user_id'];
    $access_level = $_POST['access_level']; // 'edit' или 'view'

    // Проверка, есть ли уже доступ
    $sql = "SELECT * FROM form_access WHERE form_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $form_id, $user_id);
    $stmt->execute();
    $access_result = $stmt->get_result();

    if ($access_result->num_rows === 0) {
        // Добавление доступа
        $sql = "INSERT INTO form_access (form_id, user_id, access_level) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $form_id, $user_id, $access_level);
        $stmt->execute();
    } else {
        // Обновление уровня доступа
        $sql = "UPDATE form_access SET access_level = ? WHERE form_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $access_level, $form_id, $user_id);
        $stmt->execute();
    }
    header("Location: manage_access.php?form_id=$form_id");
    exit;
}

// Обработка отзыва доступа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_user_id'])) {
    $user_id = (int)$_POST['revoke_user_id'];

    $sql = "DELETE FROM form_access WHERE form_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $form_id, $user_id);
    $stmt->execute();

    header("Location: manage_access.php?form_id=$form_id");
    exit;
}

// Получение всех пользователей
$users_result = $conn->query("SELECT id, full_name, email, role FROM users");

// Получение пользователей с доступом к форме
$sql = "SELECT u.id, u.full_name, u.email, u.role, fa.access_level 
        FROM form_access fa 
        JOIN users u ON fa.user_id = u.id 
        WHERE fa.form_id = ?
        ORDER BY fa.access_level = 'owner' DESC, u.full_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$access_users_result = $stmt->get_result();

// Преобразование ролей
function transformRole($role) {
    switch ($role) {
        case 'admin':
            return 'Эксперт';
        case 'user':
            return 'Респондент';
        case 'teacher':
            return 'Учитель';
        default:
            return $role;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Управление доступом к форме</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h1>Управление доступом к форме: <?php echo htmlspecialchars($form['name']); ?></h1>

        <h2 class="mt-4">Добавить доступ</h2>
        <form method="post" action="manage_access.php?form_id=<?php echo $form_id; ?>">
            <div class="form-group">
                <label>Выберите пользователя:</label>
                <select name="user_id" class="form-control" required>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['full_name'] . " (" . $user['email'] . ", " . transformRole($user['role']) . ")"); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Уровень доступа:</label>
                <select name="access_level" class="form-control" required>
                    <option value="view">Просмотр</option>
                    <option value="edit">Редактирование</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Добавить доступ</button>
        </form>

        <h2 class="mt-4">Пользователи с доступом</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Роль пользователя в системе</th>
                    <th>Уровень доступа к форме</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $access_users_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars(transformRole($user['role'])); ?></td>
                        <td>
                            <?php echo $user['access_level'] === 'owner' ? 'Полный доступ' : ($user['access_level'] === 'edit' ? 'Редактирование' : 'Просмотр'); ?>
                        </td>
                        <td>
                            <?php if ($user['access_level'] === 'owner'): ?>
                                <span class="d-inline-block" tabindex="0" data-toggle="tooltip" title="Отзыв роли невозможен, так как пользователь является создателем формы">
                                    <button class="btn btn-secondary btn-sm" style="pointer-events: none;" disabled>Отозвать</button>
                                </span>
                            <?php else: ?>
                                <form method="post" action="manage_access.php?form_id=<?php echo $form_id; ?>" style="display:inline;">
                                    <input type="hidden" name="revoke_user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Вы уверены, что хотите отозвать доступ у этого пользователя?');">Отозвать</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <a href="dashboard.php" class="btn btn-secondary mt-4">Назад</a>
    </div>

    <!-- Bootstrap JS, Popper.js, и jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>
