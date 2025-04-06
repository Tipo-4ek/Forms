
<?php
require 'database.php';

$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получение данных из формы
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // Валидация
    if (empty($full_name) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = 'Пожалуйста, заполните все поля!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный формат email!';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают!';
    } else {
        // Проверка, существует ли уже пользователь с таким email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if (!$stmt) {
            die("Ошибка подготовки запроса: " . var_dump($stmt));
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Пользователь с таким email уже зарегистрирован!';
        } else {
            // Хеширование пароля
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Вставка нового пользователя в базу данных
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'user')");
            if (!$stmt) {
                die("Ошибка подготовки запроса 2: " . var_dump($stmt));
            }
            $stmt->bind_param("sss", $full_name, $email, $hashed_password);

            if ($stmt->execute()) {
                // Перенаправление на страницу успешной регистрации или входа
                $_COOKIE['user_id'] = $stmt->insert_id;
                $_COOKIE['full_name'] = $full_name;
                $_COOKIE['role'] = 'user';
                echo "<script>window.location.replace('https://w-what.ru/login.php');</script>";
                exit();
            } else {
                $error = 'Ошибка при регистрации, попробуйте еще раз!';
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Регистрация</title>
</head>
<body>
    <h1>Регистрация</h1>
    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="POST" action="register.php">
        <label>Полное имя:</label><br>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required><br>

        <label>Email:</label><br>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required><br>

        <label>Пароль:</label><br>
        <input type="password" name="password" required><br>

        <label>Подтверждение пароля:</label><br>
        <input type="password" name="password_confirm" required><br><br>

        <input type="submit" value="Зарегистрироваться">
    </form>
</body>
</html>
