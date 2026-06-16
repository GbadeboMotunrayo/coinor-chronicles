<!-- ═══════════════════════════════════════════════════
     SUBSCRIBE SECTION
═══════════════════════════════════════════════════ -->
<section id="subscribe" aria-labelledby="subscribe-heading">
  <span class="subscribe-crown" aria-hidden="true">♚</span>
  <h2 id="subscribe-heading" data-reveal="fade-up">Receive the Daily Scroll</h2>
  <p data-reveal="fade-up">
    Every morning, the chronicle arrives at your door. Aragorn speaks.
    The fellowship moves. The road continues — whether you watch or not.
    Choose to watch.
  </p>

  <form class="subscribe-form" data-reveal="fade-up" id="subscribe-form" novalidate>
    <label for="sub-email" class="visually-hidden">Your scroll address</label>
    <input
      type="email"
      id="sub-email"
      name="email"
      class="subscribe-input"
      placeholder="Your scroll address…"
      autocomplete="email"
      required
    />
    <button type="submit" class="subscribe-btn">Enter the Fellowship</button>
  </form>
  <p id="subscribe-message" style="display:none;font-size:0.85rem;color:var(--green-up);margin-top:0.75rem;" role="status"></p>

  <p class="subscribe-privacy" data-reveal="fade-up">
    No provisions taken. No scroll sold. Unsubscribe at any gate.
  </p>

  <div class="subscribe-badges" data-reveal="fade-up">
    <div class="sub-badge"><span class="sub-badge-icon">📜</span><span>Daily chronicle delivered</span></div>
    <div class="sub-badge"><span class="sub-badge-icon">⚔</span><span>Gate &amp; Heaven alerts</span></div>
    <div class="sub-badge"><span class="sub-badge-icon">♚</span><span>Fellowship milestone events</span></div>
  </div>
</section>


<!-- ═══════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════ -->
<footer id="footer" role="contentinfo">
  <div class="footer-inner">
    <div class="footer-brand">
      <h3>Coinor Chronicles</h3>
      <p>
        An AI media empire. The markets of the Realm of Coinor, told as an
        eternal fantasy saga. Twenty-one companions. One road. Updated every six hours.
      </p>
      <div class="footer-social">
        <a href="#" class="footer-social-link" aria-label="YouTube" rel="noopener noreferrer">▶</a>
        <a href="#" class="footer-social-link" aria-label="TikTok"  rel="noopener noreferrer">♪</a>
        <a href="#" class="footer-social-link" aria-label="X / Twitter" rel="noopener noreferrer">𝕏</a>
      </div>
    </div>

    <div class="footer-col">
      <h4>The Realm</h4>
      <ul>
        <li><a href="/chronicle.php">The Chronicle</a></li>
        <li><a href="/the-fellowship.php">The Fellowship</a></li>
        <li><a href="/lore.html">The Lore</a></li>
        <li><a href="/#subscribe">Subscribe</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>The Clans</h4>
      <ul>
        <li><a href="/the-fellowship.php#ancients">Clan of the Ancients</a></li>
        <li><a href="/the-fellowship.php#swift">Clan of the Swift</a></li>
        <li><a href="/the-fellowship.php#meme">Clan of Meme Lords</a></li>
        <li><a href="/the-fellowship.php#builders">Clan of the Builders</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Follow the Quest</h4>
      <ul>
        <li><a href="#" rel="noopener noreferrer">YouTube</a></li>
        <li><a href="#" rel="noopener noreferrer">TikTok</a></li>
        <li><a href="#" rel="noopener noreferrer">X / Twitter</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <p>© <?= date('Y') ?> Coinor Chronicles · The Realm of Coinor</p>
    <p>The chronicle continues ∞</p>
  </div>
</footer>


<!-- ═══════════════════════════════════════════════════
     SHARED SCRIPTS
═══════════════════════════════════════════════════ -->
<script>
(function() {
  /* ── Progress bar ── */
  window.addEventListener('scroll', function() {
    const scrolled = document.documentElement.scrollTop;
    const total    = document.documentElement.scrollHeight - window.innerHeight;
    const pct      = total > 0 ? (scrolled / total) * 100 : 0;
    const bar = document.getElementById('progress-bar');
    if (bar) bar.style.width = pct + '%';
  }, { passive: true });

  /* ── Nav scrolled class ── */
  const nav = document.getElementById('nav');
  function checkNav() {
    if (!nav) return;
    nav.classList.toggle('scrolled', window.scrollY > 60);
  }
  window.addEventListener('scroll', checkNav, { passive: true });
  checkNav();

  /* ── Active nav link ── */
  document.querySelectorAll('.nav-links a').forEach(function(a) {
    if (a.href === window.location.href) a.classList.add('active');
  });

  /* ── Mobile menu ── */
  const toggle = document.querySelector('.nav-toggle');
  const menu   = document.getElementById('mobile-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', function() {
      const isOpen = menu.classList.toggle('open');
      toggle.classList.toggle('open', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
    menu.querySelectorAll('a').forEach(function(a) {
      a.addEventListener('click', function() {
        menu.classList.remove('open');
        toggle.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* ── Subscribe form ── */
  const form = document.getElementById('subscribe-form');
  const msg  = document.getElementById('subscribe-message');
  if (form) {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const email = form.querySelector('input[type=email]').value.trim();
      if (!email) return;
      const btn = form.querySelector('button');
      btn.textContent = 'Sending…';
      btn.disabled = true;
      try {
        const res = await fetch('/api/subscribe.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email })
        });
        const data = await res.json();
        if (data.ok) {
          if (msg) { msg.textContent = 'The scroll is on its way. Welcome to the fellowship.'; msg.style.display = 'block'; }
          form.reset();
        } else {
          if (msg) { msg.textContent = data.message || 'Something went wrong. Try again.'; msg.style.color = 'var(--red-down)'; msg.style.display = 'block'; }
        }
      } catch {
        if (msg) { msg.textContent = 'Could not send. Check your connection and try again.'; msg.style.color = 'var(--red-down)'; msg.style.display = 'block'; }
      }
      btn.textContent = 'Enter the Fellowship';
      btn.disabled = false;
    });
  }

  /* ── Scroll reveal (GSAP-free fallback) ── */
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.style.transition = 'opacity 0.85s cubic-bezier(0.16,1,0.3,1), transform 0.85s cubic-bezier(0.16,1,0.3,1)';
          entry.target.style.opacity   = '1';
          entry.target.style.transform = 'none';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    document.querySelectorAll('[data-reveal]').forEach(function(el) {
      el.style.opacity   = '0';
      el.style.transform = 'translateY(40px)';
      observer.observe(el);
    });
  } else {
    document.querySelectorAll('[data-reveal]').forEach(function(el) {
      el.style.opacity = '1';
    });
  }
}());
</script>
</body>
</html>
