<?php
declare(strict_types=1);
require_once __DIR__ . '/scan_helpers.php';

$tree = [
    'name' => basename(MAXIMOV_MODULE_DIR),
    'type' => 'dir',
    'path' => '',
    'children' => checklist_scan_dir(MAXIMOV_MODULE_DIR),
];
$stateFiles = checklist_read_for('files');
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$dirWritable = is_writable(__DIR__);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Чеклист файлов модуля maximov.autocrossell</title>
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
code{background:var(--bg);padding:1px 6px;border-radius:5px}
.topbar{position:sticky;top:0;z-index:20;background:var(--card);border-bottom:1px solid var(--border);padding:12px 18px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.topbar h1{margin:0 0 2px;font-size:17px}
.subtitle{color:var(--dim);font-size:12px;margin-bottom:10px;word-break:break-all}
.actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
button{font:inherit;padding:6px 12px;border:1px solid var(--border);background:#fff;border-radius:8px;cursor:pointer;color:var(--text)}
button:hover{background:#f8fafc;border-color:#cbd5e1}
button.danger{color:var(--red);border-color:#fecaca}
button.danger:hover{background:#fef2f2}
.toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:14px}
#search{padding:7px 12px;border:1px solid var(--border);border-radius:8px;width:260px;font:inherit}
#search:focus{outline:2px solid #bfdbfe;border-color:#93c5fd}
.protect{display:flex;gap:6px;align-items:center;color:var(--dim);user-select:none;cursor:pointer;font-size:13px}
.counts{font-size:13px;color:var(--dim);white-space:nowrap}
.counts.all-done{color:var(--accent);font-weight:600}
.savestate{margin-left:auto;font-size:13px;color:var(--dim)}
.savestate.err{color:var(--red);font-weight:600}
.legend{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
.chip{display:flex;align-items:center;gap:7px;padding:5px 11px;border-radius:999px;border:1px solid var(--border);background:#fff;cursor:pointer;font-size:13px;user-select:none}
.chip:hover{border-color:#cbd5e1}
.chip.active{outline:2px solid #2563eb;outline-offset:1px}
.dot{width:13px;height:13px;border-radius:50%;border:2px solid var(--gray);flex:none;background:transparent;padding:0}
main{padding:14px 18px 60px}
.panel{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:6px 0;overflow:hidden}
.dir-head{display:flex;align-items:center;gap:8px;padding:6px 14px;cursor:pointer;user-select:none;font-weight:600}
.dir-head:hover{background:#f8fafc}
.caret{display:inline-block;transition:transform .15s;color:var(--dim);width:12px;flex:none}
.dir>.dir-kids{display:none}
.dir.open>.dir-kids{display:block}
.dir.open>.dir-head .caret{transform:rotate(90deg)}
.dir.hidden{display:none}
.dbadge{margin-left:auto;font-size:12px;font-weight:400;color:var(--dim);background:var(--bg);border-radius:999px;padding:2px 9px;white-space:nowrap}
.row{display:flex;align-items:center;gap:10px;padding:5px 14px;border-left:3px solid transparent}
.row:hover{background:#f8fafc}
.row.hidden{display:none}
.row .fname{word-break:break-all}
.row .fsize{margin-left:auto;color:var(--dim);font-size:12px;white-space:nowrap}
.row input[type=checkbox]{width:16px;height:16px;accent-color:var(--accent);cursor:pointer;flex:none;margin:0}
.row .dot{cursor:pointer}
.row.st-none{border-left-color:var(--gray)}
.row.st-none .dot{background:#fff;border-color:var(--gray)}
.row.st-green{border-left-color:var(--accent);background:#eef2ff}
.row.st-green .dot{background:var(--accent);border-color:var(--accent)}
.row.st-blue{border-left-color:var(--blue);background:#f0f9ff}
.row.st-blue .dot{background:var(--blue);border-color:var(--blue)}
.row.st-yellow{border-left-color:var(--yellow);background:#fefce8}
.row.st-yellow .dot{background:var(--yellow);border-color:var(--yellow)}
.row.st-red{border-left-color:var(--red);background:#fef2f2;text-decoration:line-through}
.row.st-red .dot{background:var(--red);border-color:var(--red)}
.menu{position:fixed;z-index:50;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.14);min-width:260px;padding:4px;display:none}
.menu.show{display:block}
.menu .mi{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:7px;cursor:pointer;white-space:nowrap}
.menu .mi:hover{background:#f1f5f9}
.menu .sep{height:1px;background:var(--border);margin:4px 6px}
.banner{display:none;margin:10px 18px 0;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;color:#92400e;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px}
.banner.show{display:flex}
.banner.err{background:#fef2f2;border-color:#fecaca;color:#991b1b}
  .hint{color:var(--dim);font-size:12px;margin-top:10px;line-height:1.6}
  .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--blue);text-decoration:none;font-size:13px;margin-bottom:10px}
  .back-link:hover{text-decoration:underline}
  @media(max-width:720px){#search{width:100%}.savestate{margin-left:0}}
  body.dark button{background:var(--card)}
  body.dark button:hover{background:#243043;border-color:#475569}
  body.dark .chip{background:var(--card)}
  body.dark .chip:hover{border-color:#475569}
  body.dark .dir-head:hover, body.dark .row:hover{background:#243043}
  body.dark .menu{background:var(--card)}
  body.dark .menu .mi:hover{background:#243043}
  body.dark .row.st-green{background:#182036}
  body.dark .row.st-blue{background:#122030}
  body.dark .row.st-yellow{background:#2a2410}
  body.dark .row.st-red{background:#2a1616}
  body.dark .row.st-none .dot{background:transparent}
  body.dark .banner{background:#3a2e05;border-color:#6b5410;color:#fde68a}
</style>
</head>
<body>

<header class="topbar">
  <a class="back-link" href="./">&#8592; Назад к дашборду</a>
  <h1>Чеклист подготовки модуля <code>maximov.autocrossell</code></h1>
  <div class="subtitle">Каталог: /bitrix/modules/maximov.autocrossell &middot; исключены vendor/ и tests/ &middot; всего файлов сканируется автоматически</div>

  <div class="legend" id="legend"></div>

  <div class="actions">
    <button id="btnExpand" type="button">Развернуть всё</button>
    <button id="btnCollapse" type="button">Свернуть всё</button>
    <button id="btnReload" type="button">Обновить дерево</button>
    <button id="btnExport" type="button">Экспорт JSON</button>
    <button id="btnImport" type="button">Импорт JSON</button>
    <input type="file" id="importFile" accept=".json,application/json" hidden>
    <button id="btnReset" type="button" class="danger">Сбросить всё</button>
  </div>

  <div class="toolbar">
    <input id="search" type="search" placeholder="Поиск по имени или пути&hellip;" autocomplete="off">
    <label class="protect"><input type="checkbox" id="protection" checked> Защита от случайных изменений (подтверждение)</label>
    <button id="btnTheme" type="button" title="Тёмная/светлая тема">Тёмная тема</button>
    <span class="counts" id="counts"></span>
    <span class="savestate" id="saveState"></span>
  </div>
</header>

<div class="banner" id="banner"></div>

<main>
  <div class="panel" id="tree"></div>
  <p class="hint">
    <b>Галочка</b> — основной индикатор (файл проверен). <b>Цвет</b> — дополнительный статус проверенного файла:<br>
    <span style="display:inline-block;width:10px;height:10px;background:#6366f1;border-radius:50%;margin:0 3px"></span>корректен ·
    <span style="display:inline-block;width:10px;height:10px;background:#0284c7;border-radius:50%;margin:0 3px"></span>требует правка ·
    <span style="display:inline-block;width:10px;height:10px;background:#ca8a04;border-radius:50%;margin:0 3px"></span>убрать из релиза ·
    <span style="display:inline-block;width:10px;height:10px;background:#dc2626;border-radius:50%;margin:0 3px"></span>удалён.<br>
    Состояние хранится на сервере в файле <code>statuses_files.json</code> рядом с этой страницей и переживает перезагрузку ВМ.
  </p>
</main>

<div class="menu" id="menu"></div>

<?php if (!$dirWritable): ?>
<div class="banner err show" style="margin-top:10px">Каталог не доступен для записи — сохранение статусов работать не будет. Проверьте права на папку.</div>
<?php endif; ?>

<script>
'use strict';
const API = 'api.php?checklist=files';
const ST = {
  none:   {c:'#94a3b8', t:'Без статуса'},
  green:  {c:'#6366f1', t:'Индиго — файл корректен'},
  blue:   {c:'#0284c7', t:'Голубой — требуется правка'},
  yellow: {c:'#ca8a04', t:'Жёлтый — убрать из релиза'},
  red:    {c:'#dc2626', t:'Красный — лишний, удалён'}
};
const ORDER = ['none','green','blue','yellow','red'];

const TREE = <?= json_encode($tree, $jsonFlags) ?>;
let STATE = sanitize(<?= json_encode($stateFiles === [] ? new stdClass() : $stateFiles, $jsonFlags) ?>);

const treeEl   = document.getElementById('tree');
const menuEl   = document.getElementById('menu');
const legendEl = document.getElementById('legend');
const searchEl = document.getElementById('search');
const saveEl   = document.getElementById('saveState');
const bannerEl = document.getElementById('banner');
const protEl   = document.getElementById('protection');
const countsEl = document.getElementById('counts');

let filter = 'all';
let saveTimer = null;
const dirEntries = [];
const rowByPath = {};

function esc(s){ return String(s).replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
function fmtSize(b){ if(b < 1024) return b + ' Б'; if(b < 1048576) return (b/1024).toFixed(1) + ' КБ'; return (b/1048576).toFixed(2) + ' МБ'; }

function sanitize(obj){
  const out = {};
  for(const [k, v] of Object.entries(obj || {})){
    if(typeof k !== 'string' || k === '' || k.includes('..')) continue;
    const st = (v && typeof v === 'object' && ORDER.includes(v.status)) ? v.status : 'none';
    const ck = !!(v && v.checked);
    if(st === 'none' && !ck) continue;
    out[k] = {checked: ck, status: st};
  }
  return out;
}

function entry(p){ if(!STATE[p]) STATE[p] = {checked:false, status:'none'}; return STATE[p]; }

const allPaths = [];
(function walk(n){
  for(const c of n.children){
    if(c.type === 'dir') walk(c);
    else allPaths.push(c.path);
  }
})(TREE);

function build(container, node, depth, owner){
  for(const ch of node.children){
    if(ch.type === 'dir'){
      const wrap = document.createElement('div'); wrap.className = 'dir open';
      const head = document.createElement('div'); head.className = 'dir-head';
      head.style.paddingLeft = (10 + depth * 20) + 'px';
      head.innerHTML = '<span class="caret">&#9656;</span><span>' + esc(ch.name) + '</span><span class="dbadge">&ndash;</span>';
      const kids = document.createElement('div'); kids.className = 'dir-kids';
      const rec = {wrap, badge: head.querySelector('.dbadge'), filePaths: []};
      build(kids, ch, depth + 1, rec);
      head.addEventListener('click', () => wrap.classList.toggle('open'));
      wrap.appendChild(head); wrap.appendChild(kids); container.appendChild(wrap);
      dirEntries.push(rec);
      if(owner) owner.filePaths.push(...rec.filePaths);
    } else {
      const row = document.createElement('div');
      row.className = 'row';
      row.style.paddingLeft = (10 + depth * 20) + 'px';
      row.dataset.path = ch.path;
      row.innerHTML =
        '<span class="dot" title="Изменить статус"></span>' +
        '<input type="checkbox" title="Проверено">' +
        '<span class="fname">' + esc(ch.name) + '</span>' +
        '<span class="fsize">' + fmtSize(ch.size) + '</span>';
      const dot = row.querySelector('.dot');
      const cb  = row.querySelector('input');
      dot.addEventListener('click', e => { e.stopPropagation(); openMenu(e, ch.path, dot); });
      cb.addEventListener('change', () => { entry(ch.path).checked = cb.checked; commit(); });
      container.appendChild(row);
      rowByPath[ch.path] = row;
      if(owner) owner.filePaths.push(ch.path);
    }
  }
}
build(treeEl, TREE, 0, null);

function paintRow(path){
  const row = rowByPath[path];
  if(!row) return;
  const st = STATE[path].status;
  row.classList.remove('st-none','st-green','st-blue','st-yellow','st-red');
  row.classList.add('st-' + st);
  row.querySelector('.dot').style.background = (st === 'none') ? '' : ST[st].c;
  row.querySelector('input').checked = !!STATE[path].checked;
}

function refreshAggregates(){
  for(const d of dirEntries){
    let done = 0;
    for(const p of d.filePaths){ if(STATE[p] && STATE[p].checked) done++; }
    d.badge.textContent = done + ' / ' + d.filePaths.length;
  }
}

function collectCounts(){
  let checked = 0;
  const cnt = {none:0, green:0, blue:0, yellow:0, red:0};
  for(const p of allPaths){
    const e = STATE[p];
    if(!e) continue;
    if(e.checked) checked++;
    if(!e.checked) cnt.none++;
    if(e.status !== 'none') cnt[e.status]++;
  }
  return {total: allPaths.length, checked, cnt};
}

function buildLegend(cnt){
  legendEl.innerHTML = '';
  const defs = [
    ['all',    'Все',              null],
    ['none',   'Не проверен',      '#ffffff'],
    ['green',  'Корректен',        ST.green.c],
    ['blue',   'Требует правки',   ST.blue.c],
    ['yellow', 'Убрать из релиза', ST.yellow.c],
    ['red',    'Удалён',           ST.red.c]
  ];
  for(const [key, label, color] of defs){
    const num = key === 'all' ? cnt.total : cnt.cnt[key];
    const chip = document.createElement('div');
    chip.className = 'chip' + (filter === key ? ' active' : '');
    const ds = color ? ' style="background:' + color + ';border-color:' + color + '"' : '';
    chip.innerHTML = '<span class="dot"' + ds + '></span>' + esc(label) + ' &middot; ' + num;
    chip.addEventListener('click', () => { filter = key; applyFilters(); });
    legendEl.appendChild(chip);
  }
}

function refreshCounts(){
  const c = collectCounts();
  const done = c.checked === c.total && c.total > 0;
  countsEl.textContent = 'Файлов: ' + c.total + ' \u00b7 Проверено: ' + c.checked
    + (done ? ' \u2713 Всё проверено' : '');
  countsEl.classList.toggle('all-done', done);
  buildLegend(c);
}

function applyFilters(){
  const q = searchEl.value.trim().toLowerCase();
  for(const p of allPaths){
    const e = STATE[p];
    const okF = filter === 'all'
      || (filter === 'none' ? !(e && e.checked) : !!(e && e.status === filter));
    const okQ = q === '' || p.toLowerCase().includes(q);
    rowByPath[p].classList.toggle('hidden', !(okF && okQ));
  }
  for(const d of dirEntries){
    let vis = 0;
    for(const p of d.filePaths){
      if(!rowByPath[p].classList.contains('hidden')) vis++;
    }
    d.wrap.classList.toggle('hidden', vis === 0 && d.filePaths.length > 0);
    if(q !== '') d.wrap.classList.add('open');
  }
}

function commit(){
  refreshAggregates();
  refreshCounts();
  applyFilters();
  scheduleSave();
}

function scheduleSave(){
  clearTimeout(saveTimer);
  saveTimer = setTimeout(doSave, 350);
}

async function doSave(){
  saveEl.classList.remove('err');
  saveEl.textContent = 'Сохранение\u2026';
  try{
    const res = await fetch(API + '&action=save', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({files: STATE})
    });
    const j = await res.json();
    if(!j.ok) throw new Error(j.error || ('HTTP ' + res.status));
    saveEl.textContent = 'Сохранено \u2713 ' + j.saved_at;
  }catch(err){
    saveEl.classList.add('err');
    saveEl.textContent = 'Ошибка сохранения: ' + err.message;
  }
}

function openMenu(ev, path, anchor){
  menuEl.innerHTML = '';
  menuEl.classList.remove('show');
  const cur = entry(path).status;
  for(const key of ORDER){
    const mi = document.createElement('div'); mi.className = 'mi';
    const ds = key === 'none' ? '' : ' style="background:' + ST[key].c + ';border-color:' + ST[key].c + '"';
    mi.innerHTML = '<span class="dot"' + ds + '></span>' + esc(ST[key].t) + (cur === key ? ' \u2713' : '');
    mi.addEventListener('click', () => { closeMenu(); chooseStatus(path, key); });
    menuEl.appendChild(mi);
  }
  const sep = document.createElement('div'); sep.className = 'sep'; menuEl.appendChild(sep);
  const cancel = document.createElement('div'); cancel.className = 'mi'; cancel.textContent = 'Отмена';
  cancel.addEventListener('click', closeMenu);
  menuEl.appendChild(cancel);

  menuEl.classList.add('show');
  const r = anchor.getBoundingClientRect();
  let x = Math.min(r.left, window.innerWidth - menuEl.offsetWidth - 8);
  let y = r.bottom + 6;
  if(y + menuEl.offsetHeight > window.innerHeight) y = r.top - menuEl.offsetHeight - 6;
  menuEl.style.left = Math.max(8, x) + 'px';
  menuEl.style.top  = Math.max(8, y) + 'px';
}

function chooseStatus(path, key){
  if(entry(path).status === key) return;
  if(protectionOn() && !confirm('Пометить \u00ab' + path + '\u00bb\nкак: ' + ST[key].t + '?')) return;
  entry(path).status = key;
  paintRow(path);
  commit();
}

function closeMenu(){ menuEl.classList.remove('show'); menuEl.innerHTML = ''; }
document.addEventListener('click', e => { if(!menuEl.contains(e.target)) closeMenu(); });
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeMenu(); });

function protectionOn(){ return protEl.checked; }
try{
  const savedProt = localStorage.getItem('afc_protection');
  if(savedProt !== null) protEl.checked = savedProt === '1';
}catch(e){}
protEl.addEventListener('change', () => {
  try{ localStorage.setItem('afc_protection', protEl.checked ? '1' : '0'); }catch(e){}
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

document.getElementById('btnExpand').addEventListener('click', () => dirEntries.forEach(d => d.wrap.classList.add('open')));
document.getElementById('btnCollapse').addEventListener('click', () => dirEntries.forEach(d => d.wrap.classList.remove('open')));
document.getElementById('btnReload').addEventListener('click', () => location.reload());
searchEl.addEventListener('input', applyFilters);

document.getElementById('btnExport').addEventListener('click', () => {
  const payload = {
    meta: {tool: 'manual_file_structure_check', checklist: 'files', module: 'maximov.autocrossell', exported: new Date().toISOString()},
    files: STATE
  };
  const blob = new Blob([JSON.stringify(payload, null, 2)], {type: 'application/json'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'checklist-files-' + new Date().toISOString().slice(0,19).replace(/[:T]/g,'-') + '.json';
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
});

document.getElementById('btnImport').addEventListener('click', () => document.getElementById('importFile').click());
document.getElementById('importFile').addEventListener('change', async e => {
  const file = e.target.files[0];
  e.target.value = '';
  if(!file) return;
  try{
    const parsed = JSON.parse(await file.text());
    const files = parsed && typeof parsed === 'object' && parsed.files ? parsed.files : parsed;
    if(!files || typeof files !== 'object' || Array.isArray(files)) throw new Error('ожидается объект с ключом files');
    STATE = sanitize(files);
    await doSave();
    location.reload();
  }catch(err){
    alert('Не удалось импортировать файл: ' + err.message);
  }
});

document.getElementById('btnReset').addEventListener('click', async () => {
  if(!confirm('Сбросить ВСЕ отметки и цветовые статусы?')) return;
  if(!confirm('Действие необратимо. Точно сбросить?')) return;
  STATE = {};
  await doSave();
  location.reload();
});

(function checkOrphans(){
  const orphans = Object.keys(STATE).filter(p => !allPaths.includes(p));
  if(orphans.length === 0) return;
  bannerEl.classList.add('show');
  bannerEl.innerHTML = 'Внимание: ' + orphans.length + ' запис(ей) ссылаются на отсутствующие файлы (удалены из модуля?). ';
  const btn = document.createElement('button');
  btn.textContent = 'Убрать устаревшие записи';
  btn.addEventListener('click', async () => {
    for(const p of orphans) delete STATE[p];
    await doSave();
    bannerEl.classList.remove('show');
  });
  bannerEl.appendChild(btn);
})();

for(const p in rowByPath){ if(STATE[p]) paintRow(p); }
refreshAggregates();
refreshCounts();
applyFilters();
</script>
</body>
</html>
