<?php
if (!isset($_COOKIE['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'vendor/autoload.php';
require 'database.php';

$user_id = $_COOKIE['user_id'];
$form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 0;

// Проверка прав доступа к форме: должны быть view, edit или owner
$sql = "SELECT access_level FROM form_access WHERE form_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $form_id, $user_id);
$stmt->execute();
$access_result = $stmt->get_result();
$access = $access_result->fetch_assoc();

if (!$access || !in_array($access['access_level'], ['view','edit','owner'])) {
    die('У вас нет прав для выгрузки этой формы.');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Получение данных о форме
$sql = "SELECT name FROM forms WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$form_result = $stmt->get_result();
$form = $form_result->fetch_assoc();

$sql = "SELECT ur.id, ur.user_id, u.full_name, u.email, ur.created_at AS response_date, q.question_text, COALESCE(a.answer_text, rd.answer_value) AS answer_text, a.score,
        r.result_text, d.name AS diagnosis_name
        FROM user_responses ur
        JOIN users u ON ur.user_id = u.id
        JOIN responses_details rd ON ur.id = rd.response_id
        JOIN questions q ON rd.question_id = q.id
        LEFT JOIN answers a ON rd.answer_id = a.id
        LEFT JOIN rules r ON ur.form_id = r.form_id AND (a.score BETWEEN r.score_min AND r.score_max)
        LEFT JOIN diagnoses d ON r.diagnosis_id = d.id
        WHERE ur.form_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $form_id);
$stmt->execute();
$results = $stmt->get_result();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Форма " . $form['name']);

// Заголовки
$sheet->setCellValue('A1', 'ID ответа');
$sheet->setCellValue('B1', 'ID пользователя');
$sheet->setCellValue('C1', 'Имя пользователя');
$sheet->setCellValue('D1', 'Email пользователя');
$sheet->setCellValue('E1', 'Дата заполнения');
$sheet->setCellValue('F1', 'Вопрос');
$sheet->setCellValue('G1', 'Ответ');
$sheet->setCellValue('H1', 'Баллы');
$sheet->setCellValue('I1', 'Диагноз');
$sheet->setCellValue('J1', 'Текст правила');

// Данные
$row_num = 2;
while ($row = $results->fetch_assoc()) {
    $sheet->setCellValue('A' . $row_num, $row['id']);
    $sheet->setCellValue('B' . $row_num, $row['user_id']);
    $sheet->setCellValue('C' . $row_num, $row['full_name']);
    $sheet->setCellValue('D' . $row_num, $row['email']);
    $sheet->setCellValue('E' . $row_num, $row['response_date']);
    $sheet->setCellValue('F' . $row_num, $row['question_text']);
    $sheet->setCellValue('G' . $row_num, $row['answer_text']);
    $sheet->setCellValue('H' . $row_num, $row['score']);
    $sheet->setCellValue('I' . $row_num, $row['diagnosis_name']);
    $sheet->setCellValue('J' . $row_num, $row['result_text']);
    $row_num++;
}

$filename = 'export_form_' . $form_id . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($filename);

// Отправка файла на скачивание
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$writer->save('php://output');

$stmt->close();
$conn->close();
exit;
?>