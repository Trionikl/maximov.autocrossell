'use strict';
/* Разбор <pre> в шагах чеклистов: визуальное выделение SQL / терминала /
 * «ОЖИДАЕМЫЙ РЕЗУЛЬТАТ» / «Как проверить». Работает по innerHTML, сохраняя
 * уже размеченные <span class="c">. Если маркеров нет — блок не трогаем. */
(function () {
  var MARKERS = [
    { re: /<span class="c">\s*--\s*SQL-запрос[^<]*<\/span>/gi, type: 'sql' },
    { re: /<span class="c">\s*#\s*Команд[^<]*<\/span>/gi, type: 'terminal' },
    { re: /(?:^|\n)[ \t]*--[ \t]*Как проверить[^\n]*/gi, type: 'info' }
  ];
  var EXPECTED_RE = /\/\*[ \t]*ОЖИДАЕМЫЙ[ \t]+РЕЗУЛЬТАТ[\s\S]*?\*\//gi;

  var LABELS = {
    sql: 'SQL',
    terminal: 'Терминал',
    expected: 'Ожидаемый результат',
    info: 'Как проверить'
  };

  function collect(html) {
    var hits = [];
    MARKERS.forEach(function (m) {
      var re = new RegExp(m.re.source, 'gi'), mm;
      while ((mm = re.exec(html)) !== null) {
        hits.push({ index: mm.index, length: mm[0].length, type: m.type });
      }
    });
    var em;
    EXPECTED_RE.lastIndex = 0;
    while ((em = EXPECTED_RE.exec(html)) !== null) {
      hits.push({ index: em.index, length: em[0].length, type: 'expected' });
    }
    hits.sort(function (a, b) { return a.index - b.index; });
    return hits;
  }

  function render(type, body, isRaw) {
    var cls = isRaw ? 'plain' : type;
    var head = isRaw ? '' : '<div class="blk-label">' + LABELS[type] + '</div>';
    return '<div class="blk blk--' + cls + '">' + head +
      '<div class="blk-body">' + body + '</div></div>';
  }

  function wrapHead(pre) {
    var st = pre.parentNode;
    if (!st || st.querySelector('.step-head')) { return; }
    var head = document.createElement('span');
    head.className = 'step-head';
    while (st.firstChild && st.firstChild !== pre) { head.appendChild(st.firstChild); }
    st.insertBefore(head, pre);
    st.classList.add('parsed');
  }

  function transform(pre) {
    var html = pre.innerHTML;
    var hits = collect(html);
    if (hits.length === 0) { return; }

    // Отбрасываем пересекающиеся хиты (оставляем первый по позиции)
    var clean = [];
    var lastEnd = -1;
    hits.forEach(function (h) {
      if (h.index >= lastEnd) { clean.push(h); lastEnd = h.index + h.length; }
    });

    var out = '';
    var cursor = 0;
    clean.forEach(function (h, i) {
      var before = html.substring(cursor, h.index).replace(/^\s+|\s+$/g, '');
      if (before !== '') { out += render('plain', before, true); }
      // Маркер владеет контентом до следующего маркера (или до конца)
      var end = (i + 1 < clean.length) ? clean[i + 1].index : html.length;
      var body = html.substring(h.index, end).replace(/^\s+|\s+$/g, '');
      // «ОЖИДАЕМЫЙ РЕЗУЛЬТАТ» и «Как проверить» — это проза, а не код: режем
      // литеральные маркеры, чтобы не дублировать заголовки .blk-label и не
      // показывать синтаксис комментариев.
      if (h.type === 'expected') {
        body = body
          .replace(/^\/\*[ \t]*ОЖИДАЕМЫЙ[ \t]+РЕЗУЛЬТАТ[^\n]*\n?/i, '')
          .replace(/\*\/[ \t]*$/, '')
          .replace(/^\s+|\s+$/g, '');
      }
      if (h.type === 'info') {
        body = body
          .replace(/^--[ \t]*Как проверить[^\n]*\n?/i, '')
          .replace(/^\s+|\s+$/g, '');
      }
      out += render(h.type, body, false);
      cursor = end;
    });
    var tail = html.substring(cursor).replace(/^\s+|\s+$/g, '');
    if (tail !== '') { out += render('plain', tail, true); }

    pre.innerHTML = out;
    pre.classList.add('has-blocks');
    wrapHead(pre);
  }

  function run() {
    var nodes = document.querySelectorAll('.step-text pre');
    for (var i = 0; i < nodes.length; i++) { transform(nodes[i]); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
