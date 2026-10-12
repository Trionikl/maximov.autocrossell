<?php
declare(strict_types=1);
require_once __DIR__ . '/scan_helpers.php';
$state = checklist_read_for('agent');
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Агент: блокировка, целостность, расписание — maximov.autocrossell</title>
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
    <h1>⚡ Агент: блокировка, целостность, расписание</h1>
    <div class="progress">Прогресс: <span id="completed">0</span> / <span id="total">0</span></div>
  </div>
  <div style="display:flex;gap:12px;align-items:center">
    <div class="bar"><div class="fill" id="fill"></div></div>
    <button class="theme-btn" id="btnTheme" title="Тёмная/светлая тема">Тёмная тема</button>
    <button class="btn-reset" id="resetBtn">Сбросить</button>
  </div>
</header>

<div class="section">
  <h2><span class="badge">1</span> Конкурентная блокировка (AgentGuard)</h2>
  <p>Проверка защиты от параллельного запуска пересчёта через замок СУБД.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="a-1"><div class="step-text">Защита от параллельного запуска пересчёта (замок AgentGuard)<pre>-- Как проверить:
Выполните готовый скрипт в консоли сервера. Первый процесс занимает замок на 3 секунды, а второй пытается запуститься одновременно с ним:

( php -r '$_SERVER["DOCUMENT_ROOT"]=(getenv("DOCUMENT_ROOT") ?: "/path/to/site"); require $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php"; \Bitrix\Main\Loader::includeModule("maximov.autocrossell"); $c=\Bitrix\Main\Application::getConnection(); if(!$c->lock(\Maximov\Autocrossell\AgentGuard::LOCK_NAME, 0)) exit(1); sleep(3);' &gt;/dev/null 2&gt;&amp;1 &amp; HOLDER=$!; sleep 0.5; php -r '$_SERVER["DOCUMENT_ROOT"]=(getenv("DOCUMENT_ROOT") ?: "/path/to/site"); require $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php"; \Bitrix\Main\Loader::includeModule("maximov.autocrossell"); $res=\Maximov\Autocrossell\RecalculationAgent::execute(true); echo (is_array($res) &amp;&amp; ($res["status"]??null)==="busy") ? "AGENT LOCK TEST: OK\n" : "AGENT LOCK TEST: FAIL\n";'; wait $HOLDER )

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
В консоли выводится строго:
AGENT LOCK TEST: OK

Это подтверждает:
1. Во время выполнения пересчёта занят глобальный замок СУБД.
2. Второй (параллельный) запуск пересчёта корректно отклонён со статусом busy («Расчёт уже выполняется»).
3. Замок автоматически освобождается после завершения процесса.
*/</pre></div></div>
</div>

<div class="section">
  <h2><span class="badge">2</span> Целостность и нормализация пар</h2>
  <p>Автономная проверка таблицы рекомендаций в БД: порог веса, уникальность и порядок ID.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="a-2"><div class="step-text">Проверка целостности пар товаров в базе данных<pre>-- Как проверить:
Выполните команду в консоли сервера (если таблица пуста, скрипт сам запустит пересчёт):

php -r '$_SERVER["DOCUMENT_ROOT"]=(getenv("DOCUMENT_ROOT") ?: "/path/to/site"); require $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php"; \Bitrix\Main\Loader::includeModule("maximov.autocrossell"); $result="PAIRS INTEGRITY: FAIL\n"; try { $check=(int)\Maximov\Autocrossell\AutocrossellPairsTable::getCount()&gt;0; if(!$check){ $run=\Maximov\Autocrossell\RecalculationAgent::execute(true); $status=is_array($run)?($run["status"]??null):null; if($status==="empty"){ echo "PAIRS INTEGRITY: NO_DATA\n"; exit; } if($status!=="ok"){ echo $result; exit; } $check=(int)\Maximov\Autocrossell\AutocrossellPairsTable::getCount()&gt;0; if(!$check){ echo "PAIRS INTEGRITY: NO_DATA\n"; exit; } } $thr=(int)\Bitrix\Main\Config\Option::get("maximov.autocrossell","min_threshold","2"); $conn=\Bitrix\Main\Application::getConnection(); $sql="SELECT IF((SELECT COUNT(*) FROM b_maximov_autocrossell_pairs)=0 OR (SELECT COUNT(*) FROM b_maximov_autocrossell_pairs WHERE PRODUCT_ID_1&gt;=PRODUCT_ID_2)&gt;0 OR (SELECT COUNT(*) FROM b_maximov_autocrossell_pairs WHERE WEIGHT&lt;$thr)&gt;0 OR (SELECT COUNT(*) FROM (SELECT LEAST(PRODUCT_ID_1,PRODUCT_ID_2) a, GREATEST(PRODUCT_ID_1,PRODUCT_ID_2) b FROM b_maximov_autocrossell_pairs GROUP BY a,b HAVING COUNT(*)&gt;1) t)&gt;0, \"PAIRS INTEGRITY: FAIL\", \"PAIRS INTEGRITY: OK\") AS res"; echo $conn-&gt;query($sql)-&gt;fetch()["res"]."\n"; } catch (\Throwable $e) { echo $result; }'

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
В консоли выводится строго:
PAIRS INTEGRITY: OK
(или PAIRS INTEGRITY: NO_DATA, если в базе ещё нет оплаченных/завершённых заказов с 2+ товарами, а пары не набрали минимальный порог)

