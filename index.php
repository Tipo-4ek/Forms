<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Добро пожаловать на Платформу Опросов</title>
    <style>
        .content {
            text-align: center;
            margin-top: 50px;
        }
        a {
            text-decoration: none;
            color: blue;
            margin: 0 10px;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Hi <?php if(isset($_COOKIE['user_id'])) echo ", " . $_COOKIE['full_name']?></h1>
    <h1>Добро пожаловать на Платформу Опросов</h1>
    <p>Создавайте, заполняйте и управляйте формами и опросами с легкостью.</p>

    <?php if (!isset($_COOKIE['user_id'])): ?>
        <!-- Если пользователь не авторизован, отображаем ссылки на вход и регистрацию -->
        <a href="login.php">Вход</a>
        |
        <a href="register.php">Регистрация</a>
    <?php else: ?>
        <!-- Если пользователь авторизован, показываем его роль и даем возможность выйти -->
        <p>Вы вошли как: <?php echo htmlspecialchars($_COOKIE['email']); ?></p>
        <?php if ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === 'teacher'): ?>
            <a href="dashboard.php">Панель управления</a>
        <?php else: ?>
            <a href="user_dashboard.php">Панель пользователя</a>
        <?php endif; ?>
        |
        <a href="logout.php">Выйти</a>
    <?php endif; ?>
</div>

</body>
</html>
