<?php
declare(strict_types=1);

/**
 * Общие функции для чеклистов модуля maximov.autocrossell.
 * Используется всеми страницами чеклистов и api.php.
 */

define(
	'MAXIMOV_MODULE_DIR',
	rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . '/bitrix/modules/maximov.autocrossell'
);
const CHECKLIST_EXCLUDED_TOP_DIRS = ['vendor', 'tests'];
const CHECKLIST_ALLOWED_STATUSES = ['none', 'green', 'blue', 'yellow', 'red'];

/**
 * Реестр всех чеклистов: id => [json_file, title, icon, description]
 */
const CHECKLIST_REGISTRY = [
    'files' => [
        'json' => 'statuses_files.json',
        'title' => 'Проверка файлов и структуры модуля',
        'icon' => '🔍',
        'description' => 'Проверка наличия, целостности и содержимого всех файлов модуля',
        // total не фиксируется: считается динамически сканом каталога модуля (см. checklist_module_file_paths)
    ],
    'install' => [
        'json' => 'statuses_install.json',
        'title' => 'Установка и удаление модуля',
        'icon' => '📦',
        'description' => 'Установка, удаление, повторная установка, сохранение данных',
        // total не фиксируется: считается по числу чекбоксов .chk в странице (см. checklist_count_items)
        'page' => 'install-uninstall.php',
    ],
    'component' => [
        'json' => 'statuses_component.json',
        'title' => 'Компонент на странице товара + кеш',
        'icon' => '🖥️',
        'description' => 'Отображение компонента, кеширование и инвалидация',
        'page' => 'component.php',
    ],
    'agent' => [
        'json' => 'statuses_agent.json',
        'title' => 'Агент: блокировка, целостность и расписание',
        'icon' => '⚡',
        'description' => 'Конкурентная блокировка AgentGuard, целостность пар, синхронизация b_agent',
        'page' => 'agent-cache.php',
    ],
    'admin' => [
        'json' => 'statuses_admin.json',
        'title' => 'Админ-панель: безопасность и UI',
        'icon' => '⚙️',
        'description' => 'Статический аудит options.php, контракт опций, единый UI smoke',
        'page' => 'admin-panel.php',
    ],
];

// ─── Общие функции ──────────────────────────────────────────────────────────

function checklist_respond(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── Функции для чеклиста файлов (backward-compatible) ──────────────────────

function checklist_scan_dir(string $absDir, string $relPath = '', int $depth = 0): array
{
    if ($depth > 12) {
        return [];
    }

    $entries = @scandir($absDir);
    if ($entries === false) {
        return [];
    }

    $dirs = [];
    $files = [];

    foreach ($entries as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if ($relPath === '' && in_array($name, CHECKLIST_EXCLUDED_TOP_DIRS, true)) {
            continue;
        }

        $abs = $absDir . '/' . $name;
        $rel = $relPath === '' ? $name : $relPath . '/' . $name;

        if (is_dir($abs)) {
            $dirs[] = [
                'name' => $name,
                'type' => 'dir',
                'path' => $rel,
                'children' => checklist_scan_dir($abs, $rel, $depth + 1),
            ];
        } elseif (is_file($abs)) {
            $size = @filesize($abs);
            $files[] = [
                'name' => $name,
                'type' => 'file',
                'path' => $rel,
                'size' => $size === false ? 0 : $size,
            ];
        }
    }

    usort($dirs, static function (array $a, array $b): int {
        return strnatcasecmp($a['name'], $b['name']);
    });
    usort($files, static function (array $a, array $b): int {
        return strnatcasecmp($a['name'], $b['name']);
    });

    return array_merge($dirs, $files);
}

function checklist_sanitize_files(mixed $rawFiles): array
{
    if (!is_array($rawFiles)) {
        return [];
    }

    $clean = [];
    foreach ($rawFiles as $path => $entry) {
        if (!is_string($path)
            || $path === ''
            || strpos($path, '..') !== false
            || strpos($path, '/') === 0
            || strpos($path, "\0") !== false
        ) {
            continue;
        }

        $status = 'none';
        $checked = false;
        if (is_array($entry)) {
            if (isset($entry['status']) && in_array($entry['status'], CHECKLIST_ALLOWED_STATUSES, true)) {
                $status = (string)$entry['status'];
            }
            $checked = !empty($entry['checked']);
        }

        if ($status === 'none' && !$checked) {
            continue;
        }

        $clean[$path] = ['checked' => $checked, 'status' => $status];
    }
    ksort($clean);

    return $clean;
}

// ─── Мульти-чеклист API ─────────────────────────────────────────────────────

/**
 * Путь к JSON-файлу состояния конкретного чеклиста.
 */
function checklist_state_path(string $checklistId): string
{
    if (!isset(CHECKLIST_REGISTRY[$checklistId])) {
        checklist_respond(['ok' => false, 'error' => 'Неизвестный чеклист: ' . $checklistId], 400);
    }
    return __DIR__ . '/' . CHECKLIST_REGISTRY[$checklistId]['json'];
}

/**
 * Чтение состояния конкретного чеклиста.
 */
function checklist_read_for(string $checklistId): array
{
    $file = checklist_state_path($checklistId);

    if (!is_file($file)) {
        return [];
    }
    $raw = (string)file_get_contents($file);
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) {
        return [];
    }

    return checklist_sanitize_files($data['files']);
}