Это подтверждает:
1. Меньший ID товара всегда записан первым (PRODUCT_ID_1 &lt; PRODUCT_ID_2).
2. В таблице нет зеркальных дублей комбинаций (A, B) и (B, A).
3. Вес всех пар не ниже минимального порога min_threshold.
4. При пустой таблице пересчёт выполняется автоматически без ручных действий.
5. Если пересчёт занят, завершился ошибкой или таблица нарушена, выводится PAIRS INTEGRITY: FAIL.
*/</pre></div></div>
</div>

<div class="section">
  <h2><span class="badge">3</span> Расписание агента (AgentScheduler)</h2>
  <p>Проверка синхронизации окна и интервала с системной записью в b_agent.</p>

  <div class="step"><input type="checkbox" class="chk" data-check="a-3"><div class="step-text">Синхронизация настроек с системным агентом Битрикса<pre>-- Как проверить:
Выполните скрипт в консоли сервера (скрипт временно меняет настройки, проверяет запись b_agent и возвращает исходные значения):

php -r '$_SERVER["DOCUMENT_ROOT"]=(getenv("DOCUMENT_ROOT") ?: "/path/to/site"); require $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php"; use Bitrix\Main\Config\Option; \Bitrix\Main\Loader::includeModule("maximov.autocrossell"); $names=["agent_window_start","agent_window_end","agent_interval"]; $old=[]; foreach($names as $n){$old[$n]=Option::get("maximov.autocrossell",$n,"");} $ok=false; try{ Option::set("maximov.autocrossell","agent_window_start","23:30"); Option::set("maximov.autocrossell","agent_window_end","23:45"); Option::set("maximov.autocrossell","agent_interval","12"); $synced=\Maximov\Autocrossell\AgentScheduler::syncAgentOptions(); $rec=\Maximov\Autocrossell\AgentScheduler::getAgentRecord(); $cnt=0; $res=\CAgent::GetList([],["MODULE_ID"=&gt;"maximov.autocrossell"]); while($res-&gt;Fetch()){$cnt++;} $next=null; if(is_array($rec)&amp;&amp;isset($rec["NEXT_EXEC"])){$next=\DateTime::createFromFormat("d.m.Y H:i:s",$rec["NEXT_EXEC"]);} $ok=$synced &amp;&amp; $cnt===1 &amp;&amp; is_array($rec) &amp;&amp; (int)$rec["AGENT_INTERVAL"]===43200 &amp;&amp; $rec["IS_PERIOD"]==="Y" &amp;&amp; $next instanceof \DateTime &amp;&amp; $next-&gt;format("H:i")==="23:30" &amp;&amp; $next-&gt;getTimestamp()&gt;time(); }catch(\Throwable $e){$ok=false;}finally{ foreach($names as $n){Option::set("maximov.autocrossell",$n,$old[$n]);} \Maximov\Autocrossell\AgentScheduler::syncAgentOptions(); } echo $ok ? "AGENT SCHEDULE: OK\n" : "AGENT SCHEDULE: FAIL\n";'

/* ОЖИДАЕМЫЙ РЕЗУЛЬТАТ:
В консоли выводится строго:
AGENT SCHEDULE: OK

Это подтверждает:
1. AgentScheduler::syncAgentOptions() корректно обновляет системного агента.
2. В таблице b_agent ровно 1 запись модуля (дублирование исключено).
3. Интервал пересчитан в секунды (12 часов = 43200 сек).
4. Время следующего запуска (NEXT_EXEC) выставлено на начало окна 23:30.
5. Скрипт возвращает исходные настройки модуля в блоке finally.
*/</pre></div></div>
</div>

<div class="footer">Чеклист агента maximov.autocrossell: блокировка AgentGuard, целостность пар, расписание AgentScheduler. Прогресс сохраняется на сервере.</div>
</div>

<script>
const API = 'api.php?checklist=agent';
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