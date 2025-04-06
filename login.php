<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require 'database.php';

    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, password_hash, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $full_name, $password_hash, $role);
        $stmt->fetch();

        if (password_verify($password, $password_hash)) {
            // Установите cookies для хранения информации о пользователе
            setcookie('user_id', $id, time() + 3600, '/', '', false, false); 
            setcookie('full_name', $full_name, time() + 3600, '/', '', false, false);
            setcookie('role', $role, time() + 3600, '/', '', false, false);
            setcookie('email', $email, time() + 3600, '/', '', false, false);
            // Используем JavaScript для перенаправления
            echo "<script>window.location.replace('index.php');</script>";
            exit;
        } else {
            $error = "Неверный пароль.";
        }
    } else {
        $error = "Пользователь с таким email не найден.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Авторизация</title>
</head>
<body>
    <h2>Авторизация</h2>
    <form method="post" action="login.php">
        <label>Email:</label><br>
        <input type="email" name="email" required><br>
        <label>Пароль:</label><br>
        <input type="password" name="password" required><br>
        <input type="submit" name="submit" value="Войти">
    </form>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
</body>
</html>
