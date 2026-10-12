<?php
declare(strict_types=1);
require_once __DIR__ . '/scan_helpers.php';

$summaries = checklist_read_all_summaries();
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Приёмочное тестирование — maximov.autocrossell</title>
<style>
:root{
  --accent:#6366f1; --blue:#0284c7; --yellow:#ca8a04; --red:#dc2626; --gray:#94a3b8;
  --bg:#f1f5f9; --card:#ffffff; --border:#e2e8f0; --text:#0f172a; --dim:#64748b;
}
body.dark{
  --bg:#0f172a; --card:#1e293b; --border:#334155; --text:#e2e8f0; --dim:#94a3b8;
}
*{box-sizing:border-box}
body{margin:0;font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
.container{max-width:960px;margin:0 auto;padding:24px 18px 60px}
header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px}
h1{margin:0;font-size:22px}
.subtitle{color:var(--dim);font-size:13px;margin-top:4px}
.theme-btn{font:inherit;padding:6px 12px;border:1px solid var(--border);background:var(--card);border-radius:8px;cursor:pointer;color:var(--text)}
.theme-btn:hover{background:#f8fafc;border-color:#cbd5e1}
body.dark .theme-btn:hover{background:#243043;border-color:#475569}
.cards{display:grid;gap:16px}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px 22px;text-decoration:none;color:var(--text);transition:box-shadow .15s,border-color .15s;display:block}
.card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);border-color:#cbd5e1}
.card-top{display:flex;align-items:center;gap:12px;margin-bottom:8px}
.card-icon{font-size:28px;line-height:1}
.card-title{font-size:17px;font-weight:600;flex:1}
.card-status{font-size:24px;line-height:1}
.card-desc{color:var(--dim);font-size:13px;margin-bottom:12px}
.card-progress{display:flex;align-items:center;gap:10px}
.progress-bar{flex:1;height:8px;background:var(--border);border-radius:99px;overflow:hidden}
.progress-fill{height:100%;background:var(--accent);transition:width .3s ease;border-radius:99px}
.card-counts{font-size:13px;color:var(--dim);white-space:nowrap}
.card.done{border-color:var(--accent);background:linear-gradient(135deg,var(--card) 0%,#eef2ff 100%)}
.card.done .card-status{color:var(--accent)}
.card.active .card-status{color:var(--yellow)}
.card.empty .card-status{color:var(--red)}
body.dark .card.done{background:linear-gradient(135deg,var(--card) 0%,#182036 100%)}
body.dark .card.active{background:linear-gradient(135deg,var(--card) 0%,#2e2606 100%)}
body.dark .card.empty{background:linear-gradient(135deg,var(--card) 0%,#2e1414 100%)}
.footer{text-align:center;color:var(--dim);font-size:12px;margin-top:32px;padding-top:16px;border-top:1px solid var(--border)}
</style>
</head>
<body>

<div class="container">
  <header>
    <div>
      <h1>🧪 Приёмочное тестирование</h1>
      <div class="subtitle"><code>maximov.autocrossell</code> — проверка модуля по чеклистам</div>
    </div>
    <button class="theme-btn" id="btnTheme" title="Тёмная/светлая тема">Тёмная тема</button>
  </header>

  <div class="cards" id="cards"></div>

  <div class="footer">Состояние чеклистов хранится на сервере (statuses_*.json) и переживает перезагрузку браузера.</div>
</div>

<script>
'use strict';
const SUMMARY = <?= json_encode($summaries, $jsonFlags) ?>;
const FILES = 'files.php';
const LINKS = {
  files:     'files.php',
  install:   'install-uninstall.php',
  component: 'component.php',
  agent:     'agent-cache.php',
  admin:     'admin-panel.php'
};

const cardsEl = document.getElementById('cards');

function render(){
  cardsEl.innerHTML = '';
  for(const [id, s] of Object.entries(SUMMARY)){
    const done = s.done;
    const pct = s.total > 0 ? Math.round(s.checked / s.total * 100) : 0;
    const cls = done ? 'done' : (s.checked > 0 ? 'active' : 'empty');
    const statusIcon = done ? '\u2705' : (s.checked > 0 ? '\u25FD' : '\u274C');
    const countsText = s.total > 0
      ? 'Проверено: ' + s.checked + ' / ' + s.total + (done ? ' \u2713 Всё проверено' : '')
      : 'Нет сохранённых данных';

    const a = document.createElement('a');
    a.className = 'card ' + cls;
    a.href = LINKS[id];
    a.innerHTML =
      '<div class="card-top">' +
        '<span class="card-icon">' + s.icon + '</span>' +
        '<span class="card-title">' + esc(s.title) + '</span>' +
        '<span class="card-status">' + statusIcon + '</span>' +
      '</div>' +
      '<div class="card-desc">' + esc(s.description) + '</div>' +
      '<div class="card-progress">' +
        '<div class="progress-bar"><div class="progress-fill" style="width:' + pct + '%"></div></div>' +
        '<span class="card-counts">' + countsText + '</span>' +
      '</div>';
    cardsEl.appendChild(a);
  }
}

function esc(s){ return String(s).replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }

render();

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
</script>
</body>
</html>
