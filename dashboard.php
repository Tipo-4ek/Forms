<?php

// Проверка авторизации и ролей
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

error_reporting(E_ALL);
ini_set('display_errors', 'On'); 

$user_id = $_COOKIE['user_id'];

// Получение форм, доступных пользователю
$sql = "
    SELECT DISTINCT f.id, f.name, f.created_at, fa.access_level
    FROM forms f
    JOIN form_access fa ON f.id = fa.form_id
    WHERE fa.user_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Панель управления</title>
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
    <h1>Добро пожаловать, <?php echo htmlspecialchars($_COOKIE['full_name']); ?>!</h1>
    <h2>Ваши формы</h2>
    <table>
        <tr>
            <th>Название формы</th>
            <th>Дата создания</th>
            <th>Действия</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td>
                    <?php if ($row['access_level'] === 'owner' || $row['access_level'] === 'edit' || $row['access_level'] === 'view'): ?>
                    <a href="view_results.php?form_id=<?php echo $row['id']; ?>">Показать результаты</a>
                    <?php endif; ?>

                    <?php if ($row['access_level'] === 'owner' || $row['access_level'] === 'edit' || $row['access_level'] === 'view'): ?>
                    |
                    <a href="fill_form.php?form_id=<?php echo $row['id']; ?>" target="_blank">Заполнить</a>
                    <?php endif; ?>

                    <?php if ($row['access_level'] === 'owner' || $row['access_level'] === 'edit'): ?>
                    | 
                    <a href="edit_form.php?form_id=<?php echo $row['id']; ?>">Редактировать</a>
                    <?php endif; ?>

                    <?php if ($row['access_level'] === 'owner' || $row['access_level'] === 'edit') : ?>
                    | 
                    <a href="manage_access.php?form_id=<?php echo $row['id']; ?>">Управление доступами</a>
                    <?php endif; ?>

                    <?php if ($row['access_level'] === 'owner' || $row['access_level'] === 'edit' || $row['access_level'] === 'view'): ?>
                    | 
                    <a href="export_excel.php?form_id=<?php echo $row['id']; ?>">Экспортировать</a>
                    <?php endif; ?>

                    <?php if ($row['access_level'] === 'owner' || $row['access_level'] === 'edit') : ?>
                    | 
                    <form method="post" action="delete_form.php" style="display:inline;">
                        <input type="hidden" name="form_id" value="<?php echo $row['id']; ?>">
                        <input type="submit" value="Удалить" onclick="return confirm('Вы уверены, что хотите удалить эту форму?');">
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    <br>
    <?php if ($result->num_rows >= 0): ?>
    <a href="create_form.php">Создать новую форму</a>
    <br><br>
    <a href="manage_diagnoses.php">Управление диагнозами</a>
    <br><br>
    <?php endif; ?>
    <a href="logout.php">Выйти</a>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
