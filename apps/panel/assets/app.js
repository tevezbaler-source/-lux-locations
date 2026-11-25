/* Otel Paneli · Lux Locations. Derleme gerektirmeyen tek dosya; veriler api.php'den gelir. */
(function () {
  'use strict';

  const S = { csrf: '', user: null, egitim: false, consts: null, lastAudit: 0, view: null, dirty: false, pollTimer: null };
  const $app = document.getElementById('app');

  /* ---------- yardımcılar ---------- */
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const nf2 = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const nf0 = new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 0 });
  const money = (v, cur) => (v === null || v === undefined || v === '') ? '—' : nf2.format(+v) + (cur ? ' ' + curSym(cur) : '');
  const money0 = (v, cur) => (v === null || v === undefined) ? '—' : nf0.format(Math.round(+v)) + (cur ? ' ' + curSym(cur) : '');
  const kur = (v) => (v === null || v === undefined || v === '') ? '' : money(v) + ' TL';
  const curSym = (c) => ({ EUR: '€', USD: '$', TRY: 'TL' }[c] || c);
  const curTag = (c) => c ? `<span class="cur cur-${esc(c)}">${esc(c)}</span>` : '';
  const pad = (n) => String(n).padStart(2, '0');
  const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const today = () => iso(new Date());
  const addDays = (s, n) => { const d = new Date(s + 'T00:00:00'); d.setDate(d.getDate() + n); return iso(d); };
  const trDate = (s) => s ? s.slice(8, 10) + '.' + s.slice(5, 7) + '.' + s.slice(0, 4) : '';
  const trDT = (s) => s ? trDate(s.slice(0, 10)) + ' ' + s.slice(11, 16) : '';
  const nightsBetween = (a, b) => (a && b) ? Math.round((new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000) : 0;
  const DOW = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
  const MON = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
  const statusBadge = (s) => `<span class="badge st-${esc(s)}">${esc(S.consts.statuses[s] || s)}</span>`;
  const can = (p) => !!(S.user && S.user.perms.includes(p));
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const parseHash = () => {
    const h = location.hash.replace(/^#\/?/, '');
    const [path, query] = h.split('?');
    const params = Object.fromEntries(new URLSearchParams(query || ''));
    return { parts: path.split('/').filter(Boolean), params };
  };
  const go = (h) => { if (location.hash === h) route(); else location.hash = h; };

  function toast(msg, kind = '') {
    const t = document.createElement('div');
    t.className = 'toast ' + kind;
    t.textContent = msg;
    document.getElementById('toasts').appendChild(t);
    setTimeout(() => t.remove(), kind === 'err' ? 7000 : 4500);
  }

  async function api(a, params = {}, body = null) {
    const url = 'api.php?a=' + encodeURIComponent(a) + Object.entries(params).map(([k, v]) => `&${encodeURIComponent(k)}=${encodeURIComponent(v ?? '')}`).join('');
    const opt = body === null ? { credentials: 'same-origin' } : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': S.csrf }, body: JSON.stringify(body) };
    let res, data;
    try {
      res = await fetch(url, opt);
      data = await res.json();
    } catch (e) {
      throw Object.assign(new Error('Sunucuya ulaşılamadı. İnternet bağlantısını kontrol edin.'), { status: 0 });
    }
    if (!res.ok) {
      if (res.status === 401 && data.login && a !== 'login') { S.user = null; renderLogin(); }
      if (res.status === 419) await loadSession();
      throw Object.assign(new Error(data.error || 'Hata'), { status: res.status, data });
    }
    return data;
  }

  function modal(title, bodyHtml, { ok = 'Tamam', cancel = 'Vazgeç', danger = false, onOpen } = {}) {
    return new Promise((resolve) => {
      const bg = document.createElement('div');
      bg.className = 'modal-bg';
      bg.innerHTML = `<div class="modal" role="dialog" aria-modal="true"><h3>${esc(title)}</h3><div class="mb">${bodyHtml}</div><div class="err-msg"></div>
        <div class="foot">${cancel ? `<button data-x="0">${esc(cancel)}</button>` : ''}<button class="${danger ? 'danger' : 'primary'}" data-x="1">${esc(ok)}</button></div></div>`;
      document.body.appendChild(bg);
      const close = (v) => { bg.remove(); resolve(v); };
      bg.addEventListener('click', (e) => {
        if (e.target === bg) close(null);
        const x = e.target.closest('[data-x]');
        if (!x) return;
        if (x.dataset.x === '0') return close(null);
        const vals = {};
        qsa('[name]', bg).forEach((el) => { vals[el.name] = el.type === 'checkbox' ? el.checked : el.value; });
        close(vals);
      });
      bg.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(null); });
      const f = qs('input, select, textarea', bg) || qs('[data-x="1"]', bg);
      if (f) f.focus();
      if (onOpen) onOpen(bg);
    });
  }
  /* ---------- yazarken öneri listesi ---------- */
  const normTr = (t) => String(t || '').toLocaleLowerCase('tr').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i');
  // input altında, yazdıkça süzülen liste. items: [{label, html?, ...}], onPick(item)
  function suggestBox(input, getItems, onPick, { showAllOnFocus = true, max = 12, extra = null } = {}) {
    const wrap = input.parentElement; wrap.classList.add('sugg-wrap');
    const ul = document.createElement('ul'); ul.className = 'sugg'; ul.hidden = true; ul.setAttribute('role', 'listbox');
    wrap.appendChild(ul);
    input.setAttribute('autocomplete', 'off');
    let shown = [], idx = -1;
    const render = () => {
      const q = normTr(input.value.trim());
      if (!q && !showAllOnFocus) { ul.hidden = true; return; }
      const all = getItems() || [];
      const t1 = [], t2 = [], t3 = [];
      all.forEach((it) => {
        const n = normTr(it.label), k = normTr(it.label + ' ' + (it.keys || ''));
        if (!q || n.startsWith(q)) t1.push(it); else if (k.split(/[\s,]+/).some((w) => w.startsWith(q))) t2.push(it); else if (k.includes(q)) t3.push(it);
      });
      shown = t1.concat(t2, t3).slice(0, max); idx = -1;
      const ex = extra && q ? extra(input.value.trim(), shown) : null;
      if (ex) shown.push(ex);
      ul.innerHTML = shown.map((it, i) => `<li role="option" data-i="${i}">${it.html || esc(it.label)}</li>`).join('');
      ul.hidden = !shown.length;
    };
    const pick = (i) => { const it = shown[i]; if (!it) return; input.value = it.label; ul.hidden = true; onPick(it); };
    const mark = () => qsa('li', ul).forEach((li, i) => li.classList.toggle('on', i === idx));
    input.addEventListener('focus', render);
    input.addEventListener('input', render);
    input.addEventListener('blur', () => setTimeout(() => { ul.hidden = true; }, 150));
    input.addEventListener('keydown', (e) => {
      if (ul.hidden) return;
      if (e.key === 'ArrowDown') { idx = Math.min(shown.length - 1, idx + 1); mark(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { idx = Math.max(0, idx - 1); mark(); e.preventDefault(); }
      else if (e.key === 'Enter' && idx >= 0) { pick(idx); e.preventDefault(); e.stopPropagation(); }
      else if (e.key === 'Escape') { ul.hidden = true; e.stopPropagation(); }
    });
    ul.addEventListener('mousedown', (e) => { const li = e.target.closest('li'); if (li) { e.preventDefault(); pick(+li.dataset.i); } });
    return { refresh: render };
  }
  // Seçim kutularını (select) yazarak aranabilir yapar; select gizlenir, değeri korunur.
  function selectCombo(root, selector, placeholder, keysOf = null) {
    qsa(selector, root).forEach((sel) => {
      if (sel.dataset.combo || sel.disabled) return;
      sel.dataset.combo = '1';
      const inp = document.createElement('input');
      inp.type = 'text'; inp.placeholder = placeholder;
      inp.value = sel.value ? (sel.selectedOptions[0]?.textContent || '') : '';
      sel.hidden = true; sel.after(inp);
      const items = () => [...sel.options].filter((o) => o.value).map((o) => ({ label: o.textContent, value: o.value, keys: keysOf ? keysOf(o.value) : (o.dataset.keys || '') }));
      const set = (v) => { if (sel.value !== v) { sel.value = v; sel.dispatchEvent(new Event('change', { bubbles: true })); } };
      suggestBox(inp, items, (it) => set(it.value));
      inp.addEventListener('change', () => {
        const t = normTr(inp.value.trim());
        if (!t && sel.querySelector('option[value=""]')) { set(''); return; }
        const hit = items().find((it) => normTr(it.label) === t);
        if (hit) set(hit.value); else inp.value = sel.value ? (sel.selectedOptions[0]?.textContent || '') : '';
      });
    });
  }
  const hotelCombo = (root) => selectCombo(root, 'select[name="hotel_id"]', 'Otel adını yazın…');
  const agencyCombo = (root, list = []) => selectCombo(root, 'select[name="agency_id"]', 'Acente adını yazın…', (id) => { const a = list.find((x) => x.id == id); return a ? `${a.aliases || ''} ${a.country || ''}` : ''; });
  // Tablo satırlarını yazdıkça süzer
  function tableFilter(input, tbody, countEl = null) {
    const run = () => {
      const q = normTr(input.value.trim()); let n = 0;
      qsa('tr', tbody).forEach((tr) => { const ok = !q || normTr(tr.textContent).includes(q); tr.hidden = !ok; if (ok) n++; });
      if (countEl) countEl.textContent = q ? `${n} sonuç` : '';
    };
    input.addEventListener('input', run);
  }
  // Daha önce yazılanlardan öneri (sunucudan bir kez alınır)
  const _gecmis = {};
  function historySuggest(input, field, params = {}, defaults = []) {
    if (!input || input.disabled) return;
    const key = field + JSON.stringify(params);
    let list = _gecmis[key] || [];
    const items = () => { const seen = new Set(); return list.concat(defaults).filter((v) => { const k = normTr(v); if (seen.has(k)) return false; seen.add(k); return true; }).map((v) => ({ label: v })); };
    suggestBox(input, items, () => input.dispatchEvent(new Event('input', { bubbles: true })), { max: 10 });
    if (!_gecmis[key]) api('gecmis', { f: field, ...params }).then((r) => { list = _gecmis[key] = r.values || []; }).catch(() => {});
  }
  const COUNTRIES = ['Romanya', 'Moldova', 'Bulgaristan', 'Sırbistan', 'Kosova', 'Arnavutluk', 'Kuzey Makedonya', 'Karadağ', 'Bosna-Hersek', 'Hırvatistan', 'Slovenya', 'Macaristan', 'Yunanistan', 'Polonya', 'Çekya', 'Slovakya', 'Ukrayna', 'Rusya', 'Belarus', 'Gürcistan', 'Azerbaycan', 'Kazakistan', 'Özbekistan', 'Türkmenistan', 'Kırgızistan', 'İran', 'Irak', 'Suriye', 'Lübnan', 'Ürdün', 'İsrail', 'Filistin', 'Mısır', 'Libya', 'Tunus', 'Cezayir', 'Fas', 'Suudi Arabistan', 'Birleşik Arap Emirlikleri', 'Katar', 'Kuveyt', 'Bahreyn', 'Umman', 'Yemen', 'Pakistan', 'Afganistan', 'Hindistan', 'Bangladeş', 'Endonezya', 'Malezya', 'Çin', 'Japonya', 'Güney Kore', 'Almanya', 'Fransa', 'İtalya', 'İspanya', 'Portekiz', 'Hollanda', 'Belçika', 'Avusturya', 'İsviçre', 'İngiltere', 'İrlanda', 'İsveç', 'Norveç', 'Danimarka', 'Finlandiya', 'Litvanya', 'Letonya', 'Estonya', 'ABD', 'Kanada', 'Meksika', 'Brezilya', 'Arjantin', 'Avustralya', 'Güney Afrika', 'Nijerya', 'Kıbrıs', 'KKTC', 'Türkiye'];

  const confirmBox = (title, text, ok = 'Evet', danger = false) => modal(title, `<p>${text}</p>`, { ok, danger }).then((v) => v !== null);

  /* ---------- oturum ---------- */
  async function loadSession() {
    const s = await api('session');
    S.csrf = s.csrf; S.user = s.user; S.egitim = s.egitim; S.consts = s.consts;
    return s;
  }

  function renderLogin(setup = false) {
    stopPoll();
    document.body.classList.remove('egitim');
    $app.innerHTML = `<form class="login" autocomplete="on">
      <h1>Otel Paneli</h1>
      ${NEXT && !setup ? '<div class="small" style="background:#eef4f8;border-radius:6px;padding:6px 8px">Site yönetimine girmek için panel kullanıcınızla giriş yapın.</div>' : ''}
      <div class="muted">${setup ? 'İlk kurulum: yönetici hesabını oluşturun. Bu hesapla diğer çalışanları ekleyeceksiniz.' : 'Lux Locations ekip paneli. Kendi kullanıcı adınızla girin.'}</div>
      ${setup ? '<label class="f">Ad soyad<input name="name" required></label>' : ''}
      <label class="f">Kullanıcı adı<input name="username" autocomplete="username" required autocapitalize="none"></label>
      <label class="f">Şifre<input name="password" type="password" autocomplete="${setup ? 'new-password' : 'current-password'}" required></label>
      ${setup ? '<div class="small muted">Kullanıcı adı: küçük harf, Türkçe karakter ve boşluk olmadan (ör. ogun). Şifre en az 8 karakter.</div>' : ''}
      <div class="err-msg"></div>
      <button class="primary" type="submit">${setup ? 'Hesabı oluştur ve gir' : 'Giriş'}</button>
    </form>`;
    const form = qs('form', $app);
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = Object.fromEntries(new FormData(form));
      try {
        await api(setup ? 'setup' : 'login', {}, d);
        await loadSession();
        start();
      } catch (err) { qs('.err-msg', form).textContent = err.message; }
    });
    qs('input', form).focus();
  }

  /* ---------- iskelet ---------- */
  // Üst menüdeki hızlı arama
  function quickSearch(inp) {
    if (!inp) return;
    let res = [], timer = null, lastQ = '';
    const sb = suggestBox(inp, () => res.map((r) => ({ label: r.label, keys: r.sub + ' ' + inp.value, r,
      html: `<span class="qs-t">${esc(r.t)}</span><span class="nm">${esc(r.label)}<br><span class="small muted">${esc(r.sub || '')}</span></span>` })),
    (it) => { inp.value = ''; inp.blur(); location.hash = it.r.href; }, { showAllOnFocus: false, max: 20 });
    inp.addEventListener('input', () => {
      clearTimeout(timer);
      const q = inp.value.trim();
      if (q.length < 2) { res = []; return; }
      timer = setTimeout(async () => {
        if (q === lastQ) return; lastQ = q;
        try { const r = await api('ara', { q }); if (inp.value.trim() === q) { res = r.results || []; sb.refresh(); } } catch (e) { /* sessiz */ }
      }, 250);
    });
    if (quickSearch.bound) return; quickSearch.bound = true;
    document.addEventListener('keydown', (e) => { const inp = qs('#qSearch'); if (inp && e.key === '/' && !/input|textarea|select/i.test(document.activeElement?.tagName || '')) { e.preventDefault(); inp.focus(); } });
  }

  const NAV = [
    ['', 'Oteller', null], ['talepler', 'Talepler', 'talep'], ['acenteler', 'Acenteler', null], ['kurlar', 'Kurlar', null], ['raporlar', 'Raporlar', 'report'], ['notlar', 'Notlar', null],
    ['otel-yonetimi', 'Otel listesi', 'hotel'], ['kullanicilar', 'Kullanıcılar', 'users'], ['kayit', 'İşlem kaydı', 'audit'], ['egitim', 'Eğitim', null],
  ];

  // Açık/koyu tema: seçim bu tarayıcıda hatırlanır
  const themeGet = () => { try { return localStorage.getItem('lx-tema') || ''; } catch (e) { return ''; } };
  const themeSet = (t) => { document.documentElement.dataset.theme = t; try { localStorage.setItem('lx-tema', t); } catch (e) { /* yok */ } };
  document.documentElement.dataset.theme = themeGet();

  function shell() {
    document.body.classList.toggle('egitim', S.egitim);
    const cur = parseHash().parts[0] || '';
    const navCur = cur === 'otel' || cur === 'grup' ? '' : cur === 'acente' ? 'acenteler' : cur;
    $app.innerHTML = `
      <header class="top">
        <a class="brand" href="#/">Lux Locations · Otel Paneli</a>
        <nav>${NAV.filter(([, , p]) => !p || can(p)).map(([h, t]) => `<a href="#/${h}" class="${navCur === h ? 'on' : ''}">${t}</a>`).join('')}</nav>
        <div class="qsearch"><input id="qSearch" type="search" placeholder="Ara: otel, acente, LUX kodu, misafir…" autocomplete="off" aria-label="Hızlı arama"></div>
        <div class="user"><a href="#/hesabim" style="color:#fff">${esc(S.user.name)}</a> <span style="opacity:.75">${esc(S.user.role_name)}</span>
          <button id="btnTheme" class="theme-btn" title="Açık / koyu görünüm" aria-label="Açık / koyu görünüm">${themeGet() === 'dark' ? '☀' : '☾'}</button><button id="btnMode">${S.egitim ? 'Gerçek panele dön' : 'Eğitim bölümü'}</button><button id="btnOut">Çıkış</button></div>
      </header>
      ${S.egitim ? `<div class="train-bar">EĞİTİM BÖLÜMÜ: Örnek verilerle çalışıyorsunuz. Burada yaptıklarınız gerçek kayıtlara dokunmaz.
        <span class="spacer"></span><a href="#/egitim" style="color:#fff;text-decoration:underline">Eğitim rehberi</a>
        <button id="btnReset">Örnek verileri baştan yükle</button><button id="btnMode2">Gerçek panele dön</button></div>` : ''}
      <main id="main"></main>`;
    quickSearch(qs('#qSearch'));
    qs('#btnTheme').onclick = (e) => { const t = themeGet() === 'dark' ? '' : 'dark'; themeSet(t); e.currentTarget.textContent = t === 'dark' ? '☀' : '☾'; };
    qs('#btnOut').onclick = async () => { await api('logout', {}, {}); await loadSession(); A.log = []; A.status = null; asstToggle(false).catch(() => {}); asstHide(); stopPoll(); renderLogin(); };
    const toggle = async () => {
      if (S.dirty && !(await confirmBox('Kaydedilmemiş değişiklik', 'Bu sayfadaki değişiklikler kaydedilmedi. Yine de geçilsin mi?'))) return;
      S.dirty = false;
      const r = await api('mode', {}, { egitim: !S.egitim });
      S.egitim = r.egitim; S.lastAudit = 0; A.log = []; A.mode = S.egitim; asstRender();
      toast(S.egitim ? 'Eğitim bölümüne geçtiniz: örnek veriler.' : 'Gerçek panele döndünüz.', 'ok');
      go(S.egitim ? '#/egitim' : '#/');
      route();
    };
    qs('#btnMode').onclick = toggle;
    if (S.egitim) {
      qs('#btnMode2').onclick = toggle;
      qs('#btnReset').onclick = async () => {
        if (!(await confirmBox('Örnek verileri sıfırla', 'Eğitim bölümündeki tüm değişiklikler silinip örnek veriler baştan yüklenecek. Gerçek kayıtlar etkilenmez.'))) return;
        await api('training_reset', {}, {});
        S.lastAudit = 0; toast('Örnek veriler yeniden yüklendi.', 'ok'); route();
      };
    }
    return qs('#main');
  }

  /* ---------- canlı güncelleme ---------- */
  function stopPoll() { if (S.pollTimer) clearInterval(S.pollTimer); S.pollTimer = null; }
  function startPoll() {
    stopPoll();
    S.pollTimer = setInterval(poll, 12000);
  }
  async function poll() {
    if (document.hidden || !S.user) return;
    try {
      const r = await api('changes', { since: S.lastAudit });
      if (r.egitim !== S.egitim) { S.egitim = r.egitim; route(); return; }
      const first = S.lastAudit === 0;
      S.lastAudit = r.last || 0;
      if (first) return;
      const others = r.items.filter((i) => +i.user_id !== S.user.id && ['grup', 'otel', 'acente', 'sozlesme', 'kontenjan', 'kur'].includes(i.entity));
      if (!others.length) return;
      others.slice(-3).forEach((i) => toast(`${i.user_name || 'Biri'}: ${i.summary}`));
      if (S.view && S.view.onRemote) S.view.onRemote(others);
    } catch (e) { /* sessizce tekrar dener */ }
  }

  /* ---------- yönlendirme ---------- */
  async function route() {
    if (!S.user) return;
    const { parts, params } = parseHash();
    const main = shell();
    if (A.el && A.mode !== S.egitim) { A.log = []; A.mode = S.egitim; asstRender(); }
    S.view = null; S.dirty = false;
    window.scrollTo(0, 0);
    try {
      switch (parts[0] || '') {
        case '': return await viewGrid(main, params);
        case 'otel': return await viewHotel(main, +parts[1], params);
        case 'grup': return await viewBooking(main, parts[1], params);
        case 'talepler': return await viewRequests(main, params);
        case 'acenteler': return await viewAgencies(main);
        case 'acente': return await viewAgency(main, +parts[1]);
        case 'kurlar': return await viewFx(main);
        case 'raporlar': return await viewReports(main, params);
        case 'notlar': return await viewNotes(main, params);
        case 'otel-yonetimi': return await viewHotelAdmin(main);
        case 'kullanicilar': return await viewUsers(main);
        case 'kayit': return await viewAudit(main, params);
        case 'hesabim': return viewAccount(main);
        case 'egitim': return viewGuide(main);
        default: main.innerHTML = '<div class="empty">Sayfa bulunamadı. <a href="#/">Otellere dön</a></div>';
      }
    } catch (e) {
      main.innerHTML = `<div class="empty">${esc(e.message)}<br><br><a href="#/">Otellere dön</a></div>`;
    }
  }
  window.addEventListener('hashchange', () => route());
  window.addEventListener('beforeunload', (e) => { if (S.dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ================= OTEL IZGARASI ================= */
  function rangePresets() {
    const t = today(); const d = new Date(t + 'T00:00:00');
    const dow = (d.getDay() + 6) % 7;
    const weekStart = addDays(t, -dow);
    const monthStart = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-01`;
    const nm = new Date(d.getFullYear(), d.getMonth() + 1, 1);
    const nm2 = new Date(d.getFullYear(), d.getMonth() + 2, 1);
    const y = d.getMonth() === 11 && d.getDate() > 10 ? d.getFullYear() : d.getFullYear() - (d.getMonth() < 1 ? 1 : 0);
    return [
      ['Bu hafta', weekStart, addDays(weekStart, 7)],
      ['30 gün', t, addDays(t, 30)],
      ['Bu ay', monthStart, iso(nm)],
      ['Gelecek ay', iso(nm), iso(nm2)],
      ['Yılbaşı', `${y}-12-26`, `${y + 1}-01-05`],
      ['90 gün', t, addDays(t, 90)],
    ];
  }

  async function viewGrid(main, p) {
    const pre = rangePresets();
    const from = p.from || pre[1][1], to = p.to || pre[1][2];
    main.innerHTML = '<div class="muted">Oteller yükleniyor…</div>';
    const d = await api('grid', { from, to, q: p.q || '', region: p.region || '', hepsi: p.hepsi || '' });
    const hs = d.hotels;
    const tot = hs.reduce((a, h) => { const s = h.summary; a.g += s.groups; a.e += s.profit_eur; a.u += s.profit_usd; a.t += s.profit_try; a.err += s.errors; a.pend += s.pending; a.unk += s.unknown; return a; }, { g: 0, e: 0, u: 0, t: 0, err: 0, pend: 0, unk: 0 });
    const setQ = (o) => { const n = Object.assign({ from, to, q: p.q || '', region: p.region || '', hepsi: p.hepsi || '' }, o); go('#/?' + new URLSearchParams(Object.entries(n).filter(([, v]) => v)).toString()); };
    main.innerHTML = `
      <div class="row"><h1>Oteller</h1><span class="spacer"></span>
        ${can('booking') ? '<a class="btn primary" href="#/grup/yeni">+ Yeni grup</a>' : ''}
        ${can('hotel') ? '<button id="addHotel">+ Otel ekle</button>' : ''}</div>
      <div class="muted small">${trDate(from)} – ${trDate(addDays(to, -1))} gecelerinde konaklayan gruplar. ${d.rate ? `Hesapta kullanılan kur (${trDate(d.rate.rate_date)}): 1 € = ${money(d.rate.eur_try)} TL, 1 $ = ${money(d.rate.usd_try)} TL.${d.tcmb && d.tcmb.USD ? ` <a href="#/kurlar">Merkez Bankası (${trDate(d.tcmb.date)}): € ${money(d.tcmb.EUR.sell)}, $ ${money(d.tcmb.USD.sell)}</a>` : ''}` : '<b style="color:var(--err)">Kur girilmemiş: kâr hesapları için <a href="#/kurlar">Kurlar</a> sayfasından günün kurunu girin.</b>'}</div>
      ${(d.options || []).length ? `<div class="panel" style="border-color:var(--warn);background:var(--warn-bg);margin-top:10px"><b>Opsiyon hatırlatma:</b> ${d.options.map((o) => {
        const late = o.option_until < today(), now = o.option_until === today();
        return `<a href="#/grup/${o.id}" style="margin-right:12px;white-space:nowrap">${esc(o.hotel)} · ${esc(o.agency || '—')} · ${esc(o.code || '#' + o.id)} <b style="color:${late || now ? 'var(--err)' : 'var(--warn)'}">${late ? 'opsiyon geçti ' + trDate(o.option_until) : now ? 'opsiyon bugün bitiyor' : 'opsiyon yarın bitiyor'}</b></a>`;
      }).join('')}</div>` : ''}
      <div class="filters">
        <div class="quick">${pre.map(([l, f, t]) => `<button data-f="${f}" data-t="${t}" class="${f === from && t === to ? 'on' : ''}">${l}</button>`).join('')}</div>
        <label class="f">Başlangıç<input type="date" id="fFrom" value="${from}"></label>
        <label class="f">Bitiş<input type="date" id="fTo" value="${to}"></label>
        <label class="f">Bölge<select id="fReg"><option value="">Tümü</option>${d.regions.map((r) => `<option ${r === p.region ? 'selected' : ''}>${esc(r)}</option>`).join('')}</select></label>
        <label class="f">Ara (otel, acente, kod)<input id="fQ" value="${esc(p.q || '')}" placeholder="ör. Tudan, LUX6301"></label>
      </div>
      <div class="totals">
        <div class="card"><div class="l">Grup</div><div class="v">${tot.g}</div></div>
        <div class="card"><div class="l">Kâr EUR</div><div class="v" style="${tot.e < 0 ? 'color:var(--err)' : ''}">${money0(tot.e, 'EUR')}</div></div>
        <div class="card"><div class="l">Kâr USD</div><div class="v">${money0(tot.u, 'USD')}</div></div>
        <div class="card"><div class="l">Kâr TL</div><div class="v">${money0(tot.t, 'TRY')}</div></div>
        <div class="card"><div class="l">Hatalı / bekleyen</div><div class="v"><span style="color:var(--err)">${tot.err}</span> / <span style="color:var(--warn)">${tot.pend}</span></div></div>
      </div>
      ${tot.unk ? `<div class="banner">${tot.unk} grubun kârı hesaplanamadı (fiyat veya kur eksik); toplamlara katılmadı.</div>` : ''}
      ${d.hidden ? `<div class="small muted" style="margin:0 0 10px">Grubu ya da sözleşmesi olmayan ${d.hidden} otel burada gösterilmiyor; "Ara" kutusuna ya da en üstteki aramaya adını yazınca çıkar. <a href="#" id="showAllH">Hepsini göster</a></div>` : (p.hepsi ? '<div class="small muted" style="margin:0 0 10px"><a href="#" id="showUsedH">Yalnız çalışılan otelleri göster</a></div>' : '')}
      ${hs.length ? `<div class="hotels">${hs.map(hotelCard).join('')}</div>` : `<div class="empty">${p.q || p.region ? 'Aramaya uyan otel yok.' : 'Henüz otel yok.' + (can('hotel') ? ' Sağ üstteki <b>+ Otel ekle</b> ile başlayın.' : '')}</div>`}`;
    qsa('.quick button', main).forEach((b) => b.onclick = () => setQ({ from: b.dataset.f, to: b.dataset.t }));
    qs('#fFrom', main).onchange = (e) => setQ({ from: e.target.value });
    qs('#fTo', main).onchange = (e) => setQ({ to: e.target.value });
    qs('#fReg', main).onchange = (e) => setQ({ region: e.target.value });
    qs('#fQ', main).onkeydown = (e) => { if (e.key === 'Enter') setQ({ q: e.target.value.trim() }); };
    const ah = qs('#addHotel', main); if (ah) ah.onclick = () => hotelForm();
    const sa = qs('#showAllH', main); if (sa) sa.onclick = (e) => { e.preventDefault(); setQ({ hepsi: '1' }); };
    const su = qs('#showUsedH', main); if (su) su.onclick = (e) => { e.preventDefault(); setQ({ hepsi: '' }); };
    qsa('.hcard', main).forEach((c) => c.onclick = () => go(`#/otel/${c.dataset.id}?from=${from}`));
    S.view = { onRemote: () => viewGrid(main, p) };
  }

  function hotelCard(h) {
    const s = h.summary;
    const flags = [];
    if (s.errors) flags.push(`<span class="flag err">${s.errors} hatalı grup</span>`);
    if (s.over_nights) flags.push(`<span class="flag err">Kontenjan aşımı ${s.over_nights} gece</span>`);
    if (s.unknown) flags.push(`<span class="flag warn">${s.unknown} hesaplanamadı</span>`);
    if (s.pending) flags.push(`<span class="flag warn">${s.pending} konfirme bekliyor</span>`);
    if (s.cancelled) flags.push(`<span class="flag info">${s.cancelled} iptal</span>`);
    if (!flags.length && s.groups) flags.push('<span class="flag ok">Tamam</span>');
    const cls = (s.errors || s.over_nights || s.profit_eur < 0) ? 'f-err' : (s.pending || s.unknown) ? 'f-warn' : s.groups ? 'f-ok' : '';
    const pct = s.max_allotment ? Math.min(100, Math.round(100 * s.max_reserved / s.max_allotment)) : (s.max_reserved ? 100 : 0);
    const initials = h.name.split(/\s+/).filter((w) => !/^(hotel|otel|the)$/i.test(w)).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
    return `<div class="hcard ${cls}" data-id="${h.id}" tabindex="0">
      ${h.cover ? `<div class="cover" style="background-image:url('${esc(encodeURI(h.cover))}')" role="img" aria-label="${esc(h.name)}"></div>` : `<div class="cover none">${esc(initials)}</div>`}
      <div><div class="name">${esc(h.name)} ${h.stars ? `<span class="muted small">${'★'.repeat(+h.stars)}</span>` : ''}</div>
        <div class="meta">${esc(h.region || '')} · ${s.groups} grup</div></div>
      <div><div class="sub"><span>En dolu gece: <b>${s.max_reserved}</b>${s.max_allotment !== null ? ` / ${s.max_allotment} oda` : ' oda (kontenjan yok)'}</span></div>
        <div class="bar ${s.over_nights ? 'over' : ''}"><i style="width:${pct}%"></i></div></div>
      <div><div class="profit ${s.profit_eur < 0 ? 'neg' : ''}">${s.groups ? money0(s.profit_eur, 'EUR') : '—'}</div>
        <div class="sub"><span>${money0(s.profit_usd, 'USD')}</span><span>${money0(s.profit_try, 'TRY')}</span></div>
        <div class="sub"><span>Alış ${money0(s.buy_eur, 'EUR')}</span><span>Satış ${money0(s.sell_eur, 'EUR')}</span></div></div>
      <div class="flags">${flags.join('')}</div>
    </div>`;
  }

  /* ================= OTEL DETAYI ================= */
  async function viewHotel(main, id, p) {
    const days = +(p.days || 31);
    const from = p.from || addDays(today(), -3);
    main.innerHTML = '<div class="muted">Yükleniyor…</div>';
    const h = await api('hotel', { id, from, days });
    const dates = h.nights.map((n) => n.date);
    const nCols = dates.length;
    const colOf = (d) => Math.max(0, Math.min(nCols, nightsBetween(from, d)));
    const nav = (o) => go(`#/otel/${id}?` + new URLSearchParams(Object.assign({ from, days }, o)).toString());

    let cal = `<div class="cal" style="grid-template-columns: 190px repeat(${nCols}, minmax(38px, 1fr))">`;
    cal += `<div class="c lbl day">Gece</div>` + dates.map((d) => { const x = new Date(d + 'T00:00:00'); const we = [0, 6].includes(x.getDay()); return `<div class="c day ${we ? 'we' : ''}">${x.getDate()}<div class="dw">${DOW[x.getDay()]}${x.getDate() === 1 || d === from ? ' ' + MON[x.getMonth()] : ''}</div></div>`; }).join('');
    cal += `<div class="c lbl">Dolu / kontenjan</div>` + h.nights.map((n) => { const over = n.allotment !== null && n.reserved > n.allotment; const full = n.allotment !== null && n.reserved === n.allotment && n.reserved > 0; return `<div class="c use ${over ? 'over' : full ? 'full' : ''}" title="${trDate(n.date)}: ${n.reserved} oda ayrıldı${n.allotment !== null ? ', kontenjan ' + n.allotment : ''}">${n.reserved}${n.allotment !== null ? '<span class="muted">/' + n.allotment + '</span>' : ''}</div>`; }).join('');
    h.bookings.forEach((b, i) => {
      const row = i + 3;
      const s = colOf(b.check_in), e = colOf(b.check_out);
      cal += `<div class="c lbl" style="grid-row:${row}" title="${esc(b.agency_name || '')}"><a href="#/grup/${b.id}">${esc(b.agency_name || '—')}</a></div>`;
      for (let c = 0; c < nCols; c++) cal += `<div class="c" style="grid-row:${row};grid-column:${c + 2}"></div>`;
      if (e > s) cal += `<div class="bk ${b.status} ${b.calc.has_error ? 'err' : ''}" data-id="${b.id}" style="grid-row:${row};grid-column:${s + 2} / ${e + 2}" title="${esc(b.code || '')} ${esc(b.agency_name || '')}: ${trDate(b.check_in)}–${trDate(b.check_out)}, ${b.calc.rooms} oda, ${esc(S.consts.statuses[b.status])}">${esc(b.code || '')} ${b.calc.rooms} oda · ${esc(S.consts.statuses[b.status])}</div>`;
    });
    if (can('booking')) {
      const row = h.bookings.length + 3;
      cal += `<div class="c lbl muted" style="grid-row:${row}">+ Yeni grup (güne tıkla)</div>` + dates.map((d, c) => `<div class="c slot" style="grid-row:${row};grid-column:${c + 2}" data-d="${d}" title="${trDate(d)} girişli yeni grup"></div>`).join('');
    }
    cal += '</div>';

    const active = h.bookings.filter((b) => b.status !== 'iptal');
    const sum = active.reduce((a, b) => { if (b.calc.profit_eur !== null) { a.e += b.calc.profit_eur; a.u += b.calc.profit_usd; a.t += b.calc.profit_try; } return a; }, { e: 0, u: 0, t: 0 });

    main.innerHTML = `
      <div class="row"><a href="#/">← Oteller</a>${h.site_slug ? `<span class="spacer"></span><a href="/oteller/${encodeURIComponent(h.site_slug)}/" target="_blank" rel="noopener">Sitedeki sayfası ↗</a>` : ''}</div>
      ${h.cover ? `<div class="hhead"><div class="cover" style="background-image:url('${esc(encodeURI(h.cover))}')"></div><div class="small muted">${esc(h.region || '')}${h.address ? '<br>' + esc(h.address) : ''}</div></div>` : ''}
      <div class="row"><h1>${esc(h.name)} ${h.stars ? `<span class="muted small">${'★'.repeat(+h.stars)}</span>` : ''}</h1>${h.active == 1 ? '' : '<span class="badge st-iptal">Pasif</span>'}<span class="spacer"></span>
        ${can('booking') ? `<a class="btn primary" href="#/grup/yeni?otel=${h.id}">+ Bu otele grup</a>` : ''}${can('hotel') ? '<button id="editH">Otel bilgileri</button>' : ''}</div>
      <div class="muted">${esc(h.region || '')}${h.address ? ' · ' + esc(h.address) : ''}${h.email ? ' · ' + esc(h.email) : ''}${h.contacts ? ' · İletişim: ' + esc(h.contacts) : ''}</div>
      ${h.notes ? `<div class="small" style="margin-top:4px;white-space:pre-wrap">${esc(h.notes)}</div>` : ''}
      <div class="filters">
        <button id="prev">← ${days} gün</button><label class="f">Başlangıç<input type="date" id="hFrom" value="${from}"></label><button id="next">${days} gün →</button>
        <label class="f">Gösterilen<select id="hDays">${[14, 31, 62, 92].map((n) => `<option ${n === days ? 'selected' : ''} value="${n}">${n} gün</option>`).join('')}</select></label>
        <button id="tdy">Bugün</button>
        <span class="spacer"></span>
        <div class="small"><b>Bu aralıkta kâr:</b> ${money0(sum.e, 'EUR')} · ${money0(sum.u, 'USD')} · ${money0(sum.t, 'TRY')}</div>
      </div>
      <div class="cal-wrap">${cal}</div>
      <div class="small muted" style="margin-top:6px">Renkler: <span class="badge st-teklif">Teklif</span> <span class="badge st-opsiyon">Opsiyon</span> <span class="badge st-konfirme">Konfirme</span> <span class="badge st-konaklamada">Konaklamada</span> <span class="badge st-kapandi">Kapandı</span> <span class="badge st-iptal">İptal</span> · Kırmızı çerçeve: hatalı grup. Teklifler kontenjandan düşmez.</div>

      <h2>Bu aralıktaki gruplar</h2>
      ${h.bookings.length ? `<div class="tbl-wrap"><table><thead><tr><th>Kod</th><th>Acente</th><th>Giriş</th><th>Çıkış</th><th class="num">Gece</th><th class="num">Oda</th><th>Durum</th><th class="num">Alış</th><th class="num">Satış</th><th class="num">Kâr €</th><th class="num">Kâr $</th><th class="num">Kâr TL</th><th>Uyarı</th></tr></thead><tbody>
        ${h.bookings.map((b) => `<tr class="click" data-id="${b.id}"><td>${esc(b.code || '')}</td><td>${esc(b.agency_name || '')}</td><td class="nowrap">${trDate(b.check_in)}</td><td class="nowrap">${trDate(b.check_out)}</td><td class="num">${b.calc.nights}</td><td class="num">${b.calc.rooms}</td><td>${statusBadge(b.status)}</td>
          <td class="num">${money(b.calc.buy_net)} ${curTag(b.calc.buy_cur)}</td><td class="num">${money(b.calc.sell_net)} ${curTag(b.calc.sell_cur)}</td>
          <td class="num" style="${b.calc.profit_eur < 0 ? 'color:var(--err)' : ''}">${money(b.calc.profit_eur)}</td><td class="num">${money(b.calc.profit_usd)}</td><td class="num">${money(b.calc.profit_try)}</td>
          <td>${b.calc.warnings.filter((w) => w.level !== 'info').map((w) => `<span class="flag ${w.level === 'error' ? 'err' : 'warn'}" title="${esc(w.msg)}">${esc(w.msg.length > 38 ? w.msg.slice(0, 36) + '…' : w.msg)}</span>`).join(' ')}</td></tr>`).join('')}
      </tbody></table></div>` : '<div class="empty">Bu tarihlerde grup yok.</div>'}

      <h2>Sözleşme fiyatları (alış)</h2>
      <div class="small muted" style="margin-bottom:6px">Otelle anlaşılan dönem fiyatları. Grup açılırken alış buradan otomatik gelir; farklı girilirse panel uyarır.</div>
      <div class="tbl-wrap"><table><thead><tr><th>Dönem</th><th>Döviz</th><th>Pansiyon</th><th class="num">SNGL</th><th class="num">DBL</th><th class="num">TWIN</th><th class="num">TRPL</th><th class="num">QUAD</th><th>Free kuralı</th><th>Faturalı</th><th>Görüşülen / not</th><th></th></tr></thead><tbody>
        ${h.rates.map((r) => `<tr><td class="nowrap">${trDate(r.date_from)} – ${trDate(r.date_to)}</td><td>${curTag(r.currency)}</td><td>${esc(r.board || '')}</td>${['sngl', 'dbl', 'twin', 'trpl', 'quad'].map((k) => `<td class="num">${r[k] !== null ? money(r[k]) : '—'}</td>`).join('')}
          <td>${r.free_every ? `Her ${r.free_every} odada 1 ${esc(r.free_type || 'DBL')}` : '—'}</td><td>${r.invoiced == 1 ? 'Evet' : 'Hayır'}</td><td class="small">${esc(r.contact || '')}${r.note ? '<br>' + esc(r.note) : ''}</td>
          <td class="nowrap">${can('hotel') ? `<button class="link" data-er="${r.id}">Düzenle</button><button class="link" data-dr="${r.id}" style="color:var(--err)">Sil</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="12" class="muted">Henüz sözleşme fiyatı yok.</td></tr>'}
      </tbody></table></div>
      ${can('hotel') ? '<div class="actions"><button id="addRate">+ Sözleşme fiyatı ekle</button></div>' : ''}

      <h2>Kontenjan (otelin bize verdiği oda)</h2>
      <div class="tbl-wrap"><table><thead><tr><th>Tarihler</th><th class="num">Oda</th><th>Not</th><th></th></tr></thead><tbody>
        ${h.allotments.map((a) => `<tr><td>${trDate(a.date_from)} – ${trDate(a.date_to)}</td><td class="num">${a.rooms}</td><td>${esc(a.note || '')}</td><td class="nowrap">${can('hotel') ? `<button class="link" data-ea="${a.id}">Düzenle</button><button class="link" data-da="${a.id}" style="color:var(--err)">Sil</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">Kontenjan girilmemiş; doluluk yalnız ayrılan oda olarak gösterilir.</td></tr>'}
      </tbody></table></div>
      ${can('hotel') ? '<div class="actions"><button id="addAl">+ Kontenjan ekle</button></div>' : ''}
      ${h.history.length ? `<h2>Otel kaydı geçmişi</h2><div class="panel hist">${h.history.map((x) => `<div class="h"><b>${esc(x.user_name || '')}</b> · <span class="muted">${trDT(x.at)}</span> · ${esc(x.summary)}</div>`).join('')}</div>` : ''}`;

    qs('#prev', main).onclick = () => nav({ from: addDays(from, -days) });
    qs('#next', main).onclick = () => nav({ from: addDays(from, days) });
    qs('#tdy', main).onclick = () => nav({ from: addDays(today(), -3) });
    qs('#hFrom', main).onchange = (e) => nav({ from: e.target.value });
    qs('#hDays', main).onchange = (e) => nav({ days: e.target.value });
    qsa('.bk', main).forEach((el) => el.onclick = () => go('#/grup/' + el.dataset.id));
    qsa('tr.click', main).forEach((el) => el.onclick = () => go('#/grup/' + el.dataset.id));
    qsa('.slot', main).forEach((el) => el.onclick = () => go(`#/grup/yeni?otel=${h.id}&giris=${el.dataset.d}`));
    const reload = () => viewHotel(main, id, p);
    if (can('hotel')) {
      qs('#editH', main).onclick = () => hotelForm(h, reload);
      qs('#addRate', main).onclick = () => rateForm(h, null, reload);
      qs('#addAl', main).onclick = () => allotForm(h, null, reload);
      qsa('[data-er]', main).forEach((b) => b.onclick = () => rateForm(h, h.rates.find((r) => r.id == b.dataset.er), reload));
      qsa('[data-dr]', main).forEach((b) => b.onclick = async () => { if (await confirmBox('Sözleşme fiyatını sil', 'Bu dönem fiyatı silinsin mi? Eski gruplar etkilenmez, yalnız karşılaştırma için kullanılmaz.', 'Sil', true)) { await api('rate_delete', {}, { id: b.dataset.dr }); reload(); } });
      qsa('[data-ea]', main).forEach((b) => b.onclick = () => allotForm(h, h.allotments.find((r) => r.id == b.dataset.ea), reload));
      qsa('[data-da]', main).forEach((b) => b.onclick = async () => { if (await confirmBox('Kontenjanı sil', 'Bu kontenjan kaydı silinsin mi?', 'Sil', true)) { await api('allotment_delete', {}, { id: b.dataset.da }); reload(); } });
    }
    S.view = { onRemote: (items) => { if (items.some((i) => ['grup', 'otel', 'sozlesme', 'kontenjan'].includes(i.entity))) reload(); } };
  }

  async function hotelForm(h = null, after = null) {
    const v = h || { name: '', stars: 4, region: 'Laleli', address: '', email: '', contacts: '', notes: '', active: 1 };
    const r = await modal(h ? 'Otel bilgileri' : 'Yeni otel', `<div class="grid-form">
      <label class="f wide">Otel adı<input name="name" value="${esc(v.name)}" required></label>
      <label class="f">Yıldız<select name="stars">${[1, 2, 3, 4, 5].map((n) => `<option ${+v.stars === n ? 'selected' : ''}>${n}</option>`).join('')}</select></label>
      <label class="f">Bölge<input name="region" value="${esc(v.region || '')}" list="regions"></label>
      <label class="f wide">Adres<input name="address" value="${esc(v.address || '')}"></label>
      <label class="f wide">Rezervasyon e-postası (fişler buraya gider; birden fazlaysa virgülle)<input name="email" type="text" inputmode="email" value="${esc(v.email || '')}" placeholder="ör. reservation@otel.com"></label>
      <label class="f wide">İletişim kişileri (ad, telefon)<textarea name="contacts">${esc(v.contacts || '')}</textarea></label>
      <label class="f wide">Notlar<textarea name="notes">${esc(v.notes || '')}</textarea></label>
      ${h ? `<label class="chk"><input type="checkbox" name="active" ${+v.active ? 'checked' : ''}> Aktif (pasif oteller ızgarada görünmez)</label>` : ''}
      ${h ? '' : '<input type="hidden" name="site_slug" value=""><div class="small muted wide" id="hnHint" style="grid-column:1/-1"></div>'}
      </div><datalist id="regions"><option>Laleli</option><option>Sultanahmet</option><option>Taksim</option><option>Bakırköy</option><option>Şişli</option><option>Şehir dışı</option></datalist>`, { ok: 'Kaydet', onOpen: h ? null : (bg) => hotelNameSuggest(bg) });
    if (!r) return;
    try {
      const saved = await api('hotel_save', {}, Object.assign({}, r, h ? { id: h.id, version: h.version } : {}));
      toast('Otel kaydedildi.', 'ok');
      if (after) after(); else go('#/otel/' + saved.id);
    } catch (e) { toast(e.message, 'err'); }
  }

  // Yeni otel formu: adı yazarken sitedeki ve paneldeki oteller çıkar; sitedeki seçilirse bilgiler dolar.
  function hotelNameSuggest(bg) {
    const name = qs('[name="name"]', bg), hint = qs('#hnHint', bg), slug = qs('[name="site_slug"]', bg);
    let list = [];
    const sb = suggestBox(name, () => list.map((x) => ({ ...x, label: x.name,
      html: `${x.cover ? `<span class="th" style="background-image:url('${esc(encodeURI(x.cover))}')"></span>` : '<span class="th"></span>'}<span class="nm">${esc(x.name)}${x.stars ? ` <span class="muted">${'★'.repeat(+x.stars)}</span>` : ''}<br><span class="small muted">${esc(x.region || '')}${x.panel_id ? ' · <b>panelde var</b>' : x.site ? ' · sitede' : ''}</span></span>` })), (it) => {
      slug.value = '';
      if (it.panel_id) { hint.innerHTML = `"${esc(it.name)}" panelde zaten var. <a href="#/otel/${it.panel_id}" class="go-h">Otelin sayfasını aç</a>`; return; }
      slug.value = it.slug || '';
      if (it.stars) qs('[name="stars"]', bg).value = String(it.stars);
      if (it.region) qs('[name="region"]', bg).value = it.region;
      if (it.address) qs('[name="address"]', bg).value = it.address;
      hint.textContent = 'Sitedeki otel seçildi: yıldız, bölge, adres ve kapak fotoğrafı siteden alındı. E-posta ve iletişim bilgisini ekleyip kaydedin.';
    });
    name.addEventListener('input', () => { slug.value = ''; hint.textContent = ''; });
    hint.addEventListener('click', (e) => { if (e.target.closest('.go-h')) { const x = qs('[data-x="0"]', bg); if (x) x.click(); } });
    api('hotel_names').then((r) => { list = r.hotels || []; if (document.activeElement === name) sb.refresh(); }).catch(() => {});
  }

  async function rateForm(h, r = null, after) {
    const v = r || { date_from: today(), date_to: addDays(today(), 180), currency: 'USD', board: 'BB', invoiced: 1, free_every: 25, free_type: 'DBL' };
    const res = await modal(r ? 'Sözleşme fiyatını düzenle' : 'Sözleşme fiyatı ekle', `<div class="grid-form">
      <label class="f">Başlangıç<input type="date" name="date_from" value="${v.date_from}"></label>
      <label class="f">Bitiş<input type="date" name="date_to" value="${v.date_to}"></label>
      <label class="f">Döviz<select name="currency">${S.consts.currencies.map((c) => `<option ${c === v.currency ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
      <label class="f">Pansiyon<select name="board">${['BB', 'HB', 'FB', 'RO'].map((c) => `<option ${c === v.board ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
      ${['sngl', 'dbl', 'twin', 'trpl', 'quad'].map((k) => `<label class="f">${k.toUpperCase()} (oda/gece)<input name="${k}" inputmode="decimal" value="${v[k] ?? ''}" placeholder="${k === 'twin' ? '= DBL' : k === 'quad' ? '= DBL x 2' : ''}"></label>`).join('')}
      <label class="f">Free: her kaç odada 1<input name="free_every" inputmode="numeric" value="${v.free_every ?? ''}" placeholder="boş = free yok"></label>
      <label class="f">Free oda tipi<select name="free_type">${S.consts.room_types.map((c) => `<option ${c === (v.free_type || 'DBL') ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
      <label class="chk"><input type="checkbox" name="invoiced" ${+v.invoiced ? 'checked' : ''}> Faturalı fiyat</label>
      <label class="f wide">Otelde görüşülen kişi<input name="contact" value="${esc(v.contact || '')}" placeholder="ör. Veli Bey"></label>
      <label class="f wide">Not<input name="note" value="${esc(v.note || '')}" placeholder="ör. Veli Bey ile görüşmeye istinaden"></label></div>`, { ok: 'Kaydet', onOpen: (bg) => historySuggest(qs('[name="contact"]', bg), 'contact', { hotel_id: h.id }) });
    if (!res) return;
    try { await api('rate_save', {}, Object.assign(res, { hotel_id: h.id }, r ? { id: r.id, version: r.version } : {})); toast('Sözleşme fiyatı kaydedildi.', 'ok'); after(); } catch (e) { toast(e.message, 'err'); }
  }

  async function allotForm(h, a = null, after) {
    const v = a || { date_from: today(), date_to: addDays(today(), 90), rooms: 25, note: '' };
    const res = await modal(a ? 'Kontenjanı düzenle' : 'Kontenjan ekle', `<div class="grid-form">
      <label class="f">Başlangıç<input type="date" name="date_from" value="${v.date_from}"></label>
      <label class="f">Bitiş<input type="date" name="date_to" value="${v.date_to}"></label>
      <label class="f">Oda sayısı (her gece)<input name="rooms" inputmode="numeric" value="${v.rooms}"></label>
      <label class="f wide">Not<input name="note" value="${esc(v.note || '')}" placeholder="ör. Otel 15 odayı 10'a düşürdü"></label></div>`, { ok: 'Kaydet' });
    if (!res) return;
    try { await api('allotment_save', {}, Object.assign(res, { hotel_id: h.id }, a ? { id: a.id, version: a.version } : {})); toast('Kontenjan kaydedildi.', 'ok'); after(); } catch (e) { toast(e.message, 'err'); }
  }

  /* ================= GRUP FORMU ================= */
  /* ---------- Misafir listesi ve belgeler (voucher, otel talep fişi) ---------- */
  function guestSection(el, b) {
    const RT = S.consts.room_types;
    const PAX = [['ADL', 'Yetişkin'], ['CHD', 'Çocuk'], ['INF', 'Bebek']];
    const PER = { SNGL: 1, DBL: 2, TWIN: 2, TRPL: 3, QUAD: 4 };
    const ed = can('booking');
    const x = b.extra || {};
    const row = (g = {}) => `<tr>
      <td><input data-k="room_no" inputmode="numeric" value="${g.room_no ?? ''}" style="width:60px"></td>
      <td><select data-k="room_type"><option value=""></option>${RT.map((t) => `<option ${t === g.room_type ? 'selected' : ''}>${t}</option>`).join('')}</select></td>
      <td><input data-k="name" value="${esc(g.name || '')}" placeholder="Ad Soyad" style="min-width:200px"></td>
      <td><select data-k="pax">${PAX.map(([v, l]) => `<option value="${v}" ${v === (g.pax || 'ADL') ? 'selected' : ''}>${l}</option>`).join('')}</select></td>
      <td><input data-k="note" value="${esc(g.note || '')}" placeholder="ör. pasaport no, özel istek"></td>
      ${ed ? '<td><button type="button" class="link rm" title="Sil" style="color:var(--err)">✕</button></td>' : ''}</tr>`;
    const docUrl = (t) => `belge.php?tip=${t}&id=${b.id}`;
    el.innerHTML = `<h2>Misafirler ve belgeler</h2>
      <div class="panel">
        <div class="row" style="margin-bottom:8px"><span class="muted small">İsim listesi, varış-dönüş ve notlar konfirme sonrası da düzenlenebilir. Belgeler yeni sekmede açılır, oradan yazdırıp PDF kaydedebilirsiniz.</span><span class="spacer"></span>
          <a class="btn" href="${docUrl('talep')}" target="_blank" rel="noopener">Otel talep fişi</a>
          ${ed ? '<button type="button" class="btn" id="mSend">Otele e-postayla gönder</button>' : ''}
          <a class="btn primary" href="${docUrl('voucher')}" target="_blank" rel="noopener">Voucher</a>
          ${can('cari') ? `<a class="btn" href="fatura.php?id=${b.id}" target="_blank" rel="noopener">Fatura taslağı</a>` : ''}</div>
        <div id="mBox" style="display:none" class="panel"></div>
        ${(b.mail_log || []).length ? `<div class="small" style="margin:0 0 8px"><b>Otele giden e-postalar:</b> ${b.mail_log.map((l) => `<div class="${+l.ok ? '' : 'neg'}">${trDT(l.at)} · ${esc(l.state || '')} · ${esc(l.to_addr)}${l.cc ? ' (cc ' + esc(l.cc) + ')' : ''} · ${esc(l.user_name || '')}${+l.ok ? (l.error ? ' · ' + esc(l.error) : ' · gönderildi') : ' · <b>gönderilemedi:</b> ' + esc(l.error || '')}</div>`).join('')}</div>` : ''}
        <div class="grid-form">
          <label class="f">Grup sorumlusu / lider<input name="contact_name" value="${esc(x.contact_name || '')}" placeholder="ör. Mr. Ardit Krasniqi"></label>
          <label class="f">Oda kategorisi<input name="room_category" value="${esc(x.room_category || '')}" placeholder="STANDART ROOM"></label>
          <label class="f">Milliyet<input name="nationality" value="${esc(x.nationality || '')}" placeholder="ör. Kosova"></label>
          <label class="f">Varış (uçuş, saat)<input name="arrival_info" value="${esc(x.arrival_info || '')}" placeholder="ör. 12.12 TK1018 14:30 IST"></label>
          <label class="f">Dönüş (uçuş, saat)<input name="departure_info" value="${esc(x.departure_info || '')}" placeholder="ör. 15.12 TK1019 18:10 IST"></label>
        </div>
        <div class="grid-form" style="margin-top:8px">
          <label class="f">Otele not (talep fişinde görünür)<textarea name="hotel_note" placeholder="ör. Odalar yan yana olsun, geç giriş 23:00">${esc(x.hotel_note || '')}</textarea></label>
          <label class="f">Voucher notu (acente ve misafir görür)<textarea name="voucher_note" placeholder="ör. Transfer dahildir">${esc(x.voucher_note || '')}</textarea></label>
        </div>
        <div class="tbl-wrap" style="margin-top:10px"><table><thead><tr><th>Oda no</th><th>Oda tipi</th><th>Ad Soyad</th><th>Tip</th><th>Not</th>${ed ? '<th></th>' : ''}</tr></thead><tbody id="gB">${(b.guests || []).map(row).join('')}</tbody></table></div>
        <div class="small muted" id="gCount"></div>
        ${ed ? `<div class="row" style="margin-top:8px"><button type="button" id="gAdd">+ Misafir</button><button type="button" id="gPaste">Listeyi yapıştır</button><span class="spacer"></span><button type="button" class="primary" id="gSave">Misafir listesini kaydet</button></div>
        <div id="gPasteBox" style="display:none;margin-top:8px"><label class="f">Her satıra bir isim yazın ya da Excel / WhatsApp'tan yapıştırın. Odalara grubun oda tiplerine göre (DBL 2, TRPL 3 kişi...) sırayla yerleştirilir. Boş satır yeni odaya geçirir.<textarea id="gPasteTxt" rows="8"></textarea></label>
          <div class="row" style="margin-top:6px"><label class="small"><input type="checkbox" id="gReplace" checked> Mevcut listenin yerine koy</label><span class="spacer"></span><button type="button" class="primary" id="gPasteOk">Listeye ekle</button></div></div>` : ''}
      </div>`;
    const tb = qs('#gB', el);
    if (ed) {
      historySuggest(qs('[name="contact_name"]', el), 'contact_name');
      historySuggest(qs('[name="room_category"]', el), 'room_category', {}, ['STANDART ROOM', 'SUPERIOR ROOM', 'DELUXE ROOM', 'FAMILY ROOM', 'SUITE']);
      historySuggest(qs('[name="nationality"]', el), 'nationality', {}, COUNTRIES);
    }
    const count = () => { const n = qsa('tr', tb).filter((tr) => qs('[data-k=name]', tr).value.trim()).length; qs('#gCount', el).textContent = n ? `${n} kişi` : 'Henüz isim yok.'; };
    count();
    if (!ed) { qsa('input, select, textarea', el).forEach((i) => { i.disabled = true; }); return; }
    tb.addEventListener('click', (e) => { if (e.target.closest('.rm')) { e.target.closest('tr').remove(); count(); } });
    tb.addEventListener('input', count);
    qs('#gAdd', el).onclick = () => {
      const last = qsa('tr', tb).pop();
      const no = last ? (+qs('[data-k=room_no]', last).value || 1) : 1;
      const type = last ? qs('[data-k=room_type]', last).value : '';
      tb.insertAdjacentHTML('beforeend', row({ room_no: no, room_type: type }));
      qs('tr:last-child [data-k=name]', tb).focus(); count();
    };
    qs('#gPaste', el).onclick = () => { const bx = qs('#gPasteBox', el); bx.style.display = bx.style.display === 'none' ? '' : 'none'; qs('#gPasteTxt', el).focus(); };
    qs('#gPasteOk', el).onclick = () => {
      // Odaların sırası: grubun oda satırları (ör. 10 DBL, 2 TRPL)
      const slots = [];
      (b.rooms || []).forEach((r) => { for (let i = 0; i < (+r.qty || 0); i++) slots.push(r.room_type); });
      const lines = qs('#gPasteTxt', el).value.split(/\r?\n/).map((l) => l.replace(/^[\s\d.)\-–*•]+/, '').replace(/\t+/g, ' ').trim());
      const replace = qs('#gReplace', el).checked;
      let room = replace ? 1 : Math.max(0, ...qsa('[data-k=room_no]', tb).map((i) => +i.value || 0)) + 1;
      let inRoom = 0; let added = [];
      for (const name of lines) {
        if (!name) { if (inRoom) { room++; inRoom = 0; } continue; }
        const type = slots[room - 1] || slots[slots.length - 1] || '';
        if (inRoom >= (PER[type] || 2)) { room++; inRoom = 0; }
        const pax = /\b(chd|çocuk|child)\b/i.test(name) ? 'CHD' : (/\b(inf|bebek|infant)\b/i.test(name) ? 'INF' : 'ADL');
        const clean = name.replace(/\s*[(\[]?\s*\b(chd|çocuk|child|inf|bebek|infant)\b\s*[)\]]?\s*/ig, ' ').trim() || name;
        added.push({ room_no: room, room_type: slots[room - 1] || type, name: clean, pax });
        inRoom++;
      }
      if (!added.length) return toast('Yapıştırılacak isim yok.', 'err');
      if (replace) tb.innerHTML = '';
      tb.insertAdjacentHTML('beforeend', added.map(row).join(''));
      qs('#gPasteTxt', el).value = ''; qs('#gPasteBox', el).style.display = 'none'; count();
      toast(`${added.length} isim eklendi. Kontrol edip kaydedin.`, 'ok');
    };
    const ms = qs('#mSend', el);
    if (ms) ms.onclick = () => {
      const bx = qs('#mBox', el);
      if (bx.style.display !== 'none') { bx.style.display = 'none'; return; }
      const cancelled = b.status === 'iptal';
      const sent = (b.mail_log || []).some((l) => +l.ok);
      bx.style.display = '';
      bx.innerHTML = `<h3 style="margin-top:0">Otel rezervasyon fişini e-postayla gönder</h3>
        ${b.mail_ready ? '' : '<div class="small neg" style="margin-bottom:6px">E-posta ayarı yapılmamış: Yönetici, Kullanıcılar sayfasındaki "E-posta gönderimi" bölümünden gönderen adresi ve şifresini girmeli.</div>'}
        ${S.egitim ? '<div class="small" style="margin-bottom:6px"><b>Eğitim bölümündesiniz:</b> gerçek e-posta gitmez, yalnız denemesi kaydedilir.</div>' : ''}
        <div class="grid-form">
          <label class="f wide">Otelin e-postası<input id="mTo" inputmode="email" value="${esc(b.hotel_email || '')}" placeholder="ör. reservation@otel.com"></label>
          <label class="f">Fiş türü<select id="mState">${cancelled ? '<option value="yeni">İPTAL</option>' : `<option value="yeni" ${sent ? '' : 'selected'}>YENİ</option><option value="degisiklik" ${sent ? 'selected' : ''}>DEĞİŞİKLİK</option>`}</select></label>
          <label class="f">Kopya (CC, isteğe bağlı)<input id="mCc" inputmode="email" placeholder="ör. info@luxlocationstours.com"></label>
          <label class="f wide">Otele kısa mesaj (fişin üstünde görünür)<textarea id="mMsg" rows="2">${cancelled ? 'Merhaba, aşağıdaki rezervasyonun iptalini rica ederiz.' : 'Merhaba, aşağıdaki rezervasyonun konfirmesini rica ederiz.'}</textarea></label>
        </div>
        <div class="row" style="margin-top:8px"><a class="btn" href="${docUrl('talep')}" id="mPrev" target="_blank" rel="noopener">Fişi önizle</a><span class="spacer"></span><button type="button" class="primary" id="mGo">Gönder</button></div>
        <div class="small muted" style="margin-top:6px">Göndermeden önce misafir listesini kaydedin; fiş kayıtlı bilgilerle gider. Otelin cevabı gönderen adrese (reservation@) gelir.</div>`;
      qs('#mState', bx).onchange = (e) => { qs('#mPrev', bx).href = docUrl('talep') + (e.target.value === 'degisiklik' ? '&durum=degisiklik' : ''); };
      qs('#mState', bx).onchange({ target: qs('#mState', bx) });
      qs('#mGo', bx).onclick = async (e) => {
        const btn = e.target; btn.disabled = true; btn.textContent = 'Gönderiliyor...';
        try {
          const r = await api('talep_send', {}, { booking_id: b.id, to: qs('#mTo', bx).value, cc: qs('#mCc', bx).value, state: qs('#mState', bx).value, message: qs('#mMsg', bx).value });
          b.mail_log = r.log; b.hotel_email = b.hotel_email || qs('#mTo', bx).value;
          toast(r.egitim ? 'Eğitim: e-posta gönderilmedi, deneme kaydedildi.' : 'Fiş otele gönderildi.', 'ok');
          guestSection(el, b);
        } catch (er) { toast(er.message, 'err'); btn.disabled = false; btn.textContent = 'Gönder'; }
      };
    };
    qs('#gSave', el).onclick = async () => {
      const guests = qsa('tr', tb).map((tr) => { const o = {}; qsa('[data-k]', tr).forEach((i) => { o[i.dataset.k] = i.value; }); return o; }).filter((g) => g.name.trim());
      const extra = {}; ['contact_name', 'room_category', 'nationality', 'arrival_info', 'departure_info', 'hotel_note', 'voucher_note'].forEach((k) => { extra[k] = qs(`[name=${k}]`, el).value; });
      try {
        const r = await api('guests_save', {}, { booking_id: b.id, guests, extra });
        b.guests = r.guests; b.extra = r.extra;
        toast('Misafir listesi kaydedildi.', 'ok');
        guestSection(el, b);
      } catch (e) { toast(e.message, 'err'); }
    };
  }

  async function viewBooking(main, idPart, p) {
    const isNew = idPart === 'yeni';
    const [hotelsR, agR] = await Promise.all([api('hotels'), api('agency_names')]);
    const hotels = hotelsR.hotels.filter((h) => +h.active || (!isNew));
    let b;
    if (isNew) {
      const ci = p.giris || today();
      b = { id: null, hotel_id: p.otel ? +p.otel : null, agency_id: null, code: '', check_in: ci, check_out: addDays(ci, 3), board: 'BB', status: 'teklif',
        rooms_reserved: 25, rooms_option: '', option_until: '', hotel_conf_code: '', buy_cur: 'USD', sell_cur: 'EUR', sell_mode: 'room', eur_try: '', usd_try: '', buy_note: '', notes: '',
        rooms: [{ room_type: 'DBL', qty: 25, buy_price: '', sell_price: '' }], free: [], services: [], version: null, history: [] };
      if (p.talep) {
        // Talepten gruba: müşteri bilgisi ve mesaj notlara, tarih tanınırsa giriş tarihine
        const t = await api('talep', { id: p.talep });
        b.notes = `Talep (${t.category_name}): ${t.name}${t.phone ? ' · ' + t.phone : ''}${t.email ? ' · ' + t.email : ''}${t.travel_date ? '\nTarih: ' + t.travel_date : ''}\n\n${t.message}`;
        const m = (t.travel_date || '').match(/(\d{4})-(\d{2})-(\d{2})|(\d{1,2})[./](\d{1,2})[./](\d{4})/);
        const dt = m ? (m[1] ? `${m[1]}-${m[2]}-${m[3]}` : `${m[6]}-${pad(m[5])}-${pad(m[4])}`) : '';
        if (dt && !isNaN(new Date(dt + 'T00:00:00'))) { b.check_in = dt; b.check_out = addDays(dt, 3); }
        b.fromTalep = t;
      }
    } else {
      b = await api('booking', { id: +idPart });
    }
    if (!hotels.length) { main.innerHTML = '<div class="empty">Önce bir otel ekleyin. <a href="#/">Otellere dön</a></div>'; return; }
    const locked = !isNew && S.consts.locked.includes(b.status) && !can('unlock');
    const ro = !can('booking');
    const RT = S.consts.room_types, CUR = S.consts.currencies;
    const opt = (list, val) => list.map((x) => `<option ${x == val ? 'selected' : ''}>${x}</option>`).join('');
    const agName = (id) => { const a = agR.agencies.find((x) => x.id == id); return a ? a.name : (b.agency_name || ''); };

    main.innerHTML = `
      <div class="row"><a href="#/otel/${b.hotel_id || ''}">← ${esc(hotels.find((h) => h.id == b.hotel_id)?.name || 'Oteller')}</a></div>
      ${b.fromTalep ? `<div class="lockbox" style="background:var(--info-bg);border-color:var(--info)">Bu grup <a href="#/talepler?q=${esc(b.fromTalep.id)}">${esc(b.fromTalep.name)} talebinden</a> açılıyor. Müşteri bilgisi ve mesaj notlara kondu; otel, acente ve odaları seçip kaydedin.</div>` : ''}
      <div class="row"><h1>${isNew ? 'Yeni grup' : `Grup ${esc(b.code || '#' + b.id)}`}</h1>${isNew ? '' : statusBadge(b.status)}${+b.deleted ? '<span class="badge st-iptal">Silinmiş</span>' : ''}</div>
      ${!isNew ? `<div class="small muted">Açan: ${esc(b.created_by_name || (b.created_by ? '—' : 'Örnek veri'))} ${trDT(b.created_at)} · Son değişiklik: ${esc(b.updated_by_name || (b.updated_by ? '—' : 'Örnek veri'))} ${trDT(b.updated_at)}${b.prev ? ` · <a href="#/grup/${b.prev.id}">${esc(b.prev.agency_name)} grubundan devralındı</a>` : ''}${b.next ? ` · <a href="#/grup/${b.next.id}">${esc(b.next.agency_name)} acentesine devredildi</a>` : ''}</div>` : ''}
      <div id="remoteBanner"></div>
      <div class="bk-layout" style="margin-top:12px">
        <form id="bf" autocomplete="off">
          ${!isNew && !locked && S.consts.locked.includes(b.status) ? `<div class="lockbox">Bu grup ${esc(S.consts.statuses[b.status])}: diğer kullanıcılar için fiyatlar kilitli. Siz yönetici olduğunuz için değiştirebilirsiniz; değişiklik kayda geçer.</div>` : ''}
          ${locked ? `<div class="lockbox">Bu grup ${esc(S.consts.statuses[b.status])}: fiyatlar, odalar ve kurlar kilitli. Değiştirmek için Yönetici gerekir. Kod, not ve durum değiştirilebilir.</div>` : ''}
          ${b.status === 'iptal' ? `<div class="banner">İptal nedeni: ${esc(b.cancel_reason || '')}</div>` : ''}
          <div class="panel"><h3 style="margin-top:0">Grup bilgileri</h3><div class="grid-form">
            <label class="f">Otel<select name="hotel_id" ${isNew ? '' : 'disabled title="Başka otele taşımak için alttaki düğmeyi kullanın"'}>${isNew && !b.hotel_id ? '<option value="" selected>—</option>' : ''}${hotels.map((h) => `<option value="${h.id}" ${h.id == b.hotel_id ? 'selected' : ''}>${esc(h.name)}</option>`).join('')}</select></label>
            <label class="f">Acente<input name="agency_name" value="${esc(agName(b.agency_id))}" placeholder="Yazmaya başlayın" ${isNew ? '' : 'disabled title="Başka acenteye vermek için Devret düğmesini kullanın"'}><span class="small muted" id="agHint"></span></label>
            <label class="f">Kod (voucher)<input name="code" value="${esc(b.code || '')}" placeholder="ör. LUX6340"></label>
            <label class="f">Giriş<input type="date" name="check_in" value="${b.check_in}"></label>
            <label class="f">Çıkış<input type="date" name="check_out" value="${b.check_out}"></label>
            <label class="f">Gece<input id="nightsOut" disabled value="${nightsBetween(b.check_in, b.check_out)}"></label>
            <label class="f">Pansiyon<select name="board">${opt(['BB', 'HB', 'FB', 'RO'], b.board)}</select></label>
            <label class="f">Durum<select name="status">${Object.entries(S.consts.statuses).map(([k, t]) => `<option value="${k}" ${k === b.status ? 'selected' : ''}>${t}</option>`).join('')}</select></label>
            <label class="f">Ayrılan oda<input name="rooms_reserved" inputmode="numeric" value="${b.rooms_reserved ?? ''}"></label>
            <label class="f">Opsiyon oda<input name="rooms_option" inputmode="numeric" value="${b.rooms_option ?? ''}" placeholder="ör. 4"></label>
            <label class="f">Opsiyon bitiş<input type="date" name="option_until" value="${b.option_until || ''}"></label>
            <label class="f">Otel konfirme kodu<input name="hotel_conf_code" value="${esc(b.hotel_conf_code || '')}"></label>
          </div></div>

          <div class="panel"><div class="row"><h3 style="margin:0">Odalar ve fiyatlar</h3><span class="spacer"></span>
              <button type="button" id="fromContract" class="${locked ? 'hidden' : ''}">Alışı sözleşmeden doldur</button></div>
            <div class="grid-form" style="margin-top:10px">
              <label class="f">Alış dövizi<select name="buy_cur" class="pz">${opt(CUR, b.buy_cur)}</select></label>
              <label class="f">Satış dövizi<select name="sell_cur" class="pz">${opt(CUR, b.sell_cur)}</select></label>
              <label class="f">Satış fiyatı nasıl<select name="sell_mode" class="pz"><option value="room" ${b.sell_mode === 'room' ? 'selected' : ''}>Oda başı gecelik</option><option value="pp" ${b.sell_mode === 'pp' ? 'selected' : ''}>Kişi başı gecelik (PP)</option></select></label>
            </div>
            <div class="small muted" id="ppHint" style="margin:6px 0">${b.sell_mode === 'pp' ? 'PP: SNGL ve DBL oda = PP x 2, TRPL = PP x 3, QUAD = PP x 4.' : ''}</div>
            <table class="edit"><thead><tr><th>Oda tipi</th><th>Adet</th><th>Alış (oda/gece)</th><th id="sellHead">Satış (${b.sell_mode === 'pp' ? 'kişi başı' : 'oda/gece'})</th><th></th></tr></thead><tbody id="roomsB"></tbody></table>
            <button type="button" class="link pz" id="addRoom">+ Oda tipi ekle</button>

            <h3>Free odalar</h3>
            <table class="edit"><thead><tr><th>Yön</th><th>Oda tipi</th><th>Adet</th><th></th></tr></thead><tbody id="freeB"></tbody></table>
            <div class="row"><button type="button" class="link pz" id="addFree">+ Free ekle</button><button type="button" class="link pz" id="suggestFree">Sözleşme kuralına göre öner</button></div>

            <h3>Ek hizmetler</h3>
            <table class="edit"><thead><tr><th>Hizmet</th><th>Açıklama</th><th>Alış</th><th>Satış</th><th>Döviz</th><th></th></tr></thead><tbody id="srvB"></tbody></table>
            <button type="button" class="link pz" id="addSrv">+ Hizmet ekle</button>

            <h3>Kur</h3>
            <div class="grid-form">
              <label class="f">1 EUR = TL<input name="eur_try" class="pz" inputmode="decimal" value="${b.eur_try ?? ''}" placeholder="boş = son kur"></label>
              <label class="f">1 USD = TL<input name="usd_try" class="pz" inputmode="decimal" value="${b.usd_try ?? ''}" placeholder="boş = son kur"></label>
            </div>
            <div class="small muted" style="margin-top:4px">Boş bırakılırsa en son girilen kur kullanılır. Grup konfirme edilince o günün kuru gruba sabitlenir.</div>
          </div>

          <div class="panel"><h3 style="margin-top:0">Notlar</h3>
            <label class="f">Alış notu (sözleşmeden farklı alışın nedeni, kiminle görüşüldü)<textarea name="buy_note" placeholder="ör. Veli Bey ile görüşmeye istinaden TRPL 85">${esc(b.buy_note || '')}</textarea></label>
            <label class="f" style="margin-top:10px">Genel notlar (isim listesi, rehber, özel istekler)<textarea name="notes">${esc(b.notes || '')}</textarea></label>
          </div>

          ${ro ? '' : `<div class="actions">
            <button type="submit" class="primary" ${+b.deleted ? 'disabled' : ''}>Kaydet</button>
            ${!isNew && !S.consts.locked.includes(b.status) && b.status !== 'iptal' ? '<button type="button" class="ok" id="btnConf">Konfirme et ve kaydet</button>' : ''}
            ${!isNew && b.status !== 'iptal' ? '<button type="button" id="btnTransfer">Başka acenteye devret</button><button type="button" id="btnMove">Başka otele taşı</button><button type="button" class="danger" id="btnCancel">İptal et</button>' : ''}
            ${!isNew && !+b.deleted ? '<button type="button" class="danger" id="btnDel">Sil</button>' : ''}
            <span class="spacer"></span><a class="btn" href="#/otel/${b.hotel_id}">Vazgeç</a>
          </div>`}
          ${!isNew ? `<h2>Tarihçe: kim, ne zaman, neyi değiştirdi</h2><div class="panel hist">${(b.history || []).map((x) => `<div class="h"><b>${esc(x.user_name || '')}</b> · <span class="muted">${trDT(x.at)}${x.ip ? ' · ' + esc(x.ip) : ''}</span><br>${esc(x.summary)}${(x.changes || []).map((c) => `<div class="ch">${esc(c.field)}: <b>${esc(c.from)}</b> → <b>${esc(c.to)}</b></div>`).join('')}</div>`).join('') || '<span class="muted">Kayıt yok.</span>'}</div>` : ''}
        </form>
        <aside class="calc panel" id="calcBox"><div class="muted">Hesaplanıyor…</div></aside>
      </div>
      ${!isNew && !+b.deleted ? '<div id="guestSec"></div>' : ''}`;

    const form = qs('#bf', main);
    const rowsEl = { rooms: qs('#roomsB', main), free: qs('#freeB', main), srv: qs('#srvB', main) };
    const del = '<td><button type="button" class="link pz rm" title="Satırı sil" style="color:var(--err)">✕</button></td>';
    const roomRow = (r = {}) => `<tr><td><select data-k="room_type" class="pz">${opt(RT, r.room_type || 'DBL')}</select></td><td><input data-k="qty" class="pz" inputmode="numeric" value="${r.qty ?? ''}"></td><td><input data-k="buy_price" class="pz" inputmode="decimal" value="${r.buy_price ?? ''}"></td><td><input data-k="sell_price" class="pz" inputmode="decimal" value="${r.sell_price ?? ''}"></td>${del}</tr>`;
    const freeRow = (f = {}) => `<tr><td><select data-k="direction" class="pz"><option value="in" ${f.direction !== 'out' ? 'selected' : ''}>Otelden alınan</option><option value="out" ${f.direction === 'out' ? 'selected' : ''}>Acenteye verilen</option></select></td><td><select data-k="room_type" class="pz">${opt(RT, f.room_type || 'DBL')}</select></td><td><input data-k="qty" class="pz" inputmode="numeric" value="${f.qty ?? 1}"></td>${del}</tr>`;
    const srvRow = (s = {}) => `<tr><td><input data-k="kind" class="pz" value="${esc(s.kind || '')}" placeholder="Transfer"></td><td><input data-k="description" class="pz" value="${esc(s.description || '')}"></td><td><input data-k="buy" class="pz" inputmode="decimal" value="${s.buy ?? ''}"></td><td><input data-k="sell" class="pz" inputmode="decimal" value="${s.sell ?? ''}"></td><td><select data-k="currency" class="pz">${opt(CUR, s.currency || 'EUR')}</select></td>${del}</tr>`;
    rowsEl.rooms.innerHTML = b.rooms.map(roomRow).join('');
    rowsEl.free.innerHTML = b.free.map(freeRow).join('');
    rowsEl.srv.innerHTML = b.services.map(srvRow).join('');
    // Hizmet adı yazarken öneri; seçilince son alış/satış fiyatı dolar
    let srvLast = null;
    const SRV_DEF = ['Transfer', 'Rehber', 'Tur lideri', 'Gala', 'Tur', 'Tekne'];
    rowsEl.srv.addEventListener('focusin', (e) => {
      const inp = e.target.closest('[data-k=kind]');
      if (!inp || inp.dataset.sb || inp.disabled) return;
      inp.dataset.sb = '1';
      if (!srvLast) { srvLast = []; api('hizmet_fiyat').then((r) => { srvLast.push(...(r.services || [])); inp.dispatchEvent(new Event('input')); }).catch(() => {}); }
      const items = () => {
        const seen = new Set(srvLast.map((x) => normTr(x.kind)));
        return srvLast.map((x) => ({ label: x.kind, keys: x.description, x,
          html: `<span class="nm">${esc(x.kind)}${x.description ? ' · ' + esc(x.description) : ''}<br><span class="small muted">Son: alış ${x.buy ?? '—'} / satış ${x.sell ?? '—'} ${esc(x.currency)} · ${trDate(x.date)}</span></span>` }))
          .concat(SRV_DEF.filter((k) => !seen.has(normTr(k))).map((k) => ({ label: k })));
      };
      suggestBox(inp, items, (it) => {
        const tr = inp.closest('tr');
        if (it.x) {
          const f = (k, v) => { const el = qs(`[data-k=${k}]`, tr); if (el && v !== null && v !== undefined && (k === 'currency' || el.value === '')) el.value = v; };
          f('description', it.x.description); f('buy', it.x.buy); f('sell', it.x.sell); f('currency', it.x.currency);
          toast('Son fiyat dolduruldu, gerekirse değiştirin.', 'ok');
        }
        schedule();
      });
      setTimeout(() => inp.dispatchEvent(new Event('focus')), 0);
    });

    const applyLock = () => {
      if (locked) qsa('.pz', main).forEach((el) => { el.disabled = true; });
      if (ro) qsa('input, select, textarea', form).forEach((el) => { el.disabled = true; });
    };
    applyLock();
    if (!isNew && !+b.deleted) guestSection(qs('#guestSec', main), b);

    const collect = () => {
      const fd = {};
      qsa('[name]', form).forEach((el) => { fd[el.name] = el.value; });
      const rows = (tb) => qsa('tr', tb).map((tr) => { const o = {}; qsa('[data-k]', tr).forEach((el) => { o[el.dataset.k] = el.value; }); return o; });
      fd.rooms = rows(rowsEl.rooms); fd.free = rows(rowsEl.free); fd.services = rows(rowsEl.srv);
      fd.hotel_id = isNew ? fd.hotel_id : b.hotel_id;
      if (isNew) {
        const name = (fd.agency_name || '').trim().toLocaleLowerCase('tr');
        const a = agR.agencies.find((x) => x.name.toLocaleLowerCase('tr') === name || (x.aliases || '').toLocaleLowerCase('tr').split(',').map((s) => s.trim()).includes(name));
        fd.agency_id = a ? a.id : null;
      } else fd.agency_id = b.agency_id;
      delete fd.agency_name;
      fd.id = b.id; fd.version = b.version;
      return fd;
    };

    let calcTimer = null, lastCalc = null;
    const runCalc = async () => {
      qs('#nightsOut', main).value = nightsBetween(form.check_in.value, form.check_out.value);
      try {
        const c = await api('calc', {}, collect());
        lastCalc = c;
        renderCalc(c);
        if (isNew && !form.code.value) form.code.placeholder = 'öneri: ' + c.next_code;
      } catch (e) { qs('#calcBox', main).innerHTML = `<div class="w error">${esc(e.message)}</div>`; }
    };
    const schedule = () => { S.dirty = true; clearTimeout(calcTimer); calcTimer = setTimeout(runCalc, 350); };

    function renderCalc(c) {
      const neg = (v) => v !== null && v < 0 ? 'neg' : '';
      const srvLines = Object.entries(c.services).filter(([, v]) => v.buy || v.sell).map(([cur, v]) => `<div class="line"><span>Ek hizmet ${cur}</span><span>${money(v.buy)} / ${money(v.sell)}</span></div>`).join('');
      qs('#calcBox', main).innerHTML = `
        <div class="muted small">${c.nights} gece · ${c.rooms} oda · ${c.free_in_rooms} free alındı · ${c.free_out_rooms} free verildi</div>
        <h3 style="margin:8px 0 4px">Alış ${curTag(c.buy_cur)}</h3>
        <div class="line"><span>Oda x gece x fiyat</span><span>${money(c.buy_gross)}</span></div>
        <div class="line"><span>Otelden alınan free</span><span>− ${money(c.free_in)}</span></div>
        <div class="line total"><span>Net alış</span><span>${money(c.buy_net, c.buy_cur)}</span></div>
        <h3 style="margin:10px 0 4px">Satış ${curTag(c.sell_cur)}</h3>
        <div class="line"><span>Oda x gece x fiyat</span><span>${money(c.sell_gross)}</span></div>
        <div class="line"><span>Acenteye verilen free</span><span>− ${money(c.free_out)}</span></div>
        <div class="line total"><span>Net satış</span><span>${money(c.sell_net, c.sell_cur)}</span></div>
        ${srvLines ? `<h3 style="margin:10px 0 4px">Ek hizmetler (alış / satış)</h3>${srvLines}` : ''}
        <h3 style="margin:12px 0 0">Kâr</h3>
        <div class="big ${neg(c.profit_eur)}">${money(c.profit_eur, 'EUR')}</div>
        <div class="prof"><div>EUR<b class="${neg(c.profit_eur)}">${money(c.profit_eur)}</b></div><div>USD<b class="${neg(c.profit_usd)}">${money(c.profit_usd)}</b></div><div>TL<b class="${neg(c.profit_try)}">${money0(c.profit_try)}</b></div></div>
        <div class="small muted">Alış ${money(c.buy_eur, 'EUR')} · Satış ${money(c.sell_eur, 'EUR')}<br>Kur: ${c.eur_try ? `1 € = ${money(c.eur_try)} TL, 1 $ = ${money(c.usd_try)} TL (${esc(c.rate_source)})` : 'girilmemiş'}</div>
        ${c.contract ? `<div class="small muted" style="margin-top:6px">Sözleşme: ${trDate(c.contract.date_from)}–${trDate(c.contract.date_to)} · ${['sngl', 'dbl', 'trpl'].map((k) => k.toUpperCase() + ' ' + (c.contract[k] ?? '—')).join(' / ')} ${esc(c.contract.currency)}${c.contract.free_every ? ` · her ${c.contract.free_every} odada 1 ${esc(c.contract.free_type)} free` : ''}</div>` : ''}
        <div class="warns">${c.warnings.length ? c.warnings.map((w) => `<div class="w ${w.level}">${esc(w.msg)}</div>`).join('') : '<div class="w info" style="background:var(--ok-bg);color:var(--ok)">Uyarı yok. Hesap tutarlı.</div>'}</div>`;
    }

    hotelCombo(form);
    if (isNew && form.agency_name) {
      suggestBox(form.agency_name, () => agR.agencies.map((a) => ({ label: a.name, keys: `${a.aliases || ''} ${a.country || ''}`, a,
        html: `<span class="nm">${esc(a.name)}<br><span class="small muted">${esc(a.country || '')}${a.aliases ? ' · ' + esc(a.aliases) : ''}</span></span>` })),
      () => { qs('#agHint', main).textContent = ''; schedule(); }, {
        extra: (q, shown) => shown.some((x) => normTr(x.label) === normTr(q)) ? null : { label: q, html: `<span class="nm"><b>+ "${esc(q)}"</b> yeni acente olarak ekle</span>` },
      });
      if (b.fromTalep) {
        api('talep_acente', { name: b.fromTalep.name || '', email: b.fromTalep.email || '' }).then((r) => {
          if (!r.agency || form.agency_name.value) return;
          form.agency_name.value = r.agency.name;
          qs('#agHint', main).textContent = `Talep sahibi bu acenteyle eşleşti (${r.agency.why}). Farklıysa değiştirin.`;
        }).catch(() => {});
      }
    }
    form.addEventListener('input', schedule);
    form.addEventListener('change', (e) => {
      if (e.target.name === 'sell_mode') {
        const pp = e.target.value === 'pp';
        qs('#sellHead', main).textContent = `Satış (${pp ? 'kişi başı' : 'oda/gece'})`;
        qs('#ppHint', main).textContent = pp ? 'PP: SNGL ve DBL oda = PP x 2, TRPL = PP x 3, QUAD = PP x 4.' : '';
      }
      if (e.target.name === 'hotel_id' && isNew && e.target.value) fillFromContract(true);
      schedule();
    });
    main.addEventListener('click', (e) => {
      const rm = e.target.closest('.rm');
      if (rm && !rm.disabled) { rm.closest('tr').remove(); schedule(); }
    });
    qs('#addRoom', main).onclick = () => { rowsEl.rooms.insertAdjacentHTML('beforeend', roomRow({ room_type: 'TRPL', qty: 1 })); schedule(); };
    qs('#addFree', main).onclick = () => { rowsEl.free.insertAdjacentHTML('beforeend', freeRow({ direction: 'in', room_type: 'DBL', qty: 1 })); schedule(); };
    qs('#addSrv', main).onclick = () => { rowsEl.srv.insertAdjacentHTML('afterbegin', srvRow({ kind: '', currency: form.sell_cur.value })); schedule(); };
    qs('#suggestFree', main).onclick = async () => {
      const r = await api('contract', { hotel_id: collect().hotel_id, date: form.check_in.value });
      const c = r.contract;
      if (!c || !c.free_every) { toast('Bu otelin sözleşmesinde free kuralı yok.'); return; }
      const rooms = qsa('tr', rowsEl.rooms).reduce((a, tr) => a + (+qs('[data-k=qty]', tr).value || 0), 0);
      const n = Math.floor(rooms / c.free_every);
      rowsEl.free.innerHTML = n ? freeRow({ direction: 'in', room_type: c.free_type || 'DBL', qty: n }) + freeRow({ direction: 'out', room_type: c.free_type || 'DBL', qty: n }) : '';
      toast(n ? `${rooms} odada ${n} ${c.free_type} free alınır ve verilir (al-ver). Vermiyorsanız "Acenteye verilen" satırını silin.` : 'Oda sayısı free için yeterli değil.');
      schedule();
    };
    async function fillFromContract(silent) {
      const r = await api('contract', { hotel_id: collect().hotel_id, date: form.check_in.value });
      const c = r.contract;
      if (!c) { if (!silent) toast('Bu tarih için sözleşme fiyatı yok. Otel sayfasından ekleyin.', 'err'); return; }
      form.buy_cur.value = c.currency;
      qsa('tr', rowsEl.rooms).forEach((tr) => {
        const t = qs('[data-k=room_type]', tr).value;
        const map = { SNGL: c.sngl, DBL: c.dbl, TWIN: c.twin ?? c.dbl, TRPL: c.trpl, QUAD: c.quad ?? (c.dbl ? c.dbl * 2 : null) };
        if (map[t] !== null && map[t] !== undefined) qs('[data-k=buy_price]', tr).value = +map[t];
      });
      if (!silent) toast('Alış fiyatları sözleşmeden dolduruldu.', 'ok');
      schedule();
    }
    qs('#fromContract', main).onclick = () => fillFromContract(false);

    const save = async (extra = {}) => {
      const body = Object.assign(collect(), extra);
      if (isNew && !body.hotel_id) { toast('Otel seçin.', 'err'); const hi = qs('select[name=hotel_id] + input', form); if (hi) hi.focus(); return; }
      if (isNew && !body.agency_id) {
        const nm = (form.agency_name.value || '').trim();
        if (!nm) { toast('Acente seçin.', 'err'); form.agency_name.focus(); return; }
        if (!(await confirmBox('Yeni acente', `"${esc(nm)}" listede yok. Yeni acente olarak eklensin mi? (Farklı yazım ise vazgeçip listeden seçin.)`, 'Ekle'))) return;
        try { const a = await api('agency_save', {}, { name: nm }); body.agency_id = a.id; agR.agencies.push(a); } catch (e) { toast(e.message, 'err'); return; }
      }
      if (isNew && !body.code && lastCalc) body.code = lastCalc.next_code;
      try {
        const r = await api('booking_save', {}, body);
        S.dirty = false;
        if (isNew && p.talep) { try { await api('talep_link', {}, { id: p.talep, booking_id: r.id }); } catch (er) { /* bağlantı kurulamasa da grup kayıtlı */ } }
        toast('Grup kaydedildi.', 'ok');
        go('#/grup/' + r.id);
        if (!isNew) route();
      } catch (e) {
        if (e.status === 409 && e.data && e.data.duplicate) {
          if (await confirmBox('Benzer grup var', esc(e.message), 'Yine de aç')) return save(Object.assign(extra, { force: 1 }));
          return;
        }
        toast(e.message, 'err');
        if (e.data && e.data.calc) renderCalc(e.data.calc);
      }
    };
    form.addEventListener('submit', (e) => { e.preventDefault(); save(); });
    const bc = qs('#btnConf', main); if (bc) bc.onclick = () => { form.status.value = 'konfirme'; save({ status: 'konfirme' }); };
    const bx = qs('#btnCancel', main); if (bx) bx.onclick = async () => {
      const r = await modal('Grubu iptal et', `<label class="f">İptal nedeni ve ön ödeme ne olacak?<textarea name="reason" placeholder="ör. Acente iptal etti, 1.500 EUR ön ödeme iade edilecek"></textarea></label><p class="small muted">Odalar kontenjana geri döner. Kayıt silinmez, tarihçede kalır.</p>`, { ok: 'İptal et', danger: true });
      if (!r) return;
      try { await api('booking_cancel', {}, { id: b.id, reason: r.reason, version: b.version }); S.dirty = false; toast('Grup iptal edildi.', 'ok'); route(); } catch (e) { toast(e.message, 'err'); }
    };
    const bt = qs('#btnTransfer', main); if (bt) bt.onclick = async () => {
      const r = await modal('Başka acenteye devret', `<p class="small">Aynı odalar yeni acenteye verilir: bu grup "İptal (devredildi)" olur, yeni acente için kopyası açılır. Yeni gruba sıradaki LUX kodu verilir (voucher farklıysa düzeltin). Yeni grubun satış fiyatını kontrol edin.</p>
        <label class="f">Yeni acente<select name="agency_id"><option value="">—</option>${agR.agencies.filter((a) => a.id != b.agency_id).map((a) => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select></label>
        <div class="grid-form" style="margin-top:10px"><label class="f">Yeni giriş (değişmiyorsa boş)<input type="date" name="check_in"></label><label class="f">Yeni çıkış<input type="date" name="check_out"></label></div>`, { ok: 'Devret', onOpen: (bg) => agencyCombo(bg, agR.agencies) });
      if (r && !r.agency_id) { toast('Yeni acenteyi seçin.', 'err'); return; }
      if (!r) return;
      try { const n = await api('booking_transfer', {}, { id: b.id, version: b.version, ...r }); S.dirty = false; toast('Grup devredildi. Yeni grubu açtım.', 'ok'); go('#/grup/' + n.id); } catch (e) { toast(e.message, 'err'); }
    };
    const bm = qs('#btnMove', main); if (bm) bm.onclick = async () => {
      const r = await modal('Başka otele taşı', `<p class="small">Grup yeni otele geçer; alış fiyatları yeni otelin sözleşmesinden yeniden yüklenir, otel konfirme kodu silinir.</p>
        <label class="f">Yeni otel<select name="hotel_id"><option value="">—</option>${hotels.filter((h) => h.id != b.hotel_id && +h.active).map((h) => `<option value="${h.id}">${esc(h.name)}</option>`).join('')}</select></label>`, { ok: 'Taşı', onOpen: hotelCombo });
      if (r && !r.hotel_id) { toast('Yeni oteli seçin.', 'err'); return; }
      if (!r) return;
      try { await api('booking_move', {}, { id: b.id, version: b.version, hotel_id: r.hotel_id }); S.dirty = false; toast('Grup taşındı. Alışı kontrol edin.', 'ok'); route(); } catch (e) { toast(e.message, 'err'); }
    };
    const bd = qs('#btnDel', main); if (bd) bd.onclick = async () => {
      if (!(await confirmBox('Grubu sil', 'Grup listelerden kalkar ama kayıt saklanır ve işlem kaydında görünür. Genelde "İptal et" daha doğrudur. Yine de silinsin mi?', 'Sil', true))) return;
      try { await api('booking_delete', {}, { id: b.id }); S.dirty = false; toast('Grup silindi.', 'ok'); go('#/otel/' + b.hotel_id); } catch (e) { toast(e.message, 'err'); }
    };

    if (isNew && b.hotel_id) await fillFromContract(true);
    await runCalc();
    S.dirty = false;
    S.view = { onRemote: (items) => {
      if (isNew) return;
      if (items.some((i) => i.entity === 'grup' && +i.entity_id === +b.id)) {
        qs('#remoteBanner', main).innerHTML = `<div class="banner">Bu grup az önce başka biri tarafından değiştirildi. <button id="rl">Yeni hâlini yükle</button> <span class="small">Kendi değişiklikleriniz kaydedilmediyse önce not alın.</span></div>`;
        qs('#rl', main).onclick = () => route();
      }
    } };
  }

  /* ================= TALEPLER (site ve uygulama formları) ================= */
  async function viewRequests(main, p) {
    const d = await api('talepler', { durum: p.durum || '', q: p.q || '' });
    const set = (o) => go('#/talepler?' + new URLSearchParams(Object.entries(Object.assign({}, p, o)).filter(([, v]) => v)).toString());
    const total = Object.values(d.counts).reduce((a, b) => a + b, 0);
    const cls = { new: 'st-opsiyon', review: 'st-konaklamada', quoted: 'st-teklif', confirmed: 'st-konfirme', closed: 'st-kapandi' };
    const chip = (k, t, n) => `<a class="btn ${(p.durum || '') === k ? 'primary' : ''}" href="#/talepler?${new URLSearchParams(Object.entries({ durum: k, q: p.q || '' }).filter(([, v]) => v))}">${esc(t)} (${n})</a>`;
    const ro = !can('talep') || S.egitim;
    const logRow = (l) => l.kind === 'grup'
      ? `<div class="small">✓ <a href="#/grup/${l.booking_id}">Grup ${esc(l.booking_code || '#' + l.booking_id)}</a> açıldı · ${esc(l.user_name || '')} ${trDT(l.at)}</div>`
      : `<details class="small" style="margin:2px 0"><summary style="cursor:pointer">${+l.ok ? '✓ E-posta gönderildi' : '<span style="color:var(--err)">✗ E-posta gönderilemedi</span>'}: ${esc(l.to_addr)}${l.cc ? ` (CC ${esc(l.cc)})` : ''} · ${esc(l.user_name || '')} ${trDT(l.at)}</summary>
          <div style="white-space:pre-wrap;margin:4px 0 6px 14px" class="muted"><b>${esc(l.subject || '')}</b>\n${esc(l.body || '')}${l.error ? '\n' + esc(l.error) : ''}</div></details>`;
    const card = (r) => {
      const digits = (r.phone || '').replace(/\D/g, '');
      const cat = d.categories[r.category] || r.category;
      return `<details class="panel" data-id="${esc(r.id)}" ${r.status === 'new' || p.q === r.id ? 'open' : ''} style="padding:12px 16px;margin-bottom:10px">
        <summary class="row" style="cursor:pointer"><b>${esc(r.name)}</b><span class="muted">${esc(cat)}</span>${r.log.length ? `<span class="small muted">· ${r.log.length} işlem</span>` : ''}
          <span class="spacer"></span><span class="badge ${cls[r.status] || 'st-teklif'}">${esc(d.statuses[r.status] || r.status)}</span><span class="small muted">${trDT(r.at)}</span></summary>
        <div style="margin-top:10px">
          <div class="small">${r.phone ? `Telefon: <a href="tel:${esc(r.phone)}">${esc(r.phone)}</a>${digits ? ` · <a href="https://wa.me/${digits}" target="_blank" rel="noopener">WhatsApp ↗</a>` : ''}<br>` : ''}
            ${r.email ? `E-posta: <a href="mailto:${esc(r.email)}">${esc(r.email)}</a><br>` : ''}${r.travel_date ? `Tarih: ${esc(r.travel_date)}` : ''}</div>
          <div dir="auto" style="white-space:pre-wrap;background:var(--bg);border-radius:8px;padding:10px;margin:8px 0">${esc(r.message)}</div>
          ${r.log.length ? `<div style="margin:6px 0 10px">${r.log.map(logRow).join('')}</div>` : ''}
          <form class="row tf" style="align-items:flex-end">
            <label class="f">Durum<select name="status" ${ro ? 'disabled' : ''}>${Object.entries(d.statuses).map(([k, t]) => `<option value="${k}" ${k === r.status ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></label>
            <label class="f" style="flex:1;min-width:220px">İç not<textarea name="note" rows="2" maxlength="2000" ${ro ? 'disabled' : ''}>${esc(r.note || '')}</textarea></label>
            ${ro ? '' : `<input type="hidden" name="id" value="${esc(r.id)}"><input type="hidden" name="revision" value="${r.revision}"><button class="primary">Kaydet</button>`}
          </form>
          ${ro ? '' : `<div class="row" style="margin-top:10px">
            <button type="button" data-reply="${esc(r.id)}">✉ E-postayla cevap ver</button>
            ${can('booking') ? `<a class="btn" href="#/grup/yeni?talep=${esc(r.id)}">→ Gruba çevir</a>` : ''}</div>
          <form class="panel rf" data-id="${esc(r.id)}" hidden style="margin:10px 0 0">
            ${d.mail_ready ? '' : '<div class="small" style="color:var(--err);margin-bottom:8px">E-posta gönderimi ayarlı değil (Kullanıcılar → E-posta gönderimi).</div>'}
            <div class="grid-form">
              <label class="f">Kime<input name="to" value="${esc(r.email || '')}" placeholder="musteri@ornek.com" required></label>
              <label class="f">Kopya (CC)<input name="cc" placeholder="İsteğe bağlı, virgülle"></label>
              <label class="f wide">Konu<input name="subject" value="${esc('Lux Locations · ' + cat)}"></label>
              <label class="f wide">Mesaj<textarea name="body" rows="7" required>${esc(`Sayın ${r.name},\n\nTalebiniz için teşekkür ederiz.\n\n`)}</textarea></label>
            </div>
            <div class="row" style="margin-top:8px"><span class="small muted">reservation@ adresinden gider; gönderilen mail burada kayıtlı kalır.</span><span class="spacer"></span><button type="button" data-cancel>Vazgeç</button><button class="primary">Gönder</button></div>
          </form>`}
        </div></details>`;
    };
    const nt = d.notify;
    const notifyBox = nt ? `<details class="panel" style="padding:12px 16px;margin-bottom:12px" ${p.ayar ? 'open' : ''}>
        <summary style="cursor:pointer"><b>Yeni talep bildirimi</b> <span class="small muted">· ${nt.recipients.length ? esc(nt.recipients.join(', ')) : 'alıcı yok'}</span></summary>
        ${nt.sender || nt.recipients.length ? `<form id="nf" class="row" style="margin-top:10px;align-items:flex-end">
          <label class="f" style="flex:1;min-width:260px">Bildirim gidecek adresler (virgülle, en fazla 10)<input name="recipients" value="${esc(nt.recipients.join(', '))}" required></label>
          <button class="primary">Kaydet</button><button type="button" id="ntest">Deneme gönder</button></form>
          <div class="small muted" style="margin-top:6px">Siteden ya da uygulamadan yeni talep gelince bu adreslere haber gider. Gönderen: ${esc(nt.sender || '—')}${nt.ready ? ' · gönderim şifresi kayıtlı' : ''}.</div>`
        : '<div class="small muted" style="margin-top:8px">Sitede e-posta bildirimi henüz kurulmamış.</div>'}</details>` : '';
    main.innerHTML = `<div class="row"><h1>Talepler</h1><span class="spacer"></span>
        <form id="tq" class="row"><input name="q" value="${esc(p.q || '')}" placeholder="İsim, telefon, e-posta, mesaj ara"><button>Ara</button></form></div>
      <div class="muted small" style="margin-bottom:10px">Sitedeki ve uygulamadaki rezervasyon/teklif formlarından gelen istekler. Müşteri aynı talebi WhatsApp'tan da gönderebilir.${S.egitim ? ' <b>Eğitim bölümündesiniz: talepler gerçektir ve burada değiştirilemez.</b>' : ''}</div>
      ${S.egitim ? '' : notifyBox}
      ${d.ready ? `<div class="row" style="margin-bottom:12px">${chip('', 'Tümü', total)}${Object.entries(d.statuses).map(([k, t]) => chip(k, t, d.counts[k] || 0)).join('')}</div>
      ${d.rows.map(card).join('') || '<div class="empty">Bu listede talep yok.</div>'}` : '<div class="empty">Talepler veritabanı bulunamadı. Siteden henüz talep gelmemiş olabilir.</div>'}`;
    const tq = qs('#tq', main);
    tq.onsubmit = (e) => { e.preventDefault(); set({ q: tq.q.value.trim() }); };
    qsa('form.tf', main).forEach((f) => {
      f.oninput = () => { S.dirty = true; };
      f.onsubmit = async (e) => {
        e.preventDefault();
        const b = Object.fromEntries(new FormData(f));
        try { await api('talep_update', {}, { ...b, revision: +b.revision }); S.dirty = false; toast('Talep güncellendi.', 'ok'); route(); } catch (er) { toast(er.message, 'err'); }
      };
    });
    qsa('[data-reply]', main).forEach((btn) => btn.onclick = () => {
      const f = qs(`form.rf[data-id="${btn.dataset.reply}"]`, main);
      f.hidden = false; btn.hidden = true; (f.to.value ? f.body : f.to).focus();
      const len = f.body.value.length; f.body.setSelectionRange(len, len);
    });
    qsa('form.rf', main).forEach((f) => {
      f.oninput = () => { S.dirty = true; };
      qs('[data-cancel]', f).onclick = () => { f.hidden = true; qs(`[data-reply="${f.dataset.id}"]`, main).hidden = false; };
      f.onsubmit = async (e) => {
        e.preventDefault();
        const b = Object.fromEntries(new FormData(f));
        if (!(await confirmBox('E-posta gönderilsin mi?', `<b>${esc(b.to)}</b>${b.cc ? ' (CC ' + esc(b.cc) + ')' : ''} adresine "${esc(b.subject)}" konulu mail reservation@ adresinden gidecek.`, 'Gönder'))) return;
        const sb = qs('button.primary', f); sb.disabled = true; sb.textContent = 'Gönderiliyor…';
        try { await api('talep_reply', {}, { id: f.dataset.id, ...b }); S.dirty = false; toast('E-posta gönderildi.', 'ok'); route(); }
        catch (er) { toast(er.message, 'err'); sb.disabled = false; sb.textContent = 'Gönder'; }
      };
    });
    const nf = qs('#nf', main);
    if (nf) {
      nf.onsubmit = async (e) => { e.preventDefault(); try { const r = await api('talep_notify_save', {}, { recipients: nf.recipients.value }); toast('Bildirim alıcıları kaydedildi: ' + r.recipients.join(', '), 'ok'); set({ ayar: '1' }); } catch (er) { toast(er.message, 'err'); } };
      qs('#ntest', main).onclick = async () => { try { const r = await api('talep_notify_test', {}, {}); toast('Deneme maili gönderildi: ' + r.to.join(', '), 'ok'); } catch (er) { toast(er.message, 'err'); } };
    }
  }

  /* ================= RAPORLAR ================= */
  async function viewReports(main, p) {
    const y = new Date().getFullYear(), m = new Date().getMonth();
    const first = (yy, mm) => iso(new Date(yy, mm, 1));
    const pre = [['Bu yıl', `${y}-01-01`, `${y + 1}-01-01`], ['Geçen yıl', `${y - 1}-01-01`, `${y}-01-01`], ['Bu ay', first(y, m), first(y, m + 1)],
      ['Son 12 ay', first(y, m - 11), first(y, m + 1)], ['Gelecek 6 ay', first(y, m), first(y, m + 6)]];
    const from = p.from || pre[0][1], to = p.to || pre[0][2], kapsam = p.kapsam === 'hepsi' ? 'hepsi' : 'satis';
    const set = (o) => go('#/raporlar?' + new URLSearchParams(Object.entries(Object.assign({ from, to, kapsam }, o)).filter(([, v]) => v)).toString());
    main.innerHTML = '<div class="muted">Rapor hazırlanıyor…</div>';
    const d = await api('rapor', { from, to, kapsam });
    const T = d.total;
    const maxP = Math.max(1, ...d.months.map((x) => Math.abs(x.profit_eur)));
    const monthName = (k) => MON[+k.slice(5, 7) - 1] + ' ' + k.slice(0, 4);
    const tbl = (rows, label, lnk) => `<div class="tbl-wrap"><table><thead><tr><th>${label}</th><th class="num">Grup</th><th class="num">Oda-gece</th><th class="num">Satış (EUR)</th><th class="num">Kâr (EUR)</th><th class="num">Kâr (TL)</th></tr></thead><tbody>
      ${rows.map((r) => `<tr><td>${lnk ? lnk(r.key) : esc(r.key)}${r.unknown ? ` <span class="small muted">(${r.unknown} hesapsız)</span>` : ''}</td><td class="num">${r.groups}</td><td class="num">${nf0.format(r.room_nights)}</td><td class="num">${money0(r.sell_eur, 'EUR')}</td><td class="num"><b class="${r.profit_eur < 0 ? 'neg' : ''}">${money0(r.profit_eur, 'EUR')}</b></td><td class="num">${money0(r.profit_try, 'TRY')}</td></tr>`).join('') || `<tr><td colspan="6" class="muted">Bu aralıkta grup yok.</td></tr>`}
      </tbody></table></div>`;
    main.innerHTML = `<div class="row"><h1>Raporlar</h1><span class="spacer"></span><a class="btn" href="api.php?a=rapor_csv&from=${from}&to=${to}&kapsam=${kapsam}">Excel'e aktar</a></div>
      <div class="small muted">Giriş tarihi ${trDate(from)} – ${trDate(addDays(to, -1))} arasındaki gruplar. ${kapsam === 'hepsi' ? 'Teklif ve opsiyonlar dahil (iptaller hariç).' : 'Yalnız konfirme, konaklamada ve kapanmış gruplar.'} Kur girilmemiş ya da fiyatı eksik gruplar toplamlara katılmaz.</div>
      <div class="filters">
        <div class="quick">${pre.map(([l, f, t]) => `<button data-f="${f}" data-t="${t}" class="${f === from && t === to ? 'on' : ''}">${l}</button>`).join('')}</div>
        <label class="f">Başlangıç<input type="date" id="rF" value="${from}"></label><label class="f">Bitiş<input type="date" id="rT" value="${to}"></label>
        <label class="f">Kapsam<select id="rK"><option value="satis">Gerçekleşen satış</option><option value="hepsi" ${kapsam === 'hepsi' ? 'selected' : ''}>Teklif ve opsiyon dahil</option></select></label>
      </div>
      <div class="totals">
        <div class="card"><div class="l">Grup</div><div class="v">${T.groups}</div></div>
        <div class="card"><div class="l">Oda-gece</div><div class="v">${nf0.format(T.room_nights)}</div></div>
        <div class="card"><div class="l">Satış EUR</div><div class="v">${money0(T.sell_eur, 'EUR')}</div></div>
        <div class="card"><div class="l">Kâr EUR</div><div class="v ${T.profit_eur < 0 ? 'neg' : ''}">${money0(T.profit_eur, 'EUR')}</div></div>
        <div class="card"><div class="l">Kâr TL</div><div class="v">${money0(T.profit_try, 'TRY')}</div></div>
      </div>
      ${T.unknown ? `<div class="panel small" style="background:var(--warn-bg)">${T.unknown} grubun kârı hesaplanamadı (fiyat veya kur eksik); toplamlara katılmadı.</div>` : ''}
      <h2>Aylara göre</h2>
      ${d.months.length ? `<div class="panel">${d.months.map((x) => `<div class="row" style="gap:8px;margin:3px 0"><span style="width:80px" class="small">${monthName(x.key)}</span>
        <span style="flex:1;max-width:520px;background:var(--bg);border-radius:4px;height:16px;position:relative"><span style="position:absolute;left:0;top:0;bottom:0;border-radius:4px;width:${Math.round(100 * Math.abs(x.profit_eur) / maxP)}%;background:${x.profit_eur < 0 ? 'var(--err)' : 'var(--brand)'}"></span></span>
        <span class="small" style="width:110px;text-align:right"><b>${money0(x.profit_eur, 'EUR')}</b></span><span class="small muted">${x.groups} grup · ${nf0.format(x.room_nights)} oda-gece</span></div>`).join('')}</div>` : ''}
      ${tbl(d.months, 'Ay', monthName)}
      <h2>Acentelere göre</h2>${tbl(d.agencies, 'Acente')}
      <h2>Otellere göre</h2>${tbl(d.hotels, 'Otel')}`;
    qsa('.quick button', main).forEach((b) => b.onclick = () => set({ from: b.dataset.f, to: b.dataset.t }));
    const chg = () => set({ from: qs('#rF', main).value, to: qs('#rT', main).value, kapsam: qs('#rK', main).value });
    ['#rF', '#rT', '#rK'].forEach((sel) => qs(sel, main).onchange = chg);
  }

  /* ================= ACENTELER ================= */
  async function viewAgencies(main) {
    const d = await api('agencies');
    main.innerHTML = `<div class="row"><h1>Acenteler</h1><span class="spacer"></span>${can('agency') ? '<button class="primary" id="addAg">+ Acente ekle</button>' : ''}</div>
      <div class="small muted" style="margin-bottom:10px">Satış toplamı: konfirme, konaklamada ve kapanmış grupların net satışı ve ek hizmetleri.${can('cari') ? ' Bakiye: satış eksi tahsilat (acentenin borcu).' : ''}</div>
      <div class="row" style="margin-bottom:8px"><input id="agQ" type="search" placeholder="Acente, ülke ya da sorumlu ara…" style="max-width:340px"><span class="small muted" id="agN"></span></div>
      <div class="tbl-wrap"><table><thead><tr><th>Acente</th><th>Ülke</th><th>Diğer yazımlar</th><th>Sorumlu</th><th class="num">Grup</th><th class="num">İptal</th><th class="num">Satış toplamı</th>${can('cari') ? '<th class="num">Bakiye</th>' : ''}</tr></thead><tbody>
      ${d.agencies.map((a) => `<tr class="click" data-id="${a.id}"><td><b>${esc(a.name)}</b>${+a.active ? '' : ' <span class="badge st-iptal">Pasif</span>'}</td><td>${esc(a.country || '')}</td><td class="small">${esc(a.aliases || '')}</td><td>${esc(a.owner || '')}</td><td class="num">${a.bookings}</td>
        <td class="num" style="${a.cancelled ? 'color:var(--err)' : ''}">${a.cancelled}${a.bookings ? ` <span class="muted small">(%${Math.round(100 * a.cancelled / a.bookings)})</span>` : ''}</td>
        <td class="num">${Object.entries(a.sales).map(([c, v]) => money0(v, c)).join('<br>') || '—'}</td>${can('cari') ? `<td class="num">${Object.entries(a.balance || {}).map(([c, v]) => `<b class="${v > 0 ? 'neg' : ''}">${money0(v, c)}</b>`).join('<br>') || '—'}</td>` : ''}</tr>`).join('') || '<tr><td colspan="8" class="muted">Henüz acente yok.</td></tr>'}
      </tbody></table></div>`;
    qsa('tr.click', main).forEach((r) => r.onclick = () => go('#/acente/' + r.dataset.id));
    tableFilter(qs('#agQ', main), qs('tbody', main), qs('#agN', main));
    const b = qs('#addAg', main); if (b) b.onclick = () => agencyForm(null, (a) => go('#/acente/' + a.id));
  }

  function agencyFormHelp(bg, a) {
    const country = qs('[name="country"]', bg);
    suggestBox(country, () => COUNTRIES.map((c) => ({ label: c })), () => {}, { max: 10 });
    historySuggest(qs('[name="owner"]', bg), 'owner');
    if (a) return;
    const name = qs('[name="name"]', bg), dup = qs('#agDup', bg);
    let list = [];
    api('agency_names').then((r) => { list = r.agencies || []; }).catch(() => {});
    suggestBox(name, () => list.map((x) => ({ label: x.name, keys: `${x.aliases || ''} ${x.country || ''}`, x,
      html: `<span class="nm">${esc(x.name)} · <b>panelde var</b><br><span class="small muted">${esc(x.country || '')}${x.aliases ? ' · ' + esc(x.aliases) : ''}</span></span>` })), (it) => {
      dup.innerHTML = `<span class="neg">"${esc(it.x.name)}" zaten var.</span> <a href="#/acente/${it.x.id}" class="go-a">Acenteyi aç</a>`;
    }, { showAllOnFocus: false });
    name.addEventListener('input', () => {
      const q = normTr(name.value.trim());
      const hit = q.length > 2 && list.find((x) => normTr(x.name) === q || (x.aliases || '').split(',').some((s) => normTr(s.trim()) === q));
      dup.innerHTML = hit ? `<span class="neg">"${esc(hit.name)}" zaten var.</span> <a href="#/acente/${hit.id}" class="go-a">Acenteyi aç</a>` : '';
    });
    dup.addEventListener('click', (e) => { if (e.target.closest('.go-a')) { const x = qs('[data-x="0"]', bg); if (x) x.click(); } });
  }

  async function agencyForm(a, after) {
    const v = a || {};
    const r = await modal(a ? 'Acenteyi düzenle' : 'Yeni acente', `<div class="grid-form">
      <label class="f wide">Acente adı<input name="name" value="${esc(v.name || '')}"></label>
      <label class="f">Ülke<input name="country" value="${esc(v.country || '')}"></label>
      <label class="f">Sorumlu (bizden)<input name="owner" value="${esc(v.owner || '')}"></label>
      <label class="f wide">Diğer yazımlar (virgülle; ör. Soco, Soca)<input name="aliases" value="${esc(v.aliases || '')}"></label>
      <label class="f wide">İletişim<textarea name="contact">${esc(v.contact || '')}</textarea></label>
      <label class="f wide">Ödeme anlaşması<textarea name="payment_terms" placeholder="ör. Aynı gün veya ertesi gün öder, her cuma 1.000 USD">${esc(v.payment_terms || '')}</textarea></label>
      <label class="f wide">Fatura bilgileri (unvan, adres, vergi no)<textarea name="invoice_info" placeholder="ör. SOCO TRAVEL SRL&#10;Str. Victoriei 12, București, Romania&#10;VAT: RO12345678">${esc(v.invoice_info || '')}</textarea></label>
      <label class="f wide">Notlar<textarea name="notes">${esc(v.notes || '')}</textarea></label>
      ${a ? `<label class="chk"><input type="checkbox" name="active" ${+v.active ? 'checked' : ''}> Aktif</label>` : ''}</div>
      ${a ? '' : '<div class="small" id="agDup" style="margin-top:6px"></div>'}`, { ok: 'Kaydet', onOpen: (bg) => agencyFormHelp(bg, a) });
    if (!r) return;
    try { const s = await api('agency_save', {}, Object.assign(r, a ? { id: a.id, version: a.version } : {})); toast('Acente kaydedildi.', 'ok'); after(s); } catch (e) { toast(e.message, 'err'); }
  }

  async function viewAgency(main, id) {
    const a = await api('agency', { id });
    main.innerHTML = `<div class="row"><a href="#/acenteler">← Acenteler</a></div>
      <div class="row"><h1>${esc(a.name)}</h1><span class="spacer"></span>${can('agency') ? '<button id="editAg">Düzenle</button>' : ''}${can('booking') ? '' : ''}</div>
      <div class="muted">${esc(a.country || '')}${a.owner ? ' · Sorumlu: ' + esc(a.owner) : ''}${a.aliases ? ' · Diğer yazımlar: ' + esc(a.aliases) : ''}</div>
      ${a.payment_terms ? `<div class="banner" style="margin-top:10px">Ödeme anlaşması: ${esc(a.payment_terms)}</div>` : ''}
      ${a.contact ? `<div class="small" style="white-space:pre-wrap">${esc(a.contact)}</div>` : ''}
      ${a.notes ? `<div class="small muted" style="white-space:pre-wrap">${esc(a.notes)}</div>` : ''}
      <h2>Grupları</h2>
      <div class="tbl-wrap"><table><thead><tr><th>Kod</th><th>Otel</th><th>Giriş</th><th>Çıkış</th><th class="num">Oda</th><th>Durum</th><th class="num">Net satış</th><th class="num">Kâr €</th></tr></thead><tbody>
      ${a.bookings.map((b) => `<tr class="click" data-id="${b.id}"><td>${esc(b.code || '')}</td><td>${esc(b.hotel)}</td><td>${trDate(b.check_in)}</td><td>${trDate(b.check_out)}</td><td class="num">${b.rooms}</td><td>${statusBadge(b.status)}</td><td class="num">${money(b.sell_net, b.sell_cur)}</td><td class="num">${money(b.profit_eur)}</td></tr>`).join('') || '<tr><td colspan="8" class="muted">Grup yok.</td></tr>'}
      </tbody></table></div>
      ${a.cari ? cariSection(a) : ''}`;
    qsa('tr.click', main).forEach((r) => r.onclick = () => go('#/grup/' + r.dataset.id));
    const e = qs('#editAg', main); if (e) e.onclick = () => agencyForm(a, () => route());
    const pf = qs('#payF', main);
    if (pf) selectCombo(pf, 'select[name="booking_id"]', 'Grup kodu, otel ya da tarih…');
    if (pf) pf.onsubmit = async (ev) => {
      ev.preventDefault();
      try { await api('payment_save', {}, Object.assign(Object.fromEntries(new FormData(pf)), { agency_id: a.id })); toast('Tahsilat kaydedildi.', 'ok'); route(); } catch (er) { toast(er.message, 'err'); }
    };
    qsa('[data-delpay]', main).forEach((btn) => btn.onclick = async () => {
      if (!(await confirmBox('Tahsilatı sil', 'Bu tahsilat kaydı silinecek (işlem kaydında kalır).', 'Sil', true))) return;
      try { await api('payment_delete', {}, { id: +btn.dataset.delpay }); toast('Tahsilat silindi.', 'ok'); route(); } catch (er) { toast(er.message, 'err'); }
    });
  }

  function cariSection(a) {
    const c = a.cari, T = Object.entries(c.totals);
    const groups = a.bookings.filter((b) => S.consts.locked.includes(b.status));
    return `<h2>Cari hesap</h2>
      <div class="totals">${T.map(([cur, t]) => `<div class="card"><div class="l">Bakiye ${esc(cur)} <span class="muted">(satış ${money0(t.debit)} · tahsilat ${money0(t.credit)})</span></div><div class="v ${t.balance > 0 ? 'neg' : ''}">${money(t.balance, cur)}</div></div>`).join('') || '<div class="muted small">Henüz konfirme grup ya da tahsilat yok.</div>'}</div>
      ${`<form class="panel row" id="payF" style="align-items:flex-end">
        <label class="f">Tarih<input type="date" name="pay_date" value="${today()}" required></label>
        <label class="f">Tutar<input name="amount" inputmode="decimal" required style="width:120px"></label>
        <label class="f">Para<select name="currency">${S.consts.currencies.map((x) => `<option ${x === (T[0] ? T[0][0] : 'EUR') ? 'selected' : ''}>${x}</option>`).join('')}</select></label>
        <label class="f">Şekli<select name="method">${Object.entries(c.methods).map(([k, l]) => `<option value="${k}">${esc(l)}</option>`).join('')}</select></label>
        <label class="f">Grup (isteğe bağlı)<select name="booking_id"><option value="">—</option>${groups.map((b) => `<option value="${b.id}">${esc(b.code || '#' + b.id)} · ${esc(b.hotel)} ${trDate(b.check_in)}</option>`).join('')}</select></label>
        <label class="f" style="flex:1;min-width:160px">Not<input name="note" placeholder="ör. dekont no"></label>
        <button class="primary">Tahsilat ekle</button></form>`}
      <div class="tbl-wrap"><table><thead><tr><th>Tarih</th><th>Hareket</th><th>Açıklama</th><th class="num">Borç</th><th class="num">Tahsilat</th><th class="num">Bakiye</th><th></th></tr></thead><tbody>
      ${c.rows.map((r) => `<tr><td class="nowrap">${trDate(r.date)}</td><td>${r.type === 'satis' ? 'Satış' : '<b style="color:var(--ok)">Tahsilat</b>'}${r.booking_id ? ` · <a href="#/grup/${r.booking_id}">${esc(r.code || '#' + r.booking_id)}</a>` : ''}</td><td class="small">${esc(r.text)}</td>
        <td class="num">${r.debit ? money(r.debit, r.currency) : ''}</td><td class="num">${r.credit ? money(r.credit, r.currency) : ''}</td><td class="num"><b class="${r.balance > 0 ? 'neg' : ''}">${money(r.balance, r.currency)}</b></td>
        <td>${r.type === 'tahsilat' ? `<button class="link" data-delpay="${r.id}" style="color:var(--err)" title="Sil">✕</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="7" class="muted">Hareket yok.</td></tr>'}
      </tbody></table></div>`;
  }

  /* ================= KURLAR ================= */
  function tcmbBox(t, canFill) {
    if (!t || t.error || !t.USD) return `<div class="panel small muted">Merkez Bankası kuru şu an alınamadı${t && t.error ? ': ' + esc(t.error) : ''}. Kuru elle girebilirsiniz.</div>`;
    const row = (c, l) => `<tr><td>${l}</td><td class="num">${kur(t[c].buy)}</td><td class="num"><b>${kur(t[c].sell)}</b></td><td class="num">${kur(t[c].bn_buy)}</td><td class="num">${kur(t[c].bn_sell)}</td></tr>`;
    return `<div class="panel"><div class="row"><b>Merkez Bankası kuru</b><span class="muted small">${trDate(t.date)} tarihli liste${t.fetched_at ? ', son kontrol ' + esc(t.fetched_at.slice(11)) : ''}${t.stale ? ' (Merkez Bankası şu an yanıt vermiyor, son alınan kur)' : ''}</span><span class="spacer"></span>
      <button id="tcmbRefresh" title="Merkez Bankası'ndan yeniden sor">Yenile</button>${canFill ? '<button id="tcmbFill">Forma koy</button>' : ''}</div>
      <div class="tbl-wrap"><table><thead><tr><th></th><th class="num">Döviz alış</th><th class="num">Döviz satış</th><th class="num">Efektif alış</th><th class="num">Efektif satış</th></tr></thead><tbody>
      ${row('USD', '1 $ (USD)')}${row('EUR', '1 € (EUR)')}${t.GBP ? row('GBP', '1 £ (GBP)') : ''}</tbody></table></div>
      <div class="small muted">Kâr hesabında döviz satış kuru kullanılır. Merkez Bankası kuru iş günlerinde 15:30 civarı bir kez yayımlar.</div></div>`;
  }

  async function viewFx(main) {
    const d = await api('fx');
    const t = d.tcmb || {};
    main.innerHTML = `<h1>Kurlar</h1>
      <div class="muted" style="margin-bottom:12px">Merkez Bankası'nın döviz satış kuru her iş günü kendiliğinden kaydedilir (o gün için kur girilmemişse). İsterseniz kendi kurunuzu da girebilirsiniz; aynı gün için son girilen kur kullanılır. Konfirme edilen gruplar o günkü kuru kendilerine sabitler; sonraki kur değişiklikleri onları etkilemez.</div>
      ${tcmbBox(t, can('rate') && !S.egitim)}
      ${can('rate') ? `<form class="panel row" id="fxf"><label class="f">Tarih<input type="date" name="rate_date" value="${today()}"></label><label class="f">1 EUR = TL<input name="eur_try" inputmode="decimal" required></label><label class="f">1 USD = TL<input name="usd_try" inputmode="decimal" required></label><label class="f" style="flex:1">Not<input name="note" placeholder="ör. Banka satış kuru"></label><button class="primary">Kaydet</button></form>` : ''}
      <div class="tbl-wrap"><table><thead><tr><th>Tarih</th><th class="num">EUR/TL</th><th class="num">USD/TL</th><th class="num">EUR/USD</th><th>Giren</th><th>Not</th></tr></thead><tbody>
      ${d.rates.map((r) => `<tr><td>${trDate(r.rate_date)}</td><td class="num">${kur(r.eur_try)}</td><td class="num">${kur(r.usd_try)}</td><td class="num">${(r.eur_try / r.usd_try).toLocaleString('tr-TR', { minimumFractionDigits: 4, maximumFractionDigits: 4 })}</td><td>${esc(r.user_name || '')}</td><td>${esc(r.note || '')}</td></tr>`).join('') || '<tr><td colspan="6" class="muted">Henüz kur girilmedi.</td></tr>'}
      </tbody></table></div>`;
    const f = qs('#fxf', main);
    const tb = qs('#tcmbFill', main);
    if (tb && f) tb.onclick = () => { f.rate_date.value = t.date; f.eur_try.value = t.EUR.sell; f.usd_try.value = t.USD.sell; f.note.value = 'TCMB döviz satış'; f.eur_try.focus(); };
    const tr = qs('#tcmbRefresh', main);
    if (tr) tr.onclick = async () => { try { await api('tcmb', { force: 1 }); route(); } catch (er) { toast(er.message, 'err'); } };
    if (f) f.onsubmit = async (e) => { e.preventDefault(); try { await api('fx_save', {}, Object.fromEntries(new FormData(f))); toast('Kur kaydedildi.', 'ok'); route(); } catch (er) { toast(er.message, 'err'); } };
  }

  /* ================= OTEL LİSTESİ ================= */
  async function hotelBulkImport() {
    const r = await modal('Toplu otel ekle', `<p class="small">Excel'den otel adı sütununu kopyalayıp buraya yapıştırın; her satıra bir otel. Paneldeki otellerle karşılaştırılır, aynı olanlar ikinci kez eklenmez. Önce kontrol listesi gösterilir.</p>
      <textarea name="text" rows="12" placeholder="GRAND MARCELLO HOTEL&#10;MARMARAY HOTEL YENIKAPI&#10;…" style="width:100%"></textarea>`, { ok: 'Kontrol et' });
    if (!r || !r.text.trim()) return;
    let pv;
    try { pv = await api('hotels_import', {}, { text: r.text, commit: 0 }); } catch (e) { toast(e.message, 'err'); return; }
    const li = (a) => a.map((x) => `<li>${esc(x)}</li>`).join('');
    let sims = pv.similar.filter((x) => !x.same).map((x) => x.name);
    const ok = await modal('Kontrol: toplu otel ekleme', `<div class="small" style="max-height:60vh;overflow:auto">
      <p><b>${pv.new.length}</b> yeni otel eklenecek, <b>${pv.exists.length}</b> otel zaten panelde${pv.similar.length ? `, <b>${pv.similar.length}</b> otel paneldekine benziyor` : ''}.</p>
      ${pv.similar.length ? `<h3>Benzer olanlar</h3><p>İşaretli olanlar ayrı otel olarak eklenir. Paneldekiyle aynı otelse işareti kaldırın.</p>${pv.similar.map((x) => `<label class="chk" style="display:flex;gap:6px;margin:3px 0"><input type="checkbox" data-sim="1" value="${esc(x.name)}" ${x.same ? '' : 'checked'}> <span><b>${esc(x.name)}</b> <span class="muted">· panelde: ${esc(x.panel)}</span></span></label>`).join('')}` : ''}
      ${pv.new.length ? `<details open><summary><b>Eklenecekler (${pv.new.length})</b></summary><ul style="columns:2;margin:6px 0">${li(pv.new)}</ul></details>` : ''}
      ${pv.exists.length ? `<details><summary>Zaten panelde olanlar (${pv.exists.length})</summary><ul>${pv.exists.map((x) => `<li>${esc(x.name)} <span class="muted">= ${esc(x.panel)}</span></li>`).join('')}</ul></details>` : ''}
      <p class="muted">Yeni oteller "Otel listesi"nde ve yazarken çıkan önerilerde görünür. "Oteller" sayfasında kart olarak, grup ya da sözleşme girildiğinde çıkar. Bölge, adındaki semtten (Laleli, Taksim…) tahmin edilir.</p></div>`,
      { ok: 'Ekle', onOpen: (bg) => { bg.querySelector('.modal').style.maxWidth = '760px'; bg.addEventListener('change', () => { sims = qsa('input[data-sim]', bg).filter((c) => c.checked).map((c) => c.value); }); } });
    if (!ok) return;
    try {
      const res = await api('hotels_import', {}, { text: r.text, commit: 1, add_similar: sims });
      toast(`${res.added} otel eklendi.`, 'ok'); route();
    } catch (e) { toast(e.message, 'err'); }
  }

  async function viewHotelAdmin(main) {
    const d = await api('hotels');
    main.innerHTML = `<div class="row"><h1>Otel listesi</h1><span class="spacer"></span><button id="bulkH">Toplu ekle (listeden yapıştır)</button><button class="primary" id="addH">+ Otel ekle</button></div>
      <div class="small muted" style="margin-bottom:10px">Pasif oteller ızgarada görünmez ama eski grupları saklanır.</div>
      <div class="row" style="margin-bottom:8px"><input id="hlQ" type="search" placeholder="Otel ya da bölge ara…" style="max-width:340px"><span class="small muted" id="hlN"></span></div>
      <div class="tbl-wrap"><table><thead><tr><th>Otel</th><th>Yıldız</th><th>Bölge</th><th>Durum</th></tr></thead><tbody>
      ${d.hotels.map((h) => `<tr class="click" data-id="${h.id}"><td>${esc(h.name)}</td><td>${h.stars ? '★'.repeat(+h.stars) : ''}</td><td>${esc(h.region || '')}</td><td>${+h.active ? 'Aktif' : '<span class="badge st-iptal">Pasif</span>'}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">Henüz otel yok.</td></tr>'}
      </tbody></table></div>`;
    qs('#addH', main).onclick = () => hotelForm();
    qs('#bulkH', main).onclick = () => hotelBulkImport();
    qsa('tr.click', main).forEach((r) => r.onclick = () => go('#/otel/' + r.dataset.id));
    tableFilter(qs('#hlQ', main), qs('tbody', main), qs('#hlN', main));
  }

  /* ================= KULLANICILAR ================= */
  async function viewUsers(main) {
    const d = await api('users');
    main.innerHTML = `<div class="row"><h1>Kullanıcılar</h1><span class="spacer"></span><button class="primary" id="addU">+ Kullanıcı ekle</button></div>
      <div class="small muted" style="margin-bottom:10px">Her çalışana ayrı kullanıcı açın; işlem kaydında kimin ne yaptığı bu adla görünür. Ayrılan çalışanı silmek yerine pasif yapın.</div>
      <div class="tbl-wrap"><table><thead><tr><th>Ad</th><th>Kullanıcı adı</th><th>Rol</th><th>Durum</th><th>Son giriş</th><th></th></tr></thead><tbody>
      ${d.users.map((u) => `<tr><td>${esc(u.name)}</td><td>${esc(u.username)}</td><td>${esc(S.consts.roles[u.role] || u.role)}</td><td>${+u.active ? 'Aktif' : '<span class="badge st-iptal">Pasif</span>'}</td><td>${trDT(u.last_login)}</td><td><button class="link" data-u="${u.id}">Düzenle</button></td></tr>`).join('')}
      </tbody></table></div>
      <h2>Roller</h2><div class="panel small">
        <b>Yönetici:</b> her şey; konfirme grubun fiyatını değiştirir, zararlı grubu onaylar, kullanıcı ekler, işlem kaydını ve yedeği görür.<br>
        <b>Operasyon:</b> grup, otel, sözleşme fiyatı, acente ve kur girer.<br>
        <b>Rezervasyon:</b> grup, otel ve acente girer.<br>
        <b>Muhasebe:</b> kur ve acente bilgisi girer, her şeyi görür.<br>
        Alış ve satış fiyatlarını tüm roller görür.</div>`;
    const form = async (u) => {
      const v = u || { role: 'operasyon', active: 1 };
      const r = await modal(u ? 'Kullanıcıyı düzenle' : 'Yeni kullanıcı', `<div class="grid-form">
        <label class="f wide">Ad soyad<input name="name" value="${esc(v.name || '')}"></label>
        ${u ? '' : '<label class="f">Kullanıcı adı<input name="username" autocapitalize="none" placeholder="ör. ceren"></label>'}
        <label class="f">Rol<select name="role">${Object.entries(S.consts.roles).map(([k, t]) => `<option value="${k}" ${k === v.role ? 'selected' : ''}>${t}</option>`).join('')}</select></label>
        <label class="f">${u ? 'Yeni şifre (değişmeyecekse boş)' : 'Şifre (en az 8)'}<input name="password" type="text" autocomplete="new-password"></label>
        ${u ? `<label class="chk"><input type="checkbox" name="active" ${+v.active ? 'checked' : ''}> Aktif</label>` : ''}</div>
        <p class="small muted">Şifreyi çalışana kendiniz iletin; ilk girişte "Hesabım" sayfasından değiştirmesini isteyin.</p>`, { ok: 'Kaydet' });
      if (!r) return;
      try { await api('user_save', {}, Object.assign(r, u ? { id: u.id } : {})); toast('Kullanıcı kaydedildi.', 'ok'); route(); } catch (e) { toast(e.message, 'err'); }
    };
    // E-posta gönderimi (otel fişleri) ayarı
    const mail = await api('mail_settings').catch(() => null);
    if (mail) {
      main.insertAdjacentHTML('beforeend', `<h2>E-posta gönderimi</h2>
        <form class="panel" id="mailF" style="max-width:640px">
          <div class="small muted" style="margin-bottom:8px">Otel rezervasyon fişleri bu adresten gider, otelin cevabı da bu adrese gelir. Microsoft 365 için sunucu smtp.office365.com, port 587. Bu bölümü yalnız yöneticiler görür; şifre güvenlik için ekranda gösterilmez.</div>
          <div class="grid-form">
            <label class="f wide">Gönderen adres<input name="user" inputmode="email" value="${esc(mail.user || '')}" placeholder="reservation@luxlocationstours.com"></label>
            ${mail.has_pass
              ? `<div class="f" id="passOk"><span>Şifre</span><div class="row" style="gap:8px;align-items:center"><span class="badge st-konfirme">✓ Şifre kayıtlı</span><button type="button" class="link" id="passChg">Şifreyi değiştir</button></div></div>
                 <label class="f" id="passNew" style="display:none">Yeni şifre<input name="pass" type="password" autocomplete="new-password" placeholder="yalnız değiştirecekseniz"></label>`
              : '<label class="f">Şifre<input name="pass" type="password" autocomplete="new-password"></label>'}
            <label class="f">Gönderen adı<input name="from_name" value="${esc(mail.from_name || '')}"></label>
            <label class="f">Sunucu<input name="host" value="${esc(mail.host || 'smtp.office365.com')}"></label>
            <label class="f">Port<input name="port" inputmode="numeric" value="${mail.port || 587}"></label>
          </div>
          <div class="row" style="margin-top:8px"><span class="small ${mail.ready ? '' : 'neg'}">${mail.ready ? 'Hazır.' : 'Henüz ayarlanmadı.'}</span><span class="spacer"></span>
            <button type="button" id="mailT" ${mail.ready ? '' : 'disabled'}>Deneme e-postası gönder</button><button class="primary">Kaydet</button></div>
        </form>`);
      const mf = qs('#mailF', main);
      const pc = qs('#passChg', main);
      if (pc) pc.onclick = () => {
        if (!confirm('Kayıtlı şifre çalışıyor. Yalnız reservation@ şifresi değiştiyse yenisini girin. Devam edilsin mi?')) return;
        qs('#passOk', main).style.display = 'none'; qs('#passNew', main).style.display = ''; qs('#passNew input', main).focus();
      };
      mf.onsubmit = async (e) => { e.preventDefault(); try { await api('mail_settings_save', {}, Object.fromEntries(new FormData(mf))); toast('E-posta ayarı kaydedildi.', 'ok'); route(); } catch (er) { toast(er.message, 'err'); } };
      qs('#mailT', main).onclick = async (e) => {
        e.target.disabled = true; e.target.textContent = 'Gönderiliyor...';
        try { const r = await api('mail_test', {}, {}); toast('Deneme e-postası gönderildi: ' + r.to, 'ok'); } catch (er) { toast(er.message, 'err'); }
        e.target.disabled = false; e.target.textContent = 'Deneme e-postası gönder';
      };
    }
    qs('#addU', main).onclick = () => form(null);
    qsa('[data-u]', main).forEach((b) => b.onclick = () => form(d.users.find((u) => u.id == b.dataset.u)));
  }

  /* ================= İŞLEM KAYDI ================= */
  async function viewAudit(main, p) {
    const d = await api('audit', p);
    const ents = { grup: 'Grup', otel: 'Otel', acente: 'Acente', sozlesme: 'Sözleşme', kontenjan: 'Kontenjan', kur: 'Kur', tahsilat: 'Tahsilat', talep: 'Talep', ayar: 'Ayar', user: 'Kullanıcı', yedek: 'Yedek' };
    const set = (o) => go('#/kayit?' + new URLSearchParams(Object.entries(Object.assign({}, p, o)).filter(([, v]) => v)).toString());
    main.innerHTML = `<div class="row"><h1>İşlem kaydı</h1><span class="spacer"></span>${can('backup') && !S.egitim ? '<button id="nightly">Gece yedekleri</button><a class="btn" href="api.php?a=backup">Yedek indir (JSON)</a>' : ''}</div>
      <div class="small muted">Panelde yapılan her ekleme, değişiklik, iptal ve giriş burada; kim, ne zaman, hangi IP'den. Kayıtlar silinemez.${S.egitim ? ' <b>Eğitim bölümündesiniz: burada eğitim kayıtları görünür; giriş-çıkışlar gerçek kayıtta tutulur.</b>' : ''}</div>
      <div class="filters">
        <label class="f">Tür<select id="aE"><option value="">Tümü</option>${Object.entries(ents).map(([k, t]) => `<option value="${k}" ${p.entity === k ? 'selected' : ''}>${t}</option>`).join('')}</select></label>
        <label class="f">Kişi<select id="aU"><option value="">Tümü</option>${d.users.map((u) => `<option ${p.user === u ? 'selected' : ''}>${esc(u)}</option>`).join('')}</select></label>
        <label class="f">Gün<input type="date" id="aD" value="${p.date || ''}"></label>
      </div>
      <div class="tbl-wrap"><table><thead><tr><th>Zaman</th><th>Kişi</th><th>IP</th><th>Tür</th><th>İşlem</th></tr></thead><tbody>
      ${d.rows.map((r) => `<tr><td class="nowrap">${trDT(r.at)}</td><td>${esc(r.user_name || '')}</td><td class="small muted">${esc(r.ip || '')}</td><td>${esc(ents[r.entity] || r.entity)}</td>
        <td>${r.entity === 'grup' && r.entity_id ? `<a href="#/grup/${r.entity_id}">${esc(r.summary)}</a>` : esc(r.summary)}${(r.changes || []).map((c) => `<div class="small muted">${esc(c.field)}: <b>${esc(c.from)}</b> → <b>${esc(c.to)}</b></div>`).join('')}</td></tr>`).join('') || '<tr><td colspan="5" class="muted">Kayıt yok.</td></tr>'}
      </tbody></table></div>`;
    qs('#aE', main).onchange = (e) => set({ entity: e.target.value });
    qs('#aU', main).onchange = (e) => set({ user: e.target.value });
    qs('#aD', main).onchange = (e) => set({ date: e.target.value });
    const nb = qs('#nightly', main);
    if (nb) nb.onclick = async () => {
      try {
        const y = await api('yedekler');
        await modal('Gece yedekleri', `<p class="small muted">Panel her gün sabah ilk kullanımda veritabanının ve site taleplerinin sıkıştırılmış kopyasını alır; son 30 gün saklanır. Son çalışma: ${esc(y.last || '—')}.</p>
          ${y.files.length ? `<div class="tbl-wrap"><table><tbody>${y.files.map((f) => `<tr><td><a href="api.php?a=yedek_indir&name=${encodeURIComponent(f.name)}">${esc(f.name)}</a></td><td class="num">${nf0.format(Math.ceil(f.size / 1024))} KB</td><td class="small muted">${esc(f.at)}</td></tr>`).join('')}</tbody></table></div>` : '<p>Henüz gece yedeği yok; yarın sabah ilk girişte alınır.</p>'}`, { ok: 'Kapat' });
      } catch (er) { toast(er.message, 'err'); }
    };
  }

  /* ================= HESABIM ================= */
  function viewAccount(main) {
    main.innerHTML = `<h1>Hesabım</h1><div class="muted">${esc(S.user.name)} · ${esc(S.user.username)} · ${esc(S.user.role_name)}</div>
      <form class="panel" id="pw" style="max-width:420px;margin-top:14px"><h3 style="margin-top:0">Şifre değiştir</h3>
        <label class="f">Mevcut şifre<input type="password" name="old" autocomplete="current-password"></label><br>
        <label class="f">Yeni şifre (en az 8 karakter)<input type="password" name="new" autocomplete="new-password"></label><br>
        <button class="primary">Değiştir</button></form>`;
    const f = qs('#pw', main);
    f.onsubmit = async (e) => { e.preventDefault(); try { await api('password', {}, Object.fromEntries(new FormData(f))); toast('Şifre değişti.', 'ok'); f.reset(); } catch (er) { toast(er.message, 'err'); } };
  }

  /* ================= EĞİTİM ================= */
  function viewGuide(main) {
    const G = window.LX_GUIDE;
    main.innerHTML = `<div class="guide"><nav class="toc"><b style="padding:6px 8px">Eğitim rehberi</b>${G.lessons.map((l, i) => `<a href="#/egitim" data-to="l${i}">${i + 1}. ${esc(l.title)}</a>`).join('')}<a href="#/egitim" data-to="tasks"><b>Alıştırmalar</b></a></nav>
      <div>
        <div class="lesson"><h1>Panele hoş geldiniz</h1>${G.intro}
          <div class="row" style="margin-top:12px">${S.egitim ? '<span class="badge st-konaklamada">Şu an eğitim bölümündesiniz: örnek verilerle deneyebilirsiniz.</span><a class="btn primary" href="#/">Örnek otellere git</a>' : '<button class="primary" id="goTrain">Eğitim bölümüne geç ve dene</button>'}</div></div>
        ${G.lessons.map((l, i) => `<section class="lesson" id="l${i}"><h2>${i + 1}. ${esc(l.title)}</h2>${l.html}</section>`).join('')}
        <section class="lesson" id="tasks"><h2>Alıştırmalar (eğitim bölümünde yapın)</h2>${G.tasks.map((t, i) => `<div class="task"><span class="n">${i + 1}</span><div>${t}</div></div>`).join('')}
          <p class="small muted">Bir şeyi bozarsanız sorun değil: üstteki turuncu çubuktan "Örnek verileri baştan yükle" ile her şey ilk hâline döner.</p></section>
      </div></div>`;
    qsa('[data-to]', main).forEach((a) => a.onclick = (e) => { e.preventDefault(); document.getElementById(a.dataset.to).scrollIntoView({ behavior: 'smooth' }); });
    const gt = qs('#goTrain', main);
    if (gt) gt.onclick = async () => { const r = await api('mode', {}, { egitim: true }); S.egitim = r.egitim; S.lastAudit = 0; toast('Eğitim bölümüne geçtiniz.', 'ok'); route(); };
  }

  /* ================= NOT DEFTERİ ================= */
  async function viewNotes(main, p) {
    const all = p.hepsi === '1';
    const [d, hs, ag] = await Promise.all([api('notes', all ? { all: 1 } : {}), api('hotels'), api('agency_names')]);
    const t = today();
    const noteRow = (n) => {
      const late = !+n.done && n.due_date && n.due_date < t;
      const links = [n.hotel_name && `<a href="#/otel/${n.hotel_id}">${esc(n.hotel_name)}</a>`, n.agency_name && `<a href="#/acente/${n.agency_id}">${esc(n.agency_name)}</a>`,
        n.booking_id && `<a href="#/grup/${n.booking_id}">${esc(n.booking_code || 'grup')}</a>`].filter(Boolean).join(' · ');
      return `<div class="note ${+n.done ? 'done' : ''} ${late ? 'late' : ''}">
        <label class="chk"><input type="checkbox" data-id="${n.id}" ${+n.done ? 'checked' : ''}></label>
        <div class="nb"><div class="nt">${esc(n.text)}</div>
          <div class="small muted">${n.due_date ? `<b class="${late ? 'neg' : ''}">${trDate(n.due_date)}</b> · ` : ''}${links ? links + ' · ' : ''}${esc(n.created_by_name || '')} ${trDT(n.created_at)}${n.via === 'asistan' ? ' · sesli asistan' : ''}${+n.done ? ` · yapıldı: ${esc(n.done_by_name || '')} ${trDT(n.done_at)}` : ''}</div></div></div>`;
    };
    main.innerHTML = `<div class="row"><h1>Notlar</h1><span class="spacer"></span><a class="btn" href="#/notlar${all ? '' : '?hepsi=1'}">${all ? 'Yalnız açık notlar' : 'Yapılanları da göster'}</a></div>
      <div class="muted small" style="margin-bottom:10px">Hatırlatmalar ve ofis notları. Sesli asistana "not al" diyerek de ekleyebilirsiniz.</div>
      <form class="panel" id="nf"><div class="grid-form">
        <label class="f wide">Not<textarea name="text" required placeholder="ör. Pazartesi Glorious'u ara, Harun Bey yaz fiyatını verecek"></textarea></label>
        <label class="f">Hatırlatma tarihi<input type="date" name="due_date"></label>
        <label class="f">Otel<select name="hotel_id"><option value="">—</option>${hs.hotels.map((h) => `<option value="${h.id}">${esc(h.name)}</option>`).join('')}</select></label>
        <label class="f">Acente<select name="agency_id"><option value="">—</option>${ag.agencies.map((a) => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select></label>
      </div><div class="row" style="margin-top:10px"><button class="primary">Notu ekle</button></div></form>
      <div class="notes">${d.notes.map(noteRow).join('') || '<div class="empty">Açık not yok.</div>'}</div>`;
    const f = qs('#nf', main);
    hotelCombo(f); agencyCombo(f, ag.agencies);
    f.onsubmit = async (e) => { e.preventDefault(); try { await api('note_save', {}, Object.fromEntries(new FormData(f))); toast('Not eklendi.', 'ok'); route(); } catch (er) { toast(er.message, 'err'); } };
    qsa('.note input[type=checkbox]', main).forEach((c) => c.onchange = async () => {
      try { await api('note_done', {}, { id: +c.dataset.id, done: c.checked }); route(); } catch (er) { toast(er.message, 'err'); c.checked = !c.checked; }
    });
    S.view = { onRemote: () => viewNotes(main, p) };
  }

  /* ================= SESLİ ASİSTAN ================= */
  const A = { el: null, open: false, busy: false, log: [], rec: null, listening: false, speak: false, status: null, mode: null };
  try { A.speak = localStorage.getItem('lx_asst_speak') === '1'; } catch (e) { /* yok */ }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

  function asstHide() { if (A.el) A.el.classList.add('hidden'); }

  function asstMount() {
    if (!A.el) {
      A.el = document.createElement('div');
      A.el.id = 'asst';
      A.el.innerHTML = `
        <button class="asst-fab" id="asstFab" title="Sesli asistan (Ctrl + Boşluk)"><span class="mic-ico">🎤</span><span>Asistan</span></button>
        <aside class="asst-panel" aria-label="Sesli asistan">
          <div class="asst-head"><b>Sesli asistan</b><span class="spacer"></span>
            <label class="small" title="Asistanın cevabını sesli okur"><input type="checkbox" id="asstSpeak"> Sesli cevap</label>
            <button class="link" id="asstNew" title="Konuşmayı temizle">Yeni konuşma</button><button class="link" id="asstClose" title="Kapat">✕</button></div>
          <div class="asst-log" id="asstLog"></div>
          <form class="asst-input" id="asstForm">
            <button type="button" class="asst-mic" id="asstMic" title="Basın ve konuşun">🎤</button>
            <textarea id="asstText" rows="2" placeholder="Konuşun ya da yazın: &quot;Glorious'a Marwen için 10 Aralık'tan 3 gece 10 DBL opsiyon aç, satış 55 dolar&quot;"></textarea>
            <button class="primary" id="asstSend">Gönder</button>
          </form>
        </aside>`;
      document.body.appendChild(A.el);
      qs('#asstFab').onclick = () => asstToggle(true, true);
      qs('#asstClose').onclick = () => asstToggle(false);
      qs('#asstNew').onclick = async () => { try { await api('assistant_reset', {}, {}); } catch (e) { /* önemsiz */ } A.log = []; asstRender(); };
      qs('#asstSpeak').checked = A.speak;
      qs('#asstSpeak').onchange = (e) => { A.speak = e.target.checked; try { localStorage.setItem('lx_asst_speak', A.speak ? '1' : '0'); } catch (er) { /* yok */ } if (!A.speak) speechSynthesis?.cancel(); };
      qs('#asstForm').onsubmit = (e) => { e.preventDefault(); asstSend(); };
      qs('#asstText').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); asstSend(); } });
      qs('#asstMic').onclick = () => asstListen();
      if (!SR) qs('#asstMic').classList.add('hidden');
      document.addEventListener('keydown', (e) => { if (e.ctrlKey && e.code === 'Space' && S.user) { e.preventDefault(); asstToggle(true, true); } });
    }
    A.el.classList.remove('hidden');
    if (A.mode !== null && A.mode !== S.egitim) { A.log = []; asstRender(); }
    A.mode = S.egitim;
  }

  async function asstToggle(open, listen = false) {
    if (!A.el) return;
    A.open = open;
    A.el.classList.toggle('open', open);
    if (!open) { asstStopListen(); return; }
    if (!A.status) { try { A.status = await api('assistant_status'); } catch (e) { A.status = { configured: false }; } }
    asstRender();
    if (!A.status.configured) return;
    if (listen && SR && !A.busy) asstListen(); else qs('#asstText').focus();
  }

  function asstAdd(entry) { A.log.push(entry); asstRender(); }

  function asstRender() {
    const box = qs('#asstLog');
    if (!box) return;
    let html = '';
    if (A.status && !A.status.configured) {
      if (!A.status.installed) html += `<div class="asst-msg sys">Asistan kütüphanesi kurulmamış (vendor klasörü). Kurulum için README'deki "Sesli asistan" bölümüne bakın.</div>`;
      else if (A.status.can_set_key) html += `<div class="asst-msg sys"><b>Asistanı açmak için API anahtarı gerekiyor.</b><br>console.anthropic.com adresinde "API Keys" bölümünden bir anahtar oluşturup buraya yapıştırın. Anahtar yalnız bu sunucuda saklanır, ekranda bir daha gösterilmez.
        <form id="asstKey" class="row" style="margin-top:8px"><input name="key" type="password" autocomplete="off" placeholder="sk-ant-..." style="flex:1;min-width:0;padding:7px 9px;border:1px solid var(--line);border-radius:6px"><button class="primary">Kaydet</button></form></div>`;
      else html += `<div class="asst-msg sys">Asistan henüz açılmadı. Yöneticinin API anahtarını girmesi gerekiyor.</div>`;
    } else if (!A.log.length) {
      html += `<div class="asst-msg sys">Ne yapmak istediğinizi söyleyin. Örnekler:<br>
        • "Bu ay en çok kâr eden üç otel hangisi?"<br>• "LUX6302'yi konfirme et"<br>• "Tudan'ın Ramada grubunu Hello'ya devret"<br>• "Bugünün kuru euro 50,10 dolar 43,05"<br>• "Not al: yarın Harun Bey'i ara"<br>
        Kayıtları değiştiren her işlemden önce onay kartı çıkar. Notlar onaysız eklenir.${SR ? '' : '<br><b>Bu tarayıcı sesle yazmayı desteklemiyor; Chrome veya Edge kullanın ya da yazın.</b>'}</div>`;
    }
    A.log.forEach((m, i) => {
      if (m.kind === 'user') html += `<div class="asst-msg me">${esc(m.text)}</div>`;
      else if (m.kind === 'wait') html += `<div class="asst-msg bot wait">Düşünüyorum…</div>`;
      else if (m.kind === 'err') html += `<div class="asst-msg err">${esc(m.text)}</div>`;
      else if (m.kind === 'bot') {
        html += (m.done || []).map((d) => `<div class="asst-done">✓ ${d.link ? `<a href="${esc(d.link)}">${esc(d.summary)}</a>` : esc(d.summary)}</div>`).join('');
        if (m.text) html += `<div class="asst-msg bot">${esc(m.text).replace(/\n/g, '<br>')}</div>`;
        if (m.proposals && m.proposals.length) {
          html += `<div class="asst-card ${m.decided ? 'decided' : ''}">${m.proposals.map((p) => `<div class="pc"><b>${esc(p.title)}</b>${p.lines.map((l) => `<div class="small">${esc(l)}</div>`).join('')}
            ${p.calc ? `<div class="pcalc">Kâr: <b class="${p.calc.profit_eur < 0 ? 'neg' : ''}">${money(p.calc.profit_eur, 'EUR')}</b> · ${money(p.calc.profit_usd, 'USD')} · ${money0(p.calc.profit_try, 'TRY')}<span class="muted"> (alış ${money(p.calc.buy[0], p.calc.buy[1])}, satış ${money(p.calc.sell[0], p.calc.sell[1])})</span></div>
              ${p.calc.warnings.map((w) => `<div class="w ${w.level}">${esc(w.msg)}</div>`).join('')}` : ''}</div>`).join('')}
            ${m.decided ? `<div class="small muted">${m.decided === 'yes' ? 'Onaylandı.' : 'Vazgeçildi.'}</div>` : `<div class="row"><button class="ok" data-yes="${i}">Onayla</button><button data-no="${i}">Vazgeç</button></div>`}</div>`;
        }
      }
    });
    box.innerHTML = html;
    const kf = qs('#asstKey', box);
    if (kf) kf.onsubmit = async (e) => {
      e.preventDefault();
      try { A.status = await api('assistant_key', {}, { key: kf.key.value }); kf.reset(); toast(A.status.configured ? 'Asistan açıldı.' : 'Anahtar kaydedilemedi.', A.status.configured ? 'ok' : 'err'); asstRender(); }
      catch (er) { toast(er.message, 'err'); }
    };
    qsa('[data-yes]', box).forEach((b) => b.onclick = () => asstDecide(+b.dataset.yes, true));
    qsa('[data-no]', box).forEach((b) => b.onclick = () => asstDecide(+b.dataset.no, false));
    box.scrollTop = box.scrollHeight;
    const busy = A.busy;
    ['#asstSend', '#asstMic'].forEach((s) => { const el = qs(s); if (el) el.disabled = busy || !(A.status && A.status.configured); });
  }

  async function asstRun(call) {
    A.busy = true;
    asstAdd({ kind: 'wait' });
    try {
      const r = await call();
      A.log = A.log.filter((m) => m.kind !== 'wait');
      asstAdd({ kind: 'bot', text: r.reply, proposals: r.proposals, done: r.done });
      if (A.speak && r.reply && window.speechSynthesis) {
        speechSynthesis.cancel();
        const u = new SpeechSynthesisUtterance(r.reply.replace(/[*_#`]/g, '')); u.lang = 'tr-TR'; speechSynthesis.speak(u);
      }
      if ((r.done || []).length && !S.dirty) { S.lastAudit = 0; route(); }
    } catch (e) {
      A.log = A.log.filter((m) => m.kind !== 'wait');
      asstAdd({ kind: 'err', text: e.message });
    } finally { A.busy = false; asstRender(); }
  }

  function asstSend(text) {
    const ta = qs('#asstText');
    const t = (text ?? ta.value).trim();
    if (!t || A.busy) return;
    ta.value = '';
    A.log.forEach((m) => { if (m.proposals && m.proposals.length && !m.decided) m.decided = 'no'; });
    asstAdd({ kind: 'user', text: t });
    asstRun(() => api('assistant', {}, { text: t }));
  }

  function asstDecide(i, yes) {
    const m = A.log[i];
    if (!m || m.decided || A.busy) return;
    m.decided = yes ? 'yes' : 'no';
    asstRun(() => api('assistant_confirm', {}, { approve: yes }));
  }

  function asstStopListen() { if (A.rec) { try { A.rec.abort(); } catch (e) { /* yok */ } } A.rec = null; A.listening = false; qs('#asstMic')?.classList.remove('on'); }

  function asstListen() {
    if (!SR || A.busy) return;
    if (A.listening) { A.rec?.stop(); return; }
    window.speechSynthesis?.cancel();
    const rec = new SR();
    rec.lang = 'tr-TR'; rec.interimResults = true; rec.continuous = false; rec.maxAlternatives = 1;
    const ta = qs('#asstText');
    let finalText = '';
    rec.onresult = (e) => {
      let interim = '';
      for (let i = e.resultIndex; i < e.results.length; i++) {
        if (e.results[i].isFinal) finalText += e.results[i][0].transcript; else interim += e.results[i][0].transcript;
      }
      ta.value = (finalText + ' ' + interim).trim();
    };
    rec.onerror = (e) => {
      if (e.error === 'not-allowed' || e.error === 'service-not-allowed') toast('Mikrofon izni verilmedi. Tarayıcının adres çubuğundaki mikrofon simgesinden izin verin.', 'err');
      else if (e.error === 'no-speech') toast('Ses duyulmadı, tekrar deneyin.');
      else if (e.error !== 'aborted') toast('Ses tanıma hatası: ' + e.error, 'err');
    };
    rec.onend = () => {
      A.listening = false; A.rec = null; qs('#asstMic')?.classList.remove('on');
      if (finalText.trim()) asstSend(finalText);
    };
    A.rec = rec; A.listening = true; qs('#asstMic').classList.add('on');
    try { rec.start(); } catch (e) { asstStopListen(); }
  }

  /* ---------- başlangıç ---------- */
  // Site yönetiminden (/admin/) gelindiyse girişten sonra oraya dön (tek giriş)
  const NEXT = (() => { const n = new URLSearchParams(location.search).get('next') || ''; return /^\/admin\/[\w\-\/.?=&%]*$/.test(n) && !n.includes('..') ? n : ''; })();
  function start() {
    if (!S.user) { asstHide(); return renderLogin(); }
    if (NEXT) { location.replace(NEXT); return; }
    asstMount();
    S.lastAudit = 0;
    poll();
    startPoll();
    route();
  }
  (async () => {
    try {
      const s = await loadSession();
      if (s.setup) return renderLogin(true);
      start();
    } catch (e) { $app.innerHTML = `<div class="boot">Panel açılamadı: ${esc(e.message)}</div>`; }
  })();
})();
