<?php
declare(strict_types=1);
require_once __DIR__ . '/scan_helpers.php';
$state = checklist_read_for('install');
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Установка и удаление — maximov.autocrossell</title>
<style>
:root{--accent:#6366f1;--red:#dc2626;--blue:#2563eb;--bg:#f8fafc;--card:#fff;--border:#e2e8f0;--text:#0f172a;--dim:#64748b;--link:#2563eb}
body.dark{--bg:#0f172a;--card:#1e293b;--border:#334155;--text:#e2e8f0;--dim:#94a3b8;--link:#93c5fd}
*{box-sizing:border-box}
body{margin:0;font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text)}
a{color:var(--link)}
.container{max-width:860px;margin:0 auto;padding:24px 18px 60px}
.back{display:inline-flex;align-items:center;gap:6px;color:var(--link);text-decoration:none;font-size:13px;margin-bottom:10px}
.back:hover{text-decoration:underline}
header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid var(--border);padding-bottom:16px;margin-bottom:24px;flex-wrap:wrap;gap:12px}
h1{margin:0;font-size:20px}
.progress{font-size:14px;color:var(--dim)}
.bar{flex:1;height:10px;background:var(--border);border-radius:99px;overflow:hidden;min-width:120px}
.fill{height:100%;background:var(--accent);transition:width .3s;border-radius:99px}
.btn-reset{background:var(--red);color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-weight:500}
.btn-reset:hover{background:#bb2d3b}
.section{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px 20px;margin-bottom:18px}
.section h2{margin:0 0 4px;font-size:16px}
.badge{font-size:12px;font-weight:600;padding:2px 8px;border-radius:99px;background:#e0e7ff;color:#3730a3;margin-right:8px}
.section>p{color:var(--dim);font-size:13px;margin:6px 0 14px}
.step{display:flex;gap:12px;padding:10px 0;border-top:1px dashed var(--border)}
.step:first-of-type{border-top:none}
.step input[type=checkbox]{width:20px;height:20px;margin-top:3px;cursor:pointer;accent-color:var(--accent);flex-shrink:0}
.step.done .step-text{text-decoration:line-through;color:var(--dim)}
.step-text{flex:1;font-size:14px}
.step-text code{background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:1px 5px;font-size:13px}
.step-text pre{background:#0f172a;color:#e2e8f0;padding:10px 12px;border-radius:6px;overflow-x:auto;font-size:13px;margin:8px 0;white-space:pre-wrap}
.step-text pre .c{color:#64748b}
.step-text pre .ok{color:#7dd3a0}
.exp{margin:6px 0;padding:8px 12px;border-left:4px solid var(--blue);background:#f0f6ff;border-radius:0 6px 6px 0;font-size:13px}
.exp.ok{border-color:var(--accent);background:#eef2ff}
.exp.no{border-color:var(--red);background:#fef2f2}
body.dark .exp{background:#122030}
body.dark .exp.ok{background:#16203a}
body.dark .exp.no{background:#2a1616}
.note{margin-top:12px;font-size:12px;color:var(--dim);border-top:1px solid var(--border);padding-top:10px}
.footer{text-align:center;color:var(--dim);font-size:12px;margin-top:24px}
.theme-btn{font:inherit;padding:6px 12px;border:1px solid var(--border);background:var(--card);border-radius:8px;cursor:pointer;color:var(--text)}
.theme-btn:hover{background:#f8fafc;border-color:#cbd5e1}
body.dark .theme-btn:hover{background:#243043;border-color:#475569}
</style>
<link rel="stylesheet" href="assets.css?v=<?= filemtime(__DIR__ . '/assets.css') ?>">
</head>
<body>
<div class="container">

<a class="back" href="./">&#8592; Назад к дашборду</a>

<header>
  <div>
    <h1>📦 Установка и удаление модуля</h1>
    <div class="progress">Прогресс: <span id="completed">0</span> / <span id="total">0</span></div>
  </div>
  <div style="display:flex;gap:12px;align-items:center">
    <div class="bar"><div class="fill" id="fill"></div></div>
    <button class="theme-btn" id="btnTheme" title="Тёмная/светлая тема">Тёмная тема</button>
    <button class="btn-reset" id="resetBtn">Сбросить</button>
  </div>
</header>

<!-- Фаза 1: Первичная чистая установка -->
<div class="section">
  <h2><span class="badge">Фаза 1</span> Первичная чистая установка (Clean Install)</h2>
  <p>Проверка корректности установки через админку Битрикс: регистрация модуля, создание таблицы и индексов, агент, дефолтные опции, событие, копирование файлов.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="i-1"><div class="step-text">Модуль устанавливается через админку <strong>без Fatal Error</strong>.<br>Путь: <a href="/bitrix/admin/settings.php?mid=maximov.autocrossell" target="_blank">Настройки модуля</a> → Установить<pre>-- Как проверить:
Перейти по ссылке (или Настройки -> Настройки продукта -> Модули ->
«Установить» у maximov.autocrossell) и дождаться завершения мастера.

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. Установка проходит без Fatal Error и белого экрана.
2. Системное уведомление: «Модуль maximov.autocrossell успешно установлен»
   (CAdminMessage::ShowNote(AUTOCROSSELL_INSTALL_SUCCESS) в install/step.php).
3. В /bitrix/admin/module_admin.php статус модуля — «Установлен»,
   доступна кнопка «Удалить».
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-2"><div class="step-text">Таблица <code>b_maximov_autocrossell_pairs</code> создана после установки.<pre><span class="c">-- SQL-запрос:</span>
SHOW TABLES LIKE 'b_maximov_autocrossell_pairs';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1 строка: b_maximov_autocrossell_pairs — таблица создана (createDbTable()).
Если таблица уже существовала — она НЕ пересоздаётся и данные сохраняются
(перед createDbTable() проверяется isTableExists()).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-3"><div class="step-text">Таблица <code>b_maximov_autocrossell_pairs</code> имеет корректную структуру (поля: ID, PRODUCT_ID_1, PRODUCT_ID_2, WEIGHT, UPDATED_AT + индексы: PRIMARY, UX_PAIR, IDX_P1, IDX_P2).<pre><span class="c">-- SQL-запрос:</span>
-- 1. Проверка колонок и типов данных:
DESCRIBE b_maximov_autocrossell_pairs;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ (DESCRIBE):
+---------------+---------+------+-----+---------+----------------+
| Field         | Type    | Null | Key | Default | Extra          |
+---------------+---------+------+-----+---------+----------------+
| ID            | int     | NO   | PRI | NULL    | auto_increment |
| PRODUCT_ID_1  | int     | NO   | MUL | NULL    |                |
| PRODUCT_ID_2  | int     | NO   | MUL | NULL    |                |
| WEIGHT        | int     | NO   |     | NULL    |                |
| UPDATED_AT    | date    | NO   |     | NULL    |                |
+---------------+---------+------+-----+---------+----------------+
ВАЖНО: Поле IS_DIRTY отсутствует. Поле UPDATED_AT присутствует (без индекса). Всего ровно 5 колонок.
*/

-- 2. Проверка индексов:
SHOW INDEX FROM b_maximov_autocrossell_pairs;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ (SHOW INDEX):
+-------------------------------+------------+----------+--------------+-------------+
| Table                         | Non_unique | Key_name | Seq_in_index | Column_name |
+-------------------------------+------------+----------+--------------+-------------+
| b_maximov_autocrossell_pairs  | 0          | PRIMARY  | 1            | ID          |
| b_maximov_autocrossell_pairs  | 0          | UX_PAIR  | 1            | PRODUCT_ID_1|
| b_maximov_autocrossell_pairs  | 0          | UX_PAIR  | 2            | PRODUCT_ID_2|
| b_maximov_autocrossell_pairs  | 1          | IDX_P1   | 1            | PRODUCT_ID_1|
| b_maximov_autocrossell_pairs  | 1          | IDX_P2   | 1            | PRODUCT_ID_2|
+-------------------------------+------------+----------+--------------+-------------+
ВАЖНО: Всего ровно 4 индекса (5 строк: UX_PAIR — составной, на 2 колонки). UX_PAIR — уникальный (Non_unique=0). Индекс IDX_UPDATED отсутствует.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-4"><div class="step-text">Агент зарегистрирован в <code>b_agent</code>:<pre><span class="c">-- SQL-запрос:</span>
SELECT NAME, ACTIVE, IS_PERIOD, AGENT_INTERVAL, NEXT_EXEC
FROM b_agent
WHERE MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Ровно 1 запись (дубликаты удаляются перед добавлением — CAgent::Delete):
- NAME: \Maximov\Autocrossell\RecalculationAgent::execute();
  (именно с (); на конце)
- ACTIVE: Y
- IS_PERIOD: Y
- AGENT_INTERVAL: 86400 (24 часа × 3600) при дефолтном интервале 24 ч
- NEXT_EXEC: в пределах окна 02:00–06:00, формат DD.MM.YYYY HH:MM:SS
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-5"><div class="step-text">Компонент скопирован в <code>/bitrix/components/maximov.autocrossell/recommendations/</code><pre><span class="c"># Команда для терминала:</span>
ls /path/to/site/bitrix/components/maximov.autocrossell/recommendations/

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ (ls без ключей):
Каталог существует, вывод содержит файлы и папки:
class.php  lang  templates

Полная копия (включая скрытые файлы) проверяется командой:
ls -la /path/to/site/bitrix/components/maximov.autocrossell/recommendations/

ОЖИДАЕМЫЙ РЕЗУЛЬТАТ (ls -la) — ровно 6 записей (не считая «.» и «..»):
.description.php  .parameters.php  .settings.php  class.php  lang  templates
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-6"><div class="step-text">Опции по умолчанию установлены:<pre><span class="c">-- SQL-запрос:</span>
SELECT NAME, VALUE
FROM b_option
WHERE MODULE_ID = 'maximov.autocrossell'
ORDER BY NAME;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ (ровно 6 опций по алфавиту — на ЧИСТОЙ установке,
без предварительного сохранения настроек в админке):
+--------------------+-------+
| NAME               | VALUE |
+--------------------+-------+
| agent_interval     | 24    |
| agent_window_end   | 06:00 |
| agent_window_start | 02:00 |
| debug              | N     |
| history_days       | 180   |
| min_threshold      | 2     |
+--------------------+-------+
ВАЖНО: опции block_title и block_container_selector при установке НЕ создаются
(пустой дефолт читается Option::get() без записи). После первого «Сохранить»
в настройках модуля они появятся (и изменятся значения других опций) —
тогда строк в выборке будет больше 6.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-8"><div class="step-text">Страница настроек модуля доступна в админке и отображается без ошибок:<br><a href="/bitrix/admin/settings.php?lang=ru&amp;mid=maximov.autocrossell&amp;mid_menu=1" target="_blank">/bitrix/admin/settings.php?lang=ru&amp;mid=maximov.autocrossell&amp;mid_menu=1</a><pre>-- Как проверить:
Перейти по ссылке выше (нужна авторизация администратора).

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Страница открывается без PHP-ошибок и белого экрана.
Вкладки: «Основные настройки» и «Агент».
Кнопка «Сохранить» присутствует и активна. */</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-9"><div class="step-text">Событие <code>main/OnProlog</code> зарегистрировано (инъекция ассетов виджета):<pre><span class="c">-- SQL-запрос:</span>
SELECT FROM_MODULE_ID, MESSAGE_ID, TO_MODULE_ID, TO_CLASS, TO_METHOD, TO_PATH
FROM b_module_to_module
WHERE FROM_MODULE_ID = 'main'
AND MESSAGE_ID = 'OnProlog'
AND TO_MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Ровно 1 запись:
- FROM_MODULE_ID: main (источник события)
- MESSAGE_ID: OnProlog (имя события)
- TO_MODULE_ID: maximov.autocrossell (владелец обработчика)
- TO_CLASS: \Maximov\Autocrossell\Events
- TO_METHOD: onProlog
- TO_PATH: пусто (регистрация через EventManager::registerEventHandler()
  без пути — класс берётся из автозагрузчика D7)
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-10"><div class="step-text">JS/CSS-ассеты виджета скопированы в публичную директорию:<pre><span class="c"># Команды для терминала:</span>
ls /path/to/site/bitrix/js/maximov.autocrossell/js/
ls /path/to/site/bitrix/js/maximov.autocrossell/css/

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Вывод 1-й команды (каталог js/):
script.js

Вывод 2-й команды (каталог css/):
style.css

ВАЖНО: ls печатает только имена файлов — полные пути в выводе не появляются.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-11"><div class="step-text">CSS стили шаблона компонента скопированы в публичную директорию:<pre><span class="c"># Команда для терминала:</span>
ls /path/to/site/bitrix/components/maximov.autocrossell/recommendations/templates/.default/style.css

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Команда печатает полный путь к файлу style.css (одна строка).
Если файла нет — ls: «No such file or directory» (шаг не пройден:
стили блока не подключатся на странице товара).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-12"><div class="step-text">Уникальный индекс <code>UX_PAIR</code> (PRODUCT_ID_1, PRODUCT_ID_2) создан:<pre><span class="c">-- SQL-запрос:</span>
SHOW INDEX FROM b_maximov_autocrossell_pairs WHERE Key_name = 'UX_PAIR';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1 запись:
- Key_name: UX_PAIR
- Non_unique: 0 (уникальный)
- Column_name: PRODUCT_ID_1 (Seq_in_index: 1) и PRODUCT_ID_2 (Seq_in_index: 2)
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="i-13"><div class="step-text">Компонент НЕ подключается вручную в шаблоне — автоинъекция через виджет:<pre><span class="c"># Команда для терминала:</span>
grep -n "IncludeComponent.*maximov.autocrossell" /path/to/site/bitrix/components/bitrix/catalog/templates/bootstrap_v4/element.php

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
grep не находит вхождений (вывод пуст) — серверного вызова компонента нет.
В файле присутствует только комментарий (строки 661–664) о том, что блок
подставляется клиентским виджетом, а серверный IncludeComponent удалён.
Если вхождение найдено — шаг N/A: виджет покажет блок дважды.
*/</pre></div></div>
</div>

<!-- Фаза 2: Удаление с сохранением данных -->
<div class="section">
  <h2><span class="badge">Фаза 2</span> Удаление с сохранением данных (savedata=Y)</h2>
  <p>При удалении с галочкой «Сохранить таблицы» — данные не теряются.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="u-1"><div class="step-text">Удалить модуль с галочкой <strong>«Сохранить таблицы»</strong>. Таблица <code>b_maximov_autocrossell_pairs</code> сохраняется.<pre>-- Как проверить:
1. До удаления зафиксировать число пар:
<span class="c">-- SQL-запрос (до удаления):</span>
   SELECT COUNT(*) FROM b_maximov_autocrossell_pairs;
<strong>ВАЖНО:</strong> записать полученное значение COUNT(*) (ожидаемо не 0) —
оно понадобится для сравнения после повторной установки (запрос №2 в шаге r-1).
2. В админке: Настройки -> Настройки продукта -> Модули (/bitrix/admin/module_admin.php)
   -> «Удалить» у maximov.autocrossell. Галочка «Сохранить таблицы» ОСТАЁТСЯ
   отмеченной (checked по умолчанию в install/uninstall_step1.php) ->
   ввести CAPTCHA, нажать «Удалить модуль».

<span class="c">-- SQL-запрос №1 (таблица существует):</span>
SHOW TABLES LIKE 'b_maximov_autocrossell_pairs';

<span class="c">-- SQL-запрос №2 (данные сохранены):</span>
SELECT COUNT(*) FROM b_maximov_autocrossell_pairs;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. Удаление проходит без Fatal Error; системное уведомление:
   «Модуль maximov.autocrossell успешно удалён»
   (CAdminMessage::ShowNote(AUTOCROSSELL_UNINSTALL_SUCCESS)).
2. SHOW TABLES возвращает 1 строку: b_maximov_autocrossell_pairs —
   таблица НЕ удалена (UnInstallDB(['savedata' => 'Y']) не вызывает dropTable()).
3. COUNT(*) равен числу, зафиксированному до удаления (не 0) — данные не потеряны.
4. Таблица готова к повторной установке (проверяется в шаге r-2).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="u-2"><div class="step-text">Агент удалён из <code>b_agent</code> (записи с MODULE_ID=maximov.autocrossell отсутствуют).<pre><span class="c">-- SQL-запрос:</span>
SELECT * FROM b_agent WHERE MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
0 записей (Empty set) — агент удалён из b_agent при деинсталляции
(unregisterAgent() в DoUninstall()).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="u-3"><div class="step-text">Компонент удалён из <code>/bitrix/components/maximov.autocrossell/</code><pre><span class="c"># Команда для терминала:</span>
ls /path/to/site/bitrix/components/maximov.autocrossell/

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
ls: cannot access '.../bitrix/components/maximov.autocrossell/':
No such file or directory — каталог удалён DeleteDirFilesEx() в UninstallFiles().
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="u-4"><div class="step-text">Опции модуля <strong>сохранены</strong> в <code>b_option</code> (при savedata=Y не удаляются):<pre><span class="c">-- SQL-запрос:</span>
SELECT NAME, VALUE FROM b_option WHERE MODULE_ID = 'maximov.autocrossell' ORDER BY NAME;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Выборка НЕ пуста (COUNT > 0) — опции сохранены, т.к. при savedata='Y'
Option::delete() НЕ вызывается (ветка if ($savedata !== 'Y') в DoUninstall()
метода install/index.php). Настройки администратора не теряются и будут
переиспользованы при повторной установке (см. r-1).
Полное удаление опций выполняется только при удалении БЕЗ сохранения (см. d-5).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="u-5"><div class="step-text">Тегированный кеш <code>maximov.autocrossell</code> сброшен.<pre><span class="c">-- SQL-запрос:</span>
-- Проверка записей тега в таблице b_cache_tag:
SELECT * FROM b_cache_tag WHERE TAG = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
0 записей (пустой результат / Empty set).
Все записи с тегом maximov.autocrossell должны быть удалены из b_cache_tag после вызова clearByTag в DoUninstall().
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="u-6"><div class="step-text">Модуль не активен в списке модулей:<pre><span class="c">-- SQL-запрос:</span>
SELECT ID, DATE_ACTIVE FROM b_module
WHERE ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
0 записей (Empty set) — ModuleManager::unRegisterModule() удаляет строку
из b_module. Визуально: /bitrix/admin/module_admin.php — у maximov.autocrossell
статус «Не установлен», кнопка «Установить».
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="u-7"><div class="step-text">Событие <code>main/OnProlog</code> отписано при деинсталляции:<pre><span class="c">-- SQL-запрос:</span>
SELECT ID, FROM_MODULE_ID, MESSAGE_ID, TO_MODULE_ID, TO_CLASS, TO_METHOD
FROM b_module_to_module WHERE TO_MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
0 записей — EventManager::unRegisterEventHandler('main', 'OnProlog',
'maximov.autocrossell', ...) снял строку (+ легаси UnRegisterModuleDependences).
«Висячий» обработчик недопустим: иначе сайт упадёт с фаталом на вызове
отсутствующего класса Events при первом обращении к публичной странице.
*/</pre></div></div>
  <div class="exp ok">Результат: таблица и опции сохранены, готовы к повторной установке.</div>
</div>

<!-- Фаза 3: Повторная установка -->
<div class="section">
  <h2><span class="badge">Фаза 3</span> Повторная установка (Re-install / Idempotency)</h2>
  <p>Проверка идемпотентности: повторная установка поверх сохранённой таблицы.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="r-1"><div class="step-text">Установить модуль повторно <strong>без ошибок</strong>.<pre>-- Как проверить:
В /bitrix/admin/module_admin.php нажать «Установить» у maximov.autocrossell
и дождаться завершения мастера.

<span class="c">-- SQL-запрос №1 (модуль зарегистрирован без фатала):</span>
SELECT ID FROM b_module WHERE ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ №1:
+----------------------+
| ID                   |
+----------------------+
| maximov.autocrossell |
+----------------------+
Ровно 1 строка — ModuleManager::registerModule() отработал, модуль активен.
Если строк 0 — установка не дошла до регистрации (смотреть Fatal Error).
Примечание: DATE_ACTIVE в выборку НЕ включаем — ядро выполняет только
INSERT INTO b_module (ID) VALUES ('...'), поле DATE_ACTIVE всегда NULL.
*/

<span class="c">-- SQL-запрос №2 (таблица НЕ пересоздана, данные сохранены):</span>
SELECT COUNT(*) FROM b_maximov_autocrossell_pairs;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ №2:
+----------+
| COUNT(*) |
+----------+
| 42       |
+----------+
Число совпадает с зафиксированным до удаления (см. u-1), т.е. COUNT > 0.
createDbTable() пропущен, потому что isTableExists($tableName) === true.
*/

<span class="c">-- SQL-запрос №3 (опции НЕ перезаписаны дефолтами):</span>
SELECT NAME, VALUE
FROM b_option
WHERE MODULE_ID = 'maximov.autocrossell'
ORDER BY NAME;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ №3:
Набор и значения совпадают с тем, что был ДО повторной установки (см. u-4).
Пример, если администратор менял min_threshold на «5»:
+--------------------+-------+
| NAME               | VALUE |
+--------------------+-------+
| agent_interval     | 24    |
| agent_window_end   | 06:00 |
| agent_window_start | 02:00 |
| debug              | N     |
| history_days       | 180   |
| min_threshold      | 5     |
+--------------------+-------+
min_threshold = 5 (пользовательское), а НЕ дефолт «2»:
setDefaultOptions() пишет значение только при Option::get(..., null) === null,
поэтому сохранённые настройки не затираются.
*/

<span class="c">-- SQL-запрос №4 (агент НЕ задублирован):</span>
SELECT COUNT(*) FROM b_agent WHERE MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ №4:
+----------+
| COUNT(*) |
+----------+
| 1        |
+----------+
Ровно 1 запись: registerAgent() вызывает \CAgent::RemoveModuleAgents()
ПЕРЕД CAgent::AddAgent() — старые/зависшие агенты снимаются, дубли не растут.
*/

/* ИТОГ (сводка ожиданий):
1. Установка проходит без Fatal Error (как при первой — см. i-1).
2. Существующая таблица НЕ пересоздаётся, данные в ней сохраняются (запрос №2).
3. Опции модуля не затираются: setDefaultOptions() пишет дефолт только тогда,
   когда Option::get($module, $name, null) === null (запрос №3).
4. Агент не дублируется: registerAgent() вызывает \CAgent::RemoveModuleAgents()
   ПЕРЕД CAgent::AddAgent() — в b_agent ровно 1 запись (запрос №4).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="r-2"><div class="step-text">Таблица <code>b_maximov_autocrossell_pairs</code> (сохранённая) — идемпотентна: данные и индексы сохранены, инсталлятор отработал без ошибок.<pre><span class="c">-- SQL-запрос:</span>
-- 1. Проверка сохранения данных в таблице:
SELECT COUNT(*) FROM b_maximov_autocrossell_pairs;
-- Ожидаем: количество записей совпадает с тем, что было до переустановки (данные не затерты)

-- 2. Проверка индексов:
SHOW INDEX FROM b_maximov_autocrossell_pairs;
/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ (SHOW INDEX):
Ровно 4 индекса (5 строк) без дублей и ошибок:
- PRIMARY (ID)
- UX_PAIR (PRODUCT_ID_1, PRODUCT_ID_2) — уникальный
- IDX_P1 (PRODUCT_ID_1)
- IDX_P2 (PRODUCT_ID_2)
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="r-3"><div class="step-text">Агент заново зарегистрирован в <code>b_agent</code> (ACTIVE=Y).<pre><span class="c">-- SQL-запрос:</span>
-- Проверка регистрации агента в b_agent:
SELECT ID, NAME, ACTIVE, IS_PERIOD, AGENT_INTERVAL
FROM b_agent
WHERE MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Ровно 1 запись (без дублей) — идемпотентная регистрация агента:
registerAgent() перед CAgent::AddAgent() вызывает \CAgent::RemoveModuleAgents(),
который снимает все прежние агенты модуля (в т.ч. «зависшие» после переустановки).
- NAME: \Maximov\Autocrossell\RecalculationAgent::execute();
- ACTIVE: Y
- IS_PERIOD: Y
- MODULE_ID: maximov.autocrossell
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="r-4"><div class="step-text">Компонент заново скопирован в <code>/bitrix/components/maximov.autocrossell/</code><pre><span class="c"># Команда для терминала:</span>
# Проверка наличия файлов компонента через консоль:
ls /path/to/site/bitrix/components/maximov.autocrossell/recommendations/

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Каталог существует, вывод содержит файлы и папки:
class.php  lang  templates
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="r-5"><div class="step-text">Уникальный индекс <code>UX_PAIR</code> сохраняется при переустановке (идемпотентно, без дублей):<pre><span class="c">-- SQL-запрос:</span>
SHOW INDEX FROM b_maximov_autocrossell_pairs WHERE Key_name = 'UX_PAIR';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1 запись: Key_name = UX_PAIR, Non_unique = 0, колонки PRODUCT_ID_1 (Seq 1)
и PRODUCT_ID_2 (Seq 2) — индекс на месте, миграция идемпотентна, дубли
не созданы (повторный ALTER INDEX перехватывается установщиком).
*/</pre></div></div>
  <div class="exp ok">Результат: данные не потеряны, модуль работает.</div>
</div>

<!-- Фаза 4: Полное удаление без сохранения данных -->
<div class="section">
  <h2><span class="badge">Фаза 4</span> Полное удаление (Purge / Teardown, savedata=N)</h2>
  <p>Удаление модуля без галочки «Сохранить таблицы» — полная зачистка системы.<br><strong>ВАЖНО: в этой фазе ЗАПРЕЩЕНО выполнять повторную установку и проверять создание сущностей — фиксируется только факт 100% очистки.</strong></p>

  <div class="step"><input type="checkbox" class="chk" data-check="d-1"><div class="step-text">Удалить модуль <strong>без</strong> галочки «Сохранить таблицы».<pre>-- Путь в админке Битрикса:
-- Настройки -> Настройки продукта -> Модули (/bitrix/admin/module_admin.php)
-- Действие: нажать «Удалить» у maximov.autocrossell и СНЯТЬ галочку
-- «Сохранить таблицы» — по умолчанию она ОТМЕЧЕНА (checked в
-- install/uninstall_step1.php); без снятия таблица сохранится (см. u-1).

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. Удаление проходит штатно, без Fatal Error и белого экрана.
2. Системное уведомление: «Модуль maximov.autocrossell успешно удалён».
3. В строке модуля статус — «Не установлен», кнопка «Установить».
4. Инсталлятор вызывает UnInstallDB(['savedata' => 'N']) — таблица
   b_maximov_autocrossell_pairs удаляется (см. d-2).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="d-2"><div class="step-text">Таблица <code>b_maximov_autocrossell_pairs</code> удалена:<pre><span class="c">-- SQL-запрос:</span>
SHOW TABLES LIKE 'b_maximov_autocrossell_pairs';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
0 строк (Empty set) — таблица удалена: UnInstallDB с savedata=N
вызывает dropTable() при деинсталляции.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="d-3"><div class="step-text">Агент удалён из <code>b_agent</code> (даже при удалении без сохранения):<pre><span class="c">-- SQL-запрос:</span>
SELECT COUNT(*) FROM b_agent WHERE MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
+----------+
| COUNT(*) |
+----------+
| 0        |
+----------+
Агент удаляется ВСЕГДА независимо от флага savedata: unregisterAgent()
вызывает \CAgent::RemoveModuleAgents() в DoUninstall() до ветки UnInstallDB().
Никаких «висячих» записей агента в b_agent остаться не должно.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="d-4"><div class="step-text">Событие <code>main/OnProlog</code> отписано после полного удаления:<pre><span class="c">-- SQL-запрос:</span>
SELECT ID, FROM_MODULE_ID, MESSAGE_ID, TO_MODULE_ID, TO_CLASS, TO_METHOD
FROM b_module_to_module WHERE TO_MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
0 записей — событие отписано (unRegisterEventHandler + легаси
UnRegisterModuleDependences при деинсталляции, как и при сохранении
данных, см. u-7).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="d-5"><div class="step-text">Опции модуля удалены из <code>b_option</code> (удаление без сохранения данных):<pre><span class="c">-- SQL-запрос:</span>
SELECT COUNT(*) FROM b_option WHERE MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
0 — при снятой галочке «Сохранить таблицы» (savedata='N') DoUninstall()
вызывает Option::delete($this->MODULE_ID), и все опции модуля удаляются
полностью (COUNT(*)=0).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="d-6"><div class="step-text">Каталог компонента <code>/bitrix/components/maximov.autocrossell/</code> удалён:<pre><span class="c"># Команда для терминала:</span>
ls /path/to/site/bitrix/components/maximov.autocrossell/

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
ls: cannot access '.../bitrix/components/maximov.autocrossell/':
No such file or directory — каталог удалён Directory::deleteDirectory()
в UninstallFiles() (install/index.php). Остаточных файлов быть не должно.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="d-7"><div class="step-text">Каталог ассетов виджета <code>/bitrix/js/maximov.autocrossell/</code> удалён:<pre><span class="c"># Команда для терминала:</span>
ls /path/to/site/bitrix/js/maximov.autocrossell/

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
ls: cannot access '.../bitrix/js/maximov.autocrossell/':
No such file or directory — каталог удалён UninstallFiles() (js/ с script.js
и css/ со style.css вычищаются рекурсивно).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="d-8"><div class="step-text">Тегированный кэш <code>maximov.autocrossell</code> сброшен при полном удалении:<pre><span class="c">-- SQL-запрос:</span>
SELECT * FROM b_cache_tag WHERE TAG = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
0 записей (Empty set) — Application::getInstance()->getTaggedCache()
->clearByTag('maximov.autocrossell') в DoUninstall() (шаг 6) удалил теги,
чтобы на витрине не висел закешированный HTML удалённого компонента.
*/</pre></div></div>
  <div class="exp no">Результат: система полностью очищена — таблица, опции, агент, события, файлы компонента и ассетов, тег-кэш удалены. Повторная установка в этой фазе НЕ выполняется (см. Фазу 5).</div>
</div>

<!-- Фаза 5: Верификация чистой установки с нуля -->
<div class="section">
  <h2><span class="badge">Фаза 5</span> Верификация чистой установки с нуля (Final Clean Verification)</h2>
  <p>Повторная установка на полностью очищенную систему (после Фазы 4) — проверка, что инсталлятор воспроизводимо создаёт все сущности «с нуля».</p>

  <div class="step"><input type="checkbox" class="chk" data-check="f-1"><div class="step-text">Предусловие: после Фазы 4 система очищена — модуль не зарегистрирован, таблицы нет.<pre><span class="c">-- SQL-запрос №1 (модуль):</span>
SELECT ID FROM b_module WHERE ID = 'maximov.autocrossell';

<span class="c">-- SQL-запрос №2 (таблица):</span>
SHOW TABLES LIKE 'b_maximov_autocrossell_pairs';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
№1: 0 записей (Empty set) — b_module не содержит модуля.
№2: 0 строк — таблица b_maximov_autocrossell_pairs не существует.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="f-2"><div class="step-text">Установить модуль на очищенную систему <strong>без ошибок</strong>.<pre>-- Как проверить:
В /bitrix/admin/module_admin.php нажать «Установить» у maximov.autocrossell
и дождаться завершения мастера.

<span class="c">-- SQL-запрос:</span>
SELECT ID FROM b_module WHERE ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. Установка проходит без Fatal Error и белого экрана.
2. Уведомление: «Модуль maximov.autocrossell успешно установлен».
3. SQL: ровно 1 строка maximov.autocrossell — ModuleManager::registerModule() отработал.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="f-3"><div class="step-text">Таблица создана заново и полностью пуста (<code>COUNT(*)=0</code>).<pre><span class="c">-- SQL-запрос:</span>
SELECT COUNT(*) FROM b_maximov_autocrossell_pairs;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
+----------+
| COUNT(*) |
+----------+
| 0        |
+----------+
createDbTable() создал таблицу с нуля (в Фазе 4 она была удалена), данных нет.
Структура — те же 5 колонок (ID, PRODUCT_ID_1, PRODUCT_ID_2, WEIGHT, UPDATED_AT).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="f-4"><div class="step-text">Дефолтные опции сгенерированы заново (ровно 6, дефолтные значения).<pre><span class="c">-- SQL-запрос:</span>
SELECT NAME, VALUE FROM b_option WHERE MODULE_ID = 'maximov.autocrossell' ORDER BY NAME;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ (ровно 6 опций по алфавиту):
+--------------------+-------+
| NAME               | VALUE |
+--------------------+-------+
| agent_interval     | 24    |
| agent_window_end   | 06:00 |
| agent_window_start | 02:00 |
| debug              | N     |
| history_days       | 180   |
| min_threshold      | 2     |
+--------------------+-------+
Пользовательские значения из Фаз 2–3 (например min_threshold=5) отсутствуют:
в Фазе 4 опции были удалены Option::delete(), setDefaultOptions() пишет дефолты.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="f-5"><div class="step-text">Агент корректно инициализирован в <code>b_agent</code> (ровно 1 запись):<pre><span class="c">-- SQL-запрос:</span>
SELECT NAME, ACTIVE, IS_PERIOD, AGENT_INTERVAL, NEXT_EXEC
FROM b_agent
WHERE MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Ровно 1 запись (без дублей — RemoveModuleAgents() перед AddAgent()):
- NAME: \Maximov\Autocrossell\RecalculationAgent::execute();
- ACTIVE: Y
- IS_PERIOD: Y
- AGENT_INTERVAL: 86400 (24 часа × 3600 при дефолтном agent_interval=24)
- NEXT_EXEC: DD.MM.YYYY HH:MM:SS — ближайший старт окна 02:00
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="f-6"><div class="step-text">Индексы созданы полностью (PRIMARY, UX_PAIR, IDX_P1, IDX_P2).<pre><span class="c">-- SQL-запрос:</span>
SHOW INDEX FROM b_maximov_autocrossell_pairs;

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Ровно 4 индекса (5 строк) без дублей:
- PRIMARY (ID)
- UX_PAIR (PRODUCT_ID_1, PRODUCT_ID_2) — Non_unique=0 (уникальный)
- IDX_P1 (PRODUCT_ID_1)
- IDX_P2 (PRODUCT_ID_2)
Поле IS_DIRTY и индекс IDX_UPDATED отсутствуют.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="f-7"><div class="step-text">Событие <code>main/OnProlog</code> зарегистрировано, легаси-события нет.<pre><span class="c">-- SQL-запрос:</span>
SELECT FROM_MODULE_ID, MESSAGE_ID, TO_CLASS, TO_METHOD, SORT
FROM b_module_to_module
WHERE TO_MODULE_ID = 'maximov.autocrossell';

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Ровно 1 запись:
- FROM_MODULE_ID: main (источник события)
- MESSAGE_ID: OnProlog (имя события)
- TO_CLASS: \Maximov\Autocrossell\Events (с ведущим обратным слешем)
- TO_METHOD: onProlog
- SORT: 100 (дефолт EventManager::registerEventHandler)
Событие OnBeforeCacheClear и любые другие устаревшие обработчики отсутствуют
(легаси-зависимость снимается UnRegisterModuleDependences() в DoInstall()).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="f-8"><div class="step-text">Компонент и ассеты виджета скопированы.<pre><span class="c"># Команды для терминала:</span>
ls -la /path/to/site/bitrix/components/maximov.autocrossell/recommendations/
ls /path/to/site/bitrix/js/maximov.autocrossell/js/
ls /path/to/site/bitrix/js/maximov.autocrossell/css/

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1-я команда: каталог существует, ровно 6 записей:
.description.php  .parameters.php  .settings.php  class.php  lang  templates
2-я команда: script.js
3-я команда: style.css
*/</pre></div></div>
  <div class="exp ok">Результат: чистая установка полностью воспроизводима и совпадает с состоянием после Фазы 1.</div>
</div>

<div class="footer">Чеклист установки/удаления модуля maximov.autocrossell (5 фаз: Clean Install → Uninstall savedata=Y → Re-install → Purge → Final Clean Verify). Прогресс сохраняется на сервере.</div>
</div>

<script>
const API = 'api.php?checklist=install';
const KEY_PREFIX = 'ac_install_';
const boxes = Array.from(document.querySelectorAll('.chk'));

function update(){
  let done = 0;
  boxes.forEach(b => {
    const key = b.dataset.check;
    const checked = Boolean(STATE[key] && STATE[key].checked);
    b.checked = checked;
    b.closest('.step').classList.toggle('done', checked);
    if(checked) done++;
  });
  const total = boxes.length;
  document.getElementById('completed').textContent = done;
  document.getElementById('total').textContent = total;
  const bar = document.getElementById('fill').parentElement;
  bar.hidden = (done === 0);
  document.getElementById('fill').style.width = total ? (done/total*100)+'%' : '0%';
}

let STATE = <?= json_encode($state === [] ? new stdClass() : $state, $jsonFlags) ?>;
let saveTimer = null;

function commit(){
  update();
  clearTimeout(saveTimer);
  saveTimer = setTimeout(doSave, 400);
}

async function doSave(){
  try{
    await fetch(API + '&action=save', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({files:STATE})
    });
  }catch(e){}
}

boxes.forEach(b => {
  b.addEventListener('change', function(){
    const key = this.dataset.check;
    if(!STATE[key]) STATE[key] = {checked:false,status:'none'};
    STATE[key].checked = this.checked;
    commit();
  });
});

document.getElementById('resetBtn').addEventListener('click', async () => {
  if(!confirm('Сбросить все отметки?')) return;
  STATE = {};
  await doSave();
  update();
});

const themeBtn = document.getElementById('btnTheme');
function applyTheme(dark){
  document.body.classList.toggle('dark', dark);
  themeBtn.textContent = dark ? 'Светлая тема' : 'Тёмная тема';
}
let savedTheme = null;
try { savedTheme = localStorage.getItem('afc_theme'); } catch(e){}
if (savedTheme === null) {
  savedTheme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
}
applyTheme(savedTheme === 'dark');
themeBtn.addEventListener('click', () => {
  const dark = !document.body.classList.contains('dark');
  applyTheme(dark);
  try { localStorage.setItem('afc_theme', dark ? 'dark' : 'light'); } catch(e){}
});

update();
</script>
<script src="assets.js?v=<?= filemtime(__DIR__ . '/assets.js') ?>"></script>
</body>
</html>
