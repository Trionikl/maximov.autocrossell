<?php
declare(strict_types=1);

require_once __DIR__ . '/scan_helpers.php';

$action = $_GET['action'] ?? '';
$checklist = $_GET['checklist'] ?? '';

switch ($action) {
    case 'status':
        // Сводка по всем чеклистам (для дашборда)
        checklist_respond(['ok' => true, 'summaries' => checklist_read_all_summaries()]);

    case 'load':
        if ($checklist === '') {
            checklist_respond(['ok' => false, 'error' => 'Параметр checklist обязателен'], 400);
        }
        checklist_respond(['ok' => true, 'files' => checklist_read_for($checklist)]);

    case 'save':
        if ($checklist === '') {
            checklist_respond(['ok' => false, 'error' => 'Параметр checklist обязателен'], 400);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            checklist_respond(['ok' => false, 'error' => 'Требуется метод POST'], 405);
        }
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body) || !array_key_exists('files', $body)) {
            checklist_respond(['ok' => false, 'error' => 'Некорректный формат запроса'], 400);
        }
        checklist_write_for($checklist, checklist_sanitize_files($body['files']));
        checklist_respond(['ok' => true, 'saved_at' => date('H:i:s')]);

    default:
        checklist_respond(['ok' => false, 'error' => 'Неизвестное действие'], 400);
}