/**
 * Сохранение состояния конкретного чеклиста.
 */
function checklist_write_for(string $checklistId, array $files): void
{
    $file = checklist_state_path($checklistId);

    $payload = json_encode(
        [
            '_meta' => ['version' => 1, 'updated' => date('c')],
            'files' => $files === [] ? new stdClass() : $files,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    $tmp = $file . '.tmp';
    $fp = fopen($tmp, 'w');
    if ($fp === false) {
        checklist_respond(['ok' => false, 'error' => 'Не удалось создать временный файл состояния'], 500);
    }
    flock($fp, LOCK_EX);
    fwrite($fp, $payload);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if (!rename($tmp, $file)) {
        @unlink($tmp);
        checklist_respond(['ok' => false, 'error' => 'Не удалось сохранить файл состояния'], 500);
    }
}

/**
 * Список относительных путей всех файлов модуля (тот же скан, что и в files.php).
 * Используется для динамического total чеклиста 'files' на дашборде.
 *
 * @return string[]
 */
function checklist_module_file_paths(): array
{
    $paths = [];
    $walk = static function (array $nodes) use (&$walk, &$paths): void {
        foreach ($nodes as $node) {
            if ($node['type'] === 'dir') {
                $walk($node['children']);
            } else {
                $paths[] = $node['path'];
            }
        }
    };
    $walk(checklist_scan_dir(MAXIMOV_MODULE_DIR));

    return $paths;
}

/**
 * Число пунктов чеклиста = количество <input class="chk"> в файле страницы.
 * Единый источник правды: те же узлы, что считает JS (boxes.length),
 * поэтому знаменатель дашборда всегда совпадает с внутренним прогрессом страницы.
 */
function checklist_count_items(string $pageFile): int
{
    $path = __DIR__ . '/' . basename($pageFile);
    if (!is_file($path)) {
        return 0;
    }
    $html = (string)file_get_contents($path);
    $count = preg_match_all('/<input\b[^>]*\bclass="[^"]*\bchk\b[^"]*"/i', $html);
    return $count === false ? 0 : $count;
}

/**
 * Сводка по всем чеклистам (для дашборда index.php).
 * Возвращает массив: id => [title, icon, description, total, checked, done]
 */
function checklist_read_all_summaries(): array
{
    $summaries = [];
    foreach (CHECKLIST_REGISTRY as $id => $meta) {
        $state = checklist_read_for($id);

        if ($id === 'files') {
            // total = фактический скан каталога модуля; checked — только по существующим файлам
            $modulePaths = array_flip(checklist_module_file_paths());
            $total = count($modulePaths);
            $checkedCount = 0;
            foreach ($state as $path => $entry) {
                if (!empty($entry['checked']) && isset($modulePaths[$path])) {
                    $checkedCount++;
                }
            }
        } else {
            // total = фактическое число пунктов на странице чеклиста
            $total = checklist_count_items($meta['page'] ?? '');
            $checkedCount = 0;
            foreach ($state as $entry) {
                if (!empty($entry['checked'])) {
                    $checkedCount++;
                }
            }
        }

        $summaries[$id] = [
            'title' => $meta['title'],
            'icon' => $meta['icon'],
            'description' => $meta['description'],
            'total' => $total,
            'checked' => $checkedCount,
            'done' => $total > 0 && $checkedCount === $total,
        ];
    }
    return $summaries;
}
