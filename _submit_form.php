<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'database.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_id = $_POST['form_id'];
    $user_id = (int) $_COOKIE['user_id'];

    // Сохранение общей информации о заполнении формы
    $stmt = $conn->prepare("INSERT INTO user_responses (user_id, form_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $form_id);
    $stmt->execute();

    $response_id = $conn->insert_id;

    // Обработка и сохранение каждого ответа
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'question_') === 0) {
            $question_id = (int)str_replace('question_', '', $key);

            if (is_array($value)) { // Специальная обработка для множественного выбора
                foreach ($value as $answer_id) {
                    $answer_id = (int)$answer_id;
                    $stmt = $conn->prepare("INSERT INTO responses_details (response_id, question_id, answer_id) VALUES (?, ?, ?)");
                    if (!$stmt) {
                        die("Ошибка подготовки запроса: " . $conn->error);
                    }
                    $stmt->bind_param("iii", $response_id, $question_id, $answer_id);
                    $stmt->execute();
                }
            } else { // Для одиночных значений и текстовых ответов
                echo "<script>console.log('Новая запись');</script>";
                echo "<script>console.log(".$question_id.");</script>";
                $answer_value = $value;
                echo "<script>console.log(".$answer_value.");</script>";
                $answer_id = 0; // Для текстовых ответов answer_id будет 0
                echo "<script>console.log(".$answer_id.");</script>";
                $stmt = $conn->prepare("INSERT INTO responses_details (response_id, question_id, answer_id, answer_value) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    die("Ошибка подготовки запроса: " . $conn->error);
                }
                $stmt->bind_param("iiis", $response_id, $question_id, $answer_id, $answer_value);
                $stmt->execute();
            }
        }
    }

    //echo "<script>window.location.replace('index.php');</script>";
}

$conn->close();
?>