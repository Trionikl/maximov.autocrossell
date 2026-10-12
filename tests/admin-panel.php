<?php
declare(strict_types=1);
require_once __DIR__ . '/scan_helpers.php';
$state = checklist_read_for('admin');
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Админ-панель: безопасность и UI — maximov.autocrossell</title>
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
.step-text ol{margin:8px 0 0;padding-left:20px}
.step-text li{margin:4px 0}
.exp{margin:6px 0;padding:8px 12px;border-left:4px solid var(--blue);background:#f0f6ff;border-radius:0 6px 6px 0;font-size:13px}
.exp.ok{border-color:var(--accent);background:#eef2ff}
.exp.no{border-color:var(--red);background:#fef2f2}
body.dark .exp{background:#122030}
body.dark .exp.ok{background:#16203a}
body.dark .exp.no{background:#2a1616}
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
    <h1>⚙️ Админ-панель: безопасность и UI</h1>
    <div class="progress">Прогресс: <span id="completed">0</span> / <span id="total">0</span></div>
  </div>
  <div style="display:flex;gap:12px;align-items:center">
    <div class="bar"><div class="fill" id="fill"></div></div>
    <button class="theme-btn" id="btnTheme" title="Тёмная/светлая тема">Тёмная тема</button>
    <button class="btn-reset" id="resetBtn">Сбросить</button>
  </div>
</header>

<div class="info">
  <strong>Как проверять:</strong> Откройте
  <a href="/bitrix/admin/settings.php?mid=maximov.autocrossell&lang=ru" target="_blank">настройки модуля</a>.<br>
  Шесть независимых проверок: статический аудит контракта <code>options.php</code>, валидация границ и запись настроек через <code>OptionsValidator</code>, единый UI smoke (сохранение + ручной пересчёт), проверка кавычек и спецсимволов в заголовке блока, позиционирование контейнера виджета по CSS-селектору с проверкой дефолта и невалидных селекторов, проверка работы флага отладки и безопасного доступа к журналу логирования.
</div>

<div class="section">
  <h2><span class="badge">1</span> Безопасность и права доступа (options.php)</h2>
  <p>Статический аудит исходного кода настроек модуля по стандарту Маркетплейса.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="a-1"><div class="step-text">Проверка CSRF-защиты, разграничения прав и экранирования вывода<pre>-- Как проверить:
Выполните команду проверки файла options.php в консоли сервера:

php -r '$file="/path/to/site/bitrix/modules/maximov.autocrossell/options.php"; $code=(string)file_get_contents($file); $checks=["1. CSRF (check_bitrix_sessid)"=>substr_count($code,"check_bitrix_sessid(")>=2, "2. CSRF (bitrix_sessid_post)"=>strpos($code,"bitrix_sessid_post(") !== false, "3. Доступ (&lt; R)"=>strpos($code,"\$POST_RIGHT < \x27R\x27") !== false, "4. Запись (&gt;= W)"=>strpos($code,"\$POST_RIGHT >= \x27W\x27") !== false, "5. Экранирование (htmlspecialcharsbx)"=>strpos($code,"htmlspecialcharsbx(") !== false]; $ok=true; foreach($checks as $n=>$res){ $label=$n . str_repeat(" ", max(0, 40 - mb_strlen($n))); echo $label . ": " . ($res?"OK":"FAIL") . "\n"; if(!$res)$ok=false; } echo $ok ? "STATIC SECURITY: OK\n" : "STATIC SECURITY: FAIL\n";'

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
В консоли строго отображается:
1. CSRF (check_bitrix_sessid)           : OK
2. CSRF (bitrix_sessid_post)            : OK
3. Доступ (&lt; R)                         : OK
4. Запись (&gt;= W)                        : OK
5. Экранирование (htmlspecialcharsbx)   : OK
STATIC SECURITY: OK

Это подтверждает:
1. Защита POST-запросов: проверка check_bitrix_sessid() в обработчиках сохранения и пересчёта.
2. Вывод скрытого поля bitrix_sessid_post() внутри формы настроек.
3. Ограничение прав: ранний return при отсутствии прав на чтение ($POST_RIGHT < 'R').
4. Защита действий: сохранение и ручной пересчёт разрешены только при правах на запись ($POST_RIGHT >= 'W').
5. Безопасность вывода: все значения, ошибки и параметры экранируются через htmlspecialcharsbx().
*/</pre></div></div>
</div>

<div class="section">
  <h2><span class="badge">2</span> Валидация граничных значений и перезапись</h2>
  <p>Проверка клампинга чисел, времени, очистки строковых значений и фактической записи настроек через OptionsValidator.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="a-2"><div class="step-text">Стресс-тест валидатора опций и проверка перезаписи в базе данных<pre>-- Как проверить:
Выполните команду из корня сайта в консоли сервера:

php -r '$root=getcwd(); if ($root === false) { throw new \RuntimeException("Site root not found"); } $_SERVER["DOCUMENT_ROOT"]=$root; require $root."/bitrix/modules/main/include/prolog_before.php"; if (!\Bitrix\Main\Loader::includeModule("maximov.autocrossell")) { throw new \RuntimeException("Module not loaded"); } $moduleId="maximov.autocrossell"; $defaults=["min_threshold"=>"2","history_days"=>"180","agent_interval"=>"24","agent_window_start"=>"02:00","agent_window_end"=>"06:00","block_title"=>"","block_container_selector"=>"","debug"=>"N"]; $before=[]; foreach ($defaults as $key=>$default) { $before[$key]=(string)\Bitrix\Main\Config\Option::get($moduleId,$key,$default); } $modulePath=$root."/bitrix/modules/maximov.autocrossell"; $logDir=$modulePath."/logs/"; $isLogWritable=is_dir($logDir) ? is_writable($logDir) : is_writable($modulePath); if (!$isLogWritable) { $before["debug"]="N"; } $ok=false; $restoreOk=false; try { $dirty=["min_threshold"=>-50,"history_days"=>999,"agent_interval"=>99,"agent_window_start"=>"++:++","agent_window_end"=>"25:99","block_title"=>"  ".html_entity_decode("&lt;b&gt;\"Хиты\"&lt;/b&gt; & " . chr(39) . "Топ" . chr(39) . " &lt;script&gt;alert(1)&lt;/script&gt;\"&gt;&lt;script&gt;alert(2)&lt;/script&gt;")."  ","block_container_selector"=>html_entity_decode("&lt;div&gt;.workarea")]; $clean=\Maximov\Autocrossell\OptionsValidator::sanitize($dirty); $clampOk=($clean["min_threshold"]===1)&&($clean["history_days"]===365)&&($clean["agent_interval"]===24)&&($clean["agent_window_start"]==="02:00")&&($clean["agent_window_end"]==="06:00")&&($clean["block_title"]==="\"Хиты\" & " . chr(39) . "Топ" . chr(39) . " alert(1)\">alert(2)")&&($clean["block_container_selector"]===".workarea"); $debugSanitizeOk=(\Maximov\Autocrossell\OptionsValidator::sanitize(["debug"=>"Y"])["debug"]==="Y"); $firstChanges=\Maximov\Autocrossell\OptionsValidator::save(["min_threshold"=>10],$isLogWritable); $first=(string)\Bitrix\Main\Config\Option::get($moduleId,"min_threshold"); $secondChanges=\Maximov\Autocrossell\OptionsValidator::save(["min_threshold"=>20],$isLogWritable); $second=(string)\Bitrix\Main\Config\Option::get($moduleId,"min_threshold"); $saveOk=($first==="10"&&$second==="20"); $changesOk=(($firstChanges===[]||(($firstChanges["min_threshold"]["new"]??null)==="10"))&&($secondChanges===[]||(($secondChanges["min_threshold"]["new"]??null)==="20"))); $debugOk=((string)\Bitrix\Main\Config\Option::get($moduleId,"debug","N")==="N"); $ok=$clampOk&&$debugSanitizeOk&&$saveOk&&$changesOk&&$debugOk; } catch (\Throwable $e) { fwrite(STDERR,get_class($e).PHP_EOL); } finally { try { \Maximov\Autocrossell\OptionsValidator::save($before,$isLogWritable); $restoreOk=true; foreach ($before as $key=>$value) { if ((string)\Bitrix\Main\Config\Option::get($moduleId,$key,$defaults[$key])!==(string)$value) { $restoreOk=false; } } } catch (\Throwable $e) { $restoreOk=false; } } $ok=$ok&&$restoreOk; echo ($ok ? "OPTIONS VALIDATION &amp; BOUNDS: OK\n" : "OPTIONS VALIDATION &amp; BOUNDS: FAIL\n"); exit($ok ? 0 : 1);'

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
В консоли выводится строго:
OPTIONS VALIDATION &amp; BOUNDS: OK

Это подтверждает:
1. Отрицательный порог (-50) зажат в минимум 1, переполнение дней (999) и часов (99) зажато в 365 и 24.
2. Невалидное время (++:++ и 25:99) сброшено в безопасные значения 02:00 и 06:00.
3. HTML-теги и XSS-скрипты (&lt;script&gt;, &lt;b&gt;) удаляются, кавычки и амперсанд сохраняются без искажения, а пробелы обрезаются.
4. Значения 10 и 20 действительно записываются и перезаписываются в базе через OptionsValidator.
5. В блоке finally восстанавливаются все настройки, затронутые тестом.
*/</pre></div></div>
</div>

<div class="section">
  <h2><span class="badge">3</span> Смоук-тест интерфейса админки</h2>
  <p>Единая ручная проверка страницы настроек в браузере: сохранение, модальное окно и пересчёт.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="a-3"><div class="step-text">Сохранение настроек и кнопка «Пересчитать сейчас» в браузере<pre>-- Как проверить:
1. Перейдите в админку: Настройки -&gt; Настройки продукта -&gt; Настройки модулей -&gt; maximov.autocrossell (или откройте прямую ссылку: /bitrix/admin/settings.php?mid=maximov.autocrossell&amp;lang=ru).
2. Нажмите F12 и перейдите на вкладку «Консоль» (Console).
3. Нажмите кнопку «Сохранить».
4. Перейдите на вкладку «Агент» и нажмите «▶ Пересчитать сейчас».
5. В открывшемся диалоге подтвердите запуск кнопкой «Да».

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. При сохранении: отображается зелёное системное уведомление «Настройки успешно сохранены», в HTML-ответе нет PHP Notice/Warning.
2. При нажатии «Пересчитать сейчас»: открывается стандартный диалог подтверждения BX.UI.Dialogs.MessageBox.
3. После клика «Да»: отображается спиннер ожидания BX.showWait(), форма отправляется POST-запросом.
4. После окончания: отображается зелёное уведомление «Пересчёт блока «С этим товаром покупают» завершён. Сохранено пар: N» (или красное предупреждение при 0 сохранённых пар, что тоже корректно).
5. В консоли браузера (F12 -&gt; Console) строго 0 ошибок JavaScript.
*/</pre></div></div>
</div>

<div class="section">
  <h2><span class="badge">4</span> Настройки модуля / Визуальное отображение компонентов</h2>
  <p>Проверка кавычек и специальных символов в пользовательском заголовке блока.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="a-4-block-title-escaping"><div class="step-text">Проверка корректности отображения спецсимволов и кавычек в заголовке блока (отсутствие двойного экранирования)<pre>-- Как проверить:
1. Откройте настройки модуля maximov.autocrossell.
2. В поле «Заголовок блока» введите буквально:
   Хиты продаж: коллекция "Лето-2026" &amp; новинки 'Top'
3. Нажмите «Сохранить».
4. Очистите кеш компонента:
   php /path/to/site/bitrix/modules/maximov.autocrossell/tests/e2e/clear_cache.php
5. Откройте детальную страницу товара, на которой отображаются рекомендации.
6. Дождитесь завершения AJAX-запроса maximov:autocrossell.detail.getComponentHtml.
7. Проверьте текст в блоке заголовка.

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. Видимый текст полностью совпадает с введённым:
   Хиты продаж: коллекция "Лето-2026" &amp; новинки 'Top'
2. В видимом тексте отсутствуют технические сущности:
   &amp;quot;, &amp;amp;, &amp;#039;.
3. В исходном HTML допустимы одиночные HTML-сущности,
   но не должно быть двойного уровня вроде &amp;amp;quot; или &amp;amp;amp;.
4. Кавычки и амперсанд не ломают разметку блока.
5. В консоли браузера нет JavaScript-ошибок.
*/</pre></div></div>
</div>

<div class="section">
  <h2><span class="badge">5</span>Настройки модуля / Позиционирование контейнера виджета</h2>
  <p>Проверка монтирования блока рекомендаций в DOM-дерево по CSS-селектору, сброса к дефолту и защиты от отсутствующих элементов.</p>
  <div class="step">
    <input type="checkbox" class="chk" data-check="a-5-container-selector-placement">
    <div class="step-text">
      Проверка вставки блока в DOM по кастомному селектору (block_container_selector), возврата к дефолту и защиты от сбоев
      <pre>-- Как проверить:
1. В настройках модуля (/bitrix/admin/settings.php?mid=maximov.autocrossell&amp;lang=ru) укажите селектор:
   .bx-nav-list-1-lvl
   Нажмите «Сохранить» и откройте страницу товара (например, /catalog/pants/pants-striped-flight/).
2. Вернитесь в настройки, полностью сотрите селектор (пустое поле), нажмите «Сохранить» и обновите страницу товара.
3. Вернитесь в настройки, введите несуществующий селектор #fake-container-404, нажмите «Сохранить» и обновите страницу товара.

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. Для селектора .bx-nav-list-1-lvl: блок рекомендаций наглядно переместился в верхнюю часть страницы (сразу под меню каталога).
2. Для пустого селектора: блок вернулся в стандартное положение внизу страницы (под карточкой товара).
3. Для несуществующего селектора (#fake-container-404): блок остаётся на стандартном месте внизу, вёрстка не ломается, а в консоли браузера (F12 -&gt; Console) строго 0 ошибок JavaScript.
*/</pre>
    </div>
  </div>
</div>

<div class="section">
  <h2><span class="badge">6</span>Настройки модуля / Логирование и режим отладки</h2>
  <p>Проверка фиксации событий в autocrossell.log при включённой отладке, чистоты при выключенной и блокировки прямого веб-доступа через терминал.</p>
  <div class="step">
    <input type="checkbox" class="chk" data-check="a-6-debug-logging">
    <div class="step-text">
      Консольная проверка режима отладки (debug) и веб-изоляции лог-файла
      <br>Настройки модуля: <a href="/bitrix/admin/settings.php?mid=maximov.autocrossell&amp;lang=ru" target="_blank">открыть админку</a>
      <pre>-- Как проверить:
1. Очистите лог перед тестом и выполните пересчёт с отладкой:
   &gt; bitrix/modules/maximov.autocrossell/logs/autocrossell.log
   В админке включите «Включить отладку», нажмите «Сохранить», затем на вкладке «Агент» нажмите «▶ Пересчитать сейчас».
   Проверьте запись в консоли:
   grep -q "Ручной пересчёт: завершён" bitrix/modules/maximov.autocrossell/logs/autocrossell.log &amp;&amp; echo "LOG RECORD: OK" || echo "LOG RECORD: FAIL"

2. Очистите лог и выполните пересчёт БЕЗ отладки:
   &gt; bitrix/modules/maximov.autocrossell/logs/autocrossell.log
   В админке снимите «Включить отладку», нажмите «Сохранить», затем на вкладке «Агент» нажмите «▶ Пересчитать сейчас».
   Убедитесь в консоли, что файл остался пустым:
   [ ! -s bitrix/modules/maximov.autocrossell/logs/autocrossell.log ] &amp;&amp; echo "NO SPAM: OK" || echo "LOG SPAM: FAIL"

3. Проверьте защиту от прямой утечки файла через HTTP в консоли:
   curl -s -o /dev/null -w "%{http_code}\n" http://your-site.local/bitrix/modules/maximov.autocrossell/logs/autocrossell.log

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
1. При включённой отладке консоль выводит строго: LOG RECORD: OK
2. При выключенной отладке консоль выводит строго: NO SPAM: OK
3. При curl-запросе к логу сервер возвращает строго: 403
*/</pre>
    </div>
  </div>
</div>

<div class="footer">Чеклист админ-панели maximov.autocrossell (options.php): статическая безопасность, валидация границ и запись настроек, UI smoke, проверка кавычек и спецсимволов в заголовке блока, позиционирование контейнера виджета по CSS-селектору, дефолту и невалидным селекторам, логирование и режим отладки. Прогресс сохраняется на сервере.</div>
</div>

<script>
const API = 'api.php?checklist=admin';
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