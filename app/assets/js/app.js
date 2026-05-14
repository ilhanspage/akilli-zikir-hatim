(() => {
  const API = '/api/app.php';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const app = $('#app');
  const toastEl = $('#toast');
  const modal = $('#modal');
  const modalContent = $('#modalContent');
  const modalClose = $('#modalClose');

  const DEFAULT_SETTINGS = {
    nickname: 'Misafir',
    vibration: true,
    sound: true,
    clientId: makeClientId(),
    defaultTarget: 1000,
    autoSaveOnTarget: false,
    keepAwake: false,
    remindersEnabled: false,
    reminderTimes: ['07:00', '13:30', '21:00'],
    dailyPlans: [
      { zikirId: 1, target: 100 },
      { zikirId: 4, target: 100 },
      { zikirId: 2, target: 33 }
    ],
    favoriteZikirIds: [],
    lastSyncAt: '',
    journalReminderEnabled: true,
    homeCompactMode: true,
    counterFocusMode: false,
    statsQuickView: 'summary',
    smartResumeCard: true,
    dailyChecklistCard: true,
    firstUseGuideDone: false,
    mobileEaseCard: true,
    sessionSummaryAfterSave: true
  };

  const state = {
    route: 'home',
    data: load('az_bootstrap', null),
    counter: load('az_counter', { zikirId: 1, count: 0, target: 1000, startedAt: Date.now(), completedToastAt: 0, intent: '' }),
    settings: Object.assign({}, DEFAULT_SETTINGS, load('az_settings', {})),
    queue: load('az_queue', []),
    tesbihat: load('az_tesbihat', { active: false, mode: 'classic99', step: 0, startedAt: 0, completedAt: 0, completedSessions: 0 }),
    vird: load('az_vird', { active: false, routineId: 'morning', step: 0, startedAt: 0, completedAt: 0, completedRoutines: 0 }),
    currentCommunitySession: null,
    selectedHatimJuz: null,
    duaFilter: 'all',
    duaCategoryFilter: 'all',
    hatimFilter: 'all',
    wakeLock: null,
    touchGuard: { pointerId: null, startAt: 0, moved: false, longPress: false, x: 0, y: 0, longTimer: null },
    modalState: { scrollY: 0 },
    activePicker: null
  };
  if (!state.settings.clientId) state.settings.clientId = makeClientId();
  if (!localStorage.getItem('az_counter_feedback_v104')) {
    state.settings.vibration = true;
    state.settings.sound = true;
    localStorage.setItem('az_counter_feedback_v104', '1');
  }
  save('az_settings', state.settings);

  function makeClientId() {
    let id = localStorage.getItem('az_client_id');
    if (!id) {
      id = 'c_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
      localStorage.setItem('az_client_id', id);
    }
    return id;
  }
  function load(key, fallback) { try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch { return fallback; } }
  function save(key, val) { localStorage.setItem(key, JSON.stringify(val)); }
  function esc(v) { return String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])); }
  function fmt(n) { return Number(n || 0).toLocaleString('tr-TR'); }
  function percent(a, b) { return b ? Math.min(100, Math.round((Number(a || 0) / Number(b || 1)) * 100)) : 0; }
  function nowDate() { return new Date().toISOString().slice(0, 10); }
  function syncTimeLabel(value = state.settings.lastSyncAt) {
    if (!value) return 'Henüz yok';
    try { return new Date(value).toLocaleString('tr-TR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' }); } catch { return 'Henüz yok'; }
  }
  function queueLabel(action) {
    return ({
      zikir_contribute: 'Toplu zikre katkı',
      dua_add: 'Dua isteği gönderimi',
      dua_amin: 'Dua halkası Âmin',
      hatim_take: 'Hatim cüz alma',
      hatim_complete: 'Hatim cüz tamamlama',
      hatim_release: 'Hatim cüz bırakma'
    })[action] || 'Bekleyen işlem';
  }
  function duaCategory(r) { return (r?.category || 'Genel').trim() || 'Genel'; }
  function sameCategory(a, b) { return String(a || '').toLocaleLowerCase('tr-TR') === String(b || '').toLocaleLowerCase('tr-TR'); }
  function titleForRoute(route) {
    return {home:'Akıllı Zikir & Hatim', counter:'Zikir Sayacı', zikir:'Toplu Zikir Halkası', dua:'Toplu Dua Halkası', hatim:'Hatim Halkası', stats:'İstatistikler', settings:'Ayarlar'}[route] || 'Akıllı Zikir & Hatim';
  }
  function toast(msg) { toastEl.textContent = msg; toastEl.classList.add('show'); clearTimeout(toastEl._t); toastEl._t = setTimeout(() => toastEl.classList.remove('show'), 2300); }
  function lockModalScroll(y) {
    state.modalState.scrollY = Number(y || 0);
    document.body.classList.add('modal-open');
    document.body.style.top = `-${state.modalState.scrollY}px`;
  }
  function unlockModalScroll() {
    const y = Number(state.modalState?.scrollY || 0);
    document.body.classList.remove('modal-open');
    document.body.style.top = '';
    scheduleScrollRestore(y);
  }
  function openModal(html) {
    const y = readScrollY();
    modalContent.innerHTML = html;
    modal.classList.remove('hidden');
    modal.dataset.openedAt = String(Date.now());
    bindFormControls(modalContent);
    lockModalScroll(y);
    scheduleScrollRestore(y);
  }
  function closeModal() { modal.classList.add('hidden'); modalContent.innerHTML = ''; unlockModalScroll(); }
  modalClose.addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) { const openedAt = Number(modal.dataset.openedAt || 0); if (Date.now() - openedAt < 250) return; closeModal(); } });


  // v1.0.97 - Native tarayıcı uyarıları yerine uygulama temalı onay penceresi
  // Android/iOS web uyarıları alan adı gösterdiği için tüm kritik onaylar
  // zümrüt-altın uygulama modalına taşındı.
  function appDecisionDialog(message, options = {}) {
    return new Promise(resolve => {
      const type = options.type || 'confirm';
      const cancelValue = type === 'prompt' ? null : false;
      const overlay = document.createElement('div');
      overlay.className = 'az-decision-backdrop';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      const defaultValue = String(options.defaultValue ?? '');
      overlay.innerHTML = `
        <div class="az-decision-card" role="document">
          <div class="az-decision-mark">☾</div>
          <h2>${esc(options.title || (type === 'prompt' ? 'Bilgi Gir' : 'İşlem Onayı'))}</h2>
          <p>${esc(message)}</p>
          ${type === 'prompt' ? `<input class="az-decision-input" value="${esc(defaultValue)}" inputmode="numeric" autocomplete="off">` : ''}
          <div class="az-decision-actions">
            <button type="button" class="az-decision-cancel">${esc(options.cancelText || 'İptal')}</button>
            <button type="button" class="az-decision-ok">${esc(options.okText || 'Tamam')}</button>
          </div>
        </div>`;
      document.body.appendChild(overlay);
      const input = $('.az-decision-input', overlay);
      const finish = value => {
        document.removeEventListener('keydown', onKey);
        overlay.classList.add('is-leaving');
        setTimeout(() => overlay.remove(), 120);
        resolve(value);
      };
      const onKey = ev => {
        if (ev.key === 'Escape') finish(cancelValue);
        if (ev.key === 'Enter') finish(type === 'prompt' ? (input?.value || '').trim() : true);
      };
      $('.az-decision-cancel', overlay)?.addEventListener('click', () => finish(cancelValue));
      $('.az-decision-ok', overlay)?.addEventListener('click', () => finish(type === 'prompt' ? (input?.value || '').trim() : true));
      overlay.addEventListener('click', ev => { if (ev.target === overlay) finish(cancelValue); });
      document.addEventListener('keydown', onKey);
      requestAnimationFrame(() => {
        overlay.classList.add('is-visible');
        if (input) { input.focus(); input.select(); }
      });
    });
  }
  function appConfirm(message, options = {}) { return appDecisionDialog(message, Object.assign({ type: 'confirm' }, options)); }
  function appPrompt(message, defaultValue = '', options = {}) { return appDecisionDialog(message, Object.assign({ type: 'prompt', defaultValue }, options)); }

  // v1.0.32 - Doğal scroll / işlem sonrası konum koruma
  // Toggle, kaydet, filtre, select/picker ve aynı sayfa içi bütün işlemlerde
  // mobil tarayıcıların ekranı en başa taşımasını engeller. Sadece gerçek route
  // değişimlerinde sayfa üstten açılır.
  let stableScrollY = null;
  let stableScrollTimer = null;
  let stableScrollUntil = 0;
  let routeChanging = false;

  function readScrollY() {
    return window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
  }

  // v1.0.68 - Beklenmeyen en üste sıçrama koruması
  // Önceki scroll-restore yardımcıları bazen y=0 değerini geri yazıp
  // pasif/boş alan dokunuşlarında ekranı en başa taşıyabiliyordu.
  // Route değişimi dışında, kullanıcı sayfanın ortasındayken 0'a geri yazma engellenir.
  function shouldBlockUnexpectedTopRestore(targetY) {
    if (routeChanging) return false;
    if (document.body.classList.contains('modal-open')) return false;
    const currentY = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
    return Number(targetY || 0) <= 4 && currentY > 32;
  }

  function writeScrollY(y) {
    if (typeof y !== 'number' || y < 0) return;
    const maxY = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
    const targetY = Math.min(y, maxY);
    if (shouldBlockUnexpectedTopRestore(targetY)) return;
    try { window.scrollTo({ top: targetY, left: 0, behavior: 'auto' }); }
    catch { window.scrollTo(0, targetY); }
    document.documentElement.scrollTop = targetY;
    document.body.scrollTop = targetY;
  }

  function scheduleScrollRestore(y) {
    if (typeof y !== 'number' || y < 0) return;
    if (shouldBlockUnexpectedTopRestore(y)) return;
    clearTimeout(stableScrollTimer);
    const restore = () => writeScrollY(y);
    requestAnimationFrame(() => {
      restore();
      setTimeout(restore, 60);
      setTimeout(() => { if (Date.now() > stableScrollUntil) stableScrollY = null; }, 180);
    });
  }


  // v1.0.32 - Doğal scroll serbest:
  // Kullanıcının normal kaydırması engellenmez. Sadece işlemle HTML yeniden çizilirse
  // eski konuma kısa bir geri dönüş denenir.
  function runWithoutScrollJump(fn, lockMs = 220) {
    const y = readScrollY();
    stableScrollY = y;
    stableScrollUntil = Date.now() + lockMs;
    try { fn(); } finally { scheduleScrollRestore(y); }
  }

  async function runWithoutScrollJumpAsync(fn, lockMs = 220) {
    const y = readScrollY();
    stableScrollY = y;
    stableScrollUntil = Date.now() + lockMs;
    try { return await fn(); } finally { scheduleScrollRestore(y); }
  }

  // v1.0.51 - Genel buton/işlem scroll koruması
  // Aynı sayfa içinde çalışan butonlar bazı mobil tarayıcılarda render/focus sonrası
  // ekranı en başa taşıyabiliyor. Route değiştiren butonlar hariç tutulur; sadece
  // beklenmeyen yukarı sıçrama varsa eski konuma geri alınır. Doğal kaydırma kilitlenmez.
  function shouldGuardSamePageInteraction(target) {
    if (!target || routeChanging) return false;
    if (target.closest?.('#modalContent')) return false;
    if (target.closest?.('.bottom-nav')) return false;
    if (target.closest?.('[data-route]')) return false;
    if (target.closest?.('[data-settings-scroll]')) return false;
    if (target.closest?.('[data-amin], #aminAllBtn')) return false;
    if (target.closest?.('#settingsPlans .plan-zikir-btn, #settingsPlans .plan-inline-picker')) return false;
    if (target.closest?.('a[href]')) return false;
    if (target.closest?.('input, textarea') && !target.closest?.('button')) return false;
    return !!target.closest?.('button, [role="button"], .setting-row, .juz, .category-chip, .hatim-filter-tabs button, .tabs button, .select-button, .plan-zikir-btn');
  }

  function restoreSamePageInteractionScroll(y, routeAt) {
    if (routeChanging || state.route !== routeAt) return;
    const current = readScrollY();
    const jumpedToTop = y > 20 && current < Math.max(12, y - 120);
    const jumpedFar = y > 20 && Math.abs(current - y) > 220;
    if (jumpedToTop || jumpedFar) {
      stableScrollY = y;
      stableScrollUntil = Date.now() + 500;
      scheduleScrollRestore(y);
    }
  }

  function rememberSamePageInteraction(ev) {
    const target = ev.target;
    if (!shouldGuardSamePageInteraction(target)) return;
    const y = readScrollY();
    const routeAt = state.route;
    setTimeout(() => restoreSamePageInteractionScroll(y, routeAt), 0);
    setTimeout(() => restoreSamePageInteractionScroll(y, routeAt), 80);
    setTimeout(() => restoreSamePageInteractionScroll(y, routeAt), 180);
  }

  // v1.0.63 - Genel body/document tıklama scroll koruması devre dışı.
  // Bu global yakalama, özellikle Toplu Zikir ekranında boş alan/stat kartı gibi
  // pasif yerlere dokununca ekranı başa sarma veya zikzak hissi oluşturabiliyordu.
  // Bundan sonra scroll koruması yalnızca ilgili işlem fonksiyonlarında
  // runWithoutScrollJump(...) ile yerel olarak uygulanacak.

  // v1.0.66 - Pasif/body alan genel dokunma yakalayıcıları kaldırıldı.
  // Boş alan/kart/background tıklamaları uygulama tarafından yakalanmaz;
  // tarayıcının doğal scroll davranışı korunur. Gerçek işlemler sadece kendi
  // buton/event fonksiyonları içinde yönetilir.


  // v1.0.26 - Mobil form kontrol düzeltmesi
  // Bazı mobil PWA tarayıcılarında dekoratif katmanlar ve özel dokunma alanları
  // input/select odaklanmasını bozabiliyordu. Bu yardımcılar tüm veri girişlerini
  // gerçek, tıklanabilir ve güvenli hale getirir.
  function bindFormControls(root = document) {
    $$('input, textarea, select', root).forEach(el => {
      if (el.dataset.formControlBound === '1') return;
      el.dataset.formControlBound = '1';
      el.autocomplete = el.autocomplete || 'off';
      el.addEventListener('pointerdown', ev => { ev.stopPropagation(); }, { passive: true });
      el.addEventListener('touchstart', ev => { ev.stopPropagation(); }, { passive: true });
      el.addEventListener('click', ev => {
        ev.stopPropagation();
        if (el.matches('input, textarea') && document.activeElement !== el) {
          setTimeout(() => { try { el.focus({ preventScroll: true }); } catch { try { el.focus(); } catch {} } }, 0);
        }
      });
    });
  }

  function handleActivePickerChoice(value, ev = null) {
    ev?.preventDefault?.();
    ev?.stopPropagation?.();
    const picker = state.activePicker;
    if (!picker || picker.done) return;
    picker.done = true;
    const item = picker.items.find(x => String(x.value) === String(value)) || { value };
    const y = Number(state.modalState?.scrollY || readScrollY());
    stableScrollY = y;
    stableScrollUntil = Date.now() + 1200;
    closeModal();
    state.activePicker = null;
    if (typeof picker.onPick === 'function') picker.onPick(item);
    scheduleScrollRestore(y);
  }

  function showSelectPicker(title, items, selectedValue, onPick) {
    const selected = String(selectedValue ?? '');
    state.activePicker = { items, onPick, done: false };
    openModal(`<h2 class="page-title">${esc(title)}</h2>
      <p class="modal-note">Seçim yap. Satırın herhangi bir yerine dokunman yeterlidir.</p>
      <div class="picker-list" data-picker-list="1">
        ${items.map(item => {
          const value = String(item.value);
          return `<button type="button" class="picker-row ${value === selected ? 'active' : ''}" data-picker-value="${esc(value)}" aria-label="${esc(item.label)} seç">
            <span>${esc(item.arabic || '☾')}</span>
            <p><strong>${esc(item.label)}</strong>${item.sub ? `<small>${esc(item.sub)}</small>` : ''}</p>
            <b>${value === selected ? '✓' : 'Seç'}</b>
          </button>`;
        }).join('')}
      </div>`);
    bindFormControls(modalContent);

    const list = $('[data-picker-list]', modalContent);
    let pickerStartX = 0;
    let pickerStartY = 0;
    let pickerMoved = false;
    let suppressPickerClickUntil = 0;

    const markPickerStart = ev => {
      const point = ev.touches?.[0] || ev;
      pickerStartX = Number(point.clientX || 0);
      pickerStartY = Number(point.clientY || 0);
      pickerMoved = false;
    };
    const markPickerMove = ev => {
      const point = ev.touches?.[0] || ev;
      const dx = Math.abs(Number(point.clientX || 0) - pickerStartX);
      const dy = Math.abs(Number(point.clientY || 0) - pickerStartY);
      if (dx > 8 || dy > 8) pickerMoved = true;
    };
    const suppressAfterPickerDrag = ev => {
      suppressPickerClickUntil = Date.now() + 520;
      ev?.stopPropagation?.();
      ev?.preventDefault?.();
      setTimeout(() => { pickerMoved = false; }, 80);
    };
    const delegatedPick = ev => {
      if (Date.now() < suppressPickerClickUntil) {
        ev?.preventDefault?.();
        ev?.stopPropagation?.();
        ev?.stopImmediatePropagation?.();
        return;
      }
      if (pickerMoved && ev.type !== 'click') {
        suppressAfterPickerDrag(ev);
        return;
      }
      const row = ev.target?.closest?.('[data-picker-value]');
      if (!row || !modalContent.contains(row)) return;
      handleActivePickerChoice(row.dataset.pickerValue, ev);
    };

    if (list) {
      list.addEventListener('touchstart', markPickerStart, { passive: true });
      list.addEventListener('touchmove', markPickerMove, { passive: true });
      list.addEventListener('touchend', delegatedPick, { capture: true, passive: false });
      list.addEventListener('pointerdown', ev => { if (ev.pointerType !== 'mouse') markPickerStart(ev); }, true);
      list.addEventListener('pointermove', ev => { if (ev.pointerType !== 'mouse') markPickerMove(ev); }, true);
      list.addEventListener('pointerup', ev => { if (ev.pointerType !== 'mouse') delegatedPick(ev); }, { capture: true, passive: false });
      list.addEventListener('click', delegatedPick, true);
    } else {
      modalContent.addEventListener('click', delegatedPick, true);
    }
  }

  function changeCounterZikir(zikirId) {
    if (state.tesbihat?.active) return toast('Tesbihat akışında zikir otomatik yönetilir.');
    if (state.vird?.active) return toast('Kişisel vird akışında zikir otomatik yönetilir.');
    const newId = Number(zikirId);
    if (!getZikirs().some(z => Number(z.id) === newId)) return toast('Zikir bulunamadı.');
    const old = currentZikir();
    addHistory(old, Number(state.counter.count || 0), state.counter.intent);
    state.counter.zikirId = newId;
    const nz = currentZikir();
    state.counter.target = Number(nz.default_target || state.settings.defaultTarget || 1000);
    state.counter.count = 0;
    state.counter.intent = '';
    state.counter.startedAt = Date.now();
    state.counter.completedToastAt = 0;
    save('az_counter', state.counter);
    const y = readScrollY();
    renderCounter();
    scheduleScrollRestore(y);
  }

  function openCounterZikirPicker() {
    const items = getZikirs().map(z => ({ value: z.id, label: z.title, sub: z.meaning || '', arabic: (z.arabic_text || '☾').slice(0, 8) }));
    showSelectPicker('Zikir Seç', items, state.counter.zikirId, item => changeCounterZikir(Number(item.value)));
  }


  // v1.0.98 - Mobil Sayaç zikir seçici dokunma düzeltmesi
  // Bazı Android/iOS WebView/PWA ortamlarında button click olayı gecikiyor veya
  // scroll/tap koruması tarafından yutulabiliyordu. Seçici artık touchend/pointerup
  // üzerinden doğrudan açılır; masaüstü click davranışı korunur.
  function bindCounterZikirSelectButton() {
    const btn = $('#zikirSelect');
    if (!btn) return;
    let startX = 0;
    let startY = 0;
    let moved = false;
    let openedAt = 0;

    const pointOf = ev => ev.changedTouches?.[0] || ev.touches?.[0] || ev;
    const markStart = ev => {
      const p = pointOf(ev);
      startX = Number(p.clientX || 0);
      startY = Number(p.clientY || 0);
      moved = false;
    };
    const markMove = ev => {
      const p = pointOf(ev);
      const dx = Math.abs(Number(p.clientX || 0) - startX);
      const dy = Math.abs(Number(p.clientY || 0) - startY);
      if (dx > 10 || dy > 10) moved = true;
    };
    const openPicker = ev => {
      if (btn.disabled || btn.getAttribute('disabled') !== null) return;
      ev?.preventDefault?.();
      ev?.stopPropagation?.();
      ev?.stopImmediatePropagation?.();
      const now = Date.now();
      if (now - openedAt < 520) return;
      openedAt = now;
      runWithoutScrollJump(openCounterZikirPicker, 15000);
    };

    btn.addEventListener('touchstart', markStart, { passive: true });
    btn.addEventListener('touchmove', markMove, { passive: true });
    btn.addEventListener('touchend', ev => { if (!moved) openPicker(ev); }, { passive: false });
    btn.addEventListener('pointerdown', ev => { if (ev.pointerType !== 'mouse') markStart(ev); }, { passive: true });
    btn.addEventListener('pointermove', ev => { if (ev.pointerType !== 'mouse') markMove(ev); }, { passive: true });
    btn.addEventListener('pointerup', ev => { if (ev.pointerType !== 'mouse' && !moved) openPicker(ev); }, { passive: false });
    btn.addEventListener('click', openPicker);
    btn.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') openPicker(ev); });
  }

  function saveDailyPlansFromDom(silent = false, rerender = false) {
    const rows = $$('.plan-edit-row');
    let plans = rows.map(row => ({
      zikirId: Number($('.plan-zikir-btn', row)?.dataset.zikirId || 1),
      target: Math.max(1, Number($('.plan-target', row)?.value || 1))
    }));
    if (!plans.length) plans = dailyPlans().map(p => ({ zikirId: Number(p.zikirId || 1), target: Number(p.target || 100) }));
    state.settings.dailyPlans = plans.slice(0, 3);
    save('az_settings', state.settings);
    if (!silent) toast('Günlük zikir planı kaydedildi.');
    if (rerender && state.route === 'settings') runWithoutScrollJump(() => renderSettings());
    return true;
  }

  function zikirTitle(id) {
    const z = getZikirs().find(item => Number(item.id) === Number(id));
    return z?.title || '';
  }

  function setDailyPlanZikir(index, item) {
    const idx = Math.max(0, Math.min(2, Number(index || 0)));
    const currentPlans = dailyPlans().map(p => ({ zikirId: Number(p.zikirId || 1), target: Number(p.target || 100) }));
    while (currentPlans.length < 3) currentPlans.push({ zikirId: 1, target: 100 });
    const btn = $(`.plan-zikir-btn[data-plan-index="${idx}"]`);
    const targetInput = $(`.plan-target[data-plan-index="${idx}"]`);
    const value = Number(item.value || 1);
    currentPlans[idx] = {
      zikirId: value,
      target: Math.max(1, Number(targetInput?.value || currentPlans[idx]?.target || 100))
    };
    state.settings.dailyPlans = currentPlans.slice(0, 3);
    save('az_settings', state.settings);
    if (btn) {
      btn.dataset.zikirId = String(value);
      const label = $('.plan-zikir-label', btn);
      if (label) label.textContent = item.label || zikirTitle(value) || 'Zikir seç';
    }
    toast('Plan zikri seçildi.');
  }

  function openPlanZikirPicker(index) {
    const idx = Number(index || 0);
    const btn = $(`.plan-zikir-btn[data-plan-index="${idx}"]`);
    const currentValue = btn?.dataset.zikirId || dailyPlans()[idx]?.zikirId || '1';
    const items = getZikirs().map(z => ({ value: z.id, label: z.title, sub: z.meaning || '', arabic: (z.arabic_text || '☾').slice(0, 8) }));
    showSelectPicker('Plandaki Zikri Seç', items, currentValue, item => setDailyPlanZikir(idx, item));
  }


  // v1.0.52 - Bugünkü Zikir Planı mobil inline seçim düzeltmesi
  // Bu bölüm modal/body kilidi kullanmaz. Sadece Ayarlar > Bugünkü Zikir Planı
  // satırlarının altında yerel bir seçim listesi açar; böylece telefonda açılmama
  // ve seçim sonrası başa sarma problemi bu alanda çözülür.
  function closeDailyPlanInlinePicker() {
    $$('.plan-inline-picker').forEach(el => el.remove());
  }

  function openDailyPlanInlinePicker(index, anchorBtn) {
    const idx = Math.max(0, Math.min(2, Number(index || 0)));
    const btn = anchorBtn || $(`.plan-zikir-btn[data-plan-index="${idx}"]`);
    const row = btn?.closest?.('.plan-edit-row');
    if (!btn || !row) return openPlanZikirPicker(idx);

    const alreadyOpen = $(`.plan-inline-picker[data-plan-index="${idx}"]`);
    if (alreadyOpen) return;
    closeDailyPlanInlinePicker();

    const currentValue = String(btn.dataset.zikirId || dailyPlans()[idx]?.zikirId || '1');
    const items = getZikirs().map(z => ({
      value: String(z.id),
      label: z.title,
      sub: z.meaning || '',
      arabic: (z.arabic_text || '☾').slice(0, 8)
    }));

    const box = document.createElement('div');
    box.className = 'picker-list plan-inline-picker';
    box.dataset.planIndex = String(idx);
    box.dataset.pickerList = '1';
    box.innerHTML = items.map(item => `<button type="button" class="picker-row ${item.value === currentValue ? 'active' : ''}" data-plan-inline-value="${esc(item.value)}" aria-label="${esc(item.label)} seç">
      <span>${esc(item.arabic || '☾')}</span>
      <p><strong>${esc(item.label)}</strong>${item.sub ? `<small>${esc(item.sub)}</small>` : ''}</p>
      <b>${item.value === currentValue ? '✓' : 'Seç'}</b>
    </button>`).join('');

    row.insertAdjacentElement('afterend', box);

    let startX = 0;
    let startY = 0;
    let moved = false;
    let chosenAt = 0;

    box.addEventListener('touchstart', ev => {
      const t = ev.touches && ev.touches[0];
      if (!t) return;
      startX = t.clientX;
      startY = t.clientY;
      moved = false;
    }, { passive: true });

    box.addEventListener('touchmove', ev => {
      const t = ev.touches && ev.touches[0];
      if (!t) return;
      if (Math.abs(t.clientX - startX) > 8 || Math.abs(t.clientY - startY) > 8) moved = true;
    }, { passive: true });

    const choose = ev => {
      const choice = ev.target?.closest?.('[data-plan-inline-value]');
      if (!choice || !box.contains(choice)) return;
      if (moved) return;
      if (Date.now() - chosenAt < 500) return;
      chosenAt = Date.now();
      ev.preventDefault?.();
      ev.stopPropagation?.();
      ev.stopImmediatePropagation?.();
      const value = String(choice.dataset.planInlineValue || '1');
      const item = items.find(x => String(x.value) === value) || { value, label: zikirTitle(value) || 'Zikir seç' };
      setDailyPlanZikir(idx, item);
      closeDailyPlanInlinePicker();
    };

    box.addEventListener('touchend', choose, { passive: false });
    box.addEventListener('click', choose, true);
  }

  // v1.0.60 - Bugünkü Zikir Planı premium seçim penceresi
  // Bu bölüm yalnızca Ayarlar > Bugünkü Zikir Planı içindir.
  // Inline liste yerine mevcut premium modal seçici kullanılır; sayfa içine gömülü liste oluşmaz.
  let lastDailyPlanOpenAt = 0;
  let lastDailyPlanOpenBtn = null;
  let dailyPlanTouch = null;
  let ignoreDailyPlanClickUntil = 0;

  function dailyPlanButtonFromEvent(ev) {
    const btn = ev.target?.closest?.('.plan-zikir-btn');
    if (!btn || state.route !== 'settings') return null;
    if (!btn.closest?.('#settingsPlans')) return null;
    return btn;
  }

  function trackDailyPlanTouchStart(ev) {
    const btn = dailyPlanButtonFromEvent(ev);
    if (!btn) return;
    const t = ev.touches && ev.touches[0];
    if (!t) return;
    dailyPlanTouch = {
      btn,
      startX: t.clientX,
      startY: t.clientY,
      moved: false,
      time: Date.now()
    };
  }

  function trackDailyPlanTouchMove(ev) {
    if (!dailyPlanTouch) return;
    const t = ev.touches && ev.touches[0];
    if (!t) return;
    if (Math.abs(t.clientX - dailyPlanTouch.startX) > 8 || Math.abs(t.clientY - dailyPlanTouch.startY) > 8) {
      dailyPlanTouch.moved = true;
    }
  }

  function handleDailyPlanButtonOpen(ev) {
    const btn = dailyPlanButtonFromEvent(ev);
    if (!btn) return;

    if (ev.type === 'touchend') {
      const info = dailyPlanTouch;
      dailyPlanTouch = null;
      if (info && info.btn === btn && info.moved) {
        // Kullanıcı bu alan üzerinden sayfayı kaydırdı; seçim penceresi açılmasın.
        // preventDefault kullanmıyoruz ki doğal mobil scroll bozulmasın.
        ignoreDailyPlanClickUntil = Date.now() + 650;
        return;
      }
    }

    if (ev.type === 'click') {
      if (Date.now() < ignoreDailyPlanClickUntil) {
        ev.preventDefault?.();
        ev.stopPropagation?.();
        ev.stopImmediatePropagation?.();
        return;
      }
      if (lastDailyPlanOpenBtn === btn && Date.now() - lastDailyPlanOpenAt < 650) {
        ev.preventDefault?.();
        ev.stopPropagation?.();
        ev.stopImmediatePropagation?.();
        return;
      }
    }

    ev.preventDefault?.();
    ev.stopPropagation?.();
    ev.stopImmediatePropagation?.();
    lastDailyPlanOpenAt = Date.now();
    lastDailyPlanOpenBtn = btn;
    closeDailyPlanInlinePicker();
    openPlanZikirPicker(btn.dataset.planIndex);
  }

  document.addEventListener('touchstart', trackDailyPlanTouchStart, { capture: true, passive: true });
  document.addEventListener('touchmove', trackDailyPlanTouchMove, { capture: true, passive: true });
  document.addEventListener('touchend', handleDailyPlanButtonOpen, { capture: true, passive: false });
  document.addEventListener('click', handleDailyPlanButtonOpen, true);

  function bindSelectFallback(select, opener) {
    if (!select || select.dataset.selectFallbackBound === '1') return;
    select.dataset.selectFallbackBound = '1';
    const open = ev => {
      if (select.disabled) return;
      stableScrollY = readScrollY();
      stableScrollUntil = Date.now() + 15000;
      ev.preventDefault();
      ev.stopPropagation();
      opener();
      scheduleScrollRestore(stableScrollY);
    };
    select.addEventListener('pointerdown', open, { passive: false });
    select.addEventListener('keydown', ev => {
      if (ev.key === 'Enter' || ev.key === ' ') open(ev);
    });
  }

  function getZikirs() {
    const defaults = [
      {id:1,title:'Sübhânallah',arabic_text:'سُبْحَانَ اللهِ',meaning:'Allah her türlü noksandan münezzehtir.',default_target:1000,is_favorite:1},
      {id:2,title:'Elhamdülillah',arabic_text:'اَلْحَمْدُ لِلّٰهِ',meaning:'Hamd, âlemlerin Rabbi olan Allah’adır.',default_target:1000,is_favorite:1},
      {id:3,title:'Allahu Ekber',arabic_text:'اَللهُ اَكْبَرُ',meaning:'Allah her şeyden büyüktür.',default_target:1000,is_favorite:1},
      {id:4,title:'Estağfirullah',arabic_text:'اَسْتَغْفِرُ اللهَ',meaning:'Allah’tan mağfiret dilerim.',default_target:1000,is_favorite:1}
    ];
    const custom = load('az_custom_zikirs', []);
    return [...(state.data?.zikirs || defaults), ...custom];
  }
  function favoriteZikirs() {
    const all = getZikirs();
    let ids = Array.isArray(state.settings.favoriteZikirIds) ? state.settings.favoriteZikirIds.map(Number).filter(Boolean) : [];
    if (!ids.length) {
      ids = all.filter(z => Number(z.is_favorite) === 1).slice(0, 6).map(z => Number(z.id));
      state.settings.favoriteZikirIds = ids;
      save('az_settings', state.settings);
    }
    const picked = ids.map(id => all.find(z => Number(z.id) === Number(id))).filter(Boolean);
    return picked.length ? picked.slice(0, 8) : all.slice(0, 4);
  }
  function saveFavoriteZikirIds(ids) {
    const valid = new Set(getZikirs().map(z => Number(z.id)));
    state.settings.favoriteZikirIds = Array.from(new Set((ids || []).map(Number).filter(id => valid.has(id)))).slice(0, 8);
    save('az_settings', state.settings);
  }
  function currentZikir() { return getZikirs().find(z => Number(z.id) === Number(state.counter.zikirId)) || getZikirs()[0]; }
  function allTimeHistory() { return load('az_history', []); }
  function journalEntries() {
    const list = load('az_journal', []);
    return Array.isArray(list) ? list.sort((a, b) => String(b.date || '').localeCompare(String(a.date || ''))) : [];
  }
  function saveJournalEntries(list) {
    save('az_journal', (Array.isArray(list) ? list : []).slice(0, 365));
  }
  function todayJournal() {
    const today = nowDate();
    return journalEntries().find(x => x.date === today) || null;
  }
  function journalSummary() {
    const list = journalEntries();
    const today = todayJournal();
    const total = list.length;
    const latest = today || list[0] || null;
    return { list, today, total, latest };
  }
  function dailyTotal() {
    const today = nowDate();
    return allTimeHistory().filter(x => x.date === today).reduce((s, x) => s + Number(x.count || 0), 0) + Number(state.counter.count || 0);
  }
  function zikirTotalToday(zikirId) {
    const today = nowDate();
    const zid = Number(zikirId || 0);
    const saved = allTimeHistory().filter(x => x.date === today && Number(x.zikirId) === zid).reduce((s, x) => s + Number(x.count || 0), 0);
    const active = Number(state.counter.zikirId) === zid ? Number(state.counter.count || 0) : 0;
    return saved + active;
  }
  function dailyPlans() {
    const zikirs = getZikirs();
    const fallback = DEFAULT_SETTINGS.dailyPlans;
    const source = Array.isArray(state.settings.dailyPlans) && state.settings.dailyPlans.length ? state.settings.dailyPlans : fallback;
    return source.slice(0, 3).map((p, idx) => {
      const zikir = zikirs.find(z => Number(z.id) === Number(p.zikirId)) || zikirs[idx] || zikirs[0];
      return { zikirId: Number(zikir?.id || 1), target: Math.max(1, Number(p.target || 100)), zikir };
    });
  }

  function recentZikirUsage(limit = 5) {
    const byId = new Map();
    const zikirs = getZikirs();
    allTimeHistory().forEach(item => {
      const id = Number(item.zikirId || 0);
      if (!id) return;
      const current = byId.get(id) || { zikirId: id, count: 0, sessions: 0, lastTs: 0 };
      current.count += Number(item.count || 0);
      current.sessions += 1;
      current.lastTs = Math.max(current.lastTs, Number(item.ts || 0));
      byId.set(id, current);
    });
    if (Number(state.counter.count || 0) > 0) {
      const id = Number(state.counter.zikirId || 0);
      const current = byId.get(id) || { zikirId: id, count: 0, sessions: 0, lastTs: 0 };
      current.count += Number(state.counter.count || 0);
      current.sessions += 1;
      current.lastTs = Math.max(current.lastTs, Date.now());
      byId.set(id, current);
    }
    return Array.from(byId.values()).map(item => ({ ...item, zikir: zikirs.find(z => Number(z.id) === Number(item.zikirId)) })).filter(x => x.zikir).sort((a, b) => b.lastTs - a.lastTs || b.count - a.count).slice(0, limit);
  }

  async function startQuickZikir(zikirId, target = null) {
    const zikir = getZikirs().find(z => Number(z.id) === Number(zikirId));
    if (!zikir) return toast('Zikir bulunamadı.');
    const newTarget = Math.max(1, Number(target || zikir.default_target || state.settings.defaultTarget || 1000));
    const isDifferent = Number(state.counter.zikirId) !== Number(zikirId) || Number(state.counter.target) !== Number(newTarget);
    if (state.tesbihat?.active) {
      if (!(await appConfirm('Namaz sonrası tesbihat akışı kapatılıp bu zikre geçilsin mi?'))) return;
      state.tesbihat.active = false;
      saveTesbihat();
    }
    if (state.vird?.active) {
      if (!(await appConfirm('Kişisel vird akışı kapatılıp bu zikre geçilsin mi?'))) return;
      state.vird.active = false;
      saveVird();
    }
    if (Number(state.counter.count || 0) > 0 && isDifferent) {
      if (!(await appConfirm('Devam eden sayaç oturumu geçmişe kaydedilip yeni zikre geçilsin mi?'))) return;
      addHistory(currentZikir(), Number(state.counter.count || 0), state.counter.intent);
      state.counter.count = 0;
      state.counter.intent = '';
    }
    state.counter.zikirId = Number(zikirId);
    state.counter.target = newTarget;
    state.counter.startedAt = Date.now();
    state.counter.completedToastAt = 0;
    save('az_counter', state.counter);
    closeModal();
    route('counter');
    toast(`${zikir.title} başlatıldı.`);
  }

  function showZikirSearchModal() {
    const zikirs = getZikirs();
    const recent = recentZikirUsage(4);
    let activeTarget = Number(state.settings.defaultTarget || state.counter.target || 1000);
    openModal(`<h2 class="page-title">Zikir Ara</h2>
      <p class="modal-note">Hazır ve kişisel zikirleri tek ekranda ara. Seçtiğin hedefle sayaç ekranı açılır.</p>
      <input id="zikirSearchInput" class="field zikir-search-input" placeholder="Zikir adı, anlamı veya Arapça metin ara..." autocomplete="off">
      <div class="quick-target-row" id="quickTargetRow">
        ${[33, 99, 100, 500, 1000].map(n => `<button data-quick-target="${n}" class="${n === activeTarget ? 'active' : ''}">${n}</button>`).join('')}
      </div>
      ${recent.length ? `<div class="section-row compact-row"><h3>Son Kullanılanlar</h3><small>${recent.length} zikir</small></div><div class="recent-chip-row">${recent.map(item => `<button data-modal-quick-start="${item.zikir.id}">${esc(item.zikir.title)} <small>${fmt(item.count)}</small></button>`).join('')}</div>` : ''}
      <div class="section-row compact-row"><h3>Tüm Zikirler</h3><button class="link-btn" id="modalAddCustomZikir">Yeni Ekle</button></div>
      <div class="zikir-search-results" id="zikirSearchResults"></div>`);

    const results = $('#zikirSearchResults', modalContent);
    const input = $('#zikirSearchInput', modalContent);
    const renderResults = () => {
      const q = (input?.value || '').trim().toLocaleLowerCase('tr-TR');
      const filtered = zikirs.filter(z => {
        const hay = `${z.title || ''} ${z.arabic_text || ''} ${z.meaning || ''}`.toLocaleLowerCase('tr-TR');
        return !q || hay.includes(q);
      }).slice(0, 30);
      results.innerHTML = filtered.map(z => `<button class="zikir-search-row" data-start-search-zikir="${z.id}"><span class="zikir-badge">${esc((z.arabic_text || z.title || '☾').slice(0, 8))}</span><p><strong>${esc(z.title)}</strong><small>${esc(z.meaning || 'Açıklama yok')} · hedef ${fmt(z.default_target || activeTarget)}</small></p><b>Başlat</b></button>`).join('') || '<div class="empty-state">Aramana uygun zikir bulunamadı.</div>';
      $$('[data-start-search-zikir]', results).forEach(btn => btn.addEventListener('click', () => startQuickZikir(Number(btn.dataset.startSearchZikir), activeTarget)));
    };
    $$('#quickTargetRow [data-quick-target]', modalContent).forEach(btn => btn.addEventListener('click', () => {
      activeTarget = Number(btn.dataset.quickTarget || activeTarget);
      $$('#quickTargetRow button', modalContent).forEach(b => b.classList.toggle('active', b === btn));
      renderResults();
    }));
    $$('[data-modal-quick-start]', modalContent).forEach(btn => btn.addEventListener('click', () => startQuickZikir(Number(btn.dataset.modalQuickStart), activeTarget)));
    $('#modalAddCustomZikir', modalContent)?.addEventListener('click', () => showCustomZikirModal(null, state.route));
    input?.addEventListener('input', renderResults);
    renderResults();
    setTimeout(() => input?.focus(), 60);
  }

  function tesbihatSequences() {
    return {
      classic99: {
        title: 'Klasik Tesbihat',
        desc: '33 Sübhânallah, 33 Elhamdülillah, 33 Allahu Ekber',
        steps: [
          { zikirId: 1, title: 'Sübhânallah', target: 33 },
          { zikirId: 2, title: 'Elhamdülillah', target: 33 },
          { zikirId: 3, title: 'Allahu Ekber', target: 33 }
        ]
      },
      hundred: {
        title: '100lük Tesbihat',
        desc: '33 Sübhânallah, 33 Elhamdülillah, 34 Allahu Ekber',
        steps: [
          { zikirId: 1, title: 'Sübhânallah', target: 33 },
          { zikirId: 2, title: 'Elhamdülillah', target: 33 },
          { zikirId: 3, title: 'Allahu Ekber', target: 34 }
        ]
      }
    };
  }
  function saveTesbihat() { save('az_tesbihat', state.tesbihat); }
  function tesbihatSequence() {
    const all = tesbihatSequences();
    return all[state.tesbihat?.mode] || all.classic99;
  }
  function tesbihatSummary() {
    const seq = tesbihatSequence();
    const stepIndex = Math.min(Math.max(0, Number(state.tesbihat?.step || 0)), seq.steps.length - 1);
    const step = seq.steps[stepIndex] || seq.steps[0];
    const currentPart = state.tesbihat?.active ? Math.min(1, Number(state.counter.count || 0) / Math.max(1, Number(step.target || 1))) : 0;
    const overall = state.tesbihat?.active ? Math.round(((stepIndex + currentPart) / seq.steps.length) * 100) : 0;
    return { active: !!state.tesbihat?.active, seq, step, stepIndex, totalSteps: seq.steps.length, overall };
  }
  function setCounterForTesbihatStep(step) {
    state.counter.zikirId = Number(step.zikirId || 1);
    state.counter.target = Math.max(1, Number(step.target || 33));
    state.counter.count = 0;
    state.counter.intent = 'Namaz sonrası tesbihat';
    state.counter.startedAt = Date.now();
    state.counter.completedToastAt = 0;
    save('az_counter', state.counter);
  }
  async function startTesbihat(mode = 'classic99') {
    if (state.vird?.active) {
      if (!(await appConfirm('Kişisel vird akışı kapatılıp namaz sonrası tesbihat başlatılsın mı?'))) return;
      state.vird.active = false;
      saveVird();
    }
    if (Number(state.counter.count || 0) > 0) {
      if (!(await appConfirm('Mevcut sayaç oturumu geçmişe kaydedilip namaz sonrası tesbihat başlatılsın mı?'))) return;
      addHistory(currentZikir(), Number(state.counter.count || 0), state.counter.intent);
    }
    state.tesbihat = { active: true, mode, step: 0, startedAt: Date.now(), completedAt: 0, completedSessions: Number(state.tesbihat?.completedSessions || 0) };
    const seq = tesbihatSequence();
    setCounterForTesbihatStep(seq.steps[0]);
    saveTesbihat();
    closeModal();
    toast('Namaz sonrası tesbihat başladı.');
    route('counter');
  }
  function nextTesbihatStep() {
    const info = tesbihatSummary();
    if (!info.active) return showTesbihatStartModal();
    if (Number(state.counter.count || 0) < Number(info.step.target || 1)) return toast('Bu aşama henüz tamamlanmadı.');
    addHistory(currentZikir(), Number(state.counter.count || 0), `Namaz sonrası tesbihat · ${info.step.title}`);
    if (info.stepIndex >= info.totalSteps - 1) {
      state.tesbihat.active = false;
      state.tesbihat.completedAt = Date.now();
      state.tesbihat.completedSessions = Number(state.tesbihat.completedSessions || 0) + 1;
      state.counter.count = 0;
      state.counter.intent = '';
      state.counter.startedAt = Date.now();
      state.counter.completedToastAt = 0;
      save('az_counter', state.counter);
      saveTesbihat();
      toast('Tesbihat tamamlandı. Allah kabul etsin.');
      runWithoutScrollJump(() => renderCounter());
      return;
    }
    state.tesbihat.step = info.stepIndex + 1;
    const next = tesbihatSequence().steps[state.tesbihat.step];
    setCounterForTesbihatStep(next);
    saveTesbihat();
    toast(`${next.title} aşamasına geçildi.`);
    runWithoutScrollJump(() => renderCounter());
  }
  async function cancelTesbihatFlow() {
    if (!state.tesbihat?.active) return;
    if (!(await appConfirm('Aktif tesbihat akışı durdurulsun mu? Sayaç değeri silinmez.'))) return;
    state.tesbihat.active = false;
    state.tesbihat.completedAt = 0;
    saveTesbihat();
    toast('Tesbihat akışı durduruldu.');
    runWithoutScrollJump(() => renderCounter());
  }
  function showTesbihatStartModal() {
    const all = tesbihatSequences();
    openModal(`<h2 class="page-title">Namaz Sonrası Tesbihat</h2><p class="modal-note">Namaz sonrası tesbihatı aşama aşama takip et. Her aşama tamamlanınca sonraki zikre sen geçersin.</p><div class="tesbihat-choice-list">${Object.entries(all).map(([key, seq]) => `<button class="tesbihat-choice" data-start-tesbihat="${key}"><span>☾</span><p><strong>${esc(seq.title)}</strong><small>${esc(seq.desc)}</small></p><b>Başlat</b></button>`).join('')}</div>`);
    $$('[data-start-tesbihat]', modalContent).forEach(btn => btn.addEventListener('click', () => startTesbihat(btn.dataset.startTesbihat)));
  }


  function virdRoutines() {
    const saved = load('az_vird_routines', null);
    const defaults = [
      { id: 'morning', title: 'Sabah Virdi', desc: 'Güne istiğfar, salavat ve tevhid ile başla.', steps: [{ zikirId: 4, target: 100 }, { zikirId: 5, target: 100 }, { zikirId: 6, target: 100 }] },
      { id: 'evening', title: 'Akşam Huzur Virdi', desc: 'Günü tesbih, hamd, tekbir ve salavatla kapat.', steps: [{ zikirId: 1, target: 33 }, { zikirId: 2, target: 33 }, { zikirId: 3, target: 33 }, { zikirId: 5, target: 100 }] },
      { id: 'daily', title: 'Günlük Koruma Virdi', desc: 'Kısa ama düzenli bir günlük zikir akışı.', steps: [{ zikirId: 4, target: 100 }, { zikirId: 1, target: 100 }, { zikirId: 5, target: 100 }] }
    ];
    const routines = Array.isArray(saved) && saved.length ? saved : defaults;
    const zikirs = getZikirs();
    return routines.map(r => ({
      ...r,
      steps: (Array.isArray(r.steps) ? r.steps : []).map(st => {
        const z = zikirs.find(x => Number(x.id) === Number(st.zikirId)) || zikirs[0];
        return { zikirId: Number(z?.id || 1), target: Math.max(1, Number(st.target || 33)), title: z?.title || 'Zikir' };
      }).filter(Boolean)
    })).filter(r => r.steps.length);
  }
  function saveVird() { save('az_vird', state.vird); }
  function virdRoutine() {
    const routines = virdRoutines();
    return routines.find(r => r.id === state.vird?.routineId) || routines[0];
  }
  function virdSummary() {
    const routine = virdRoutine();
    const stepIndex = Math.min(Math.max(0, Number(state.vird?.step || 0)), Math.max(0, routine.steps.length - 1));
    const step = routine.steps[stepIndex] || routine.steps[0];
    const currentPart = state.vird?.active ? Math.min(1, Number(state.counter.count || 0) / Math.max(1, Number(step.target || 1))) : 0;
    const overall = state.vird?.active ? Math.round(((stepIndex + currentPart) / routine.steps.length) * 100) : 0;
    return { active: !!state.vird?.active, routine, step, stepIndex, totalSteps: routine.steps.length, overall };
  }
  function setCounterForVirdStep(step, routine) {
    state.counter.zikirId = Number(step.zikirId || 1);
    state.counter.target = Math.max(1, Number(step.target || 33));
    state.counter.count = 0;
    state.counter.intent = `Kişisel vird · ${routine?.title || 'Vird'}`;
    state.counter.startedAt = Date.now();
    state.counter.completedToastAt = 0;
    save('az_counter', state.counter);
  }
  async function startVirdRoutine(id = 'morning') {
    const routine = virdRoutines().find(r => r.id === id) || virdRoutines()[0];
    if (!routine) return toast('Vird akışı bulunamadı.');
    if (state.tesbihat?.active) {
      if (!(await appConfirm('Namaz sonrası tesbihat kapatılıp kişisel vird başlatılsın mı?'))) return;
      state.tesbihat.active = false;
      saveTesbihat();
    }
    if (Number(state.counter.count || 0) > 0) {
      if (!(await appConfirm('Mevcut sayaç oturumu geçmişe kaydedilip kişisel vird başlatılsın mı?'))) return;
      addHistory(currentZikir(), Number(state.counter.count || 0), state.counter.intent);
    }
    state.vird = { active: true, routineId: routine.id, step: 0, startedAt: Date.now(), completedAt: 0, completedRoutines: Number(state.vird?.completedRoutines || 0) };
    setCounterForVirdStep(routine.steps[0], routine);
    saveVird();
    closeModal();
    toast(`${routine.title} başladı.`);
    route('counter');
  }
  function nextVirdStep() {
    const info = virdSummary();
    if (!info.active) return showVirdStartModal();
    if (Number(state.counter.count || 0) < Number(info.step.target || 1)) return toast('Bu vird adımı henüz tamamlanmadı.');
    addHistory(currentZikir(), Number(state.counter.count || 0), `Kişisel vird · ${info.routine.title} · ${info.step.title}`);
    if (info.stepIndex >= info.totalSteps - 1) {
      state.vird.active = false;
      state.vird.completedAt = Date.now();
      state.vird.completedRoutines = Number(state.vird.completedRoutines || 0) + 1;
      state.counter.count = 0;
      state.counter.intent = '';
      state.counter.startedAt = Date.now();
      state.counter.completedToastAt = 0;
      save('az_counter', state.counter);
      saveVird();
      toast('Kişisel vird tamamlandı. Allah kabul etsin.');
      runWithoutScrollJump(() => renderCounter());
      return;
    }
    state.vird.step = info.stepIndex + 1;
    const next = virdRoutine().steps[state.vird.step];
    setCounterForVirdStep(next, virdRoutine());
    saveVird();
    toast(`${next.title} adımına geçildi.`);
    runWithoutScrollJump(() => renderCounter());
  }
  async function cancelVirdFlow() {
    if (!state.vird?.active) return;
    if (!(await appConfirm('Aktif kişisel vird akışı durdurulsun mu? Sayaç değeri silinmez.'))) return;
    state.vird.active = false;
    state.vird.completedAt = 0;
    saveVird();
    toast('Kişisel vird akışı durduruldu.');
    renderCounter();
  }
  function showVirdStartModal() {
    const routines = virdRoutines();
    openModal(`<h2 class="page-title">Kişisel Vird Akışı</h2><p class="modal-note">Birden fazla zikri sırasıyla takip et. Her adım tamamlanınca sonraki adıma sen geçersin.</p><div class="tesbihat-choice-list vird-choice-list">${routines.map(r => `<button class="tesbihat-choice vird-choice" data-start-vird="${esc(r.id)}"><span>✦</span><p><strong>${esc(r.title)}</strong><small>${esc(r.desc || '')}</small><em>${r.steps.map(st => esc(st.title) + ' ' + fmt(st.target)).join(' · ')}</em></p><b>Başlat</b></button>`).join('')}</div>`);
    $$('[data-start-vird]', modalContent).forEach(btn => btn.addEventListener('click', () => startVirdRoutine(btn.dataset.startVird)));
  }

  function localTotalCount() {
    return allTimeHistory().reduce((s, x) => s + Number(x.count || 0), 0) + Number(state.counter.count || 0);
  }
  function bestZikirTitle() {
    const byTitle = {};
    allTimeHistory().forEach(x => { byTitle[x.title] = (byTitle[x.title] || 0) + Number(x.count || 0); });
    byTitle[currentZikir().title] = (byTitle[currentZikir().title] || 0) + Number(state.counter.count || 0);
    const best = Object.entries(byTitle).sort((a,b)=>b[1]-a[1])[0];
    return best ? { title: best[0], count: best[1] } : null;
  }
  function todayPlanPercent() {
    const plans = dailyPlans();
    if (!plans.length) return 0;
    const total = plans.reduce((s, p) => s + Math.min(100, percent(zikirTotalToday(p.zikirId), p.target)), 0);
    return Math.round(total / plans.length);
  }
  function getAchievements() {
    const total = localTotalCount();
    const streak = streakDays(allTimeHistory());
    const planPct = todayPlanPercent();
    const mine = state.data?.my_stats || {};
    const hatimMine = (state.data?.hatim_juz || []).filter(j => j.client_id === state.settings.clientId);
    return [
      { id:'first', icon:'☾', title:'İlk Zikir', desc:'İlk zikrini kaydet', unlocked: total > 0, progress: Math.min(1, total) },
      { id:'hundred', icon:'100', title:'100 Tekrar', desc:'Toplam 100 zikre ulaş', unlocked: total >= 100, progress: Math.min(100, total), target:100 },
      { id:'thousand', icon:'1K', title:'1.000 Tekrar', desc:'Toplam 1.000 zikre ulaş', unlocked: total >= 1000, progress: Math.min(1000, total), target:1000 },
      { id:'tenk', icon:'10K', title:'10.000 Tekrar', desc:'Toplam 10.000 zikre ulaş', unlocked: total >= 10000, progress: Math.min(10000, total), target:10000 },
      { id:'streak3', icon:'3', title:'3 Gün Seri', desc:'3 gün üst üste devam et', unlocked: streak >= 3, progress: Math.min(3, streak), target:3 },
      { id:'streak7', icon:'7', title:'7 Gün Seri', desc:'7 günlük devam serisi yap', unlocked: streak >= 7, progress: Math.min(7, streak), target:7 },
      { id:'plan', icon:'✓', title:'Planlı Gün', desc:'Bugünkü zikir planını tamamla', unlocked: planPct >= 100, progress: planPct, target:100 },
      { id:'dua', icon:'♡', title:'Dua Halkası', desc:'Dua veya Âmin ile katıl', unlocked: Number(mine.my_dua_count || 0) > 0 || Number(mine.amin_count || 0) > 0, progress: Math.min(1, Number(mine.my_dua_count || 0) + Number(mine.amin_count || 0)) },
      { id:'hatim', icon:'▤', title:'Hatim Katılımı', desc:'Bir cüz al veya tamamla', unlocked: hatimMine.length > 0 || Number(mine.my_juz_count || 0) > 0, progress: Math.min(1, hatimMine.length + Number(mine.my_juz_count || 0)) }
    ];
  }
  function achievementSummary() {
    const list = getAchievements();
    const unlocked = list.filter(x => x.unlocked).length;
    const latest = list.filter(x => x.unlocked).slice(-1)[0] || list[0];
    return { list, unlocked, total: list.length, latest };
  }

  function reminderTimes() {
    const list = Array.isArray(state.settings.reminderTimes) ? state.settings.reminderTimes : DEFAULT_SETTINGS.reminderTimes;
    const clean = list.map(t => String(t || '').slice(0, 5)).filter(t => /^\d{2}:\d{2}$/.test(t));
    return (clean.length ? clean : DEFAULT_SETTINGS.reminderTimes).slice(0, 3);
  }
  function nextReminderInfo() {
    const times = reminderTimes();
    const now = new Date();
    const candidates = [];
    times.forEach(t => {
      const [hh, mm] = t.split(':').map(Number);
      const d = new Date(now);
      d.setHours(hh, mm, 0, 0);
      if (d <= now) d.setDate(d.getDate() + 1);
      candidates.push({ time: t, date: d });
    });
    candidates.sort((a, b) => a.date - b.date);
    return candidates[0] || null;
  }
  function notificationStatusLabel() {
    if (!('Notification' in window)) return 'Bu cihazda desteklenmiyor';
    if (Notification.permission === 'granted') return 'İzin açık';
    if (Notification.permission === 'denied') return 'İzin kapalı';
    return 'İzin bekliyor';
  }
  async function askNotificationPermission() {
    if (!('Notification' in window)) return toast('Bu cihaz bildirim iznini desteklemiyor.');
    if (Notification.permission === 'granted') return toast('Bildirim izni zaten açık.');
    const perm = await Notification.requestPermission();
    toast(perm === 'granted' ? 'Bildirim izni açıldı.' : 'Bildirim izni verilmedi.');
    if (state.route === 'settings') runWithoutScrollJump(() => renderSettings());
  }
  function showNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
      try {
        new Notification(title, {
          body,
          icon: '/app/assets/icons/icon-192.png?v=20260506124752',
          badge: '/app/assets/icons/icon-192.png?v=20260506124752',
          tag: 'akilli-zikir-hatim-reminder',
          renotify: true
        });
        return true;
      } catch {}
    }
    toast(body);
    return false;
  }
  function testNotification() {
    const title = 'Akıllı Zikir & Hatim';
    const body = 'Test bildirimi hazır. Allah kabul etsin.';
    const shown = showNotification(title, body);
    if (state.settings.vibration && navigator.vibrate) navigator.vibrate([35, 40, 35]);
    if (state.settings.sound) beep();
    if (!shown && 'Notification' in window && Notification.permission !== 'granted') {
      toast('Önce bildirim izni vermen gerekiyor.');
    }
  }
  function notifyReminder(time) {
    const title = 'Zikir hatırlatması';
    const body = `${time} zikir vaktin geldi. Allah kabul etsin.`;
    showNotification(title, body);
    if (state.settings.vibration && navigator.vibrate) navigator.vibrate([35, 40, 35]);
    if (state.settings.sound) beep();
  }
  function checkReminders() {
    if (!state.settings.remindersEnabled) return;
    const now = new Date();
    const hm = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    if (!reminderTimes().includes(hm)) return;
    const key = `${now.toISOString().slice(0, 10)}_${hm}`;
    if (localStorage.getItem('az_last_reminder') === key) return;
    localStorage.setItem('az_last_reminder', key);
    notifyReminder(hm);
  }
  function startReminderWatcher() {
    clearInterval(startReminderWatcher._timer);
    startReminderWatcher._timer = setInterval(checkReminders, 30000);
    checkReminders();
  }
  async function requestWakeLock() {
    if (!('wakeLock' in navigator)) { toast('Bu cihaz ekran açık tutma özelliğini desteklemiyor.'); return false; }
    try {
      state.wakeLock = await navigator.wakeLock.request('screen');
      state.settings.keepAwake = true;
      save('az_settings', state.settings);
      state.wakeLock.addEventListener?.('release', () => { state.wakeLock = null; });
      toast('Sayaçta ekran açık kalacak.');
      return true;
    } catch {
      state.settings.keepAwake = false;
      save('az_settings', state.settings);
      toast('Ekran açık tutma izni alınamadı.');
      return false;
    }
  }
  async function releaseWakeLock(showToast = true) {
    try { if (state.wakeLock) await state.wakeLock.release(); } catch {}
    state.wakeLock = null;
    state.settings.keepAwake = false;
    save('az_settings', state.settings);
    if (showToast) toast('Ekran açık tutma kapandı.');
  }
  async function toggleWakeLock() {
    if (state.settings.keepAwake || state.wakeLock) await releaseWakeLock();
    else await requestWakeLock();
    if (state.route === 'counter') runWithoutScrollJump(() => renderCounter());
  }
  function addHistory(zikir, count, intent = '') {
    if (!count) return;
    const h = allTimeHistory();
    h.unshift({ date: nowDate(), ts: Date.now(), zikirId: zikir.id, title: zikir.title, count, intent: String(intent || '').trim() });
    save('az_history', h.slice(0, 700));
  }
  function duration(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    return h ? `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}` : `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  }
  function updateNetworkBadge() {
    let badge = $('#netBadge');
    const topbar = $('.topbar');
    if (!badge && topbar) {
      badge = document.createElement('div');
      badge.id = 'netBadge';
      badge.className = 'net-badge';
      topbar.appendChild(badge);
    }
    if (badge) {
      badge.textContent = navigator.onLine ? 'Online' : 'Offline';
      badge.classList.toggle('offline', !navigator.onLine);
    }
  }
  function render() {
    const keepScroll = !routeChanging && stableScrollY !== null ? stableScrollY : null;
    document.body.dataset.route = state.route;
    document.body.classList.toggle('focus-counter', state.route === 'counter' && !!state.settings.counterFocusMode);
    $('#screenTitle').textContent = titleForRoute(state.route);
    $$('.bottom-nav button').forEach(b => b.classList.toggle('active', b.dataset.route === state.route));
    const fn = {home: renderHome, counter: renderCounter, zikir: renderZikir, dua: renderDua, hatim: renderHatim, stats: renderStats, settings: renderSettings}[state.route] || renderHome;
    fn();
    bindCommon();
    bindFormControls(app);
    updateNetworkBadge();
    if (keepScroll !== null) scheduleScrollRestore(keepScroll);
  }
  const VALID_ROUTES = new Set(['home', 'counter', 'zikir', 'dua', 'hatim', 'stats', 'settings']);
  function route(to) {
    if (!VALID_ROUTES.has(to)) return;
    // Aynı ekrandayken tekrar route çağrısı gelirse ekranı başa alma.
    // Boş/pasif alan tıklamalarında bazı tarayıcılar aktif route butonunun click davranışını tetikleyebiliyor.
    if (to === state.route && !routeChanging) return;
    state.route = to;
    routeChanging = true;
    stableScrollY = null;
    clearTimeout(stableScrollTimer);
    render();
    window.scrollTo({top:0, behavior:'smooth'});
    setTimeout(() => { routeChanging = false; }, 500);
  }
  function bindCommon() {
    $$('[data-route]').forEach(btn => {
      const targetRoute = btn.dataset.route;
      // main/body gibi yanlışlıkla data-route alan pasif kapsayıcılara route bağlama.
      if (!VALID_ROUTES.has(targetRoute) || btn === app || btn === document.body) {
        btn.onclick = null;
        return;
      }
      btn.onclick = (ev) => {
        ev?.preventDefault?.();
        ev?.stopPropagation?.();
        route(targetRoute);
      };
    });
  }



  // v1.0.67 - Pasif alan tıklama güvenliği
  // Boş kart/body alanlarına dokunmak hiçbir uygulama aksiyonu tetiklemez.
  // Bu kod scroll'a, touchmove'a veya gerçek butonlara müdahale etmez;
  // yalnızca pasif alan click/pointerup olayının üst katmanlara taşınmasını keser.
  function isInteractiveTapTarget(target) {
    if (!target || !target.closest) return false;
    return !!target.closest('button, a[href], input, textarea, select, label, [role="button"], [data-route], [data-settings-scroll], [data-stats-jump], [data-plan-inline-value], .picker-row, .plan-zikir-btn, .select-button, .juz, .category-chip, .activity[data-session], .tabs button, .hatim-filter-tabs button, .bottom-nav, #modal, #modalContent');
  }

  function stopPassiveAreaClick(ev) {
    if (!app || !app.contains(ev.target)) return;
    if (isInteractiveTapTarget(ev.target)) return;
    // Sadece click/pointerup yayılımını durdur; preventDefault yok, scroll restore yok.
    ev.stopPropagation?.();
    ev.stopImmediatePropagation?.();
  }

  // v1.0.68: Pasif alanlara global click/pointer müdahalesi yapılmaz.

  // v1.0.69 - Pasif/boş alan tap düzeltmesi
  // Sorun: mobilde kart/body gibi pasif alanlara kısa dokunma bazen tarayıcı/üst seviye
  // click davranışına dönüşüp ekranı en üste taşıyordu. Bu düzeltme sadece pasif
  // kısa tap'i yutar; scroll/sürükleme ve gerçek butonlar etkilenmez. Scroll restore yok.
  const passiveBlankTap = { active: false, x: 0, y: 0, moved: false, route: '', blockClickUntil: 0 };

  function isRealInteractiveTarget(target) {
    if (!target || !target.closest) return true;
    return !!target.closest([
      'button',
      'a[href]',
      'input',
      'textarea',
      'select',
      'label',
      '[role="button"]',
      '[data-route]',
      '[data-settings-scroll]',
      '[data-stats-jump]',
      '[data-plan-inline-value]',
      '[data-picker-value]',
      '.picker-row',
      '.plan-zikir-btn',
      '.select-button',
      '.juz',
      '.category-chip',
      '.activity[data-session]',
      '.tabs button',
      '.hatim-filter-tabs button',
      '.bottom-nav',
      '.topbar',
      '#modal',
      '#modalContent',
      '.modal'
    ].join(','));
  }

  function isPassiveBlankTarget(target) {
    return !!(app && target && app.contains(target) && !isRealInteractiveTarget(target));
  }

  function blankTapPoint(ev) {
    const t = ev.changedTouches?.[0] || ev.touches?.[0] || ev;
    return { x: Number(t.clientX || 0), y: Number(t.clientY || 0) };
  }

  function startPassiveBlankTap(ev) {
    if (!isPassiveBlankTarget(ev.target) || routeChanging) {
      passiveBlankTap.active = false;
      return;
    }
    const p = blankTapPoint(ev);
    passiveBlankTap.active = true;
    passiveBlankTap.x = p.x;
    passiveBlankTap.y = p.y;
    passiveBlankTap.moved = false;
    passiveBlankTap.route = state.route;
  }

  function movePassiveBlankTap(ev) {
    if (!passiveBlankTap.active) return;
    const p = blankTapPoint(ev);
    if (Math.abs(p.x - passiveBlankTap.x) > 8 || Math.abs(p.y - passiveBlankTap.y) > 8) {
      passiveBlankTap.moved = true;
    }
  }

  function endPassiveBlankTap(ev) {
    if (!passiveBlankTap.active) return;
    const moved = passiveBlankTap.moved;
    const routeAt = passiveBlankTap.route;
    passiveBlankTap.active = false;
    if (moved || routeChanging || state.route !== routeAt || !isPassiveBlankTarget(ev.target)) return;
    // Kısa pasif tap: hiçbir uygulama aksiyonu tetiklemesin, scroll'a da dokunmasın.
    ev.preventDefault?.();
    ev.stopPropagation?.();
    ev.stopImmediatePropagation?.();
    passiveBlankTap.blockClickUntil = Date.now() + 450;
  }

  function blockSyntheticBlankClick(ev) {
    if (Date.now() > passiveBlankTap.blockClickUntil) return;
    if (!isPassiveBlankTarget(ev.target)) return;
    ev.preventDefault?.();
    ev.stopPropagation?.();
    ev.stopImmediatePropagation?.();
  }

  document.addEventListener('touchstart', startPassiveBlankTap, { capture: true, passive: true });
  document.addEventListener('touchmove', movePassiveBlankTap, { capture: true, passive: true });
  document.addEventListener('touchend', endPassiveBlankTap, { capture: true, passive: false });
  document.addEventListener('click', blockSyntheticBlankClick, true);

  function renderHome() {
    const daily = state.data?.daily;
    const favorite = favoriteZikirs();
    const h = allTimeHistory();
    const total = localTotalCount();
    const sessions = state.data?.zikir_sessions || [];
    const requests = state.data?.dua_requests || [];
    const juz = state.data?.hatim_juz || [];
    const completed = juz.filter(j => j.status === 'completed').length;
    const plans = dailyPlans();
    const activeZikir = currentZikir();
    const activeIntent = String(state.counter.intent || '').trim();
    const ach = achievementSummary();
    const best = bestZikirTitle();
    const journal = journalSummary();
    const tes = tesbihatSummary();
    const tesbihatHomeHtml = `<section class="card tesbihat-home-card"><div class="tesbihat-orb">${tes.active ? (tes.stepIndex + 1) + '/' + tes.totalSteps : '33'}</div><div><strong>Namaz Sonrası Tesbihat</strong><p>${tes.active ? esc(tes.step.title) + ' · ' + fmt(state.counter.count) + '/' + fmt(tes.step.target) + ' tekrar' : '33-33-33 tesbihat akışını takip et'}</p><div class="mini-progress"><span style="width:${tes.overall}%"></span></div></div><button class="link-btn" id="homeTesbihatBtn">${tes.active ? 'Devam' : 'Başlat'}</button></section>`;
    const vird = virdSummary();
    const virdHomeHtml = `<section class="card vird-home-card"><div class="vird-orb">${vird.active ? (vird.stepIndex + 1) + '/' + vird.totalSteps : '✦'}</div><div><strong>Kişisel Vird Akışı</strong><p>${vird.active ? esc(vird.routine.title) + ' · ' + esc(vird.step.title) + ' · ' + fmt(state.counter.count) + '/' + fmt(vird.step.target) : 'Günlük virdini adım adım takip et'}</p><div class="mini-progress"><span style="width:${vird.overall}%"></span></div></div><button class="link-btn" id="homeVirdBtn">${vird.active ? 'Devam' : 'Başlat'}</button></section>`;
    const recentQuick = recentZikirUsage(3);
    const quickStartHomeHtml = `<section class="card quick-start-home-card"><div class="quick-start-orb">⌕</div><div><strong>Zikir Ara</strong><p>${recentQuick.length ? 'Son kullandığın zikirlerden hızlı devam et veya tüm zikirlerde ara.' : 'Hazır ve kişisel zikirleri ara, hedef seçip başlat.'}</p>${recentQuick.length ? `<div class="home-recent-zikir">${recentQuick.map(item => `<button data-home-quick-zikir="${item.zikir.id}" data-home-quick-target="${item.zikir.default_target || state.settings.defaultTarget || 1000}">${esc(item.zikir.title)}</button>`).join('')}</div>` : ''}</div><button class="link-btn" id="openZikirSearchHome">Aç</button></section>`;
    const journalHomeHtml = `<section class="card journal-home-card"><div class="journal-orb">✍</div><div><strong>Manevi Not Defteri</strong><p>${journal.today ? 'Bugünkü not hazır · ' + esc((journal.today.text || '').slice(0, 42)) : journal.total ? fmt(journal.total) + ' günlük not kayıtlı · son notunu incele' : 'Bugünün niyetini veya kısa notunu yaz'}</p></div><button class="link-btn" id="openJournalHome">Aç</button></section>`;
    const resumeRows = smartResumeData();
    const smartResumeHtml = state.settings.smartResumeCard ? `<section class="card smart-resume-card"><div class="smart-resume-head"><div><span>Kaldığın Yer</span><strong>Akıllı devam merkezi</strong></div><button class="link-btn" id="openSmartResumeHome">Tümü</button></div><div class="smart-resume-list">${resumeRows.slice(0,3).map((item, idx) => `<button class="smart-resume-row" data-resume-index="${idx}"><span>${esc(item.icon)}</span><p><strong>${esc(item.title)}</strong><small>${esc(item.text)}</small></p><b>${esc(item.badge || 'Aç')}</b></button>`).join('')}</div></section>` : '';
    const checklist = dailyChecklistData();
    const checklistHtml = state.settings.dailyChecklistCard ? `<section class="card daily-checklist-card"><div class="daily-checklist-head"><div><span>Bugünkü Manevi Kontrol</span><strong>${checklist.done}/${checklist.total} görev tamam</strong></div><button class="link-btn" id="openDailyChecklistHome">Tümü</button></div><div class="daily-checklist-progress"><span style="width:${checklist.percent}%"></span></div><div class="daily-checklist-list">${checklist.items.slice(0,4).map((item, idx) => `<button class="daily-check-item ${item.done ? 'done' : ''}" data-check-index="${idx}"><span>${item.done ? '✓' : esc(item.icon)}</span><p><strong>${esc(item.title)}</strong><small>${esc(item.text)}</small></p><b>${item.done ? 'Tamam' : esc(item.badge || 'Aç')}</b></button>`).join('')}</div></section>` : '';
    const achievementHomeHtml = `<section class="card achievement-home-card"><div><span class="achievement-kicker">Manevi Yolculuk</span><h3>${ach.unlocked}/${ach.total} rozet açıldı</h3><p>${ach.latest?.unlocked ? 'Son rozet: ' + esc(ach.latest.title) : 'İlk rozet için zikre başla.'}${best ? ' · En çok: ' + esc(best.title) : ''}</p></div><button class="link-btn" data-route="stats">Rozetler</button></section>`;
    const activeSessionHtml = Number(state.counter.count || 0) > 0 ? `<section class="card active-session-card"><div class="section-row" style="margin-top:0"><h3>Devam Eden Oturum</h3><button class="link-btn" data-route="counter">Devam Et</button></div><div class="active-session-line"><span>☾</span><p><strong>${esc(activeZikir.title)}</strong><small>${fmt(state.counter.count)} / ${fmt(state.counter.target)} tekrar${activeIntent ? ' · ' + esc(activeIntent) : ''}</small></p><b>%${percent(state.counter.count,state.counter.target)}</b></div><div class="progress"><span style="width:${percent(state.counter.count,state.counter.target)}%"></span></div></section>` : '';
    const remoteSettings = state.data?.settings || {};
    const announcementActive = String(remoteSettings.app_announcement_enabled || '0') === '1';
    const announcementTitle = remoteSettings.app_announcement_title || 'Duyuru';
    const announcementBody = remoteSettings.app_announcement_body || '';
    const announcementHtml = announcementActive && announcementBody ? `<section class="card announcement-card"><div class="announcement-icon">✦</div><div><strong>${esc(announcementTitle)}</strong><p>${esc(announcementBody)}</p></div></section>` : '';
    const nextReminder = nextReminderInfo();
    const reminderHtml = state.settings.remindersEnabled && nextReminder ? `<section class="card reminder-card"><span class="reminder-icon">⏰</span><div><strong>Sıradaki Zikir Hatırlatması</strong><p>${esc(nextReminder.time)} · Hatırlatıcı telefonda/offline takip edilir.</p></div><button class="link-btn" data-route="settings">Düzenle</button></section>` : '';
    const syncStateText = navigator.onLine ? (state.queue.length ? `${state.queue.length} bekleyen işlem var` : 'Güncel') : 'Offline mod';
    const syncHomeHtml = `<section class="card sync-home-card"><div class="sync-orb ${navigator.onLine ? 'online' : 'offline'}">${navigator.onLine ? '↻' : '!'}</div><div><strong>Veri Durumu</strong><p>${esc(syncStateText)} · Son: ${esc(syncTimeLabel())}</p></div><button class="link-btn" data-route="settings">Yönet</button></section>`;
    const firstUseHtml = !state.settings.firstUseGuideDone ? `<section class="card first-use-card"><div class="first-use-mark">☾</div><div><span>Yeni Başlayan Rehberi</span><strong>3 adımda uygulamayı kullanmaya başla</strong><p>Rumuzunu belirle, günlük hedefini seç, sayaçtan zikre başla. Dua ve hatim alanlarına sonra geçebilirsin.</p><div class="first-use-actions"><button id="openFirstUseGuide">Rehberi Aç</button><button id="hideFirstUseGuide">Bir Daha Gösterme</button></div></div></section>` : '';
    const planDoneAvg = plans.length ? Math.round(plans.reduce((sum, p) => sum + percent(zikirTotalToday(p.zikirId), p.target), 0) / plans.length) : 0;
    const nextFocusText = Number(state.counter.count || 0) > 0 ? `${esc(activeZikir.title)} · ${fmt(state.counter.count)}/${fmt(state.counter.target)}` : (tes.active ? 'Tesbihat akışına devam et' : (vird.active ? 'Vird akışına devam et' : 'Bugünkü plana başla'));
    const homeFocusHtml = `<section class="card home-focus-card"><div class="focus-kicker">Bugünün Akıllı Özeti</div><div class="home-focus-main"><div><strong>${nextFocusText}</strong><small>Plan %${planDoneAvg} · Bugünkü toplam ${fmt(dailyTotal())}</small></div><b>%${planDoneAvg}</b></div><div class="mini-progress"><span style="width:${planDoneAvg}%"></span></div><div class="home-focus-actions"><button data-route="counter">Sayaç</button><button data-route="stats">Özet</button><button id="homeSummaryOpen">Detay</button></div></section>`;
    const nextPlanHome = plans.map(p => ({ ...p, done: zikirTotalToday(p.zikirId), pct: percent(zikirTotalToday(p.zikirId), p.target) })).filter(p => p.pct < 100).sort((a,b) => a.pct - b.pct)[0] || null;
    const hasActiveCounter = Number(state.counter.count || 0) > 0 || tes.active || vird.active;
    const homeTaskTitle = hasActiveCounter ? 'Kaldığın yerden devam et' : (nextPlanHome ? 'Bugünkü sıradaki hedefe başla' : 'Sayaçtan zikre başla');
    const homeTaskText = hasActiveCounter ? `${esc(activeZikir.title)} · ${fmt(state.counter.count || 0)} tekrar` : (nextPlanHome ? `${esc(nextPlanHome.zikir.title)} · ${fmt(nextPlanHome.done)} / ${fmt(nextPlanHome.target)}` : 'Günlük hedefini seçip ilk oturumu başlatabilirsin.');
    const homeTaskHtml = `<section class="card home-task-command-card">
      <div class="home-task-mark">☾</div>
      <div class="home-task-body">
        <span>Şimdi ne yapmalıyım?</span>
        <strong>${homeTaskTitle}</strong>
        <p>${homeTaskText}</p>
        <div class="home-task-actions">
          <button id="homePrimaryTask">${hasActiveCounter ? 'Devam Et' : (nextPlanHome ? 'Hedefe Başla' : 'Sayaç Aç')}</button>
          <button id="homeTaskDua">Dua</button>
          <button id="homeTaskHatim">Hatim</button>
          <button id="homeTaskZikir">Toplu Zikir</button>
        </div>
      </div>
    </section>`;
    app.innerHTML = `
      <h1 class="page-title">Akıllı Zikir & Hatim</h1>
      <p class="subtle">Kişisel zikir, dua, hatim ve toplu zikir takibi.</p>
      <section class="card hero-card premium-hero">
        <div class="starfield">☾</div><div class="masjid">🕌</div>
        <div class="verse">“${esc(daily?.body || 'Bilesiniz ki, kalpler ancak Allah’ın zikriyle huzur bulur.')}”</div>
        <div class="ref">${esc(daily?.reference_text || 'Ra’d Suresi, 28. Ayet')}</div>
      </section>
      ${homeFocusHtml}
      ${homeTaskHtml}
      ${activeSessionHtml}
      ${firstUseHtml}
      ${smartResumeHtml}
      ${checklistHtml}
      <div class="home-section-title"><span>Günlük Kısa Yollar</span><small>Hiçbir özellik kaldırılmadı; sadece daha düzenli gösteriliyor.</small></div>
      ${tesbihatHomeHtml}
      ${virdHomeHtml}
      ${quickStartHomeHtml}
      ${journalHomeHtml}
      <div class="home-section-title secondary"><span>Durum ve Hatırlatma</span><small>Bildirim, duyuru ve veri durumu</small></div>
      ${announcementHtml}
      ${reminderHtml}
      ${syncHomeHtml}
      <div class="quick-grid home-grid">
        <button class="quick-card" data-route="counter"><b>${fmt(dailyTotal())}</b><small>Bugünkü Zikir</small></button>
        <button class="quick-card" data-route="stats"><b>${fmt(total)}</b><small>Toplam Kayıt</small></button>
        <button class="quick-card" data-route="zikir"><b>${fmt(sessions.length)}</b><small>Canlı Zikir</small></button>
        <button class="quick-card" data-route="hatim"><b>${completed}/30</b><small>Hatim</small></button>
      </div>
      ${achievementHomeHtml}
      <section class="card daily-plan-card">
        <div class="section-row" style="margin-top:0"><h3>Bugünkü Zikir Planı</h3><button class="link-btn" data-route="settings">Düzenle</button></div>
        <div class="daily-plan-list">
          ${plans.map(p => {
            const done = zikirTotalToday(p.zikirId);
            const pct = percent(done, p.target);
            return `<button class="daily-plan-item" data-plan-start="${p.zikirId}" data-plan-target="${p.target}"><span class="plan-orb">☾</span><p><strong>${esc(p.zikir.title)}</strong><small>${fmt(done)} / ${fmt(p.target)} tekrar</small><i><em style="width:${pct}%"></em></i></p><b>%${pct}</b></button>`;
          }).join('')}
        </div>
      </section>
      <section class="community-strip">
        <button data-route="zikir"><span>☾</span><strong>Toplu Zikir</strong><small>${fmt(sessions.reduce((s,x)=>s+Number(x.participant_count||0),0))} katılımcı</small></button>
        <button data-route="dua"><span>♡</span><strong>Dua Halkası</strong><small>${fmt(requests.length)} dua isteği</small></button>
        <button data-route="hatim"><span>▤</span><strong>Hatim</strong><small>${fmt(juz.filter(j=>j.client_id===state.settings.clientId).length)} cüz sende</small></button>
      </section>
      <div class="section-row"><h3>Favori Zikirler</h3><div class="section-actions"><button class="link-btn" id="manageFavoritesHome">Düzenle</button><button class="link-btn" id="addCustomZikir">Yeni Ekle</button></div></div>
      <div class="zikir-list">
        ${favorite.map(z => `
          <button class="zikir-item" data-pick-zikir="${z.id}">
            <span class="zikir-badge">${esc((z.arabic_text || z.title).slice(0, 8))}</span>
            <span><strong>${esc(z.title)}</strong><small>${esc(z.meaning || '')}</small></span>
            <span class="star">★</span>
          </button>`).join('')}
      </div>
      <section class="card install-card">
        <strong>Uygulama gibi kullan</strong>
        <p style="margin:6px 0 0;color:var(--muted);font-size:13px;line-height:1.45">Ana ekrana ekleyerek uygulama gibi kullanabilirsin. Sayaç internet yokken de çalışır.</p>
        <button class="soft-share-btn" id="shareAppBtn">Uygulamayı Paylaş</button>
      </section>
      <div class="safe-space"></div>`;
    $$('[data-pick-zikir]').forEach(btn => btn.onclick = () => { state.counter.zikirId = Number(btn.dataset.pickZikir); const z = currentZikir(); state.counter.target = Number(z.default_target || state.settings.defaultTarget || 1000); save('az_counter', state.counter); route('counter'); });
    $$('[data-plan-start]').forEach(btn => btn.onclick = () => { state.counter.zikirId = Number(btn.dataset.planStart); state.counter.target = Math.max(1, Number(btn.dataset.planTarget || state.settings.defaultTarget || 100)); save('az_counter', state.counter); route('counter'); });
    $('#addCustomZikir')?.addEventListener('click', showCustomZikirModal);
    $('#manageFavoritesHome')?.addEventListener('click', showFavoriteManager);
    $('#openJournalHome')?.addEventListener('click', showJournalModal);
    $('#homeTesbihatBtn')?.addEventListener('click', () => { if (state.tesbihat?.active) route('counter'); else showTesbihatStartModal(); });
    $('#homeVirdBtn')?.addEventListener('click', () => { if (state.vird?.active) route('counter'); else showVirdStartModal(); });
    $('#openZikirSearchHome')?.addEventListener('click', showZikirSearchModal);
    $('#homeSummaryOpen')?.addEventListener('click', showTodaySmartSummary);
    $('#homePrimaryTask')?.addEventListener('click', () => {
      if (hasActiveCounter) return route('counter');
      if (nextPlanHome) {
        state.counter.zikirId = Number(nextPlanHome.zikirId);
        state.counter.target = Math.max(1, Number(nextPlanHome.target || state.settings.defaultTarget || 100));
        save('az_counter', state.counter);
      }
      route('counter');
    });
    $('#homeTaskDua')?.addEventListener('click', () => route('dua'));
    $('#homeTaskHatim')?.addEventListener('click', () => route('hatim'));
    $('#homeTaskZikir')?.addEventListener('click', () => route('zikir'));
    $('#openFirstUseGuide')?.addEventListener('click', () => showFirstUseGuide(true));
    $('#hideFirstUseGuide')?.addEventListener('click', () => { state.settings.firstUseGuideDone = true; save('az_settings', state.settings); localStorage.setItem('az_first_use_guide_done_v1240', '1'); toast('Yeni başlayan rehberi gizlendi.'); render(); });
    $('#openSmartResumeHome')?.addEventListener('click', showSmartResumeModal);
    $('#openDailyChecklistHome')?.addEventListener('click', showDailyChecklistModal);
    $$('[data-check-index]').forEach(btn => btn.addEventListener('click', () => handleDailyChecklist(checklist.items[Number(btn.dataset.checkIndex)])));
    $$('[data-resume-index]').forEach(btn => btn.addEventListener('click', () => handleSmartResume(resumeRows[Number(btn.dataset.resumeIndex)])));
    $$('[data-home-quick-zikir]').forEach(btn => btn.addEventListener('click', () => startQuickZikir(Number(btn.dataset.homeQuickZikir), Number(btn.dataset.homeQuickTarget || state.settings.defaultTarget || 1000))));
    $('#shareAppBtn')?.addEventListener('click', shareApp);
  }


  function shouldShowFirstUseGuide() {
    return state.settings.firstUseGuideDone !== true && localStorage.getItem('az_first_use_guide_done_v1240') !== '1';
  }

  function completeFirstUseGuide() {
    state.settings.firstUseGuideDone = true;
    save('az_settings', state.settings);
    localStorage.setItem('az_first_use_guide_done_v1240', '1');
  }

  function showFirstUseGuide(force = false) {
    if (!force && !shouldShowFirstUseGuide()) return;
    openModal(`
      <div class="first-use-modal">
        <div class="first-use-modal-mark">☾</div>
        <h2>Akıllı Zikir & Hatim’e Hoş Geldin</h2>
        <p class="modal-note">Uygulamayı en kolay şekilde kullanmak için önce üç temel adımı tamamla. İstersen daha sonra Ayarlar bölümünden değiştirebilirsin.</p>
        <div class="first-use-step-list">
          <button id="firstUseNick"><b>1</b><span><strong>Rumuzunu Belirle</strong><small>Dua, âmin ve hatim kayıtlarında görünen adın.</small></span></button>
          <button id="firstUsePlan"><b>2</b><span><strong>Günlük Hedefini Ayarla</strong><small>Ana sayfada bugün ne yapacağını daha net gör.</small></span></button>
          <button id="firstUseCounter"><b>3</b><span><strong>Sayaçtan Başla</strong><small>Offline çalışır, hedefe ulaşınca kayıt alabilirsin.</small></span></button>
        </div>
        <div class="first-use-modal-actions">
          <button class="soft-share-btn full" id="firstUseFinish">Tamam, Anladım</button>
          <button class="link-btn" id="firstUseLater">Sonra Hatırlat</button>
        </div>
      </div>
    `);
    $('#firstUseNick')?.addEventListener('click', () => { closeModal(); route('settings'); setTimeout(() => $('#nickInput')?.focus(), 300); });
    $('#firstUsePlan')?.addEventListener('click', () => { closeModal(); route('settings'); setTimeout(() => document.getElementById('settingsPlans')?.scrollIntoView({behavior:'smooth', block:'start'}), 300); });
    $('#firstUseCounter')?.addEventListener('click', () => { completeFirstUseGuide(); closeModal(); route('counter'); });
    $('#firstUseFinish')?.addEventListener('click', () => { completeFirstUseGuide(); closeModal(); render(); toast('Rehber tamamlandı.'); });
    $('#firstUseLater')?.addEventListener('click', closeModal);
  }



  function dailyChecklistData() {
    const plans = dailyPlans();
    const planRows = plans.map(p => ({ ...p, done: zikirTotalToday(p.zikirId), pct: percent(zikirTotalToday(p.zikirId), p.target) }));
    const planAvg = planRows.length ? Math.round(planRows.reduce((sum, p) => sum + p.pct, 0) / planRows.length) : 0;
    const todayTotal = dailyTotal();
    const journal = journalSummary();
    const mine = state.data?.mine || {};
    const myJuz = (state.data?.hatim_juz || []).filter(j => j.client_id === state.settings.clientId && j.status !== 'completed');
    const checklist = [
      { icon:'☾', title:'Bugünkü zikir başlangıcı', text: todayTotal > 0 ? `${fmt(todayTotal)} tekrar yapıldı` : 'En az bir zikir oturumu başlat', done: todayTotal > 0, badge:'Sayaç', route:'counter' },
      { icon:'▣', title:'Günlük plan takibi', text: planAvg >= 100 ? 'Bugünkü plan tamamlandı' : `Plan %${planAvg}`, done: planAvg >= 100, badge:`%${planAvg}`, action:'nextPlan' },
      { icon:'✍', title:'Manevi not', text: journal.today ? 'Bugünkü not kaydedildi' : 'Kısa niyet, dua veya tefekkür notu ekle', done: !!journal.today, badge:'Not', action:'journal' },
      { icon:'♡', title:'Dua halkasına katılım', text: Number(mine.amin_count || 0) > 0 || Number(mine.my_dua_count || 0) > 0 ? 'Dua halkasına katıldın' : 'Bir duaya Âmin de veya dua isteği ekle', done: Number(mine.amin_count || 0) > 0 || Number(mine.my_dua_count || 0) > 0, badge:'Dua', route:'dua' },
      { icon:'▤', title:'Hatim takibi', text: myJuz.length ? `${myJuz.map(j => j.juz_number + '. Cüz').slice(0,2).join(', ')} sende` : 'Müsaitsen boş bir cüz al', done: myJuz.length > 0 || Number(mine.my_juz_count || 0) > 0, badge:'Hatim', route:'hatim' },
      { icon:'↻', title:'Veri Durumu temizliği', text: state.queue.length ? `${fmt(state.queue.length)} bekleyen işlem var` : 'Bekleyen işlem yok', done: state.queue.length === 0, badge:'Sync', route:'settings' }
    ];
    const done = checklist.filter(x => x.done).length;
    return { items: checklist, done, total: checklist.length, percent: percent(done, checklist.length) };
  }

  function handleDailyChecklist(item) {
    if (!item) return;
    if (item.action === 'journal') { showJournalModal(); return; }
    if (item.action === 'nextPlan') {
      const plans = dailyPlans().map(p => ({ ...p, done: zikirTotalToday(p.zikirId), pct: percent(zikirTotalToday(p.zikirId), p.target) })).filter(p => p.pct < 100).sort((a, b) => a.pct - b.pct);
      if (plans[0]) { startQuickZikir(plans[0].zikirId, plans[0].target); return; }
      route('stats'); return;
    }
    if (item.route) route(item.route);
  }

  function showDailyChecklistModal() {
    const checklist = dailyChecklistData();
    openModal(`<h2 class="page-title">Bugünkü Manevi Kontrol</h2><p class="modal-note">Bu kart yeni bir özellik olarak sadece takip kolaylığı sağlar; önceki bölümlerin hiçbirini silmez. Günlük zikir, plan, dua, hatim, not ve senkron durumunu tek yerde görürsün.</p><div class="daily-checklist-progress modal-progress"><span style="width:${checklist.percent}%"></span></div><div class="daily-checklist-list modal-checklist-list">${checklist.items.map((item, idx) => `<button class="daily-check-item ${item.done ? 'done' : ''}" data-modal-check-index="${idx}"><span>${item.done ? '✓' : esc(item.icon)}</span><p><strong>${esc(item.title)}</strong><small>${esc(item.text)}</small></p><b>${item.done ? 'Tamam' : esc(item.badge || 'Aç')}</b></button>`).join('')}</div>`);
    $$('[data-modal-check-index]', modalContent).forEach(btn => btn.addEventListener('click', () => { const item = checklist.items[Number(btn.dataset.modalCheckIndex)]; closeModal(); handleDailyChecklist(item); }));
  }


  function smartResumeData() {
    const rows = [];
    const activeZikir = currentZikir();
    const counterPct = percent(state.counter.count, state.counter.target);
    const tes = tesbihatSummary();
    const vird = virdSummary();
    const plans = dailyPlans();
    const nextPlan = plans.map(p => ({ ...p, done: zikirTotalToday(p.zikirId), pct: percent(zikirTotalToday(p.zikirId), p.target) })).filter(p => p.pct < 100).sort((a, b) => a.pct - b.pct)[0];
    const myJuz = (state.data?.hatim_juz || []).filter(j => j.client_id === state.settings.clientId && j.status !== 'completed');
    const todayNote = todayJournal();
    if (Number(state.counter.count || 0) > 0) {
      rows.push({ icon:'☾', title:'Sayaçtan devam et', text:`${activeZikir.title} · ${fmt(state.counter.count)}/${fmt(state.counter.target)} tekrar`, badge:`%${counterPct}`, route:'counter' });
    }
    if (tes.active) {
      rows.push({ icon:'◴', title:'Tesbihat akışı açık', text:`${tes.step.title} · ${fmt(state.counter.count)}/${fmt(tes.step.target)} tekrar`, badge:`${tes.stepIndex + 1}/${tes.totalSteps}`, route:'counter' });
    }
    if (vird.active) {
      rows.push({ icon:'✦', title:'Vird akışı açık', text:`${vird.routine.title} · ${vird.step.title}`, badge:`${vird.stepIndex + 1}/${vird.totalSteps}`, route:'counter' });
    }
    if (nextPlan) {
      rows.push({ icon:'▣', title:'Bugünkü plandan devam et', text:`${nextPlan.zikir.title} · ${fmt(nextPlan.done)}/${fmt(nextPlan.target)} tekrar`, badge:`%${nextPlan.pct}`, action:'planStart', zikirId: nextPlan.zikirId, target: nextPlan.target });
    }
    if (myJuz.length) {
      rows.push({ icon:'▤', title:'Hatimde sende cüz var', text: myJuz.slice(0,3).map(j => `${j.juz_number}. Cüz`).join(', ') + (myJuz.length > 3 ? ` +${myJuz.length - 3}` : ''), badge:'Hatim', route:'hatim' });
    }
    if (state.queue.length) {
      rows.push({ icon:'↻', title:'Bekleyen senkronizasyon var', text:`${fmt(state.queue.length)} işlem internet gelince gönderilecek`, badge:'Sync', route:'settings' });
    }
    if (!todayNote) {
      rows.push({ icon:'✍', title:'Bugünün manevi notu boş', text:'Kısa niyet, dua veya tefekkür notu ekleyebilirsin.', badge:'Not', action:'journal' });
    }
    if (!rows.length) rows.push({ icon:'☾', title:'Yeni zikre başla', text:'Sayaç ekranından sakin ve odaklı bir oturum başlat.', badge:'Başla', route:'counter' });
    return rows.slice(0, 8);
  }

  function handleSmartResume(item) {
    if (!item) return;
    if (item.action === 'planStart') {
      state.counter.zikirId = Number(item.zikirId);
      state.counter.target = Math.max(1, Number(item.target || state.settings.defaultTarget || 100));
      save('az_counter', state.counter);
      route('counter');
      return;
    }
    if (item.action === 'journal') { showJournalModal(); return; }
    if (item.route) route(item.route);
  }

  function showSmartResumeModal() {
    const rows = smartResumeData();
    openModal(`<h2 class="page-title">Kaldığın Yer</h2><p class="modal-note">Eklenen hiçbir bölümü silmeden, sadece devam etmen gereken işleri tek yerde toplar.</p><div class="smart-resume-list modal-resume-list">${rows.map((item, idx) => `<button class="smart-resume-row" data-modal-resume-index="${idx}"><span>${esc(item.icon)}</span><p><strong>${esc(item.title)}</strong><small>${esc(item.text)}</small></p><b>${esc(item.badge || 'Aç')}</b></button>`).join('')}</div>`);
    $$('[data-modal-resume-index]', modalContent).forEach(btn => btn.addEventListener('click', () => { const item = rows[Number(btn.dataset.modalResumeIndex)]; closeModal(); handleSmartResume(item); }));
  }

  function showTodaySmartSummary() {
    const plans = dailyPlans();
    const h = allTimeHistory();
    const today = nowDate();
    const todaySaved = h.filter(x => x.date === today).reduce((sum, x) => sum + Number(x.count || 0), 0);
    const todayActive = Number(state.counter.count || 0);
    const planRows = plans.map(p => {
      const done = zikirTotalToday(p.zikirId);
      const pct = percent(done, p.target);
      return `<div class="summary-plan-row"><span>☾</span><p><strong>${esc(p.zikir.title)}</strong><small>${fmt(done)} / ${fmt(p.target)} tekrar</small><i><em style="width:${pct}%"></em></i></p><b>%${pct}</b></div>`;
    }).join('');
    openModal(`<h2 class="page-title">Bugünün Özeti</h2><p class="modal-note">Telefonundaki offline kayıtlar ve aktif sayaç baz alınır.</p><div class="summary-total-grid"><div><span>Kaydedilen</span><b>${fmt(todaySaved)}</b></div><div><span>Aktif Sayaç</span><b>${fmt(todayActive)}</b></div><div><span>Toplam</span><b>${fmt(todaySaved + todayActive)}</b></div></div><div class="section-row"><h3>Plan İlerlemesi</h3></div><div class="summary-plan-list">${planRows}</div><button class="cta" data-route="counter" style="margin-top:12px">Sayaçtan Devam Et</button>`);
  }

  function renderCounter() {
    const z = currentZikir();
    const pct = percent(state.counter.count, state.counter.target);
    const elapsed = duration(Date.now() - Number(state.counter.startedAt || Date.now()));
    const tes = tesbihatSummary();
    const tesbihatCounterHtml = tes.active ? `<section class="card tesbihat-flow-card"><div class="section-row" style="margin-top:0"><h3>Namaz Sonrası Tesbihat</h3><button class="link-btn" id="cancelTesbihat">Durdur</button></div><div class="tesbihat-step-head"><span>${tes.stepIndex + 1}/${tes.totalSteps}</span><p><strong>${esc(tes.step.title)}</strong><small>${fmt(state.counter.count)} / ${fmt(tes.step.target)} tekrar</small></p><b>%${percent(state.counter.count, tes.step.target)}</b></div><div class="progress"><span style="width:${percent(state.counter.count, tes.step.target)}%"></span></div><div class="tesbihat-step-list">${tes.seq.steps.map((st, idx) => `<span class="${idx < tes.stepIndex ? 'done' : idx === tes.stepIndex ? 'active' : ''}">${idx + 1}. ${esc(st.title)} <small>${st.target}</small></span>`).join('')}</div><button class="cta secondary" id="nextTesbihatStep" style="margin-top:12px">${tes.stepIndex >= tes.totalSteps - 1 ? 'Tesbihatı Tamamla' : 'Sonraki Zikre Geç'}</button></section>` : `<section class="card tesbihat-start-card"><div><strong>Namaz sonrası tesbihat</strong><p>33-33-33 akışını sayaçla takip etmek için başlat.</p></div><button class="link-btn" id="startTesbihatCounter">Başlat</button></section>`;
    const vird = virdSummary();
    const virdCounterHtml = vird.active ? `<section class="card vird-flow-card"><div class="section-row" style="margin-top:0"><h3>Kişisel Vird Akışı</h3><button class="link-btn" id="cancelVird">Durdur</button></div><div class="tesbihat-step-head"><span>${vird.stepIndex + 1}/${vird.totalSteps}</span><p><strong>${esc(vird.routine.title)}</strong><small>${esc(vird.step.title)} · ${fmt(state.counter.count)} / ${fmt(vird.step.target)} tekrar</small></p><b>%${percent(state.counter.count, vird.step.target)}</b></div><div class="progress"><span style="width:${percent(state.counter.count, vird.step.target)}%"></span></div><div class="tesbihat-step-list vird-step-list">${vird.routine.steps.map((st, idx) => `<span class="${idx < vird.stepIndex ? 'done' : idx === vird.stepIndex ? 'active' : ''}">${idx + 1}. ${esc(st.title)} <small>${fmt(st.target)}</small></span>`).join('')}</div><button class="cta secondary" id="nextVirdStep" style="margin-top:12px">${vird.stepIndex >= vird.totalSteps - 1 ? 'Virdi Tamamla' : 'Sonraki Adıma Geç'}</button></section>` : `<section class="card vird-start-card"><div><strong>Kişisel vird akışı</strong><p>Sabah, akşam veya günlük virdini sayaçla sırayla takip et.</p></div><button class="link-btn" id="startVirdCounter">Başlat</button></section>`;
    app.innerHTML = `
      <h1 class="page-title">Zikir Sayacı</h1>
      <div class="counter-select"><button type="button" id="zikirSelect" class="select-pill select-button zikir-select-button" data-zikir-id="${Number(z.id)}" ${(tes.active || vird.active) ? 'disabled' : ''}><span>${esc(z.title)}</span><b>⌄</b></button></div>
      <div class="arabic">${esc(z.arabic_text || '')}</div>
      <div class="meaning">${esc(z.meaning || '')}</div>
      <section class="card counter-guide-card">
        <div class="counter-guide-mark">☝</div>
        <div class="counter-guide-body">
          <span>Bugünkü sayaç rehberi</span>
          <strong>${fmt(state.counter.count)} tekrar çekildi · ${fmt(Math.max(0, Number(state.counter.target || 0) - Number(state.counter.count || 0)))} kaldı</strong>
          <p>${pct >= 100 ? 'Hedef tamamlandı. İstersen oturumu geçmişe kaydet veya toplu zikre katkı olarak aktar.' : 'Ortadaki büyük alana dokunarak zikri artır. Hedefi aşağıdaki hızlı butonlardan değiştirebilirsin.'}</p>
          <div class="counter-guide-actions">
            <button id="guideSaveSession">${Number(state.counter.count || 0) ? 'Geçmişe Kaydet' : 'Sayaçta Başla'}</button>
            <button id="guideTransferSession">Toplu Zikre Aktar</button>
            <button id="guideTargetFocus">Hedefi Değiştir</button>
          </div>
        </div>
      </section>
      <div id="counterTouchArea" class="tasbih-wrap" role="button" tabindex="0" aria-label="Zikir artır">
        <div class="tasbih-ring"></div>
        <div id="tapCounter" class="tap-btn" aria-hidden="true">☝</div>
        <div class="counter-core"><div><div class="counter-number">${fmt(state.counter.count)}</div><div class="counter-label">TEKRAR</div></div></div>
        <div class="tasbih-tail">⌄</div>
      </div>
      <div class="counter-session-row">
        <span>Oturum: <b id="sessionDuration">${elapsed}</b></span>
        <span>Bugün: <b>${fmt(dailyTotal())}</b></span>
      </div>
      <div class="counter-actions">
        <button class="round-action" id="undoBtn">↶<small>Geri Al</small></button>
        <button class="round-action" id="resetBtn">⟲<small>Sıfırla</small></button>
        <button class="round-action" id="soundBtn">${state.settings.sound ? '🔊' : '🔈'}<small>Ses</small></button>
        <button class="round-action" id="wakeBtn">${state.settings.keepAwake ? '☀' : '☾'}<small>Ekran</small></button>
        <button class="round-action" id="focusBtn">${state.settings.counterFocusMode ? '⇱' : '⛶'}<small>${state.settings.counterFocusMode ? 'Çık' : 'Odak'}</small></button>
      </div>
      <section class="card progress-card">
        <div class="progress-head"><span>Günlük Hedef</span><span><input id="targetInput" class="field" type="number" min="1" value="${state.counter.target}" ${(tes.active || vird.active) ? 'disabled' : ''} style="width:92px;padding:7px 9px;text-align:right"></span></div>
        <div class="counter-target-summary"><span>Kalan</span><b>${fmt(Math.max(0, Number(state.counter.target || 0) - Number(state.counter.count || 0)))}</b><small>Hedefe ulaşınca kayıt alabilirsin.</small></div>
        <div class="target-chips"><button data-target="33" ${(tes.active || vird.active) ? 'disabled' : ''}>33</button><button data-target="99" ${(tes.active || vird.active) ? 'disabled' : ''}>99</button><button data-target="100" ${(tes.active || vird.active) ? 'disabled' : ''}>100</button><button data-target="1000" ${(tes.active || vird.active) ? 'disabled' : ''}>1000</button><button data-target="5000" ${(tes.active || vird.active) ? 'disabled' : ''}>5000</button></div>
        <div class="progress"><span style="width:${pct}%"></span></div>
        <div class="progress-foot"><span>${fmt(state.counter.count)} / ${fmt(state.counter.target)}</span><span>%${pct}</span></div>
      </section>
      ${tesbihatCounterHtml}
      ${virdCounterHtml}
      <section class="card intent-card">
        <div class="section-row" style="margin-top:0"><h3>Oturum Niyeti</h3><button class="link-btn" id="clearIntent">Temizle</button></div>
        <textarea id="sessionIntent" class="field intent-field" maxlength="160" placeholder="İstersen bu oturum için kısa bir niyet/not yaz.">${esc(state.counter.intent || '')}</textarea>
        <div class="intent-chips"><button data-intent-template="Şifa niyetiyle">Şifa</button><button data-intent-template="Hayırlı iş için">Hayırlı iş</button><button data-intent-template="Vefat edenler için">Vefat edenler</button><button data-intent-template="Ailem için">Ailem</button></div>
      </section>
      <button class="cta" id="completeSession" style="margin-top:12px">Oturumu Geçmişe Kaydet</button>
      <button class="cta secondary" id="transferToCircle" style="margin-top:10px">Toplu Halkaya Aktar</button>`;

    const tap = $('#tapCounter');
    const touchArea = $('#counterTouchArea');
    const LONG_PRESS_MS = 2000;
    const MOVE_CANCEL_PX = 18;
    const resetTapGuard = () => {
      clearTimeout(state.touchGuard.longTimer);
      state.touchGuard = { pointerId: null, startAt: 0, moved: false, longPress: false, x: 0, y: 0, longTimer: null };
      tap?.classList.remove('pressed', 'hold-blocked');
      touchArea?.classList.remove('pressed', 'hold-blocked');
    };
    const startCounterTouch = e => {
      e.preventDefault();
      if (state.touchGuard.pointerId !== null) return;
      state.touchGuard.pointerId = e.pointerId;
      state.touchGuard.startAt = Date.now();
      state.touchGuard.x = e.clientX || 0;
      state.touchGuard.y = e.clientY || 0;
      state.touchGuard.moved = false;
      state.touchGuard.longPress = false;
      tap?.classList.add('pressed');
      touchArea?.classList.add('pressed');
      try { touchArea?.setPointerCapture(e.pointerId); } catch {}
      state.touchGuard.longTimer = setTimeout(() => {
        state.touchGuard.longPress = true;
        tap?.classList.add('hold-blocked');
        touchArea?.classList.add('hold-blocked');
      }, LONG_PRESS_MS);
    };
    const moveCounterTouch = e => {
      if (state.touchGuard.pointerId !== e.pointerId) return;
      const dx = Math.abs((e.clientX || 0) - state.touchGuard.x);
      const dy = Math.abs((e.clientY || 0) - state.touchGuard.y);
      if (dx > MOVE_CANCEL_PX || dy > MOVE_CANCEL_PX) state.touchGuard.moved = true;
    };
    const endCounterTouch = e => {
      e.preventDefault();
      if (state.touchGuard.pointerId !== e.pointerId) return;
      const elapsed = Date.now() - state.touchGuard.startAt;
      const shouldCount = !state.touchGuard.longPress && !state.touchGuard.moved && elapsed <= LONG_PRESS_MS;
      if (shouldCount) incrementCounter();
      resetTapGuard();
    };
    touchArea.addEventListener('pointerdown', startCounterTouch);
    touchArea.addEventListener('pointermove', moveCounterTouch);
    touchArea.addEventListener('pointerup', endCounterTouch);
    ['pointerleave','pointercancel','lostpointercapture'].forEach(evt => touchArea.addEventListener(evt, resetTapGuard));
    touchArea.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        incrementCounter();
      }
    });
    bindCounterZikirSelectButton();
    $('#undoBtn').onclick = () => runWithoutScrollJump(() => { state.counter.count = Math.max(0, Number(state.counter.count || 0) - 1); save('az_counter', state.counter); renderCounter(); });
    $('#resetBtn').onclick = () => runWithoutScrollJumpAsync(async () => { if(await appConfirm('Bu oturum sıfırlansın mı? Geçmişe kaydedilmemiş sayı silinir.')) { state.counter.count = 0; state.counter.startedAt = Date.now(); state.counter.completedToastAt = 0; save('az_counter', state.counter); renderCounter(); } });
    $('#soundBtn').onclick = () => runWithoutScrollJump(() => { state.settings.sound = !state.settings.sound; save('az_settings', state.settings); renderCounter(); });
    $('#wakeBtn').onclick = toggleWakeLock;
    $('#focusBtn').onclick = () => runWithoutScrollJump(() => { state.settings.counterFocusMode = !state.settings.counterFocusMode; save('az_settings', state.settings); toast(state.settings.counterFocusMode ? 'Odak modu açıldı.' : 'Odak modu kapandı.'); render(); });
    const applyTargetInput = e => { if (state.tesbihat?.active) return toast('Tesbihat hedefi bu akışta sabittir.'); if (state.vird?.active) return toast('Kişisel vird hedefi bu akışta sabittir.'); state.counter.target = Math.max(1, Number(e.target.value || 1)); state.counter.completedToastAt = 0; save('az_counter', state.counter); };
    $('#targetInput')?.addEventListener('input', applyTargetInput);
    $('#targetInput')?.addEventListener('change', e => runWithoutScrollJump(() => { applyTargetInput(e); renderCounter(); }));
    $$('[data-target]').forEach(btn => btn.onclick = () => runWithoutScrollJump(() => { if (state.tesbihat?.active) return toast('Tesbihat hedefi bu akışta sabittir.'); if (state.vird?.active) return toast('Kişisel vird hedefi bu akışta sabittir.'); state.counter.target = Number(btn.dataset.target); state.settings.defaultTarget = state.counter.target; save('az_settings', state.settings); save('az_counter', state.counter); renderCounter(); }));
    $('#startTesbihatCounter')?.addEventListener('click', showTesbihatStartModal);
    $('#nextTesbihatStep')?.addEventListener('click', nextTesbihatStep);
    $('#cancelTesbihat')?.addEventListener('click', cancelTesbihatFlow);
    $('#startVirdCounter')?.addEventListener('click', showVirdStartModal);
    $('#nextVirdStep')?.addEventListener('click', nextVirdStep);
    $('#cancelVird')?.addEventListener('click', cancelVirdFlow);
    $('#sessionIntent')?.addEventListener('input', e => { state.counter.intent = e.target.value.slice(0, 160); save('az_counter', state.counter); });
    $$('[data-intent-template]').forEach(btn => btn.onclick = () => runWithoutScrollJump(() => { state.counter.intent = btn.dataset.intentTemplate; save('az_counter', state.counter); renderCounter(); }));
    $('#clearIntent')?.addEventListener('click', () => runWithoutScrollJump(() => { state.counter.intent = ''; save('az_counter', state.counter); renderCounter(); }));
    $('#guideSaveSession')?.addEventListener('click', () => {
      if (!Number(state.counter.count || 0)) return toast('Ortadaki sayaç alanına dokunarak zikre başlayabilirsin.');
      $('#completeSession')?.click();
    });
    $('#guideTransferSession')?.addEventListener('click', () => {
      if (!Number(state.counter.count || 0)) return toast('Önce sayaçta zikir çek, sonra toplu halkaya aktar.');
      showCommunityTransferModal();
    });
    $('#guideTargetFocus')?.addEventListener('click', () => {
      document.getElementById('targetInput')?.focus();
      document.querySelector('.progress-card')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    $('#completeSession').onclick = () => {
      if (!Number(state.counter.count || 0)) return toast('Kaydetmek için önce sayaçta zikir çekmelisin.');
      const zikir = currentZikir();
      const savedSession = {
        zikirId: Number(zikir.id),
        title: zikir.title,
        count: Number(state.counter.count || 0),
        target: Number(state.counter.target || 0),
        intent: String(state.counter.intent || '').trim(),
        duration: duration(Date.now() - Number(state.counter.startedAt || Date.now()))
      };
      addHistory(zikir, savedSession.count, savedSession.intent);
      state.counter.count = 0;
      state.counter.intent = '';
      state.counter.startedAt = Date.now();
      state.counter.completedToastAt = 0;
      save('az_counter', state.counter);
      toast('Oturum geçmişe kaydedildi. Allah kabul etsin.');
      runWithoutScrollJump(() => renderCounter());
      if (state.settings.sessionSummaryAfterSave !== false) setTimeout(() => showSessionSavedSummary(savedSession), 80);
    };
    $('#transferToCircle').onclick = showCommunityTransferModal;
    clearInterval(renderCounter._dur); renderCounter._dur = setInterval(() => { const el = $('#sessionDuration'); if (el && state.route === 'counter') el.textContent = duration(Date.now() - Number(state.counter.startedAt || Date.now())); }, 1000);
  }

  function nextIncompletePlan() {
    return dailyPlans()
      .map(p => ({ ...p, done: zikirTotalToday(p.zikirId), pct: percent(zikirTotalToday(p.zikirId), p.target) }))
      .filter(p => p.pct < 100)
      .sort((a, b) => a.pct - b.pct)[0] || null;
  }

  function showSessionSavedSummary(session) {
    const pct = percent(session.count, session.target);
    const nextPlan = nextIncompletePlan();
    const sessions = state.data?.zikir_sessions || [];
    const firstSession = sessions[0] || null;
    openModal(`<h2 class="page-title">Oturum Özeti</h2>
      <p class="modal-note">Oturum geçmişe kaydedildi. Buradan aynı zikre devam edebilir, bugünkü plandaki sıradaki hedefe geçebilir veya katkını toplu halkaya ekleyebilirsin.</p>
      <section class="session-summary-card">
        <div><span>Zikir</span><b>${esc(session.title)}</b></div>
        <div><span>Tekrar</span><b>${fmt(session.count)}</b></div>
        <div><span>Süre</span><b>${esc(session.duration)}</b></div>
        <div><span>Hedef</span><b>%${pct}</b></div>
      </section>
      ${session.intent ? `<div class="session-summary-intent">${esc(session.intent)}</div>` : ''}
      <div class="session-summary-actions">
        <button class="cta" id="summarySameZikir">Aynı Zikirle Devam Et</button>
        <button class="soft-share-btn full" id="summaryNextPlan">${nextPlan ? 'Bugünkü Plandan Sıradaki Hedef' : 'İstatistiklere Git'}</button>
        <button class="soft-share-btn full" id="summaryTransfer" ${firstSession ? '' : 'disabled'}>${firstSession ? 'Toplu Halkaya Katkı Olarak Ekle' : 'Aktif Halka Yok'}</button>
        <button class="soft-share-btn full" id="summaryStats">Geçmiş ve İstatistikleri Aç</button>
      </div>`);
    $('#summarySameZikir')?.addEventListener('click', () => startQuickZikir(session.zikirId, session.target || state.settings.defaultTarget || 1000));
    $('#summaryNextPlan')?.addEventListener('click', () => {
      if (nextPlan) startQuickZikir(nextPlan.zikirId, nextPlan.target);
      else { closeModal(); route('stats'); }
    });
    $('#summaryTransfer')?.addEventListener('click', async () => {
      if (!firstSession) return toast('Aktif toplu zikir halkası yok.');
      closeModal();
      await contributeZikir(Math.max(1, Number(session.count || 1)), firstSession);
      route('zikir');
    });
    $('#summaryStats')?.addEventListener('click', () => { closeModal(); route('stats'); });
  }

  function incrementCounter() {
    state.counter.count = Number(state.counter.count || 0) + 1;
    if (state.settings.vibration && navigator.vibrate) navigator.vibrate(18);
    if (state.settings.sound) beep();
    const target = Number(state.counter.target || 0);
    if (target && state.counter.count >= target && state.counter.completedToastAt !== target) {
      state.counter.completedToastAt = target;
      toast(state.tesbihat?.active ? 'Aşama tamamlandı. Sonraki zikre geçebilirsin.' : (state.vird?.active ? 'Vird adımı tamamlandı. Sonraki adıma geçebilirsin.' : 'Hedef tamamlandı. Allah kabul etsin.'));
      if (state.settings.autoSaveOnTarget && !state.tesbihat?.active && !state.vird?.active) { addHistory(currentZikir(), state.counter.count, state.counter.intent); state.counter.count = 0; state.counter.intent = ''; state.counter.startedAt = Date.now(); state.counter.completedToastAt = 0; }
    }
    save('az_counter', state.counter);
    const num = $('.counter-number'); if (num) num.textContent = fmt(state.counter.count);
    const pct = percent(state.counter.count, state.counter.target);
    const bar = $('.progress span'); if (bar) bar.style.width = pct + '%';
    const foot = $$('.progress-foot span');
    if (foot[0]) foot[0].textContent = `${fmt(state.counter.count)} / ${fmt(state.counter.target)}`;
    if (foot[1]) foot[1].textContent = `%${pct}`;
  }
  function beep() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)(); const osc = ctx.createOscillator(); const gain = ctx.createGain();
      osc.frequency.value = 560; gain.gain.value = .035; osc.connect(gain); gain.connect(ctx.destination); osc.start(); setTimeout(() => { osc.stop(); ctx.close(); }, 45);
    } catch {}
  }

  async function shareText(title, text, url = location.origin + '/') {
    const payload = { title, text, url };
    try {
      if (navigator.share) { await navigator.share(payload); return; }
      const full = `${title}\n${text}\n${url}`;
      if (navigator.clipboard?.writeText) { await navigator.clipboard.writeText(full); toast('Paylaşım metni kopyalandı.'); return; }
      openModal(`<h2 class="page-title">Paylaş</h2><p class="modal-note">Aşağıdaki metni kopyalayıp paylaşabilirsin.</p><textarea class="field share-textarea" readonly>${esc(full)}</textarea>`);
    } catch { toast('Paylaşım iptal edildi.'); }
  }
  function shareApp() {
    shareText('Akıllı Zikir & Hatim', 'Offline zikir sayacı, toplu zikir halkası, dua halkası ve online hatim takibi.', location.origin + '/');
  }
  function shareZikirCircle(session) {
    if (!session) return shareApp();
    shareText('Toplu Zikir Halkası', `${session.title} halkasına katıl. Hedef: ${fmt(session.target_count)} tekrar, mevcut: ${fmt(session.current_count)}.`, location.origin + '/?screen=zikir');
  }
  function shareDuaCircle(circle) {
    shareText('Toplu Dua Halkası', `${circle?.title || 'Toplu Dua Halkası'} için duaya katıl.`, location.origin + '/?screen=dua');
  }
  function shareHatimCircle(hatim, completed, empty) {
    if (!hatim) return shareApp();
    shareText('Hatim Halkası', `${hatim.title} için online hatime katıl. Tamamlanan cüz: ${completed}/30, boş cüz: ${empty}.`, location.origin + '/?screen=hatim');
  }
  function shareTodaySummary(todayTotal, weekTotal, streak) {
    shareText('Bugünkü Zikir Özeti', `Bugünkü zikir toplamım: ${fmt(todayTotal)}. Haftalık toplam: ${fmt(weekTotal)}. Devam serim: ${fmt(streak)} gün.`, location.origin + '/');
  }

  function showCommunityTransferModal() {
    const sessions = state.data?.zikir_sessions || [];
    if (!sessions.length) return toast('Aktif toplu zikir halkası yok.');
    const currentAmount = Math.max(1, Number(state.counter.count || 0));
    openModal(`<h2 class="page-title">Toplu Halkaya Aktar</h2>
      <p class="modal-note">Sayaçtaki zikir sayını seçtiğin canlı halkaya ekleyebilirsin. Bu işlem yerel sayacı sıfırlamaz.</p>
      <div class="form-grid"><label>Aktarılacak sayı</label><input id="transferAmount" class="field" type="number" min="1" value="${currentAmount}"></div>
      <div class="community-transfer-list">${sessions.map(s => `<button class="transfer-session" data-transfer-session="${s.id}"><span>☾</span><p><strong>${esc(s.title)}</strong><small>${fmt(s.current_count)} / ${fmt(s.target_count)} tekrar · ${fmt(s.participant_count)} kişi</small></p><b>%${percent(s.current_count,s.target_count)}</b></button>`).join('')}</div>`);
    $$('[data-transfer-session]', modalContent).forEach(btn => btn.onclick = async () => {
      const amount = Math.max(1, Number($('#transferAmount')?.value || currentAmount));
      const session = sessions.find(x => Number(x.id) === Number(btn.dataset.transferSession));
      closeModal();
      await contributeZikir(amount, session);
    });
  }

  function renderZikir() {
    const sessions = state.data?.zikir_sessions || [];
    const s = state.currentCommunitySession || sessions[0];
    const recent = state.data?.zikir_recent || [];
    const mine = state.data?.my_stats || {};
    app.innerHTML = `
      <h1 class="page-title">Toplu Zikir Halkası</h1>
      <p class="subtle">İnternet varken canlı halkalara katıl; katkın sunucuya işlensin.</p>
      <section class="card zikir-guide-card">
        <div class="zikir-guide-mark">☾</div>
        <div class="zikir-guide-body">
          <span>Toplu zikir rehberi</span>
          <strong>${s ? 'Canlı halkaya katkı verebilirsin' : 'Şu anda aktif halka yok'}</strong>
          <p>${s ? 'Kişisel sayaçtaki sayını halkaya katabilir veya hazır +33 / +100 / +1000 katkılarını kullanabilirsin.' : 'Bir halka açıldığında burada görünecek. Sayacı offline kullanmaya devam edebilirsin.'}</p>
          <div class="zikir-guide-actions">
            <button id="zikirGuideJoin">${s ? 'Sayımı Halkaya Kat' : 'Sayaçta Devam Et'}</button>
            <button id="zikirGuideSessions">Aktif Halkalar</button>
            <button id="zikirGuideRefresh">Yenile</button>
          </div>
        </div>
      </section>
      ${s ? `<section class="card session-head">
        <span class="live-badge">CANLI</span>
        <h2>${esc(s.title)}</h2>
        <div class="arabic">${esc(s.arabic_text || '')}</div>
        <p class="meaning">${esc(s.subtitle || s.meaning || 'Beraber zikrediyor, bereketi paylaşıyoruz.')}</p>
        <div class="zikir-session-help"><span>Nasıl katılırım?</span><b>Önce zikrini çek, sonra “Sayaçtaki Sayımı Halkaya Kat” butonuna bas.</b></div>
        <div class="stat-row"><div class="stat-box"><b class="community-participant-count">${fmt(s.participant_count)}</b><small>Katılımcı</small></div><div class="stat-box"><b><span class="green-dot"></span></b><small>Canlı</small></div><div class="stat-box"><b class="community-percent">%${percent(s.current_count,s.target_count)}</b><small>Hedef</small></div><div class="stat-box"><b class="community-mine-today">${fmt(mine.zikir_today || 0)}</b><small>Benim Bugün</small></div></div>
        <div class="big-total"><span>Toplu Zikir Sayısı</span><b class="community-current-count">${fmt(s.current_count)}</b><span>TEKRAR</span></div>
        <div class="progress community-progress"><span style="width:${percent(s.current_count,s.target_count)}%"></span></div>
        <div class="progress-foot"><span class="community-target-count">Hedef: ${fmt(s.target_count)}</span><span class="community-progress-percent">%${percent(s.current_count,s.target_count)}</span></div>
        <div class="row-actions"><button class="mini-btn" data-contribute="33">+33</button><button class="mini-btn" data-contribute="100">+100</button><button class="mini-btn" data-contribute="1000">+1000</button></div>
        <div class="custom-contribution"><input id="customContribution" class="field" type="number" min="1" value="${Math.max(1, Number(state.counter.count || 33))}"><button class="mini-btn gold-soft" id="customContributeBtn">Özel Ekle</button></div>
        <div class="counter-share-note">Kişisel sayaçtaki mevcut sayı: <b>${fmt(state.counter.count || 0)}</b></div>
        <button class="cta" id="joinCircle" style="margin-top:12px">Sayaçtaki Sayımı Halkaya Kat</button>
        <button class="soft-share-btn full" id="shareZikirCircle">Halkayı Paylaş</button>
      </section>` : `<div class="empty-state">Aktif toplu zikir oturumu yok.</div>`}
      <div class="section-row"><h3>Aktif Oturumlar</h3><button class="link-btn" id="syncNow">Yenile</button></div>
      <div class="activity-list">${sessions.map(item => `<button class="activity" data-session="${item.id}"><span class="avatar">☾</span><p><strong>${esc(item.title)}</strong><small>${fmt(item.participant_count)} kişi ile devam ediyor</small></p><span class="live-badge">CANLI</span></button>`).join('')}</div>
      <section class="card recent-card"><div class="section-row" style="margin-top:0"><h3>Son Katılanlar</h3></div>${recent.slice(0,5).map(r=>`<div class="mini-activity"><span>☾</span><p><strong>${esc(r.nickname || 'Misafir')}</strong><small>${esc(r.session_title || '')} halkasına ${fmt(r.amount)} ekledi.</small></p></div>`).join('') || '<div class="empty-state">Henüz katılım yok.</div>'}</section>`;
    $$('[data-session]').forEach(btn => btn.onclick = () => runWithoutScrollJump(() => { state.currentCommunitySession = sessions.find(x => Number(x.id) === Number(btn.dataset.session)); renderZikir(); }));
    $$('[data-contribute]').forEach(btn => btn.onclick = () => contributeZikir(Number(btn.dataset.contribute), s));
    $('#customContributeBtn')?.addEventListener('click', () => contributeZikir(Math.max(1, Number($('#customContribution')?.value || 1)), s));
    $('#joinCircle')?.addEventListener('click', () => contributeZikir(Math.max(1, Number(state.counter.count || 33)), s));
    $('#zikirGuideJoin')?.addEventListener('click', () => {
      if (s) contributeZikir(Math.max(1, Number(state.counter.count || 33)), s);
      else route('counter');
    });
    $('#zikirGuideSessions')?.addEventListener('click', () => document.querySelector('.activity-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    $('#zikirGuideRefresh')?.addEventListener('click', syncBootstrap);
    $('#shareZikirCircle')?.addEventListener('click', () => shareZikirCircle(s));
    $('#syncNow')?.addEventListener('click', syncBootstrap);
  }
  function applyZikirContributionLocal(sessionId, amount, serverSession = null) {
    const id = Number(sessionId);
    const added = Math.max(1, Number(amount || 0));
    if (!state.data) state.data = {};
    const sessions = Array.isArray(state.data.zikir_sessions) ? state.data.zikir_sessions : [];
    let updatedSession = null;
    state.data.zikir_sessions = sessions.map(item => {
      if (Number(item.id) !== id) return item;
      const merged = Object.assign({}, item, serverSession || {});
      if (serverSession && serverSession.current_count !== undefined) merged.current_count = Number(serverSession.current_count || 0);
      else merged.current_count = Number(item.current_count || 0) + added;
      if (serverSession && serverSession.participant_count !== undefined) merged.participant_count = Number(serverSession.participant_count || 0);
      else merged.participant_count = Number(item.participant_count || 0);
      updatedSession = merged;
      return merged;
    });
    if (state.currentCommunitySession && Number(state.currentCommunitySession.id) === id) {
      state.currentCommunitySession = Object.assign({}, state.currentCommunitySession, updatedSession || serverSession || {});
    }
    if (state.data.my_stats) state.data.my_stats.zikir_today = Number(state.data.my_stats.zikir_today || 0) + added;
    else state.data.my_stats = { zikir_today: added };
    save('az_bootstrap', state.data);
    updateZikirContributionDom(id);
  }

  function updateZikirContributionDom(sessionId) {
    if (state.route !== 'zikir') return;
    const id = Number(sessionId);
    const sessions = Array.isArray(state.data?.zikir_sessions) ? state.data.zikir_sessions : [];
    const visible = state.currentCommunitySession || sessions[0];
    const session = sessions.find(item => Number(item.id) === id) || (Number(visible?.id) === id ? visible : null);
    if (!session || Number(visible?.id) !== id) return;
    const pct = percent(session.current_count, session.target_count);
    const currentEl = $('.community-current-count');
    if (currentEl) currentEl.textContent = fmt(session.current_count);
    const participantEl = $('.community-participant-count');
    if (participantEl) participantEl.textContent = fmt(session.participant_count);
    const percentEl = $('.community-percent');
    if (percentEl) percentEl.textContent = `%${pct}`;
    const progressEl = $('.community-progress span');
    if (progressEl) progressEl.style.width = pct + '%';
    const progressPercentEl = $('.community-progress-percent');
    if (progressPercentEl) progressPercentEl.textContent = `%${pct}`;
    const targetEl = $('.community-target-count');
    if (targetEl) targetEl.textContent = `Hedef: ${fmt(session.target_count)}`;
    const mineEl = $('.community-mine-today');
    if (mineEl) mineEl.textContent = fmt(state.data?.my_stats?.zikir_today || 0);
  }

  async function contributeZikir(amount, session) {
    if (!session) return;
    const payload = { session_id: session.id, amount, nickname: state.settings.nickname, client_id: state.settings.clientId };
    if (!navigator.onLine) { enqueue('zikir_contribute', payload); toast('Çevrimdışı: zikir katkın kuyruğa alındı. İnternet gelince gönderilecek.'); return; }
    try {
      const res = await api('zikir_contribute', payload);
      if (res.ok) {
        applyZikirContributionLocal(session.id, amount, res.session || null);
        toast(`${fmt(amount)} zikir halkaya eklendi. Allah kabul etsin.`);
      } else toast(res.message || 'Zikir katkısı eklenemedi. Lütfen tekrar dene.');
    } catch {
      enqueue('zikir_contribute', payload);
      toast('Bağlantı yok: katkın güvenle kuyruğa alındı.');
    }
  }

  function renderDua() {
    const circle = state.data?.dua_circle || {id:1, title:'Toplu Dua Halkası', subtitle:'Beraber duâ edelim, dualarımız kabul olsun.', participant_count:0};
    const allRequests = state.data?.dua_requests || [];
    const myRequests = state.data?.my_dua_requests || [];
    const baseRequests = state.duaFilter === 'mine' ? myRequests : allRequests;
    const categoryOptions = ['all', 'Şifa', 'Başarı', 'Rızık', 'Aile', 'Hayırlı İş', 'Genel'];
    const requests = state.duaCategoryFilter === 'all' ? baseRequests : baseRequests.filter(r => sameCategory(duaCategory(r), state.duaCategoryFilter));
    const mine = state.data?.my_stats || {};
    app.innerHTML = `
      <h1 class="page-title">Toplu Dua Halkası</h1>
      <section class="card hero-card dua-hero"><div class="starfield">☾</div><div class="masjid">🕌</div><div class="verse">${esc(circle.subtitle || 'Beraber duâ edelim, dualarımız kabul olsun.')}</div></section>
      <div class="stat-row"><div class="stat-box"><b>${fmt(circle.participant_count)}</b><small>Katılımcı</small></div><div class="stat-box"><b>${fmt(allRequests.length)}</b><small>Dua İsteği</small></div><div class="stat-box"><b>${fmt(mine.amin_count || 0)}</b><small>Benim Âmin</small></div></div>
      <section class="card dua-guide-card">
        <div class="dua-guide-mark">♡</div>
        <div class="dua-guide-body">
          <span>Dua halkası rehberi</span>
          <strong>${allRequests.length ? 'Dua isteği gönder veya bir duaya Âmin de' : 'İlk dua isteğini sen ekleyebilirsin'}</strong>
          <p>${myRequests.length ? 'Senin ' + myRequests.length + ' dua isteğin görünüyor. İstersen Benim Dualarım sekmesinden takip et.' : 'Dua isteğini kısa ve samimi yaz. Onay açıksa yayınlanmadan önce kontrol edilir.'}</p>
          <div class="dua-guide-actions">
            <button id="duaGuideAdd">Dua İsteği Gönder</button>
            <button id="duaGuideAmin">Âmin de</button>
            <button id="duaGuideMine">Benim Dualarım</button>
          </div>
        </div>
      </section>
      <div class="dua-share-row"><button class="soft-share-btn full" id="shareDuaCircle">Dua Halkasını Paylaş</button></div>
      <div class="dua-tabs"><button data-dua-filter="all" class="${state.duaFilter === 'all' ? 'active' : ''}">Tüm Dualar</button><button data-dua-filter="mine" class="${state.duaFilter === 'mine' ? 'active' : ''}">Benim Dualarım <small>${fmt(mine.my_dua_count || myRequests.length || 0)}</small></button></div>
      <div class="dua-category-head"><strong>Kategoriye göre süz</strong><small>${state.duaCategoryFilter === 'all' ? 'Tüm dua istekleri gösteriliyor' : esc(state.duaCategoryFilter) + ' kategorisi gösteriliyor'}</small></div>
      <div class="category-scroll">${categoryOptions.map(cat => `<button data-dua-category="${esc(cat)}" class="category-chip ${state.duaCategoryFilter === cat ? 'active' : ''}">${cat === 'all' ? 'Tümü' : esc(cat)}</button>`).join('')}</div>
      <div class="section-row"><h3>${state.duaFilter === 'mine' ? 'Benim Dua İsteklerim' : 'Dua İstekleri'}</h3><button class="link-btn" id="syncNow">Yenile</button></div>
      <div class="dua-card">${requests.map(r => `<div class="dua-item ${r.client_id === state.settings.clientId ? 'mine' : ''}"><h4>${esc(r.title)} ${r.client_id === state.settings.clientId ? '<span class="mine-label">Senin</span>' : ''} <span class="dua-category-badge">${esc(duaCategory(r))}</span></h4><p>${esc(r.body)}</p><div class="dua-meta"><span>${esc(r.nickname)} • ${new Date(r.created_at || Date.now()).toLocaleDateString('tr-TR')}</span><button class="amin-btn" data-amin="${r.id}">♡ ${fmt(r.amin_count)} Âmin</button></div></div>`).join('') || '<div class="empty-state">Bu bölümde henüz dua isteği yok.</div>'}</div>
      <div class="quote">“Bir mümin kardeşi için gıyaben dua ettiğinde melek: Âmin, bir misli de sana olsun, der.”</div>`;
    $('#addDuaBtn')?.addEventListener('click', showDuaModal);
    $('#duaGuideAdd')?.addEventListener('click', showDuaModal);
    $('#duaGuideAmin')?.addEventListener('click', ev => {
      ev?.preventDefault?.();
      ev?.stopPropagation?.();
      const first = requests[0] || allRequests[0];
      first ? aminDua(Number(first.id), $('#duaGuideAmin')) : toast('Âmin denilecek dua isteği yok.');
    });
    $('#duaGuideMine')?.addEventListener('click', () => runWithoutScrollJump(() => { state.duaFilter = 'mine'; renderDua(); }));
    $('#aminAllBtn')?.addEventListener('click', ev => {
      ev?.preventDefault?.();
      ev?.stopPropagation?.();
      allRequests[0] ? aminDua(Number(allRequests[0].id), $('#aminAllBtn')) : toast('Âmin denilecek dua isteği yok.');
    });
    $('#shareDuaCircle')?.addEventListener('click', () => shareDuaCircle(circle));
    $('#syncNow')?.addEventListener('click', syncBootstrap);
    $$('[data-dua-filter]').forEach(btn => btn.onclick = () => runWithoutScrollJump(() => { state.duaFilter = btn.dataset.duaFilter; renderDua(); }));
    $$('[data-dua-category]').forEach(btn => btn.onclick = () => runWithoutScrollJump(() => { state.duaCategoryFilter = btn.dataset.duaCategory; renderDua(); }));
    $$('[data-amin]').forEach(btn => {
      btn.onclick = ev => {
        ev?.preventDefault?.();
        ev?.stopPropagation?.();
        aminDua(Number(btn.dataset.amin), btn);
      };
    });
  }
  function showDuaModal() {
    openModal(`<div class="dua-modal-head"><div class="dua-modal-mark">♡</div><h2 class="page-title">Dua İsteği Gönder</h2><p class="modal-note">Dua isteğini kısa, samimi ve anlaşılır yaz. İstersen ismini gizleyebilirsin.</p></div><div class="form-grid dua-premium-form"><label>Dua Başlığı</label><input id="duaTitle" class="field" maxlength="120" placeholder="Örn: Şifa için dua"><label>Kategori</label><select id="duaCategory" class="field"><option>Şifa</option><option>Başarı</option><option>Rızık</option><option>Aile</option><option>Hayırlı İş</option><option selected>Genel</option></select><label>Dua Metni</label><textarea id="duaBody" class="field" maxlength="700" placeholder="Dua isteğinizi yazın"></textarea><div class="dua-writing-help"><span>Başlık en fazla 120 karakter</span><span>Metin en fazla 700 karakter</span></div><label class="check-row"><input id="duaAnon" type="checkbox"> İsmim görünmesin</label><button class="cta" id="saveDua">Dua Halkasına Ekle</button><p class="modal-note">Admin panelde dua onayı açıksa isteğin önce onaya düşer.</p></div>`);
    $('#saveDua').onclick = async () => {
      const payload = { circle_id: state.data?.dua_circle?.id || 1, nickname: $('#duaAnon').checked ? 'Gizli Kul' : state.settings.nickname, client_id: state.settings.clientId, category: $('#duaCategory').value || 'Genel', title: $('#duaTitle').value.trim(), body: $('#duaBody').value.trim() };
      if (!payload.title || !payload.body) return toast('Dua başlığı ve dua metni gerekli.');
      if (payload.title.length < 3 || payload.body.length < 8) return toast('Dua isteğini biraz daha açıklayıcı yaz.');
      if (!navigator.onLine) { enqueue('dua_add', payload); closeModal(); toast('Çevrimdışı: dua isteği kuyruğa alındı.'); return; }
      const res = await api('dua_add', payload); closeModal(); toast(res.ok ? 'Dua isteğin alındı. Allah kabul etsin.' : (res.message || 'Dua isteği eklenemedi.')); syncBootstrap(false);
    };
  }
  async function aminDua(id, sourceButton = null) {
    const payload = { request_id: id, nickname: state.settings.nickname, client_id: state.settings.clientId };
    if (sourceButton) sourceButton.disabled = true;

    if (!navigator.onLine) {
      enqueue('dua_amin', payload);
      if (sourceButton) sourceButton.disabled = false;
      toast('Çevrimdışı: Âmin kuyruğa alındı.');
      return;
    }

    try {
      const res = await api('dua_amin', payload);
      toast(res.ok ? (res.message || 'Âmin duan kaydedildi. Allah kabul etsin.') : (res.message || 'Âmin işlemi yapılamadı.'));

      if (res.ok) {
        const requestId = Number(id);
        const updatedRequest = res.request || null;
        const applyUpdatedRequest = list => {
          if (!Array.isArray(list)) return null;
          const idx = list.findIndex(item => Number(item.id) === requestId);
          if (idx < 0) return null;
          const current = list[idx] || {};
          list[idx] = updatedRequest ? { ...current, ...updatedRequest } : { ...current, amin_count: Number(current.amin_count || 0) + 1 };
          return list[idx];
        };

        const updatedMain = applyUpdatedRequest(state.data?.dua_requests);
        applyUpdatedRequest(state.data?.my_dua_requests);

        if (state.data?.dua_circle) {
          state.data.dua_circle.participant_count = Number(state.data.dua_circle.participant_count || 0) + 1;
        }
        if (state.data?.my_stats) {
          state.data.my_stats.amin_count = Number(state.data.my_stats.amin_count || 0) + 1;
        }
        if (state.data) save('az_bootstrap', state.data);

        const displayRequest = updatedRequest || updatedMain;
        if (displayRequest) {
          $$(`[data-amin="${requestId}"]`).forEach(btn => {
            btn.innerHTML = `♡ ${fmt(displayRequest.amin_count || 0)} Âmin`;
          });
        }
      }
    } finally {
      if (sourceButton) sourceButton.disabled = false;
    }
  }

  function renderHatim() {
    const hatim = state.data?.hatim;
    const juz = state.data?.hatim_juz || [];
    const completed = juz.filter(j => j.status === 'completed').length;
    const empty = juz.filter(j => j.status === 'empty').length;
    const mine = juz.filter(j => j.client_id === state.settings.clientId);
    const visibleJuz = state.hatimFilter === 'empty' ? juz.filter(j => j.status === 'empty') : state.hatimFilter === 'mine' ? mine : state.hatimFilter === 'completed' ? juz.filter(j => j.status === 'completed') : juz;
    app.innerHTML = `
      <h1 class="page-title">Hatim Halkası</h1>
      ${hatim ? `<section class="card hatim-hero-card"><div class="hatim-top"><div class="book-icon premium" aria-hidden="true"><div class="book-icon-ring"></div><div class="book-icon-glow"></div><img src="/app/assets/img/kuran-hatim-v1_2_8.svg?v=20260506124752" class="book-icon-svg book-icon-img user-kuran-svg" alt="" aria-hidden="true"/></div><div class="hatim-hero-copy"><h2 style="margin:0;color:var(--gold2);font-family:Georgia,serif">${esc(hatim.title)}</h2><p style="margin:5px 0 0;color:var(--muted);font-size:13px">${esc(hatim.description || '')}</p></div></div><div class="stat-row"><div class="stat-box"><b>${completed}</b><small>Tamamlanan Cüz</small></div><div class="stat-box"><b>${empty}</b><small>Kalan Cüz</small></div><div class="stat-box"><b>${fmt(hatim.participant_count)}</b><small>Katılımcı</small></div></div></section>
      <section class="card hatim-task-card">
        <div class="hatim-task-mark">▤</div>
        <div class="hatim-task-body">
          <span>Hatim görev önerisi</span>
          <strong>${empty > 0 ? 'Boş cüz var, hemen başlayabilirsin' : 'Bu hatimde boş cüz kalmamış'}</strong>
          <p>${mine.length ? 'Sende ' + mine.length + ' cüz görünüyor. Okuduğun cüzü tamamlandı işaretlemeyi unutma.' : 'Cüz aramakla uğraşma; sistem senin için ilk uygun boş cüzü seçebilir.'}</p>
          <div class="hatim-task-actions">
            <button id="quickHatimAssign">${empty > 0 ? 'Bana Boş Cüz Ver' : 'Boş Cüz Yok'}</button>
            <button id="showMineJuz">Benim Cüzlerim</button>
            <button id="showEmptyJuz">Boşları Göster</button>
          </div>
        </div>
      </section>
      <section class="card my-juz-card"><div class="section-row" style="margin-top:0"><h3>Benim Cüzlerim</h3><button class="link-btn" id="syncMine">Yenile</button></div>${mine.length ? `<div class="mine-juz-list">${mine.map(j=>`<button data-juz="${j.juz_number}" class="mine-chip ${j.status}">${j.juz_number}. Cüz <small>${j.status === 'completed' ? 'Tamamlandı' : 'Okunuyor'}</small></button>`).join('')}</div>` : '<div class="empty-state">Henüz aldığın cüz yok.</div>'}</section>
      <section class="card" style="margin-top:12px"><div class="section-row" style="margin-top:0"><h3>Cüz Dağılımı</h3><button class="link-btn" id="syncNow">Yenile</button></div><div class="legend"><span class="l-empty">Boş</span><span class="l-reserved">Dolu</span><span class="l-completed">Tamamlandı</span><span class="l-mine">Senin</span></div><div class="hatim-filter-tabs"><button data-hatim-filter="all" class="${state.hatimFilter === 'all' ? 'active' : ''}">Tümü</button><button data-hatim-filter="empty" class="${state.hatimFilter === 'empty' ? 'active' : ''}">Boş</button><button data-hatim-filter="mine" class="${state.hatimFilter === 'mine' ? 'active' : ''}">Benim</button><button data-hatim-filter="completed" class="${state.hatimFilter === 'completed' ? 'active' : ''}">Tamam</button></div><div class="juz-grid">${visibleJuz.map(j => `<button class="juz ${j.status} ${j.client_id === state.settings.clientId ? 'mine' : ''} ${Number(state.selectedHatimJuz)===Number(j.juz_number)?'selected':''}" data-juz="${j.juz_number}" title="${esc(j.nickname || '')}">${j.juz_number}</button>`).join('') || '<div class="empty-state juz-empty-state">Bu filtrede cüz yok.</div>'}</div></section>
      <div class="selected-juz-line">Seçili cüz: <b>${state.selectedHatimJuz ? state.selectedHatimJuz + '. Cüz' : 'Yok'}</b></div>
      <div class="selected-juz-info">${selectedJuzInfo(juz)}</div>
      <button class="cta" id="takeFirstEmptyJuz" style="margin-top:12px">Bana Boş Cüz Ver</button>
      <button class="soft-share-btn full" id="shareHatimCircle">Hatimi Paylaş</button>
      <button class="cta secondary" id="takeJuz" style="margin-top:10px">Seçili Cüzü Al</button><button class="cta secondary" id="completeJuz" style="margin-top:10px">Seçili Cüzü Tamamla</button><button class="ghost-danger" id="releaseJuz" style="margin-top:10px">Seçili Cüzü Bırak</button>` : `<div class="empty-state">Aktif hatim yok.</div>`}`;
    $$('[data-juz]').forEach(btn => btn.onclick = () => runWithoutScrollJump(() => {
      state.selectedHatimJuz = Number(btn.dataset.juz);
      $$('[data-juz]').forEach(item => item.classList.toggle('selected', Number(item.dataset.juz) === Number(state.selectedHatimJuz)));
      const selectedLine = $('.selected-juz-line b');
      if (selectedLine) selectedLine.textContent = state.selectedHatimJuz ? state.selectedHatimJuz + '. Cüz' : 'Yok';
      const selectedInfo = $('.selected-juz-info');
      if (selectedInfo) selectedInfo.innerHTML = selectedJuzInfo(juz);
      toast(`${state.selectedHatimJuz}. cüz seçildi.`);
    }));
    $$('[data-hatim-filter]').forEach(btn => btn.onclick = () => runWithoutScrollJump(() => { state.hatimFilter = btn.dataset.hatimFilter; renderHatim(); }));
    $('#syncNow')?.addEventListener('click', syncBootstrap);
    $('#syncMine')?.addEventListener('click', syncBootstrap);
    $('#takeFirstEmptyJuz')?.addEventListener('click', () => takeFirstEmptyJuz(hatim, juz));
    $('#quickHatimAssign')?.addEventListener('click', () => takeFirstEmptyJuz(hatim, juz));
    $('#showMineJuz')?.addEventListener('click', () => runWithoutScrollJump(() => { state.hatimFilter = 'mine'; renderHatim(); }));
    $('#showEmptyJuz')?.addEventListener('click', () => runWithoutScrollJump(() => { state.hatimFilter = 'empty'; renderHatim(); }));
    $('#shareHatimCircle')?.addEventListener('click', () => shareHatimCircle(hatim, completed, empty));
    $('#takeJuz')?.addEventListener('click', () => takeJuz(hatim));
    $('#completeJuz')?.addEventListener('click', () => completeJuz(hatim));
    $('#releaseJuz')?.addEventListener('click', () => releaseJuz(hatim));
  }

  function selectedJuzInfo(juz) {
    if (!state.selectedHatimJuz) return 'Cüz seçmek için numaraya dokun. Dilersen ilk boş cüzü otomatik alabilirsin.';
    const j = juz.find(x => Number(x.juz_number) === Number(state.selectedHatimJuz));
    if (!j) return 'Seçili cüz bulunamadı.';
    const mine = j.client_id === state.settings.clientId;
    const statusMap = { empty: 'Boşta', reserved: mine ? 'Sende / okunuyor' : 'Başka katılımcıda', completed: mine ? 'Sen tamamladın' : 'Tamamlandı' };
    return `${state.selectedHatimJuz}. Cüz durumu: <b>${statusMap[j.status] || j.status}</b>${j.nickname ? ` · ${esc(j.nickname)}` : ''}`;
  }
  async function takeFirstEmptyJuz(hatim, juz) {
    const first = (juz || []).find(j => j.status === 'empty');
    if (!first) return toast('Bu hatimde boş cüz kalmamış görünüyor. Benim/Tamam sekmelerinden durumu kontrol edebilirsin.');
    state.selectedHatimJuz = Number(first.juz_number);
    await takeJuz(hatim);
  }

  async function takeJuz(hatim) {
    if (!hatim) return;
    const n = state.selectedHatimJuz || Number(await appPrompt('Kaçıncı cüzü almak istiyorsun? (1-30)', '1'));
    if (!n) return;
    const payload = { hatim_id: hatim.id, juz_number: n, nickname: state.settings.nickname, client_id: state.settings.clientId };
    if (!navigator.onLine) { enqueue('hatim_take', payload); toast('Çevrimdışı: cüz alma kuyruğa alındı.'); return; }
    const res = await api('hatim_take', payload); toast(res.message || (res.ok ? 'Cüz alındı.' : 'Cüz alınamadı.')); syncBootstrap(false);
  }
  async function completeJuz(hatim) {
    if (!hatim) return;
    const n = state.selectedHatimJuz || Number(await appPrompt('Kaçıncı cüz tamamlandı? (1-30)', '1'));
    if (!n) return;
    const payload = { hatim_id: hatim.id, juz_number: n, client_id: state.settings.clientId };
    if (!navigator.onLine) { enqueue('hatim_complete', payload); toast('Çevrimdışı: tamamlama kuyruğa alındı.'); return; }
    const res = await api('hatim_complete', payload); toast(res.message || 'Cüz tamamlandı.'); syncBootstrap(false);
  }

  async function releaseJuz(hatim) {
    if (!hatim) return;
    const n = state.selectedHatimJuz || Number(await appPrompt('Kaçıncı cüzü bırakmak istiyorsun? (1-30)', '1'));
    if (!n) return;
    if (!(await appConfirm(`${n}. cüz üzerindeyse boşa çıkarılsın mı?`))) return;
    const payload = { hatim_id: hatim.id, juz_number: n, client_id: state.settings.clientId };
    if (!navigator.onLine) { enqueue('hatim_release', payload); toast('Çevrimdışı: cüz bırakma kuyruğa alındı.'); return; }
    const res = await api('hatim_release', payload); toast(res.message || (res.ok ? 'Cüz boşa çıkarıldı.' : 'Cüz bırakılamadı.')); syncBootstrap(false);
  }

  function streakDays(history) {
    const days = new Set(history.filter(x => Number(x.count || 0) > 0).map(x => x.date));
    if (Number(state.counter.count || 0) > 0) days.add(nowDate());
    let count = 0; const d = new Date();
    while (days.has(d.toISOString().slice(0,10))) { count++; d.setDate(d.getDate() - 1); }
    return count;
  }

  function historyRows(filter = 'all') {
    const now = Date.now();
    const today = nowDate();
    const weekAgo = now - 7 * 24 * 60 * 60 * 1000;
    const monthAgo = now - 30 * 24 * 60 * 60 * 1000;
    return allTimeHistory().map((x, index) => Object.assign({}, x, { _index: index })).filter(x => {
      if (filter === 'today') return x.date === today;
      if (filter === 'week') return Number(x.ts || 0) >= weekAgo;
      if (filter === 'month') return Number(x.ts || 0) >= monthAgo;
      return true;
    });
  }
  function historyFilterTitle(filter) {
    return ({ all:'Tüm Geçmiş', today:'Bugün', week:'7 Gün', month:'30 Gün' })[filter] || 'Tüm Geçmiş';
  }
  function showHistoryManager(filter = 'all') {
    const rows = historyRows(filter);
    const total = rows.reduce((s, x) => s + Number(x.count || 0), 0);
    const byTitle = {};
    rows.forEach(x => { byTitle[x.title] = (byTitle[x.title] || 0) + Number(x.count || 0); });
    const top = Object.entries(byTitle).sort((a,b)=>b[1]-a[1])[0];
    openModal(`<h2 class="page-title">Zikir Geçmişi</h2>
      <p class="modal-note">Yanlış kaydedilen oturumları buradan silebilirsin. Bu bölüm sadece telefondaki offline geçmişi etkiler.</p>
      <div class="history-filter-tabs">
        ${['all','today','week','month'].map(key => `<button data-history-filter="${key}" class="${filter === key ? 'active' : ''}">${historyFilterTitle(key)}</button>`).join('')}
      </div>
      <div class="history-summary-card">
        <div><span>${historyFilterTitle(filter)}</span><b>${fmt(total)}</b><small>Toplam tekrar</small></div>
        <div><span>Oturum</span><b>${fmt(rows.length)}</b><small>Kayıt</small></div>
        <div><span>En Çok</span><b>${top ? fmt(top[1]) : '0'}</b><small>${top ? esc(top[0]) : '-'}</small></div>
      </div>
      <div class="history-manager-list">
        ${rows.map(x => `<div class="history-manage-item"><span>☾</span><p><strong>${esc(x.title)}</strong><small>${new Date(x.ts || Date.now()).toLocaleString('tr-TR')}${x.intent ? ' · ' + esc(x.intent) : ''}</small></p><b>${fmt(x.count)}</b><button data-history-delete="${x._index}">Sil</button></div>`).join('') || '<div class="empty-state">Bu filtrede geçmiş kaydı yok.</div>'}
      </div>
      <div class="modal-actions-grid">
        <button class="soft-share-btn full" id="exportHistoryText">Bu Listeyi Kopyala</button>
        <button class="soft-share-btn full danger" id="clearFilteredHistory">Bu Filtreyi Temizle</button>
      </div>`);
    $$('[data-history-filter]', modalContent).forEach(btn => btn.onclick = () => showHistoryManager(btn.dataset.historyFilter));
    $$('[data-history-delete]', modalContent).forEach(btn => btn.onclick = () => deleteHistoryItem(Number(btn.dataset.historyDelete), filter));
    $('#exportHistoryText')?.addEventListener('click', () => copyHistoryText(filter));
    $('#clearFilteredHistory')?.addEventListener('click', () => clearFilteredHistory(filter));
  }
  async function deleteHistoryItem(index, filter = 'all') {
    const h = allTimeHistory();
    const item = h[index];
    if (!item) return;
    if (!(await appConfirm(`${item.title || 'Bu oturum'} geçmişten silinsin mi?`))) return;
    h.splice(index, 1);
    save('az_history', h);
    toast('Geçmiş kaydı silindi.');
    showHistoryManager(filter);
  }
  async function clearFilteredHistory(filter = 'all') {
    const rows = historyRows(filter);
    if (!rows.length) return toast('Silinecek geçmiş kaydı yok.');
    if (!(await appConfirm(`${historyFilterTitle(filter)} içindeki ${rows.length} geçmiş kaydı silinsin mi?`))) return;
    const remove = new Set(rows.map(x => x._index));
    const next = allTimeHistory().filter((_, idx) => !remove.has(idx));
    save('az_history', next);
    toast('Seçili geçmiş temizlendi.');
    showHistoryManager(filter);
  }
  async function copyHistoryText(filter = 'all') {
    const rows = historyRows(filter);
    if (!rows.length) return toast('Kopyalanacak geçmiş yok.');
    const total = rows.reduce((s, x) => s + Number(x.count || 0), 0);
    const lines = [`Akıllı Zikir & Hatim - ${historyFilterTitle(filter)}`, `Toplam: ${fmt(total)} tekrar`, `Oturum: ${fmt(rows.length)} kayıt`, ''];
    rows.slice(0, 80).forEach(x => lines.push(`${new Date(x.ts || Date.now()).toLocaleString('tr-TR')} - ${x.title}: ${fmt(x.count)}${x.intent ? ' (' + x.intent + ')' : ''}`));
    if (rows.length > 80) lines.push(`... ${rows.length - 80} kayıt daha`);
    const text = lines.join('\\n');
    try {
      if (navigator.clipboard?.writeText) { await navigator.clipboard.writeText(text); toast('Geçmiş özeti kopyalandı.'); return; }
    } catch {}
    openModal(`<h2 class="page-title">Geçmiş Özeti</h2><textarea class="field share-textarea" readonly>${esc(text)}</textarea>`);
  }
  function renderStats() {
    const h = allTimeHistory();
    const today = nowDate();
    const weekAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
    const monthAgo = Date.now() - 30 * 24 * 60 * 60 * 1000;
    const todayTotal = h.filter(x => x.date === today).reduce((s, x) => s + Number(x.count || 0), 0) + Number(state.counter.count || 0);
    const weekTotal = h.filter(x => Number(x.ts || 0) >= weekAgo).reduce((s, x) => s + Number(x.count || 0), 0) + Number(state.counter.count || 0);
    const monthTotal = h.filter(x => Number(x.ts || 0) >= monthAgo).reduce((s, x) => s + Number(x.count || 0), 0) + Number(state.counter.count || 0);
    const total = localTotalCount();
    const byTitle = {};
    h.forEach(x => { byTitle[x.title] = (byTitle[x.title] || 0) + Number(x.count || 0); });
    byTitle[currentZikir().title] = (byTitle[currentZikir().title] || 0) + Number(state.counter.count || 0);
    const top = Object.entries(byTitle).sort((a,b)=>b[1]-a[1]).slice(0,5);
    const liveSessions = state.data?.zikir_sessions || [];
    const hatimJuz = state.data?.hatim_juz || [];
    const completedJuz = hatimJuz.filter(j => j.status === 'completed').length;
    const mine = state.data?.my_stats || {};
    const plans = dailyPlans();
    const achievements = getAchievements();
    const unlockedAchievements = achievements.filter(x => x.unlocked).length;
    app.innerHTML = `
      <h1 class="page-title">İstatistikler</h1>
      <p class="subtle">Telefonundaki offline zikir geçmişi ve online halkaların özet görünümü.</p>
      <section class="card stats-guide-card">
        <div class="stats-guide-mark">▥</div>
        <div class="stats-guide-body">
          <span>Bugünkü durum</span>
          <strong>${fmt(todayTotal)} tekrar bugün · ${fmt(streakDays(h))} gün seri</strong>
          <p>${top[0] ? 'En çok yaptığın zikir: ' + esc(top[0][0]) + ' · ' + fmt(top[0][1]) + ' tekrar.' : 'Henüz istatistik oluşmadı. Sayaçtan zikir kaydettikçe bu alan dolacak.'}</p>
          <div class="stats-guide-actions">
            <button id="statsGoCounter">Sayaçta Devam Et</button>
            <button id="statsOpenHistory">Geçmişi Yönet</button>
            <button id="statsShareToday">Özeti Paylaş</button>
          </div>
        </div>
      </section>
      <div class="stats-jump-tabs"><button data-stats-jump="statsSummary">Özet</button><button data-stats-jump="statsBadges">Rozet</button><button data-stats-jump="statsOnline">Online</button><button data-stats-jump="statsHistory">Geçmiş</button></div>
      <section class="card stats-period-card">
        <div class="section-row" style="margin-top:0"><h3>Zaman Özeti</h3><button class="link-btn" id="statsRefresh">Yenile</button></div>
        <div class="stats-period-grid">
          <button data-history-period="today"><span>Bugün</span><b>${fmt(todayTotal)}</b><small>Detayları gör</small></button>
          <button data-history-period="week"><span>7 Gün</span><b>${fmt(weekTotal)}</b><small>Haftalık geçmiş</small></button>
          <button data-history-period="month"><span>30 Gün</span><b>${fmt(monthTotal)}</b><small>Aylık geçmiş</small></button>
          <button data-history-period="all"><span>Tüm Zaman</span><b>${fmt(total)}</b><small>Tüm geçmiş</small></button>
        </div>
      </section>
      <div id="statsSummary" class="stats-grid">
        <section class="stat-card"><span>Bugün</span><b>${fmt(todayTotal)}</b><small>Toplam zikir</small></section>
        <section class="stat-card"><span>7 Gün</span><b>${fmt(weekTotal)}</b><small>Haftalık toplam</small></section>
        <section class="stat-card"><span>30 Gün</span><b>${fmt(monthTotal)}</b><small>Aylık toplam</small></section>
        <section class="stat-card"><span>Seri Gün</span><b>${fmt(streakDays(h))}</b><small>Devam serisi</small></section>
        <section class="stat-card"><span>Rozet</span><b>${unlockedAchievements}/${achievements.length}</b><small>Açılan rozet</small></section>
      </div>
      <button class="soft-share-btn full" id="shareTodaySummary">Bugünkü Özeti Paylaş</button>
      <section id="statsBadges" class="card achievement-card"><div class="section-row" style="margin-top:0"><h3>Rozetlerim</h3><small>${unlockedAchievements}/${achievements.length} açıldı</small></div><div class="achievement-grid">${achievements.map(a => `<div class="achievement ${a.unlocked ? 'unlocked' : 'locked'}"><span>${esc(a.icon)}</span><p><strong>${esc(a.title)}</strong><small>${esc(a.desc)}</small>${a.target ? `<i><em style="width:${percent(a.progress, a.target)}%"></em></i>` : ''}</p>${a.unlocked ? '<b>✓</b>' : '<b>⌁</b>'}</div>`).join('')}</div></section>
      <section id="statsPlan" class="card" style="margin-top:12px"><div class="section-row" style="margin-top:0"><h3>Bugünkü Plan İlerlemesi</h3><button class="link-btn" data-route="settings">Planı Düzenle</button></div><div class="daily-plan-list compact">${plans.map(p => { const done = zikirTotalToday(p.zikirId); const pct = percent(done, p.target); return `<div class="daily-plan-item readonly"><span class="plan-orb">☾</span><p><strong>${esc(p.zikir.title)}</strong><small>${fmt(done)} / ${fmt(p.target)} tekrar</small><i><em style="width:${pct}%"></em></i></p><b>%${pct}</b></div>`; }).join('')}</div></section>
      <section class="card" style="margin-top:12px"><div class="section-row" style="margin-top:0"><h3>En Çok Yapılanlar</h3><button class="link-btn" data-route="counter">Sayaç</button></div>
        <div class="rank-list">${top.map((x,i)=>`<div class="rank-item"><span>${i+1}</span><strong>${esc(x[0])}</strong><b>${fmt(x[1])}</b></div>`).join('') || '<div class="empty-state">Henüz istatistik yok.</div>'}</div>
      </section>
      <section id="statsOnline" class="card" style="margin-top:12px"><div class="section-row" style="margin-top:0"><h3>Online Özet</h3><button class="link-btn" id="syncNow">Yenile</button></div>
        <div class="stats-grid small"><section class="stat-card"><span>Canlı Zikir</span><b>${fmt(liveSessions.length)}</b><small>Aktif halka</small></section><section class="stat-card"><span>Benim Katkım</span><b>${fmt(mine.zikir_today || 0)}</b><small>Bugün online</small></section><section class="stat-card"><span>Benim Dua</span><b>${fmt(mine.my_dua_count || 0)}</b><small>Gönderdiğim</small></section><section class="stat-card"><span>Benim Âmin</span><b>${fmt(mine.amin_count || 0)}</b><small>Katılımım</small></section><section class="stat-card"><span>Hatim</span><b>${completedJuz}/30</b><small>Tamamlanan cüz</small></section><section class="stat-card"><span>Benim Cüz</span><b>${fmt(mine.my_juz_count || 0)}</b><small>Üzerimde</small></section></div>
      </section>
      <section id="statsHistory" class="card stats-history-card" style="margin-top:12px"><div class="section-row" style="margin-top:0"><h3>Son Zikir Geçmişi</h3><button class="link-btn" id="historyManagerBtn">Tümünü Yönet</button></div><div class="stats-history-actions"><button data-history-period="today">Bugün</button><button data-history-period="week">7 Gün</button><button data-history-period="month">30 Gün</button><button data-history-period="all">Tümü</button></div>
        <div class="history-list">${h.slice(0,10).map(x=>`<div class="history-item"><span>☾</span><p><strong>${esc(x.title)}</strong><small>${new Date(x.ts || Date.now()).toLocaleString('tr-TR')}${x.intent ? ' · ' + esc(x.intent) : ''}</small></p><b>${fmt(x.count)}</b></div>`).join('') || '<div class="empty-state">Geçmiş kaydı yok.</div>'}</div>
      </section>`;
    $('#syncNow')?.addEventListener('click', syncBootstrap);
    $('#statsRefresh')?.addEventListener('click', syncBootstrap);
    $('#shareTodaySummary')?.addEventListener('click', () => shareTodaySummary(todayTotal, weekTotal, streakDays(h)));
    $('#statsShareToday')?.addEventListener('click', () => shareTodaySummary(todayTotal, weekTotal, streakDays(h)));
    $('#statsGoCounter')?.addEventListener('click', () => route('counter'));
    $('#statsOpenHistory')?.addEventListener('click', () => showHistoryManager('all'));
    $('#historyManagerBtn')?.addEventListener('click', () => showHistoryManager('all'));
    $$('[data-history-period]').forEach(btn => btn.addEventListener('click', () => showHistoryManager(btn.dataset.historyPeriod || 'all')));
    $$('[data-stats-jump]').forEach(btn => btn.addEventListener('click', () => document.getElementById(btn.dataset.statsJump)?.scrollIntoView({ behavior: 'smooth', block: 'start' })));
  }


  function appSetting(key, fallback = '') {
    const value = state.data?.settings?.[key];
    if (value === undefined || value === null || String(value).trim() === '') return fallback;
    return String(value);
  }

  function publisherName() {
    return appSetting('publisher_name', 'İlhan BELUK');
  }

  function developerName() {
    return appSetting('developer_name', publisherName());
  }

  function supportAmounts() {
    const raw = appSetting('support_amounts', '25,50,100,250');
    const parsed = raw.split(',').map(x => Number(String(x).trim())).filter(x => Number.isFinite(x) && x > 0);
    return parsed.length ? parsed.slice(0, 6) : [25, 50, 100, 250];
  }

  function openSupportUrl(url, fallbackMessage) {
    const clean = String(url || '').trim();
    if (!clean) return toast(fallbackMessage || 'Destek bağlantısı admin panelden eklenince aktif olacak.');
    try { window.open(clean, '_blank', 'noopener'); }
    catch { location.href = clean; }
  }

  function supportUrlForAmount(amount) {
    return appSetting(`support_${amount}_url`, '') || appSetting('support_general_url', '');
  }

  function legalUrl(key, fallback) {
    return appSetting(key, fallback);
  }

  function openAppLink(url, fallbackMessage = 'Bağlantı şu anda hazır değil.') {
    const clean = String(url || '').trim();
    if (!clean) return toast(fallbackMessage);
    try { window.open(clean, '_blank', 'noopener'); }
    catch { location.href = clean; }
  }

  function legalTitleFromUrl(url) {
    const clean = String(url || '').toLowerCase();
    if (clean.includes('privacy')) return 'Gizlilik Politikası';
    if (clean.includes('terms')) return 'Kullanım Şartları';
    if (clean.includes('data-deletion') || clean.includes('deletion')) return 'Veri Silme';
    if (clean.includes('support')) return 'Destek';
    return 'Yasal Bilgi';
  }

  window.fitLegalFrame = function(frame) {
    try {
      const doc = frame?.contentDocument || frame?.contentWindow?.document;
      if (!doc) return;
      let style = doc.getElementById('azLegalFrameFitStyle');
      if (!style) {
        style = doc.createElement('style');
        style.id = 'azLegalFrameFitStyle';
        style.textContent = `
          html,body{max-width:100%!important;overflow-x:hidden!important;box-sizing:border-box!important;background:#021c18!important;color:#f6e4b0!important;}
          body{margin:0!important;padding:22px!important;font-size:15px!important;line-height:1.58!important;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif!important;}
          *,*:before,*:after{box-sizing:border-box!important;max-width:100%!important;}
          img,svg,video,iframe,table,pre,code{max-width:100%!important;}
          table{width:100%!important;display:block!important;overflow-x:auto!important;}
          pre,code{white-space:pre-wrap!important;word-break:break-word!important;}
          h1{font-size:clamp(30px,8vw,44px)!important;line-height:1.08!important;color:#f4d991!important;font-family:Georgia,'Times New Roman',serif!important;text-shadow:0 4px 24px rgba(244,217,145,.16)!important;}
          h2{font-size:clamp(22px,6vw,32px)!important;line-height:1.15!important;color:#f4d991!important;font-family:Georgia,'Times New Roman',serif!important;}
          h3{color:#f4d991!important;font-family:Georgia,'Times New Roman',serif!important;}
          p,li,div{overflow-wrap:anywhere!important;}
          a{color:#f4d991!important;}
          section,article,.card,.box{border-color:rgba(242,211,137,.24)!important;background:rgba(8,54,47,.42)!important;border-radius:22px!important;}
        
        `;
        doc.head.appendChild(style);
      }
    } catch (e) {
      /* same-origin değilse sessiz geç */
    }
  };

  function openLegalInApp(url, fallbackMessage = 'Bu sayfa bağlantısı admin panelden düzenlenebilir.') {
    const clean = String(url || '').trim();
    if (!clean) return toast(fallbackMessage);
    const title = legalTitleFromUrl(clean);
    openModal(`<div class="legal-inapp-modal">
      <div class="legal-inapp-head">
        <div><span>Yasal Bilgi</span><strong>${esc(title)}</strong></div>
        <button class="legal-return-btn" id="legalReturnBtn">Uygulamaya Dön</button>
      </div>
      <div class="legal-inapp-info">Bu sayfa uygulama içinde açıldı. İşin bitince “Uygulamaya Dön” butonuyla kaldığın ekrana dönebilirsin.</div>
      <iframe class="legal-inapp-frame" src="${esc(clean)}" title="${esc(title)}" onload="window.fitLegalFrame && window.fitLegalFrame(this)"></iframe>
      <button class="legal-return-wide" id="legalReturnBtnBottom">Uygulamaya Dön</button>
    </div>`);
    $('#legalReturnBtn', modalContent)?.addEventListener('click', closeModal);
    $('#legalReturnBtnBottom', modalContent)?.addEventListener('click', closeModal);
  }

  function bindLegalButtons(root = document) {
    $$('[data-open-legal]', root).forEach(btn => {
      if (btn.dataset.legalBound === '1') return;
      btn.dataset.legalBound = '1';
      btn.addEventListener('click', () => openLegalInApp(btn.dataset.openLegal, 'Bu sayfa bağlantısı admin panelden düzenlenebilir.'));
    });
  }

  function showAboutModal() {
    const publisher = publisherName();
    const developer = developerName();
    openModal(`<div class="about-premium-modal">
      <div class="about-premium-hero">
        <div class="about-premium-logo">☾</div>
        <div><span>Reklamsız manevi takip uygulaması</span><h2>Akıllı Zikir & Hatim</h2><p>${esc(appSetting('about_text', 'Akıllı Zikir & Hatim; zikir, dua, hatim ve manevi takipleri kolaylaştırmak için hazırlanmış ücretsiz ve reklamsız bir uygulamadır. Ücret beklentisiyle değil, Allah rızası niyetiyle geliştirilmiştir.'))}</p></div>
      </div>
      <div class="about-premium-grid">
        <div><span>Yayımcı / Geliştirici</span><strong>${esc(publisher)}</strong></div>
        ${developer !== publisher ? `<div><span>Yazılım Geliştirici</span><strong>${esc(developer)}</strong></div>` : ''}
        <div><span>Uygulama</span><strong>Reklamsız</strong></div>
        <div><span>Offline Kullanım</span><strong>Destekli</strong></div>
      </div>
      <div class="about-premium-note">Uygulamadan fayda gören kullanıcıların hayır duası bizim için en güzel karşılıktır. Dualarınızda bizi, ailemizi, emeği geçenleri ve tüm müminleri hatırlamanız yeterlidir. Gizlilik, şartlar, destek ve veri silme bağlantıları Ayarlar → Gizlilik ve Destek bölümündedir.</div>
    </div>`);
  }

  function googlePlayBillingAvailable() {
    return typeof window !== 'undefined' && !!window.AkilliZikirBilling && typeof window.AkilliZikirBilling.purchase === 'function';
  }

  window.azhBillingResult = function(status, productId, message) {
    const ok = String(status || '') === 'success';
    toast(message || (ok ? 'Desteğin için teşekkür ederiz.' : 'Google Play destek işlemi tamamlanamadı.'));
  };

  function showSupportModal() {
    const publisher = publisherName();
    const billingReady = googlePlayBillingAvailable();
    const products = [
      { id: 'support_25', title: '25 TL' },
      { id: 'support_50', title: '50 TL' },
      { id: 'support_100', title: '100 TL' },
      { id: 'support_250', title: '250 TL' }
    ];
    openModal(`<div class="support-premium-modal play-safe">
      <div class="support-premium-hero">
        <div class="support-premium-heart">♡</div>
        <div>
          <span>${billingReady ? 'Google Play destek sistemi' : 'Ücretsiz ve reklamsız'}</span>
          <h2>${billingReady ? 'Gönüllü destek' : 'Allah rızası niyetiyle hazırlandı'}</h2>
          <p>${billingReady ? 'Dilersen Google Play ödeme altyapısı üzerinden uygulamanın gelişimine gönüllü katkıda bulunabilirsin.' : 'Akıllı Zikir & Hatim ücret beklentisiyle değil, Allah rızası niyetiyle hazırlanmıştır. Uygulamadan fayda görüyorsanız hayır duanız bizim için en güzel destektir.'}</p>
        </div>
      </div>
      <div class="support-trust-grid play-safe"><div>Ücretsiz</div><div>Reklamsız</div><div>${billingReady ? 'Google Play' : 'Hayır duası'}</div></div>
      ${billingReady ? `<div class="billing-support-grid">${products.map(p => `<button data-billing-product="${p.id}">${p.title}</button>`).join('')}</div><div class="play-safe-support-note soft"><strong>Bilgilendirme</strong><p>Destek vermek hiçbir özel avantaj, premium özellik, rozet veya ayrıcalık sağlamaz. Uygulamadaki tüm temel özellikler herkes için ücretsiz kalır.</p></div>` : `<div class="play-safe-support-note dua-note">
        <strong>Bizim için en güzel destek</strong>
        <p>Dualarınızda bizi, ailemizi, emeği geçenleri ve tüm müminleri hatırlamanız yeterlidir. Uygulamadaki temel özellikler herkes için ücretsiz kalır.</p>
      </div>
      <div class="play-safe-support-note soft">
        <strong>Google Play bilgilendirmesi</strong>
        <p>İlk yayında ödeme alınmaz. IBAN, havale/EFT veya harici ödeme yönlendirmesi gösterilmez. İleride destek paketleri açılırsa yalnızca Google Play ödeme altyapısı ile aktif edilir.</p>
      </div>`}
      <div class="credit-line premium"><span>Yayımcı / Geliştirici</span><strong>${esc(publisher)}</strong></div>
    </div>`);
    if (billingReady) {
      $$('[data-billing-product]', modalContent).forEach(btn => btn.addEventListener('click', () => {
        try {
          window.AkilliZikirBilling.purchase(String(btn.dataset.billingProduct || ''));
        } catch (e) {
          toast('Google Play destek sistemi şu anda başlatılamadı.');
        }
      }));
    }
  }


  function renderSettings() {
    const plans = dailyPlans();
    const zikirs = getZikirs();
    const customZikirs = load('az_custom_zikirs', []);
    app.innerHTML = `
      <h1 class="page-title">Ayarlar</h1>
      <p class="subtle">Uygulama telefonda offline çalışır. İnternet gelince bekleyen katkılar gönderilir.</p>
      <section class="card settings-guide-card">
        <div class="settings-guide-mark">⚙</div>
        <div class="settings-guide-body">
          <span>Ayar merkezi</span>
          <strong>${esc(state.settings.nickname || 'Misafir')} · hedef ${fmt(state.settings.defaultTarget || 1000)}</strong>
          <p>${navigator.onLine ? 'Online görünüyorsun. Bekleyen işlem varsa şimdi senkronize edebilirsin.' : 'Offline moddasın. İşlemlerin telefonda saklanır, internet gelince gönderilir.'}</p>
          <div class="settings-guide-actions">
            <button id="settingsGuideReminders">Bildirimler</button>
            <button id="settingsGuideData">Verilerim</button>
            <button id="settingsGuideSync">Senkron</button>
          </div>
        </div>
      </section>
      <section id="settingsGeneral" class="card form-grid"><label>Görünen ad / takma ad</label><input id="nickInput" class="field" value="${esc(state.settings.nickname)}" placeholder="Misafir"><label>Varsayılan hedef</label><input id="defaultTargetInput" class="field" type="number" min="1" value="${Number(state.settings.defaultTarget || 1000)}"><button class="cta" id="saveSettings">Kaydet</button></section>
      <section class="card settings-index-card"><strong>Ayar Kategorileri</strong><div><button data-settings-scroll="settingsSync">Senkron</button><button data-settings-scroll="settingsPlans">Plan</button><button data-settings-scroll="settingsReminders">Hatırlatıcı</button><button data-settings-scroll="settingsPersonal">Kişisel</button><button data-settings-scroll="settingsUsage">Kullanım</button><button data-settings-scroll="settingsData">Verilerim</button><button data-settings-scroll="settingsAbout">Hakkında</button></div></section>
      <section class="card smart-resume-settings-card"><div><strong>Kaldığın Yer kartı</strong><small>Ana sayfada aktif sayaç, plan, hatim, senkron ve not işlerini tek kartta toplar.</small></div><button class="toggle ${state.settings.smartResumeCard ? 'on' : ''}" id="toggleSmartResume"><span></span></button></section>
      <section class="card smart-resume-settings-card"><div><strong>Bugünkü Manevi Kontrol</strong><small>Ana sayfada zikir, plan, dua, hatim, not ve senkron durumunu kontrol listesi olarak gösterir.</small></div><button class="toggle ${state.settings.dailyChecklistCard ? 'on' : ''}" id="toggleDailyChecklist"><span></span></button></section>
      <section class="card smart-resume-settings-card"><div><strong>Oturum Sonrası Özet</strong><small>Zikir oturumu kaydedilince tekrar, süre, hedef ve devam önerilerini gösterir.</small></div><button class="toggle ${state.settings.sessionSummaryAfterSave !== false ? 'on' : ''}" id="toggleSessionSummary"><span></span></button></section>
      <section id="settingsSync" class="card sync-center-card"><div class="section-row" style="margin-top:0"><h3>Veri Durumu Merkezi</h3><span class="sync-pill ${navigator.onLine ? 'online' : 'offline'}">${navigator.onLine ? 'Online' : 'Offline'}</span></div><div class="sync-summary"><div><span>Bekleyen</span><b>${fmt(state.queue.length)}</b><small>işlem</small></div><div><span>Son Sync</span><b>${esc(syncTimeLabel())}</b><small>cihaz kaydı</small></div></div><div class="sync-actions"><button id="syncManual">Şimdi Senkronize Et</button><button id="queueDetails">Bekleyenleri Gör</button><button id="clearQueue" class="danger">Kuyruğu Temizle</button></div><p class="modal-note" style="text-align:left;margin-top:10px">Offline iken zikir/dua/hatim işlemleri telefonda kuyrukta tutulur. İnternet gelince otomatik gönderilir.</p></section>
      <section id="settingsData" class="card data-center-card">
        <div class="section-row" style="margin-top:0"><h3>Verilerim</h3><span class="sync-pill ${navigator.onLine ? 'online' : 'offline'}">${navigator.onLine ? 'Online' : 'Offline'}</span></div>
        <p class="modal-note" style="text-align:left;margin-bottom:10px">Cihazındaki zikir geçmişi, kişisel zikirler, favoriler ve bekleyen işlemler güvenle korunur. Teknik destek işlemleri aşağıdaki gelişmiş bölümde saklanır.</p>
        <div class="data-summary-grid clean">
          <div><span>Geçmiş</span><b>${fmt(allTimeHistory().length)}</b><small>kayıt</small></div>
          <div><span>Kişisel</span><b>${fmt(customZikirs.length)}</b><small>zikir</small></div>
          <div><span>Bekleyen</span><b>${fmt(state.queue.length)}</b><small>işlem</small></div>
          <div><span>Durum</span><b>${navigator.onLine ? 'Online' : 'Offline'}</b><small>cihaz</small></div>
        </div>
        <details class="advanced-support-details">
          <summary><span>Gelişmiş / Destek İşlemleri</span><small>Client ID, yedek alma/yükleme ve teknik veri merkezi</small></summary>
          <div class="advanced-support-body">
            <div class="client-id-box"><span>Client ID</span><b>${esc((state.settings.clientId || '').slice(0, 10))}…</b><small>Destek gerektiğinde kullanılabilir.</small></div>
            <div class="data-center-actions advanced">
              <button id="openDataCenter">Veri Merkezini Aç</button>
              <button id="copyClientId">Client ID Kopyala</button>
              <button id="exportLocal2">Yedek Al</button>
              <button id="importLocal2">Yedek Yükle</button>
            </div>
          </div>
        </details>
      </section>
      <section id="settingsPlans" class="card plan-settings-card"><div class="section-row" style="margin-top:0"><h3>Bugünkü Zikir Planı</h3><button class="link-btn" id="saveDailyPlans">Kaydet</button></div><p class="modal-note" style="text-align:left;margin-bottom:10px">Ana sayfada görünecek 3 kişisel hedefi belirle. Bu bölüm tamamen telefonda saklanır.</p>${plans.map((p,i)=>`<div class="plan-edit-row"><button type="button" class="field plan-zikir-btn" data-plan-index="${i}" data-zikir-id="${Number(p.zikirId)}"><span class="plan-zikir-label">${esc(p.zikir.title)}</span><b>⌄</b></button><input class="field plan-target" data-plan-index="${i}" type="number" min="1" value="${p.target}"></div>`).join('')}</section>
      <section id="settingsReminders" class="card reminder-settings-card"><div class="section-row" style="margin-top:0"><h3>Zikir Hatırlatıcıları</h3><button class="link-btn" id="saveReminders">Kaydet</button></div><p class="modal-note" style="text-align:left;margin-bottom:10px">Bildirimler telefonda takip edilir. İnternet olmasa da uygulama/PWA açıkken çalışır. APK aşamasında aynı yapı native bildirime bağlanacaktır.</p><div class="reminder-help-card"><span>Bildirim durumu</span><strong>${esc(notificationStatusLabel())}</strong><small>${state.settings.remindersEnabled ? 'Hatırlatıcılar açık. Saatleri aşağıdan düzenleyebilirsin.' : 'Hatırlatıcılar kapalı. Açmak için butona basıp izin ver.'}</small></div><div class="notification-status-line"><span>Bildirim durumu</span><b>${esc(notificationStatusLabel())}</b></div><button class="setting-row reminder-toggle-row" id="toggleReminders"><span><strong>Hatırlatıcılar</strong><small>${state.settings.remindersEnabled ? 'Açık' : 'Kapalı'} · Sıradaki: ${nextReminderInfo()?.time || '-'}</small></span><span class="switch ${state.settings.remindersEnabled?'on':''}"></span></button><div class="reminder-time-grid">${reminderTimes().map((t,i)=>`<input class="field reminder-time" data-reminder-index="${i}" type="time" value="${esc(t)}">`).join('')}</div><div class="notification-action-grid"><button class="soft-share-btn full" id="notificationPermission">Bildirim İzni Ver</button><button class="soft-share-btn full" id="testNotificationBtn">Test Bildirimi Gönder</button></div></section>
      <section id="settingsPersonal" class="card vird-settings-card"><div class="section-row" style="margin-top:0"><h3>Kişisel Vird Akışı</h3><button class="link-btn" id="openVirdSettings">Başlat</button></div><p class="modal-note" style="text-align:left;margin-bottom:10px">Sabah, akşam veya günlük vird akışlarını sırayla sayaçta takip et. Veriler telefonda/offline saklanır.</p><div class="vird-mini-list">${virdRoutines().map(r => `<button data-settings-vird="${esc(r.id)}"><strong>${esc(r.title)}</strong><small>${r.steps.length} adım · ${r.steps.map(st => esc(st.title)).join(' · ')}</small></button>`).join('')}</div></section>
      <section class="card custom-zikir-card"><div class="section-row" style="margin-top:0"><h3>Kişisel Zikirlerim</h3><button class="link-btn" id="settingsAddCustomZikir">Yeni Ekle</button></div><p class="modal-note" style="text-align:left;margin-bottom:10px">Kendi eklediğin zikirler telefonda/offline saklanır. Sayaçta ve günlük planda kullanılabilir.</p><div class="custom-zikir-manage-list">${customZikirs.map(item => `<div class="custom-zikir-row"><span class="zikir-badge">${esc((item.arabic_text || item.title || '☾').slice(0, 8))}</span><p><strong>${esc(item.title || 'Kişisel Zikir')}</strong><small>${fmt(item.default_target || state.settings.defaultTarget || 1000)} hedef · ${esc(item.meaning || 'Açıklama yok')}</small></p><div class="custom-zikir-actions"><button data-edit-custom="${item.id}">Düzenle</button><button data-delete-custom="${item.id}">Sil</button></div></div>`).join('') || '<div class="empty-state">Henüz kişisel zikir eklenmedi.</div>'}</div></section>
      <div id="settingsUsage" class="section-row"><h3>Kullanım</h3></div>
      <div class="settings-list">
        <button class="setting-row" id="toggleVibration"><span><strong>Titreşim</strong><small>Sayaçta hafif titreşim verir.</small></span><span class="switch ${state.settings.vibration?'on':''}"></span></button>
        <button class="setting-row" id="toggleSound"><span><strong>Ses</strong><small>Her dokunuşta kısa ses verir.</small></span><span class="switch ${state.settings.sound?'on':''}"></span></button>
        <button class="setting-row" id="toggleAutoSave"><span><strong>Hedefte otomatik kaydet</strong><small>Hedef dolunca oturumu geçmişe alır.</small></span><span class="switch ${state.settings.autoSaveOnTarget?'on':''}"></span></button>
        <button class="setting-row" id="sendQueue"><span><strong>Bekleyen Veri Durumu</strong><small>${state.queue.length} işlem bekliyor.</small></span><span>↻</span></button>
        <button class="setting-row" id="exportLocal"><span><strong>Offline Yedek Al</strong><small>Telefonundaki zikir geçmişini JSON olarak indirir.</small></span><span>⇩</span></button>
        <button class="setting-row" id="importLocal"><span><strong>Offline Yedek Yükle</strong><small>Daha önce aldığın JSON yedeğini telefona geri alır.</small></span><span>⇧</span></button>
        <button class="setting-row" id="openJournalSettings"><span><strong>Manevi Not Defteri</strong><small>Günlük niyet, dua ve tefekkür notlarını offline sakla.</small></span><span>✍</span></button>
        <button class="setting-row" id="openFavoriteManager"><span><strong>Favori Zikirleri Düzenle</strong><small>Ana sayfada görünecek zikirleri seç ve sırala.</small></span><span>★</span></button>
        <button class="setting-row" id="openZikirSearchSettings"><span><strong>Zikir Ara</strong><small>Hazır ve kişisel zikirleri tek ekranda ara.</small></span><span>⌕</span></button>
        <button class="setting-row" id="manageHistory"><span><strong>Zikir Geçmişini Yönet</strong><small>Yanlış oturumları sil, bugün/hafta/ay filtresiyle kontrol et.</small></span><span>▤</span></button>
        <button class="setting-row" id="clearLocal"><span><strong>Offline Verileri Temizle</strong><small>Sadece telefondaki sayaç/geçmiş temizlenir.</small></span><span>🧹</span></button>
      </div>
      <section id="settingsAbout" class="card about-support-card premium">
        <div class="about-support-head"><span>☾</span><div><strong>Yayımcı / Geliştirici</strong><small>${esc(publisherName())}</small></div></div>
        <p>Akıllı Zikir & Hatim ücretsiz ve reklamsızdır. Allah rızası niyetiyle hazırlanmıştır; fayda gören kullanıcıların hayır duası bizim için en güzel destektir.</p>
        <div class="about-support-actions"><button id="openAboutInfo">Hakkında</button><button id="openVoluntarySupport">Destek Bilgisi</button></div>
      </section>
      <section class="card install-card release-info-card premium"><strong>Gizlilik ve Destek</strong><p style="margin:6px 0 0;color:var(--muted);font-size:13px;line-height:1.45">Gizlilik politikası, kullanım şartları, destek ve veri silme sayfalarına buradan ulaşabilirsin.</p><div class="legal-action-grid"><button data-open-legal="${esc(legalUrl('privacy_policy_url', '/legal/privacy.php'))}">Gizlilik</button><button data-open-legal="${esc(legalUrl('terms_url', '/legal/terms.php'))}">Şartlar</button><button data-open-legal="${esc(legalUrl('support_url', '/legal/support.php'))}">Destek</button><button data-open-legal="${esc(legalUrl('data_deletion_url', '/legal/data-deletion.php'))}">Veri Silme</button></div></section>`;
    const saveGeneralSettings = (silent = false) => { state.settings.nickname = $('#nickInput')?.value.trim() || 'Misafir'; state.settings.defaultTarget = Math.max(1, Number($('#defaultTargetInput')?.value || 1000)); save('az_settings', state.settings); if (!silent) toast('Ayarlar kaydedildi.'); };
    const setSettingToggleVisual = (btn, on) => {
      if (!btn) return;
      btn.classList.toggle('on', !!on);
      const sw = btn.querySelector('.switch');
      if (sw) sw.classList.toggle('on', !!on);
      const tg = btn.classList.contains('toggle') ? btn : btn.querySelector('.toggle');
      if (tg) tg.classList.toggle('on', !!on);
    };
    const stopSettingToggleEvent = (e) => {
      e?.preventDefault?.();
      e?.stopPropagation?.();
    };
    $('#nickInput')?.addEventListener('input', () => saveGeneralSettings(true));
    $('#defaultTargetInput')?.addEventListener('input', () => saveGeneralSettings(true));
    $('#saveSettings').onclick = () => saveGeneralSettings(false);
    $('#toggleSmartResume')?.addEventListener('click', e => { stopSettingToggleEvent(e); runWithoutScrollJump(() => { state.settings.smartResumeCard = !state.settings.smartResumeCard; save('az_settings', state.settings); setSettingToggleVisual(e.currentTarget, state.settings.smartResumeCard); toast(state.settings.smartResumeCard ? 'Kaldığın Yer kartı açıldı.' : 'Kaldığın Yer kartı kapatıldı.'); }); });
    $('#toggleDailyChecklist')?.addEventListener('click', e => { stopSettingToggleEvent(e); runWithoutScrollJump(() => { state.settings.dailyChecklistCard = !state.settings.dailyChecklistCard; save('az_settings', state.settings); setSettingToggleVisual(e.currentTarget, state.settings.dailyChecklistCard); toast(state.settings.dailyChecklistCard ? 'Manevi kontrol kartı açıldı.' : 'Manevi kontrol kartı kapatıldı.'); }, 950); });
    $('#toggleSessionSummary')?.addEventListener('click', e => { stopSettingToggleEvent(e); runWithoutScrollJump(() => { state.settings.sessionSummaryAfterSave = state.settings.sessionSummaryAfterSave === false; save('az_settings', state.settings); setSettingToggleVisual(e.currentTarget, state.settings.sessionSummaryAfterSave !== false); toast(state.settings.sessionSummaryAfterSave !== false ? 'Oturum özeti açıldı.' : 'Oturum özeti kapatıldı.'); }, 950); });
    $$('[data-settings-scroll]').forEach(btn => btn.addEventListener('click', () => document.getElementById(btn.dataset.settingsScroll)?.scrollIntoView({ behavior: 'smooth', block: 'start' })));
    $('#settingsGuideReminders')?.addEventListener('click', () => document.getElementById('settingsReminders')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    $('#settingsGuideData')?.addEventListener('click', () => document.getElementById('settingsData')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    $('#settingsGuideSync')?.addEventListener('click', () => document.getElementById('settingsSync')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    $('#syncManual')?.addEventListener('click', async () => { await flushQueue(true); await syncBootstrap(true); });
    $('#queueDetails')?.addEventListener('click', showQueueModal);
    $('#clearQueue')?.addEventListener('click', async () => { if (!state.queue.length) return toast('Bekleyen işlem yok.'); if (await appConfirm('Bekleyen senkronizasyon kuyruğu temizlensin mi?')) { state.queue = []; save('az_queue', state.queue); toast('Bekleyen kuyruk temizlendi.'); runWithoutScrollJump(() => renderSettings()); } });
    $('#openDataCenter')?.addEventListener('click', showDataCenterModal);
    $('#copyClientId')?.addEventListener('click', copyClientId);
    $('#exportLocal2')?.addEventListener('click', exportLocalBackup);
    $('#importLocal2')?.addEventListener('click', importLocalBackup);
    const saveDailyPlanSettings = (silent = false) => saveDailyPlansFromDom(silent, false);
    $$('.plan-zikir-btn').forEach(btn => {
      btn.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') handleDailyPlanButtonOpen(ev); });
    });
    $$('.plan-target').forEach(input => input.addEventListener('input', () => saveDailyPlanSettings(true)));
    $('#saveDailyPlans')?.addEventListener('click', ev => { ev.preventDefault(); ev.stopPropagation(); closeDailyPlanInlinePicker(); saveDailyPlanSettings(false); });
    $('#toggleReminders')?.addEventListener('click', e => { stopSettingToggleEvent(e); runWithoutScrollJump(() => { state.settings.remindersEnabled = !state.settings.remindersEnabled; save('az_settings', state.settings); setSettingToggleVisual(e.currentTarget, state.settings.remindersEnabled); if (state.settings.remindersEnabled) askNotificationPermission(); startReminderWatcher(); toast(state.settings.remindersEnabled ? 'Hatırlatıcılar açıldı. Saatleri kontrol etmeyi unutma.' : 'Hatırlatıcılar kapatıldı.'); }); });
    $('#saveReminders')?.addEventListener('click', () => runWithoutScrollJump(() => { const times = $$('.reminder-time').map(i => i.value || '').filter(Boolean).slice(0,3); state.settings.reminderTimes = times.length ? times : DEFAULT_SETTINGS.reminderTimes; save('az_settings', state.settings); startReminderWatcher(); toast('Hatırlatıcılar kaydedildi.'); }, 950));
    $('#notificationPermission')?.addEventListener('click', askNotificationPermission);
    $('#testNotificationBtn')?.addEventListener('click', testNotification);
    $('#openVirdSettings')?.addEventListener('click', showVirdStartModal);
    $$('[data-settings-vird]').forEach(btn => btn.addEventListener('click', () => startVirdRoutine(btn.dataset.settingsVird)));
    $('#settingsAddCustomZikir')?.addEventListener('click', () => showCustomZikirModal(null, 'settings'));
    $$('[data-edit-custom]').forEach(btn => btn.addEventListener('click', () => showCustomZikirModal(Number(btn.dataset.editCustom), 'settings')));
    $$('[data-delete-custom]').forEach(btn => btn.addEventListener('click', () => deleteCustomZikir(Number(btn.dataset.deleteCustom))));
    $('#toggleVibration').onclick = e => { stopSettingToggleEvent(e); runWithoutScrollJump(() => { state.settings.vibration = !state.settings.vibration; save('az_settings', state.settings); setSettingToggleVisual(e.currentTarget, state.settings.vibration); toast(state.settings.vibration ? 'Titreşim açıldı.' : 'Titreşim kapatıldı.'); }); };
    $('#toggleSound').onclick = e => { stopSettingToggleEvent(e); runWithoutScrollJump(() => { state.settings.sound = !state.settings.sound; save('az_settings', state.settings); setSettingToggleVisual(e.currentTarget, state.settings.sound); toast(state.settings.sound ? 'Ses açıldı.' : 'Ses kapatıldı.'); }); };
    $('#toggleAutoSave').onclick = e => { stopSettingToggleEvent(e); runWithoutScrollJump(() => { state.settings.autoSaveOnTarget = !state.settings.autoSaveOnTarget; save('az_settings', state.settings); setSettingToggleVisual(e.currentTarget, state.settings.autoSaveOnTarget); toast(state.settings.autoSaveOnTarget ? 'Hedefte otomatik kaydet açıldı.' : 'Hedefte otomatik kaydet kapatıldı.'); }); };
    $('#sendQueue').onclick = () => flushQueue(true);
    $('#exportLocal').onclick = exportLocalBackup;
    $('#importLocal').onclick = importLocalBackup;
    $('#openJournalSettings')?.addEventListener('click', showJournalModal);
    $('#openFavoriteManager')?.addEventListener('click', showFavoriteManager);
    $('#openZikirSearchSettings')?.addEventListener('click', showZikirSearchModal);
    $('#openAboutInfo')?.addEventListener('click', showAboutModal);
    $('#openVoluntarySupport')?.addEventListener('click', showSupportModal);
    bindLegalButtons(app);
    $('#manageHistory')?.addEventListener('click', () => showHistoryManager('all'));
    $('#clearLocal').onclick = () => runWithoutScrollJumpAsync(async () => { if(await appConfirm('Telefondaki sayaç ve geçmiş temizlensin mi?')) { localStorage.removeItem('az_history'); state.counter.count = 0; save('az_counter', state.counter); toast('Offline veriler temizlendi.'); } });
  }
  function showJournalModal() {
    const list = journalEntries();
    const today = nowDate();
    const current = list.find(x => x.date === today) || { date: today, title: '', text: '' };
    const recentRows = list.slice(0, 12).map(item => `<div class="journal-row"><span>${esc((item.date || '').slice(5).replace('-', '.'))}</span><p><strong>${esc(item.title || 'Manevi Not')}</strong><small>${esc((item.text || '').slice(0, 96))}${(item.text || '').length > 96 ? '…' : ''}</small></p><button data-delete-journal="${esc(item.date)}">Sil</button></div>`).join('') || '<div class="empty-state">Henüz manevi not eklenmedi.</div>';
    openModal(`<h2 class="page-title">Manevi Not Defteri</h2><p class="modal-note">Bu alan tamamen telefonda/offline saklanır. Günlük niyet, dua, tefekkür veya hatırlatma notu yazabilirsin.</p><div class="form-grid journal-editor"><label>Bugünün başlığı</label><input id="journalTitle" class="field" maxlength="80" placeholder="Örn: Bugünkü niyetim" value="${esc(current.title || '')}"><label>Bugünün notu</label><textarea id="journalText" class="field journal-textarea" maxlength="1200" placeholder="Kısa dua, niyet veya manevi not...">${esc(current.text || '')}</textarea><button class="cta" id="saveJournalToday">Bugünün Notunu Kaydet</button></div><div class="section-row"><h3>Son Notlar</h3><button class="link-btn" id="copyJournalSummary">Özet Kopyala</button></div><div class="journal-list">${recentRows}</div>`);
    $('#saveJournalToday')?.addEventListener('click', saveJournalToday);
    $('#copyJournalSummary')?.addEventListener('click', copyJournalSummary);
    $$('[data-delete-journal]').forEach(btn => btn.addEventListener('click', () => deleteJournalEntry(btn.dataset.deleteJournal)));
  }

  function saveJournalToday() {
    const today = nowDate();
    const title = ($('#journalTitle')?.value || '').trim() || 'Manevi Not';
    const text = ($('#journalText')?.value || '').trim();
    if (!text) return toast('Not alanı boş olamaz.');
    const list = journalEntries().filter(x => x.date !== today);
    list.unshift({ date: today, title, text, ts: Date.now() });
    saveJournalEntries(list);
    toast('Manevi not kaydedildi.');
    closeModal();
    if (state.route === 'home') runWithoutScrollJump(() => renderHome());
  }

  async function deleteJournalEntry(date) {
    if (!date) return;
    if (!(await appConfirm('Bu manevi not silinsin mi?'))) return;
    saveJournalEntries(journalEntries().filter(x => x.date !== date));
    toast('Not silindi.');
    showJournalModal();
    if (state.route === 'home') runWithoutScrollJump(() => renderHome());
  }

  function copyJournalSummary() {
    const list = journalEntries().slice(0, 7);
    if (!list.length) return toast('Kopyalanacak not yok.');
    const text = ['Akıllı Zikir & Hatim - Manevi Not Özeti', ...list.map(x => `${x.date} - ${x.title || 'Manevi Not'}: ${x.text || ''}`)].join('\n');
    navigator.clipboard?.writeText(text).then(() => toast('Not özeti kopyalandı.')).catch(() => toast('Kopyalama desteklenmedi.'));
  }

  function localDataSummary() {
    const history = allTimeHistory();
    const custom = load('az_custom_zikirs', []);
    const journal = journalEntries();
    const favs = favoriteZikirs();
    const routines = load('az_vird_routines', []);
    return {
      history,
      custom,
      journal,
      favs,
      routines,
      totalCount: history.reduce((s, x) => s + Number(x.count || x.amount || 0), 0)
    };
  }

  function copyClientId() {
    const id = state.settings.clientId || '';
    if (!id) return toast('Client ID bulunamadı.');
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(id).then(() => toast('Client ID kopyalandı.')).catch(() => toast(id));
    } else {
      toast(id);
    }
  }

  function showDataCenterModal() {
    const s = localDataSummary();
    openModal(`
      <div class="data-center-modal">
        <h2 class="page-title">Verilerim ve Veri Durumu</h2>
        <p class="modal-note">Bu bölüm sadece cihazındaki yerel verileri ve bekleyen online işlemleri yönetir. Sunucudaki onaylı kayıtlar admin tarafında tutulur.</p>
        <div class="data-summary-grid modal-grid">
          <div><span>Client ID</span><b>${esc((state.settings.clientId || '').slice(0, 10))}…</b></div>
          <div><span>Toplam Zikir</span><b>${fmt(s.totalCount)}</b></div>
          <div><span>Geçmiş</span><b>${fmt(s.history.length)}</b></div>
          <div><span>Bekleyen</span><b>${fmt(state.queue.length)}</b></div>
          <div><span>Kişisel Zikir</span><b>${fmt(s.custom.length)}</b></div>
          <div><span>Not</span><b>${fmt(s.journal.length)}</b></div>
        </div>
        <div class="data-action-list">
          <button id="dcExport"><strong>Yedek Al</strong><small>Ayarlar, sayaç, geçmiş, kişisel zikirler ve notları JSON olarak indir.</small></button>
          <button id="dcImport"><strong>Yedek Yükle</strong><small>Daha önce aldığın JSON yedeğini bu cihaza aktar.</small></button>
          <button id="dcQueue"><strong>Bekleyen İşlemleri Gör</strong><small>Dua, âmin, hatim ve toplu zikir kuyruğunu kontrol et.</small></button>
          <button id="dcCopy"><strong>Client ID Kopyala</strong><small>Destek veya veri silme talebi için gerekebilir.</small></button>
          <button id="dcClearHistory" class="danger"><strong>Zikir Geçmişini Temizle</strong><small>Sadece telefondaki zikir geçmişini siler.</small></button>
          <button id="dcClearAll" class="danger"><strong>Tüm Yerel Verileri Temizle</strong><small>Ayarlar hariç sayaç, geçmiş, notlar ve kişisel kayıtları temizler.</small></button>
        </div>
      </div>
    `);
    $('#dcExport')?.addEventListener('click', exportLocalBackup);
    $('#dcImport')?.addEventListener('click', importLocalBackup);
    $('#dcQueue')?.addEventListener('click', showQueueModal);
    $('#dcCopy')?.addEventListener('click', copyClientId);
    $('#dcClearHistory')?.addEventListener('click', async () => {
      if (!(await appConfirm('Telefondaki zikir geçmişi temizlensin mi?'))) return;
      localStorage.removeItem('az_history');
      toast('Zikir geçmişi temizlendi.');
      closeModal();
      if (state.route === 'settings') runWithoutScrollJump(() => renderSettings());
    });
    $('#dcClearAll')?.addEventListener('click', async () => {
      if (!(await appConfirm('Sayaç, geçmiş, not, kişisel zikir ve bekleyen kuyruk bu cihazdan temizlensin mi?'))) return;
      ['az_history','az_queue','az_custom_zikirs','az_journal','az_tesbihat','az_vird','az_vird_routines'].forEach(k => localStorage.removeItem(k));
      state.queue = [];
      state.tesbihat = { active: false, mode: 'classic99', step: 0, startedAt: 0, completedAt: 0, completedSessions: 0 };
      state.vird = { active: false, routineId: 'morning', step: 0, startedAt: 0, completedAt: 0, completedRoutines: 0 };
      state.counter.count = 0;
      save('az_counter', state.counter);
      toast('Yerel veriler temizlendi.');
      closeModal();
      if (state.route === 'settings') runWithoutScrollJump(() => renderSettings());
    });
  }

  function showQueueModal() {
    const rows = state.queue.map((item, idx) => {
      const date = item.ts ? new Date(item.ts).toLocaleString('tr-TR') : '-';
      const amount = item.payload?.amount ? ` · ${fmt(item.payload.amount)} tekrar` : '';
      const juz = item.payload?.juz_number ? ` · ${item.payload.juz_number}. Cüz` : '';
      const title = item.payload?.title ? ` · ${esc(item.payload.title)}` : '';
      return `<div class="queue-row"><span>${idx + 1}</span><p><strong>${esc(queueLabel(item.action))}</strong><small>${esc(date)}${amount}${juz}${title}</small></p></div>`;
    }).join('') || '<div class="empty-state">Bekleyen senkronizasyon işlemi yok.</div>';
    openModal(`<h2 class="page-title">Bekleyen Veri Durumu</h2><p class="modal-note">İnternet geldiğinde bu işlemler sırayla sunucuya gönderilir.</p><div class="queue-list">${rows}</div><div class="modal-actions-grid"><button class="soft-share-btn full" id="modalFlushQueue">Şimdi Gönder</button><button class="soft-share-btn danger" id="modalClearQueue">Temizle</button></div>`);
    $('#modalFlushQueue')?.addEventListener('click', () => { closeModal(); flushQueue(true); });
    $('#modalClearQueue')?.addEventListener('click', async () => { if (!state.queue.length) return toast('Bekleyen işlem yok.'); if (await appConfirm('Bekleyen kuyruk temizlensin mi?')) { state.queue = []; save('az_queue', state.queue); closeModal(); toast('Kuyruk temizlendi.'); if (state.route === 'settings') runWithoutScrollJump(() => renderSettings()); } });
  }

  function exportLocalBackup() {
    const payload = {
      exported_at: new Date().toISOString(),
      app: 'Akıllı Zikir & Hatim',
      version: '1.2.41',
      settings: state.settings,
      counter: state.counter,
      history: allTimeHistory(),
      custom_zikirs: load('az_custom_zikirs', []),
      journal: journalEntries(),
      tesbihat: state.tesbihat,
      vird: state.vird,
      vird_routines: load('az_vird_routines', [])
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `akilli-zikir-yedek-${nowDate()}.json`;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 400);
    toast('Offline yedek hazırlandı.');
  }
  function importLocalBackup() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'application/json';
    input.onchange = () => {
      const file = input.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = async () => {
        try {
          const data = JSON.parse(reader.result || '{}');
          if (!(await appConfirm('Bu işlem telefondaki offline sayaç/geçmiş verisini yedekten güncelleyecek. Devam edilsin mi?'))) return;
          if (data.settings) { state.settings = Object.assign({}, state.settings, data.settings, { clientId: state.settings.clientId }); save('az_settings', state.settings); }
          if (data.counter) { state.counter = Object.assign({}, state.counter, data.counter); save('az_counter', state.counter); }
          if (Array.isArray(data.history)) save('az_history', data.history.slice(0, 1000));
          if (Array.isArray(data.custom_zikirs)) save('az_custom_zikirs', data.custom_zikirs);
          if (Array.isArray(data.journal)) saveJournalEntries(data.journal);
          if (data.tesbihat) { state.tesbihat = Object.assign({}, state.tesbihat, data.tesbihat); saveTesbihat(); }
          if (data.vird) { state.vird = Object.assign({}, state.vird, data.vird); saveVird(); }
          if (Array.isArray(data.vird_routines)) save('az_vird_routines', data.vird_routines);
          toast('Offline yedek yüklendi.');
          runWithoutScrollJump(() => renderSettings());
        } catch { toast('Yedek dosyası okunamadı.'); }
      };
      reader.readAsText(file);
    };
    input.click();
  }

  function showFavoriteManager() {
    const all = getZikirs();
    const selected = new Set(favoriteZikirs().map(z => Number(z.id)));
    openModal(`<h2 class="page-title">Favori Zikirleri Düzenle</h2>
      <p class="modal-note">Ana sayfada görünecek zikirleri seç. En fazla 8 favori gösterilir; seçimler telefonda/offline saklanır.</p>
      <div class="favorite-manager-list">${all.map(z => `<label class="favorite-row"><input type="checkbox" value="${z.id}" ${selected.has(Number(z.id)) ? 'checked' : ''}><span class="zikir-badge">${esc((z.arabic_text || z.title || '☾').slice(0, 8))}</span><p><strong>${esc(z.title)}</strong><small>${esc(z.meaning || 'Açıklama yok')} · hedef ${fmt(z.default_target || state.settings.defaultTarget || 1000)}</small></p><b>★</b></label>`).join('')}</div>
      <div class="modal-actions-grid"><button class="soft-share-btn full" id="saveFavoriteZikirs">Favorileri Kaydet</button><button class="soft-share-btn" id="selectDefaultFavorites">Varsayılana Dön</button></div>`);
    $('#saveFavoriteZikirs')?.addEventListener('click', () => {
      const ids = $$('.favorite-row input:checked', modalContent).map(input => Number(input.value));
      if (!ids.length) return toast('En az bir favori zikir seçmelisin.');
      saveFavoriteZikirIds(ids);
      closeModal();
      toast('Favori zikirler kaydedildi.');
      if (state.route === 'home') runWithoutScrollJump(() => renderHome());
      if (state.route === 'settings') runWithoutScrollJump(() => renderSettings());
    });
    $('#selectDefaultFavorites')?.addEventListener('click', () => {
      saveFavoriteZikirIds(all.filter(z => Number(z.is_favorite) === 1).slice(0, 6).map(z => Number(z.id)));
      closeModal();
      toast('Varsayılan favoriler yüklendi.');
      if (state.route === 'home') runWithoutScrollJump(() => renderHome());
      if (state.route === 'settings') runWithoutScrollJump(() => renderSettings());
    });
  }

  function showCustomZikirModal(editId = null, returnRoute = state.route) {
    const list = load('az_custom_zikirs', []);
    const editItem = editId ? list.find(x => Number(x.id) === Number(editId)) : null;
    openModal(`<h2 class="page-title">${editItem ? 'Zikri Düzenle' : 'Yeni Zikir'}</h2><div class="form-grid"><label>Zikir adı</label><input id="czTitle" class="field" placeholder="Zikir adı" value="${esc(editItem?.title || '')}"><label>Arapça metin / kısa ifade</label><input id="czArabic" class="field" placeholder="Arapça metin / kısa ifade" value="${esc(editItem?.arabic_text || '')}"><label>Açıklama</label><textarea id="czMeaning" class="field" placeholder="Açıklama">${esc(editItem?.meaning || '')}</textarea><label>Varsayılan hedef</label><input id="czTarget" class="field" type="number" value="${Number(editItem?.default_target || state.settings.defaultTarget || 1000)}" min="1"><button class="cta" id="saveCustomZikir">${editItem ? 'Güncelle' : 'Kaydet'}</button></div>`);
    $('#saveCustomZikir').onclick = () => {
      const title = $('#czTitle').value.trim();
      if (!title) return toast('Zikir adı gerekli.');
      const payload = {
        id: editItem ? editItem.id : Date.now(),
        title,
        arabic_text: $('#czArabic').value.trim(),
        meaning: $('#czMeaning').value.trim(),
        default_target: Math.max(1, Number($('#czTarget').value || state.settings.defaultTarget || 1000)),
        is_favorite: 1,
        custom: true
      };
      const next = editItem ? list.map(item => Number(item.id) === Number(editItem.id) ? Object.assign({}, item, payload) : item) : [payload, ...list];
      save('az_custom_zikirs', next.slice(0, 50));
      if (!editItem) {
        const favIds = Array.isArray(state.settings.favoriteZikirIds) ? state.settings.favoriteZikirIds.map(Number) : favoriteZikirs().map(z => Number(z.id));
        saveFavoriteZikirIds([payload.id, ...favIds].slice(0, 8));
      }
      closeModal();
      toast(editItem ? 'Zikir güncellendi.' : 'Zikir eklendi.');
      if (returnRoute === 'settings') runWithoutScrollJump(() => renderSettings()); else runWithoutScrollJump(() => renderHome());
    };
  }

  async function deleteCustomZikir(id) {
    const list = load('az_custom_zikirs', []);
    const item = list.find(x => Number(x.id) === Number(id));
    if (!item) return;
    if (!(await appConfirm(`${item.title} silinsin mi?`))) return;
    save('az_custom_zikirs', list.filter(x => Number(x.id) !== Number(id)));
    if (Array.isArray(state.settings.favoriteZikirIds)) saveFavoriteZikirIds(state.settings.favoriteZikirIds.filter(fid => Number(fid) !== Number(id)));
    if (Number(state.counter.zikirId) === Number(id)) {
      state.counter.zikirId = 1;
      state.counter.count = 0;
      state.counter.startedAt = Date.now();
      save('az_counter', state.counter);
    }
    toast('Kişisel zikir silindi.');
    runWithoutScrollJump(() => renderSettings());
  }

  async function api(action, payload = null, params = {}) {
    const query = new URLSearchParams(Object.assign({ action }, params));
    const opt = payload ? { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) } : {};
    const res = await fetch(`${API}?${query.toString()}`, opt);
    return await res.json();
  }
  function enqueue(action, payload) { state.queue.push({ action, payload, ts: Date.now() }); save('az_queue', state.queue); }
  async function flushQueue(verbose = false) {
    if (!navigator.onLine || state.queue.length === 0) { if (verbose) toast(state.queue.length ? 'İnternet yok.' : 'Bekleyen işlem yok.'); return; }
    const q = [...state.queue]; state.queue = []; save('az_queue', state.queue);
    for (const item of q) { try { const res = await api(item.action, item.payload); if (!res.ok) throw new Error(res.message || 'fail'); } catch { state.queue.push(item); } }
    save('az_queue', state.queue);
    if (state.queue.length === 0) { state.settings.lastSyncAt = new Date().toISOString(); save('az_settings', state.settings); }
    if (verbose) toast(state.queue.length ? `${state.queue.length} işlem gönderilemedi.` : 'Tüm bekleyen işlemler gönderildi.');
    syncBootstrap(false);
  }
  async function syncBootstrap(show = true) {
    try {
      const data = await api('bootstrap', null, { client_id: state.settings.clientId });
      if (data.ok) {
        state.data = data;
        save('az_bootstrap', data);
        state.settings.lastSyncAt = new Date().toISOString();
        save('az_settings', state.settings);
        if (show) toast('Veriler güncellendi.');
        if (routeChanging) render();
        else if (readScrollY() > 10) runWithoutScrollJump(() => render(), 220);
        else render();
      }
    } catch { if (show) toast('Offline mod.'); updateNetworkBadge(); }
  }

  $$('.bottom-nav button').forEach(btn => btn.addEventListener('click', () => route(btn.dataset.route)));
  $('[data-route="settings"]').addEventListener('click', () => route('settings'));
  $('#menuBtn').addEventListener('click', () => openModal(`<h2 class="page-title">Hızlı Menü</h2><div class="drawer-grid"><button data-route="counter"><span>◴</span><strong>Zikir Sayacı</strong></button><button data-route="zikir"><span>☾</span><strong>Toplu Zikir</strong></button><button data-route="dua"><span>♡</span><strong>Dua Halkası</strong></button><button data-route="hatim"><span>▤</span><strong>Hatim</strong></button><button data-route="stats"><span>▥</span><strong>İstatistik</strong></button><button data-route="settings"><span>⚙</span><strong>Ayarlar</strong></button></div>`));
  modal.addEventListener('click', e => {
    const routeButton = e.target?.closest?.('[data-route]');
    if (!routeButton || !modalContent.contains(routeButton)) return;
    const r = routeButton.dataset?.route;
    if (r) { closeModal(); route(r); }
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && state.settings.keepAwake && state.route === 'counter') requestWakeLock();
  });
  window.addEventListener('online', () => { toast('İnternet geldi, senkronizasyon yapılıyor.'); updateNetworkBadge(); flushQueue(false); syncBootstrap(false); });
  window.addEventListener('offline', () => { toast('Offline moda geçildi. Sayaç çalışmaya devam eder.'); updateNetworkBadge(); });
  if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js').catch(() => {})); }
  const initialScreen = new URLSearchParams(location.search).get('screen');
  if (initialScreen && ['home','counter','zikir','dua','hatim','stats','settings'].includes(initialScreen)) state.route = initialScreen;
  startReminderWatcher();
  render(); setTimeout(() => { if (state.route === 'home') showFirstUseGuide(false); }, 850); syncBootstrap(false); flushQueue(false);
})();
