<?php
declare(strict_types=1);
require_once __DIR__ . '/scan_helpers.php';
$state = checklist_read_for('component');
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Компонент на странице товара + кеш — maximov.autocrossell</title>
<style>
:root{--accent:#6366f1;--red:#dc2626;--blue:#2563eb;--bg:#f8fafc;--card:#fff;--border:#e2e8f0;--text:#0f172a;--dim:#64748b}
body.dark{--bg:#0f172a;--card:#1e293b;--border:#334155;--text:#e2e8f0;--dim:#94a3b8}
*{box-sizing:border-box}
body{margin:0;font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text)}
.container{max-width:860px;margin:0 auto;padding:24px 18px 60px}
.back{display:inline-flex;align-items:center;gap:6px;color:var(--blue);text-decoration:none;font-size:13px;margin-bottom:10px}
.back:hover{text-decoration:underline}
header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid var(--border);padding-bottom:16px;margin-bottom:24px;flex-wrap:wrap;gap:12px}
h1{margin:0;font-size:20px}
.progress{font-size:14px;color:var(--dim)}
.bar{flex:1;height:10px;background:var(--border);border-radius:99px;overflow:hidden;min-width:120px}
.fill{height:100%;background:var(--accent);transition:width .3s;border-radius:99px}
.btn-reset{background:var(--red);color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-weight:500}
.btn-reset:hover{background:#bb2d3b}
.info{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin-bottom:18px;font-size:13px;line-height:1.6}
.info a{color:var(--blue)}
.info code{background:#e0e7ff;padding:1px 5px;border-radius:4px;font-size:12px}
body.dark .info{background:#132030;border-color:#243b52}
body.dark .info a{color:#93c5fd}
body.dark .info code{background:#163a5f;color:#93c5fd}
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
    <h1>🖥️ Компонент на странице товара + кеш</h1>
    <div class="progress">Прогресс: <span id="completed">0</span> / <span id="total">0</span></div>
  </div>
  <div style="display:flex;gap:12px;align-items:center">
    <div class="bar"><div class="fill" id="fill"></div></div>
    <button class="theme-btn" id="btnTheme" title="Тёмная/светлая тема">Тёмная тема</button>
    <button class="btn-reset" id="resetBtn">Сбросить</button>
  </div>
</header>

<div class="info">
  <strong>Откуда брать страницы товаров:</strong> откройте render-лог по файловой
  системе: <code>/bitrix/modules/maximov.autocrossell/logs/autocrossell_render.log</code>
  (SSH / файловый менеджер — веб-доступ закрыт: в каталоге <code>logs/</code> лежит
  <code>.htaccess</code> с «Require all denied»). Файл содержит URL всех товаров,
  на которых отображается блок рекомендаций.<br>
  Формат строки: <code>http://your-site.local/catalog/.../tovar/ (ID: 21, Name: Название)</code>
</div>

<!-- 1. Отображение компонента -->
<div class="section">
  <h2><span class="badge">1</span> Отображение компонента</h2>
  <p>Проверка визуального отображения блока рекомендаций на детальной странице товара.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="c-1"><div class="step-text">На странице товара из render-лога отображается блок рекомендаций (заголовок по умолчанию — «С этим покупают»).<pre>-- Как проверить:
1. Откройте URL товара из render-лога (см. инфо-блок вверху страницы)
2. Прокрутите страницу вниз до заголовка блока

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Блок виден на странице.
Обёртка: div.autocrossell-recommendations
Заголовок: div.autocrossell-recommendations__title (data-entity="header")
Текст заголовка: по умолчанию «С этим покупают» (GetMessage AUTOCROSSELL_TITLE);
если в настройках модуля задан block_title — он (обрезается до 150 символов).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="c-2"><div class="step-text">Блок содержит карточки товаров: название, картинка, цена.<pre>-- Как проверить:
В блоке рекомендаций (заголовок «С этим покупают») осмотрите карточки товаров.

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Обёртка блока: div.autocrossell-recommendations
Контейнер карточек: div.catalog-products-crossell.bx-blue
Карточка: div.product-item-container > div.product-item
Каждая карточка содержит:
- Изображение товара (span.product-item-image-wrapper;
  внутри span.product-item-image-slide с background-image)
- Название товара (h3.product-item-title > a.stretched-link)
- Цену (span.product-item-price-current внутри div.product-item-price-container)
- Визуальную кнопку «Подробнее» (span.btn.btn-link.btn-sm)
Карточки приходят AJAX-ответом (data.html) и подменяют скелетон;
лимит количества проверяется отдельно в c-4b.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="c-3"><div class="step-text">Ссылки на карточки товаров ведут на правильные URL (проверить клик).<pre>-- Как проверить:
1. Кликните на название товара или изображение в карточке
2. Проверьте URL в адресной строке браузера

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
Произведён переход на детальную страницу соответствующего товара.
URL соответствует формату /catalog/.../tovar/
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="c-4b"><div class="step-text">В блоке <strong>не более 4 карточек</strong> — контроллер хардкодит <code>LIMIT=4</code>.<pre>-- Как проверить:
1. Откройте страницу товара из render-лога и посчитайте карточки
2. (Контроль хардкода, терминал):
   grep -n "'LIMIT'" /path/to/site/bitrix/modules/maximov.autocrossell/lib/controller/detail.php

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. Карточек в блоке: 0..4, никогда больше 4.
2. grep: контроллер передаёт 'LIMIT' => 4 в $APPLICATION->IncludeComponent.
   Ручной подбор LIMIT невозможен: компонент подключается только через
   AJAX-контроллер, отдельной опции LIMIT в настройках модуля нет.
   class.php дополнительно применяет max(0, min(4, LIMIT)).
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="c-6"><div class="step-text">При отсутствии пар для товара блок <strong>не отображается</strong> и не ломает вёрстку (AJAX-флоу: скелетон удаляется).<pre>-- Как проверить:

Шаг 1. Получение тестового товара через SQL:
Выполните SQL-запрос в админке Битрикса (Настройки -&gt; Производительность -&gt; SQL-запрос)
или в консоли MySQL. Запрос находит активный товар каталога, отсутствующий в таблице пар
ОБЕИМИ ролями (и PRODUCT_ID_1, и PRODUCT_ID_2), и генерирует готовый URL:

SELECT
    e.ID,
    e.NAME,
    CONCAT('/catalog/', IF(s.CODE IS NOT NULL, CONCAT(s.CODE, '/'), ''), e.CODE, '/') AS TEST_URL
FROM b_iblock_element e
LEFT JOIN b_iblock_section s ON e.IBLOCK_SECTION_ID = s.ID
WHERE e.ACTIVE = 'Y'
  AND e.IBLOCK_ID = (
      SELECT IBLOCK_ID
      FROM b_iblock_element
      WHERE ID = (SELECT PRODUCT_ID_1 FROM b_maximov_autocrossell_pairs LIMIT 1)
  )
  AND e.ID NOT IN (SELECT PRODUCT_ID_1 FROM b_maximov_autocrossell_pairs)
  AND e.ID NOT IN (SELECT PRODUCT_ID_2 FROM b_maximov_autocrossell_pairs)
LIMIT 1;

Важно: проверка только по PRODUCT_ID_1 даёт ложноположительный результат —
связи в модуле двусторонние, и товар может стоять вторым элементом (PRODUCT_ID_2).
Исключаем из обоих полей.

----------------------------------------------------------------------
Проверка в Яндекс Браузере (XHR/fetch Breakpoint):
1. Откройте в браузере страницу товара по полученному TEST_URL.
2. Откройте инструменты разработчика (F12) -&gt; вкладка «Источники» (Sources).
3. На панели справа найдите блок «Точки останова XHR или fetch», нажмите «+»
   и введите строго: autocrossell
   - НЕ используйте ajax.php: общий шаблон перехватит системные XHR Битрикса
     (BigData, «Просмотренные товары» и др.), у которых свои скелетоны, и остановка
     произойдёт не в том месте.
   - Перехватывается только запрос модуля:
     action=maximov:autocrossell.detail.getComponentHtml.
4. Обновите страницу (F5).
5. Страница замрёт с плашкой «Приостановлено в отладчике»:
   - Убедитесь, что в блоке отображается скелетон модуля .autocrossell-skeleton
     (внутри обёртки .autocrossell-inject-wrap).
6. Нажмите синюю кнопку Play (▶) на всплывающей плашке или клавишу F8
   (возобновить выполнение).
7. Скелетон должен исчезнуть. Перейдите на вкладку «Элементы» (Ctrl+F -&gt; autocrossell-inject-wrap)
   и убедитесь, что обёртка .autocrossell-inject-wrap полностью удалена из DOM.

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. SQL-запрос возвращает 1 строку с активным товаром, отсутствующим в таблице пар
   и как PRODUCT_ID_1, и как PRODUCT_ID_2, и валидным адресом TEST_URL.
2. Проверка в Яндекс Браузере:
   - До завершения AJAX-запроса (на паузе отладчика): обёртка со скелетоном
     .autocrossell-inject-wrap стабильно видна в разметке.
   - После нажатия Play (F8): обёртка .autocrossell-inject-wrap полностью удалена из DOM.
   - Ошибок в консоли нет.
*/</pre></div></div>
  <div class="step"><input type="checkbox" class="chk" data-check="c-7"><div class="step-text">При наличии пар для товара блок рекомендаций корректно отображается (AJAX-флоу: скелетон заменяется карточками товаров).<pre>-- Как проверить:

Шаг 1. Получение тестового товара через SQL:
Выполните SQL-запрос в админке Битрикса (Настройки -&gt; Производительность -&gt; SQL-запрос)
или в консоли MySQL. Запрос находит активный товар каталога, присутствующий в таблице пар
(в роли PRODUCT_ID_1 или PRODUCT_ID_2), и генерирует готовый URL:

SELECT
    e.ID,
    e.NAME,
    CONCAT('/catalog/', IF(s.CODE IS NOT NULL, CONCAT(s.CODE, '/'), ''), e.CODE, '/') AS TEST_URL
FROM b_iblock_element e
LEFT JOIN b_iblock_section s ON e.IBLOCK_SECTION_ID = s.ID
WHERE e.ACTIVE = 'Y'
  AND e.IBLOCK_ID = (
      SELECT IBLOCK_ID
      FROM b_iblock_element
      WHERE ID = (SELECT PRODUCT_ID_1 FROM b_maximov_autocrossell_pairs LIMIT 1)
  )
  AND (
      e.ID IN (SELECT PRODUCT_ID_1 FROM b_maximov_autocrossell_pairs)
      OR e.ID IN (SELECT PRODUCT_ID_2 FROM b_maximov_autocrossell_pairs)
  )
LIMIT 1;

Важно: связи в модуле двусторонние, поэтому поиск товара идёт по обоим полям
(PRODUCT_ID_1 и PRODUCT_ID_2).

----------------------------------------------------------------------
Проверка в Яндекс Браузере (XHR/fetch Breakpoint):
1. Откройте в браузере страницу товара по полученному TEST_URL.
2. Откройте инструменты разработчика (F12) -&gt; вкладка «Источники» (Sources).
3. На панели справа найдите блок «Точки останова XHR или fetch», нажмите «+»
   и введите строго: autocrossell
   - Перехватывается только запрос модуля:
     action=maximov:autocrossell.detail.getComponentHtml.
4. Обновите страницу (F5).
5. Страница замрёт с плашкой «Приостановлено в отладчике»:
   - Убедитесь, что в разметке присутствует скелетон модуля .autocrossell-skeleton
     внутри обёртки .autocrossell-inject-wrap.
6. Нажмите кнопку Play (▶) или клавишу F8 (возобновить выполнение).
7. Скелетон должен успешно замениться на рабочий блок рекомендаций.

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. SQL-запрос возвращает 1 строку: активный товар, присутствующий в таблице пар
   хотя бы одной ролью (PRODUCT_ID_1 или PRODUCT_ID_2), и валидный адрес TEST_URL.
2. Проверка в Яндекс Браузере:
   - До завершения AJAX-запроса (на паузе отладчика): в DOM видна обёртка
     .autocrossell-inject-wrap с контейнером скелетона .autocrossell-skeleton
     (сетка row.product-item-list-col-4, 4 колонки col-6 col-md-3).
   - После нажатия Play (F8): скелетон .autocrossell-skeleton исчезает;
     внутри той же обёртки .autocrossell-inject-wrap отрендерен блок
     div.autocrossell-recommendations (обёртка НЕ удаляется — в отличие от c-6,
     где при отсутствии пар она удаляется целиком).
   - Заголовок блока: div.catalog-block-header.autocrossell-recommendations__title
     (по умолчанию «С этим покупают» — GetMessage AUTOCROSSELL_TITLE;
     либо значение опции block_title, обрезается до 150 символов).
   - Внутри: div.catalog-products-crossell.bx-blue с карточками
     div.product-item-container &gt; div.product-item (название, картинка, цена,
     ссылка a.stretched-link ведёт на /catalog/.../tovar/).
   - Верстка блока не ломает общую структуру страницы.
   - Ошибок в консоли браузера (F12 -&gt; Console) и PHP-ошибок нет.
*/</pre></div></div>

<!-- 2. Кеширование компонента (терминальная проверка по ФС) -->
<div class="section">
  <h2><span class="badge">2</span> Кеширование компонента</h2>
  <p>Детерминированная проверка кеша ядра по файловой системе: холодный запрос создаёт файл кеша, горячий — отдаёт данные из кеша без пересоздания файла.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="h-1"><div class="step-text">Проверка кеширования ядра в терминале (find + stat + curl)<pre>-- Как проверить:
Выполните готовый скрипт в консоли сервера (копируется целиком, ручной ввод ID не требуется):

rm -rf /path/to/site/bitrix/cache/* && PID=$(grep -m1 -oE 'ID: [0-9]+' /path/to/site/bitrix/modules/maximov.autocrossell/logs/autocrossell_render.log | grep -oE '[0-9]+') && CK=$(mktemp) && SID=$(curl -s -c "$CK" "http://your-site.local/" | grep -oE '"bitrix_sessid":"[a-f0-9]{32}"' | grep -oE '[a-f0-9]{32}' | head -n1) && curl -s -b "$CK" "http://your-site.local/bitrix/services/main/ajax.php?action=maximov:autocrossell.detail.getComponentHtml&productId=$PID&sessid=$SID" >/dev/null && F=$(find /path/to/site/bitrix/cache/ -path "*autocrossell*" -type f | head -n1) && T1=$(stat -c %Y "$F") && curl -s -b "$CK" "http://your-site.local/bitrix/services/main/ajax.php?action=maximov:autocrossell.detail.getComponentHtml&productId=$PID&sessid=$SID" >/dev/null && T2=$(stat -c %Y "$F") && rm -f "$CK" && ([ -n "$F" ] && [ "$T1" -eq "$T2" ] && echo "CACHE HIT: OK (Product ID: $PID)" || echo "CACHE FAIL")

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
В выводе консоли строго отображается:
CACHE HIT: OK (Product ID: {динамический_ID_из_лога})

Это подтверждает:
1. ID товара динамически извлечён из лога рендера (autocrossell_render.log).
2. Первый (холодный) запрос создал файл кеша в /bitrix/cache/.
3. Второй (горячий) запрос отдал данные из кеша без изменения файла (mtime совпадает).
4. Кеширование ядра работает штатно, тест пройден.
*/</pre></div></div>
</div>

<!-- 3. Инвалидация кеша -->
<div class="section">
  <h2><span class="badge">3</span> Инвалидация кеша</h2>
  <p>Проверка обновления кеша: по тегу при пересчёте пар и ручным сбросом.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="v-3"><div class="step-text">Инвалидация тегированного кеша при пересчёте пар (кнопка «Пересчитать сейчас»)<pre>-- Как проверить:
Выполните автономную проверку инвалидации кеша в консоли:

PID=$(grep -m1 -oE 'ID: [0-9]+' /path/to/site/bitrix/modules/maximov.autocrossell/logs/autocrossell_render.log | grep -oE '[0-9]+') &amp;&amp; CK=$(mktemp) &amp;&amp; SID=$(curl -s -c "$CK" "http://your-site.local/" | grep -oE '"bitrix_sessid":"[a-f0-9]{32}"' | grep -oE '[a-f0-9]{32}' | head -n1) &amp;&amp; curl -s -b "$CK" "http://your-site.local/bitrix/services/main/ajax.php?action=maximov:autocrossell.detail.getComponentHtml&amp;productId=$PID&amp;sessid=$SID" &gt;/dev/null &amp;&amp; FILE=$(find /path/to/site/bitrix/cache/ -path "*autocrossell*" -type f | head -n1) &amp;&amp; T1=$(stat -c %Y "$FILE") &amp;&amp; sleep 1 &amp;&amp; php -r '$_SERVER["DOCUMENT_ROOT"]=(getenv("DOCUMENT_ROOT") ?: "/path/to/site"); require $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php"; \Bitrix\Main\Loader::includeModule("maximov.autocrossell"); if (class_exists(\Maximov\Autocrossell\RecalculationAgent::class)) { \Maximov\Autocrossell\RecalculationAgent::execute(true); } else { \Maximov\Autocrossell\Agent\RecalculationAgent::execute(true); }' &amp;&amp; curl -s -b "$CK" "http://your-site.local/bitrix/services/main/ajax.php?action=maximov:autocrossell.detail.getComponentHtml&amp;productId=$PID&amp;sessid=$SID" &gt;/dev/null &amp;&amp; T2=$(stat -c %Y "$FILE") &amp;&amp; rm -f "$CK" &amp;&amp; ([ -n "$FILE" ] &amp;&amp; [ "$T2" -gt "$T1" ] &amp;&amp; echo "TAG INVALIDATION: OK" || echo "TAG INVALIDATION: FAIL")

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
В консоли выводится строго:
TAG INVALIDATION: OK

Значение:
Тегированный кеш сброшен по тегу maximov.autocrossell, файл кеша успешно пересоздан заново с обновлённым временем (T2 &gt; T1). Тест пройден.
Вывод TAG INVALIDATION: FAIL означает ошибку инвалидации кеша.
*/</pre></div></div>
</div>

<div class="footer">Чеклист проверки компонента maximov.autocrossell:recommendations: отображение блока на странице товара, кеширование ядра и инвалидация тегированного кеша. Прогресс сохраняется на сервере.</div>
</div>

<script>
const API = 'api.php?checklist=component';
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
