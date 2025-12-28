/* Public/assets/js/header-nav.js */
/* 三層控制 + sticky 偏移量 + 回頂端 */

(function () {
  const header = document.querySelector('.jh-header');
  const nav2   = header?.querySelector('.jh-nav2');
  const items  = header ? Array.from(header.querySelectorAll('.jh-nav2-item')) : [];
  if (!header || !nav2 || items.length === 0) {
    // 即便沒有三層，仍要初始化 sticky 偏移量 + 回頂端
    initStickyOffset(null);
    initBackToTop();
    return;
  }

  let open2 = false;
  let t2In = null, t2Out = null, tDropOut = null;
  const isTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;

  function measureH(el){ return el.getBoundingClientRect().height; }

  // --- Sticky 偏移量：把 header 高度寫到 --jh-header-h ---
  function setStickyOffset(){
    const h = header ? Math.ceil(header.getBoundingClientRect().height) : 0;
    document.documentElement.style.setProperty('--jh-header-h', h + 'px');
  }
  function initStickyOffset(targetHeader){
    // 初始 & 事件
    window.addEventListener('load', setStickyOffset);
    window.addEventListener('resize', setStickyOffset);
    if (targetHeader && 'ResizeObserver' in window) {
      new ResizeObserver(setStickyOffset).observe(targetHeader);
    }
    // 保險：延遲再算一次（因字型/圖片載入可能變高）
    setTimeout(setStickyOffset, 0);
    setTimeout(setStickyOffset, 200);
  }

  // --- 回頂端（全站共用；頁面需放置 #btnBackToTop） ---
  function initBackToTop(){
    const btn = document.getElementById('btnBackToTop');
    if (!btn) return;
    const onScroll = () => {
      btn.style.display = (window.scrollY > 200) ? 'block' : 'none';
    };
    window.addEventListener('scroll', onScroll);
    onScroll(); // 初始判斷
    btn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  initStickyOffset(header);
  initBackToTop();

  // 第二層：展開/收起（高度補間）
  function openNav2(){
    if (open2) return;
    open2 = true;
    header.classList.remove('nav2-closing');
    header.classList.add('nav2-open');
    nav2.style.height = 'auto';
    const h = measureH(nav2);
    nav2.style.height = '0px';
    requestAnimationFrame(() => {
      nav2.style.height = h + 'px';
      setStickyOffset(); // 展開啟動先算一次
    });
  }

  function closeNav2(){
    if (!open2) return;
    open2 = false;
    header.classList.add('nav2-closing');
    nav2.style.height = '0px';
    closeAllDropdowns();
    setStickyOffset(); // 開始收合也先算一次
  }

  // 在第一次載入時綁定 transitionend（避免重複綁定）
  let bound = false;
  if (!bound) {
    nav2.addEventListener('transitionend', (e) => {
      if (e.propertyName !== 'height') return;
      if (!open2) {
        header.classList.remove('nav2-open', 'nav2-closing');
        nav2.style.overflow = 'hidden';
      } else {
        nav2.style.overflow = 'visible';
      }
      setStickyOffset(); // 高度動畫結束後再保險一次
    });
    bound = true;
  }

  // 第三層：下拉控制
  function closeAllDropdowns(){
    items.forEach(li => {
      li.classList.remove('open', 'align-right');
      const pop = li.querySelector('.jh-pop');
      if (pop) pop.hidden = true;
      const btn = li.querySelector('.jh-nav2-btn');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }

  function openDropdown(li){
    closeAllDropdowns();
    const btn = li.querySelector('.jh-nav2-btn');
    const pop = li.querySelector('.jh-pop');
    if (!pop) return;

    // 邊緣偵測（右對齊）
    li.classList.remove('align-right');
    pop.hidden = false;
    void pop.offsetHeight; // reflow 以啟動 transition
    const liBox = li.getBoundingClientRect();
    const popW  = pop.getBoundingClientRect().width || 300;
    const spaceRight = window.innerWidth - liBox.left;
    if (spaceRight < Math.max(300, popW + 24)) li.classList.add('align-right');

    li.classList.add('open');
    if (btn) btn.setAttribute('aria-expanded', 'true');
  }

  // hover 第一層 header → 展開第二層
  header.addEventListener('mouseenter', () => {
    clearTimeout(t2Out);
    t2In = setTimeout(openNav2, 80);
  });
  header.addEventListener('mouseleave', () => {
    clearTimeout(t2In);
    t2Out = setTimeout(() => {
      closeAllDropdowns();
      closeNav2();
    }, 140);
  });

  // hover 第二層某項 → 該項下拉
  items.forEach(li => {
    li.addEventListener('mouseenter', () => {
      clearTimeout(tDropOut);
      openDropdown(li);
    });
    li.addEventListener('mouseleave', () => {
      tDropOut = setTimeout(closeAllDropdowns, 120);
    });
  });

  // 觸控：點抬頭開合；點項目切換下拉
  if (isTouch) {
    header.addEventListener('click', (e) => {
      const btn = e.target.closest('.jh-nav2-btn');
      if (!btn) {
        open2 ? closeNav2() : openNav2();
        return;
      }
      e.preventDefault();
      const li = btn.closest('.jh-nav2-item');
      if (li.classList.contains('open')) closeAllDropdowns();
      else openDropdown(li);
    }, true);

    // 點外部收起
    document.addEventListener('click', (e) => {
      if (!header.contains(e.target)) { closeAllDropdowns(); closeNav2(); }
    });
  }

  // Esc 關閉
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeAllDropdowns(); closeNav2(); }
  });

  // 重新計算右對齊
  window.addEventListener('resize', () => {
    const opened = header.querySelector('.jh-nav2-item.open');
    if (!opened) return;
    const pop = opened.querySelector('.jh-pop');
    if (!pop) return;
    opened.classList.remove('align-right');
    const liBox = opened.getBoundingClientRect();
    const popW  = pop.getBoundingClientRect().width || 300;
    const spaceRight = window.innerWidth - liBox.left;
    if (spaceRight < Math.max(300, popW + 24)) opened.classList.add('align-right');
  });
})();
