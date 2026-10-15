(function () {
  'use strict';

  var CLANS = {
    ancients:   { name: 'Clan of the Ancients', place: 'The Obsidian Citadel',
                  blurb: 'The oldest roads and the longest memories.',
                  who: [['Aragorn', 'Bitcoin'], ['Gandalf', 'Ethereum'], ['Samwise', 'Litecoin'], ['Elrond', 'BNB'], ['Boromir', 'XRP']] },
    swift:      { name: 'Clan of the Swift', place: 'The Electric Plains',
                  blurb: 'Fast riders on open ground. They win by moving first.',
                  who: [['Legolas', 'Solana'], ['Eomer', 'Toncoin'], ['Eowyn', 'Avalanche'], ['Faramir', 'Stellar'], ['Saruman', 'Tron']] },
    meme_lords: { name: 'Clan of Meme Lords', place: 'Kekiston Bazaar',
                  blurb: 'Loud, loved and loyal. The Bazaar never fully sleeps.',
                  who: [['Tom Bombadil', 'Pepe'], ['Merry', 'Shiba Inu'], ['Pippin', 'Dogecoin'], ['Theoden', 'Floki'], ['Frodo', 'Notcoin'], ['Bilbo', 'Book of Meme'], ['Gollum', 'Bonk']] },
    builders:   { name: 'Clan of the Builders', place: 'The Forge of Chains',
                  blurb: 'Patient makers who build in silence.',
                  who: [['Treebeard', 'Cardano'], ['Gimli', 'Sui'], ['Galadriel', 'Jasmy']] }
  };
  var CONDITIONS = {
    golden_season: 'The Golden Season',
    dark_siege: 'The Dark Siege',
    waiting_plains: 'The Waiting Plains'
  };

  var app = document.getElementById('app');
  var params = new URLSearchParams(location.search);
  var preview = params.has('preview');
  var episodes = [];

  /* ---------- tiny DOM helpers (text only, never innerHTML) ---------- */
  function el(tag, attrs, children) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'class') n.className = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else n.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  function inline(text) {          // supports **bold** and *italic* only
    var frag = document.createDocumentFragment();
    String(text).split(/(\*\*[^*]+\*\*|\*[^*]+\*)/g).forEach(function (part) {
      if (!part) return;
      if (part.slice(0, 2) === '**') frag.appendChild(el('strong', { text: part.slice(2, -2) }));
      else if (part[0] === '*' && part.length > 2) frag.appendChild(el('em', { text: part.slice(1, -1) }));
      else frag.appendChild(document.createTextNode(part));
    });
    return frag;
  }
  function fmtDate(iso) {
    var d = new Date(iso + 'T12:00:00');
    return isNaN(d) ? '' : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
  }
  function excerpt(body) {
    var paras = String(body).split(/\n\s*\n/).map(function (p) { return p.trim(); }).filter(Boolean);
    var first = paras.filter(function (p, i) { return i > 0 && p.length >= 140; })[0] || paras[0] || '';
    first = first.replace(/\*+/g, '');
    return first.length > 170 ? first.slice(0, 167).replace(/\s+\S*$/, '') + '…' : first;
  }
  function visible() {
    return episodes
      .filter(function (e) { return e.status === 'approved' || (preview && e.status === 'sample'); })
      .sort(function (a, b) { return b.number - a.number; });
  }
  function setTitle(t) { document.title = t ? t + ' · CoinorChronicles' : 'The Book of Meme · CoinorChronicles'; }
  function setNav(route) {
    document.querySelectorAll('nav a').forEach(function (a) {
      if (a.dataset.route === route) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
  }
  function show(nodes, title, route) {
    app.textContent = '';
    nodes.forEach(function (n) { if (n) app.appendChild(n); });
    setTitle(title); setNav(route); setWeather();
    var h1 = app.querySelector('h1'); if (h1) { h1.setAttribute('tabindex', '-1'); }
    window.scrollTo(0, 0);
    if (h1) h1.focus({ preventScroll: true });
  }
  function link(hash) { return hash + (preview ? '' : ''); }


  /* ---------- the living map ---------- */
  var SVGNS = 'http://www.w3.org/2000/svg';
  function svg(tag, attrs, kids) {
    var n = document.createElementNS(SVGNS, tag);
    if (attrs) Object.keys(attrs).forEach(function (k) { if (k === 'text') n.textContent = attrs[k]; else n.setAttribute(k, attrs[k]); });
    (kids || []).forEach(function (c) { if (c) n.appendChild(c); });
    return n;
  }
  // three cubic segments from the Citadel to the Golden Gate
  var SEG = [
    [[40, 232], [120, 120], [200, 262], [300, 172]],
    [[300, 172], [400, 82], [470, 176], [560, 122]],
    [[560, 122], [640, 70], [700, 112], [748, 66]]
  ];
  var ROUTE_D = 'M40 232C120 120 200 262 300 172C400 82 470 176 560 122C640 70 700 112 748 66';
  var STOPS = [[0, .0], [0, .42], [0, .8], [1, .2], [1, .5], [1, .8], [2, .2], [2, .45], [2, .7], [2, .92]];
  function bez(s, t) {
    var u = 1 - t;
    return [0, 1].map(function (i) {
      return u * u * u * s[0][i] + 3 * u * u * t * s[1][i] + 3 * u * t * t * s[2][i] + t * t * t * s[3][i];
    });
  }
  function stopPoint(i) { return bez(SEG[STOPS[i][0]], STOPS[i][1]); }

  function roadMap(list) {
    var asc = list.slice(0, 10).sort(function (a, b) { return a.number - b.number; });
    var latest = list[0];
    var kids = [
      svg('rect', { x: 8, y: 8, width: 784, height: 264, rx: 10, class: 'ground' }),
      svg('path', { d: ROUTE_D, class: 'route-bg' }),
      svg('path', { d: ROUTE_D, class: 'route' })
    ];
    STOPS.forEach(function (s, i) { var p = stopPoint(i); kids.push(svg('circle', { cx: p[0].toFixed(1), cy: p[1].toFixed(1), r: 4, class: 'stop' })); });
    [['The Obsidian Citadel', 22, 258], ['The Electric Plains', 200, 292 - 150], ['Kekiston Bazaar', 392, 204], ['The Forge of Chains', 560, 160]].forEach(function (pl) {
      kids.push(svg('text', { x: pl[1], y: pl[2], class: 'place', text: pl[0] }));
    });
    kids.push(svg('ellipse', { cx: 748, cy: 62, rx: 46, ry: 40, class: 'gate-glow' }));
    kids.push(svg('path', { d: 'M734 82V58a14 14 0 0 1 28 0v24', class: 'gate-arch' }));
    kids.push(svg('text', { x: 790, y: 112, 'text-anchor': 'end', class: 'gate-name', text: 'The Golden Gate' }));
    asc.forEach(function (e) {
      var p = stopPoint((e.number - 1) % 10);
      var isLatest = latest && e.number === latest.number;
      kids.push(svg('a', { href: '/episode/' + e.number, class: 'pin clan-' + e.clan + (isLatest ? ' latest' : ''), 'aria-label': 'Episode ' + e.number + ': ' + e.title },
        [svg('title', { text: 'Episode ' + e.number + ' · ' + e.title }),
         isLatest ? svg('circle', { cx: p[0].toFixed(1), cy: p[1].toFixed(1), r: 11, class: 'halo' }) : null,
         svg('circle', { cx: p[0].toFixed(1), cy: p[1].toFixed(1), r: 11, class: 'dot' }),
         svg('text', { x: p[0].toFixed(1), y: (p[1] + 3.6).toFixed(1), 'text-anchor': 'middle', text: String(e.number) })]));
    });
    var map = svg('svg', { viewBox: '0 0 800 280', role: 'img', 'aria-label': 'A map of the road from the Obsidian Citadel to the Golden Gate, with a marker for each recent chapter' }, kids);
    return el('section', { class: 'road' }, [
      el('h2', { text: 'The road to the Golden Gate' }), map,
      el('p', { class: 'legend', text: list.length ? 'Each marker is a chapter. Choose one to read it. The glowing marker is the newest.' : 'No chapter has been written yet. The first marker will appear here.' })
    ]);
  }

  function setWeather() {
    var l = visible()[0];
    var w = l && l.condition === 'dark_siege' ? 'siege' : l && l.condition === 'golden_season' ? 'golden' : 'calm';
    document.body.setAttribute('data-weather', w);
  }

  function embers() {
    var cv = document.getElementById('embers');
    if (!cv || (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
    var cx = cv.getContext('2d'), W = 0, H = 0, ps = [], raf = 0;
    function size() { W = cv.width = innerWidth; H = cv.height = innerHeight; }
    size(); addEventListener('resize', size);
    var N = innerWidth < 640 ? 22 : 42;
    function spawn(p, fresh) {
      p.x = Math.random() * W; p.y = fresh ? H + 10 : Math.random() * H; p.r = .6 + Math.random() * 1.8;
      p.v = .15 + Math.random() * .5; p.d = (Math.random() - .5) * .3; p.a = .2 + Math.random() * .6; p.t = Math.random() * 6.28;
      return p;
    }
    for (var i = 0; i < N; i++) ps.push(spawn({}, false));
    function frame() {
      cx.clearRect(0, 0, W, H);
      var w = document.body.getAttribute('data-weather');
      var col = w === 'siege' ? '150,180,235' : '255,190,90';
      ps.forEach(function (p) {
        p.t += .02; p.y -= p.v * (w === 'siege' ? 1.8 : 1); p.x += p.d + Math.sin(p.t) * .25;
        if (p.y < -10) spawn(p, true);
        cx.beginPath(); cx.arc(p.x, p.y, p.r, 0, 6.283);
        cx.fillStyle = 'rgba(' + col + ',' + (p.a * (.6 + .4 * Math.sin(p.t * 2))).toFixed(3) + ')'; cx.fill();
      });
      raf = requestAnimationFrame(frame);
    }
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) cancelAnimationFrame(raf); else raf = requestAnimationFrame(frame);
    });
    raf = requestAnimationFrame(frame);
  }

  /* ---------- views ---------- */
  function card(e) {
    var clan = CLANS[e.clan] || { name: '' };
    var a = el('a', { class: 'card clan-' + e.clan, href: '/episode/' + e.number }, [
      el('span', { class: 'seal', 'aria-hidden': 'true', text: String(e.number) }),
      el('p', { class: 'meta' }, [
        el('span', { class: 'tag', text: 'Episode ' + e.number }),
        el('span', { text: clan.name }),
        el('span', { text: CONDITIONS[e.condition] || '' }),
        el('span', { text: fmtDate(e.date) })
      ]),
      el('h3', { text: e.title }),
      el('p', { text: excerpt(e.body) })
    ]);
    return a;
  }

  function viewHome() {
    var list = visible();
    var gates = el('div', { class: 'gate', 'aria-hidden': 'true' }, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9].map(function (i) {
      return el('i', { class: i < Math.min(list.length, 10) ? 'on' : '' });
    }));
    var hero = el('section', { class: 'hero' }, [
      el('p', { class: 'eyebrow', text: 'The Realm of Coinor · Journey to the Golden Gate' }),
      el('h1', { text: 'The Book of Meme' }),
      el('p', { class: 'lede', text: 'A living chronicle of travellers who chase Golden Gates across a realm that never stops moving. New chapters arrive as the markets turn.' }),
      gates
    ]);
    var nodes = [hero, roadMap(list)];

    if (!list.length) {
      nodes.push(el('div', { class: 'empty' }, [
        el('h2', { text: 'The first chronicle is being written', style: 'margin-top:0' }),
        el('p', { text: 'New chapters appear here by themselves as the realm moves, up to twice a day. Check back soon.' }),
        el('p', {}, [el('a', { href: '#/realm', text: 'Meet the clans meanwhile' })])
      ]));
      return show(nodes, '', 'home');
    }

    var active = 'all';
    var chips = el('div', { class: 'chips', role: 'group', 'aria-label': 'Filter by clan' });
    var cards = el('div', { class: 'cards' });
    function draw() {
      cards.textContent = '';
      list.filter(function (e) { return active === 'all' || e.clan === active; }).forEach(function (e) { cards.appendChild(card(e)); });
      if (!cards.children.length) cards.appendChild(el('p', { class: 'muted', text: 'No episodes from this clan yet.' }));
      chips.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.clan === active)); });
    }
    [['all', 'All clans']].concat(Object.keys(CLANS).map(function (k) { return [k, CLANS[k].name.replace('Clan of the ', '').replace('Clan of ', '')]; }))
      .forEach(function (c) {
        var b = el('button', { class: 'chip', type: 'button', 'data-clan': c[0], 'aria-pressed': 'false', text: c[1] });
        b.addEventListener('click', function () { active = c[0]; draw(); });
        chips.appendChild(b);
      });
    var stamp = feedUpdated ? el('p', { class: 'muted', style: 'font-size:.85rem;font-family:var(--sans);margin:.2rem 0 0', text: 'Book last updated ' + new Date(feedUpdated).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) }) : null;
    nodes.push(el('h2', { text: 'Latest chronicles' }), stamp, chips, cards);
    show(nodes, '', 'home');
    draw();
  }

  function viewEpisode(n) {
    var list = visible();
    var e = list.filter(function (x) { return x.number === n; })[0];
    if (!e) return show([el('h1', { text: 'That chapter is not in the Book' }), el('p', {}, [el('a', { href: '#/', text: 'Back to the chronicle' })])], 'Not found', 'home');
    var clan = CLANS[e.clan] || { name: '' };
    var idx = list.indexOf(e);
    var newer = list[idx - 1], older = list[idx + 1];
    var body = el('div', { class: 'body' });
    String(e.body).split(/\n\s*\n/).forEach(function (p) {
      p = p.trim(); if (p) body.appendChild(el('p', {}, [inline(p)]));
    });
    var copy = el('button', { class: 'btn', type: 'button', text: 'Copy link' });
    copy.addEventListener('click', function () {
      var url = location.href.split('?')[0] + '#/episode/' + e.number;
      (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(
        function () { copy.textContent = 'Link copied'; }, function () { copy.textContent = url; });
    });
    var pager = el('div', { class: 'pager' }, [
      older ? el('a', { href: '#/episode/' + older.number, text: '← Episode ' + older.number }) : el('span'),
      copy,
      newer ? el('a', { href: '#/episode/' + newer.number, text: 'Episode ' + newer.number + ' →' }) : el('span')
    ]);
    show([
      el('p', { class: 'back' }, [el('a', { href: '#/', text: '← All chronicles' })]),
      el('article', { class: 'episode clan-' + e.clan }, [
        el('p', { class: 'meta' }, [
          el('span', { class: 'tag', text: 'Episode ' + e.number }),
          el('span', { text: clan.name }),
          el('span', { text: CONDITIONS[e.condition] || '' }),
          el('span', { text: fmtDate(e.date) })
        ]),
        el('h1', { text: e.title }),
        body, pager
      ])
    ], e.title, 'home');
  }

  function viewRealm() {
    var nodes = [
      el('p', { class: 'eyebrow', text: 'The Realm' }),
      el('h1', { text: 'The Realm of Coinor' }),
      el('p', { class: 'lede', text: 'Travellers walk toward Golden Gates: great milestones that lift them to a new height of power. Between one Gate and the next lie ten Heavens. The journey never ends, because a new Gate always appears beyond the last.' }),
      el('h2', { text: 'The market weather' }),
      el('p', { text: 'Each chapter is set in one of three conditions. The Golden Season is a rising market, the Dark Siege is a falling one, and in the Waiting Plains nothing moves enough to tell, so no chapter is written.' }),
      el('h2', { text: 'The clans' })
    ];
    Object.keys(CLANS).forEach(function (k) {
      var c = CLANS[k];
      nodes.push(el('section', { class: 'clan clan-' + k }, [
        el('h3', { text: c.name }),
        el('p', { class: 'where', text: c.place }),
        el('p', { style: 'margin:.2em 0', text: c.blurb }),
        el('ul', {}, c.who.map(function (w) { return el('li', {}, [el('b', { text: w[0] }), ' · ' + w[1]]); }))
      ]));
    });
    nodes.push(el('p', { class: 'muted', style: 'font-size:.95rem', text: 'Each traveller is a character who stands for a real digital asset. The stories use public price movement as material. They are fiction and are not advice.' }));
    show(nodes, 'The Realm', 'realm');
  }

  function viewAbout() {
    show([
      el('p', { class: 'eyebrow', text: 'About' }),
      el('h1', { text: 'How the Book is written' }),
      el('p', { text: 'CoinorChronicles turns real market movement into an unfolding fantasy saga. When the markets move, a new chapter is drafted with the help of AI, using a fixed style guide and the live price data as story material. New chapters can appear up to twice a day.' }),
      el('h2', { text: 'It runs by itself, with safety checks' }),
      el('p', { text: 'No person reads each chapter before it appears. Instead, every draft must pass a set of automatic checks in code: a fixed length and structure, in-world language only, no real-world names, no finance terms, no advice, no links or wallet addresses. If a draft fails, it is rewritten, and if it still fails, nothing is published. A person watches the system and can remove any chapter at any time.' }),
      el('h2', { text: 'What this is not' }),
      el('p', { text: 'It is not financial advice, a price prediction, or a recommendation to buy or sell anything. The characters are metaphors. Digital assets are volatile and you can lose money.' }),
      el('h2', { text: 'Who is behind it' }),
      el('p', {}, ['CoinorChronicles is a story project from MG Holdings. The style guide that governs the writing is open to read at ', el('a', { href: 'https://github.com/GbadeboMotunrayo/coinor-chronicles', rel: 'noopener', text: 'github.com/GbadeboMotunrayo/coinor-chronicles' }), '.'])
    ], 'About', 'about');
  }

  /* ---------- router ---------- */
  function route() {
    var h = location.hash.replace(/^#/, '') || '/';
    var m;
    if (h === '/' || h === '') return viewHome();
    if (h === '/realm') return viewRealm();
    if (h === '/about') return viewAbout();
    if ((m = h.match(/^\/episode\/(\d+)$/))) return viewEpisode(parseInt(m[1], 10));
    show([el('h1', { text: 'That path is not on the map' }), el('p', {}, [el('a', { href: '#/', text: 'Back to the chronicle' })])], 'Not found', 'home');
  }

  embers();
  if (preview) document.getElementById('preview-banner').hidden = false;
  var FEED_URL = 'https://coinor.2-24-128-252.sslip.io/feed/episodes.json';
  var CACHE_KEY = 'coinor_feed_v1';
  var feedUpdated = '';

  function getJson(url, timeoutMs) {
    var ctl = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = ctl ? setTimeout(function () { ctl.abort(); }, timeoutMs || 10000) : null;
    return fetch(url + (url.indexOf('?') < 0 ? '?v=' + Date.now() : ''), { cache: 'no-store', signal: ctl ? ctl.signal : undefined })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (d) { if (timer) clearTimeout(timer); return d; });
  }
  function fromCache() {
    try { var c = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null'); return c && c.episodes ? c : null; } catch (e) { return null; }
  }
  function loadFeed() {
    // 1) the live feed  2) last good copy in this browser  3) the static file shipped with the site
    return getJson(FEED_URL, 10000).then(function (d) {
      try { localStorage.setItem(CACHE_KEY, JSON.stringify(d)); } catch (e) {}
      return d;
    }).catch(function () {
      var c = fromCache();
      if (c) return c;
      return getJson('episodes.json', 8000).catch(function () { return { episodes: [] }; });
    });
  }
  function applyFeed(d) {
    var list = (d && d.episodes) || [];
    var before = episodes.length;
    feedUpdated = (d && d.updated) || '';
    return getJson(preview ? 'episodes.sample.json' : 'episodes.json', 8000).catch(function () { return { episodes: [] }; }).then(function (extra) {
      episodes = list.concat(preview ? (extra.episodes || []) : []);
      return before;
    });
  }
  loadFeed().then(applyFeed).then(function () {
    window.addEventListener('hashchange', route);
    route();
    setInterval(function () {            // pick up new episodes while the page stays open
      loadFeed().then(function (d) {
        var n = ((d && d.episodes) || []).length;
        if (n !== episodes.filter(function (e) { return e.status === 'approved'; }).length) {
          applyFeed(d).then(function () { if ((location.hash || '#/') === '#/' || location.hash === '') route(); });
        }
      });
    }, 5 * 60 * 1000);
  });
})();
