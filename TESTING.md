# Чек-лист ручного тестирования `maximov.autocrossell`

Прогонять перед публикацией каждой версии. Окно браузера: F12 -> Console,
в конце проверок — строго 0 ошибок JavaScript.

## 1. Чистая установка

- [ ] Папка `maximov.autocrossell` скопирована в `/bitrix/modules/`, модуль ставится через админку без Fatal Error.
- [ ] `SHOW TABLES LIKE 'b_maximov_autocrossell_pairs';` — таблица создана.
- [ ] `DESCRIBE b_maximov_autocrossell_pairs;` — ровно 5 колонок: ID, PRODUCT_ID_1, PRODUCT_ID_2, WEIGHT, UPDATED_AT.
- [ ] `SHOW INDEX FROM b_maximov_autocrossell_pairs;` — 4 индекса: PRIMARY, UX_PAIR (уникальный), IDX_P1, IDX_P2.
- [ ] `SELECT NAME, ACTIVE, IS_PERIOD, AGENT_INTERVAL, NEXT_EXEC FROM b_agent WHERE MODULE_ID='maximov.autocrossell';` — ровно 1 запись, ACTIVE=Y, IS_PERIOD=Y, NEXT_EXEC в окне 02:00-06:00.
- [ ] `SELECT NAME, VALUE FROM b_option WHERE MODULE_ID='maximov.autocrossell';` — ровно 6 дефолтных опций (min_threshold=2, history_days=180, agent_interval=24, agent_window_start=02:00, agent_window_end=06:00, debug=N).
- [ ] `SELECT * FROM b_module_to_module WHERE MESSAGE_ID='OnProlog' AND TO_MODULE_ID='maximov.autocrossell';` — ровно 1 запись на `\Maximov\Autocrossell\Events::onProlog`.
- [ ] Компонент скопирован в `/bitrix/components/maximov.autocrossell/recommendations/` (class.php, lang, templates).
- [ ] Ассеты виджета скопированы в `/bitrix/js/maximov.autocrossell/js/` (script.js) и `.../css/` (style.css).
- [ ] Страница настроек модуля открывается без ошибок, вкладки «Основные настройки» и «Агент» на месте.

## 2. Удаление с сохранением данных (savedata=Y)

- [ ] Зафиксировать `SELECT COUNT(*) FROM b_maximov_autocrossell_pairs;` ДО удаления.
- [ ] Удалить модуль с галочкой «Сохранить таблицы» — без Fatal Error.
- [ ] Таблица осталась, `COUNT(*)` совпадает с зафиксированным.
- [ ] Опции в `b_option` сохранены (выборка не пустая).
- [ ] Агент удалён из `b_agent` (0 записей).
- [ ] Событие `main/OnProlog` отписано (0 записей в `b_module_to_module`).

## 3. Повторная установка (идемпотентность)

- [ ] Установка поверх сохранённой таблицы проходит без ошибок.
- [ ] Таблица НЕ пересоздана, данные на месте.
- [ ] Пользовательские опции не затёрты дефолтами (`setDefaultOptions` пишет значение только при отсутствии опции).
- [ ] В `b_agent` по-прежнему ровно 1 запись (без дублей).
- [ ] Индекс UX_PAIR на месте, дубли не созданы.

## 4. Полное удаление (savedata=N)

- [ ] Снять галочку «Сохранить таблицы», удалить модуль — без Fatal Error.
- [ ] Таблица `b_maximov_autocrossell_pairs` удалена.
- [ ] Опции удалены из `b_option` (COUNT(*) = 0).
- [ ] Агент удалён из `b_agent` (0 записей).
- [ ] Событие `main/OnProlog` отписано.
- [ ] Каталоги `/bitrix/components/maximov.autocrossell/` и `/bitrix/js/maximov.autocrossell/` удалены.

## 5. Права доступа и безопасность

- [ ] Пользователь без права `W` не может сохранить настройки и запустить пересчёт.
- [ ] POST без валидного `bitrix_sessid` отклоняется (`check_bitrix_sessid`).
- [ ] Все значения и ошибки в `options.php` экранируются через `htmlspecialcharsbx`.
- [ ] Прямой HTTP-доступ к `bitrix/modules/maximov.autocrossell/logs/autocrossell.log` закрыт (403).
- [ ] `tools/run_recalc.php` недоступен по HTTP, работает только из CLI.

## 6. Генерация пар товаров

- [ ] Кнопка «Пересчитать сейчас» на вкладке «Агент» -> подтверждение -> уведомление «Пересчёт завершён. Сохранено пар: N».
- [ ] `SELECT COUNT(*) FROM b_maximov_autocrossell_pairs;` > 0 при наличии завершённых заказов с 2+ товарами.
- [ ] Целостность: нет зеркальных дублей (A,B)/(B,A), выполняется `PRODUCT_ID_1 < PRODUCT_ID_2`.
- [ ] Вес всех пар не ниже `min_threshold`.
- [ ] Параллельный второй запуск возвращает статус `busy` (замок `AgentGuard`).
- [ ] При `debug=N` лог не растёт; при `debug=Y` содержит запись «Ручной пересчёт: завершён».
- [ ] Опции с границами: значения вне диапазона зажимаются, невалидное время сбрасывается в дефолт.

## 7. Виджет: JS/CSS на странице товара

- [ ] На странице товара появляется блок `div.autocrossell-recommendations` через AJAX (скелетон -> карточки).
- [ ] Карточки содержат изображение, название, цену; ссылка ведёт на карточку товара.
- [ ] Карточек не более 4 (контроллер хардкодит `LIMIT = 4`).
- [ ] Товар БЕЗ пар: блок не отображается, скелетон удалён из DOM, вёрстка не ломается.
- [ ] Кастомный `block_container_selector` переносит блок в заданный контейнер.
- [ ] Пустой селектор возвращает блок в дефолтное место; несуществующий селектор не ломает вёрстку и не даёт ошибок JS.
- [ ] Заголовок блока: дефолт «С этим покупают» либо свой `block_title` без двойного экранирования.
- [ ] Кеш: повторный AJAX-запрос отдаёт данные без пересоздания файла кеша.
- [ ] Пересчёт пар инвалидирует тегированный кеш `maximov.autocrossell` (файл кеша пересоздаётся).
- [ ] В консоли браузера строго 0 ошибок JavaScript.

## 8. Ручные HTML-чеклисты разработчика

- [ ] Папка `tests/` скопирована в корень сайта, `tests/index.php` открывается.
- [ ] В `tests/files.php` каталог модуля находится через `$_SERVER['DOCUMENT_ROOT']`, дерево файлов строится.
- [ ] Отметки чекбоксов сохраняются сервером и переживают перезагрузку страницы.
- [ ] Файлы `statuses_*.json` не отдаются по прямому HTTP-запросу (закрыты `.htaccess`).
- [ ] В командах чеклистов вместо локального стенда используются плейсхолдеры `/path/to/site` и `http://your-site.local`.
