<?php

// Проверка авторизации
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';

$user_id = $_COOKIE['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_id = (int)$_POST['form_id'];
    
    // Проверка наличия прав доступа на форме (view, edit, owner)
    $sql = "SELECT access_level FROM form_access WHERE form_id = ? AND user_id = ? AND access_level IN ('view', 'edit', 'owner')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $form_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die('У вас нет прав для удаления этой формы.');
    }
    
    // Удаление формы (без условия created_by)
    $stmt = $conn->prepare("DELETE FROM forms WHERE id = ?");
    $stmt->bind_param("i", $form_id);
    $stmt->execute();

    header("Location: dashboard.php");
    exit;
}

$conn->close();
?>