<?php
// Проверка авторизации
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

$user_id = $_COOKIE['user_id'];

// Получение rule_id из POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rule_id = (int)$_POST['rule_id'];

    // Получаем form_id для данного правила
    $stmt = $conn->prepare("SELECT form_id FROM rules WHERE id = ?");
    $stmt->bind_param("i", $rule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rule = $result->fetch_assoc();
    
    if (!$rule) {
        die('Правило не найдено');
    }

    $form_id = $rule['form_id'];
    
    // Проверяем права доступа для формы: должны быть 'edit' или 'owner'
    $stmt = $conn->prepare("SELECT access_level FROM form_access WHERE form_id = ? AND user_id = ? AND access_level IN ('edit','owner')");
    $stmt->bind_param("ii", $form_id, $user_id);
    $stmt->execute();
    $access_result = $stmt->get_result();
    if ($access_result->num_rows === 0) {
        die('У вас нет прав для удаления этого правила.');
    }
    
    // Удаляем правило
    $stmt = $conn->prepare("DELETE FROM rules WHERE id = ?");
    $stmt->bind_param("i", $rule_id);
    $stmt->execute();
    header("Location: manage_diagnoses.php");
    exit;
}
?>
