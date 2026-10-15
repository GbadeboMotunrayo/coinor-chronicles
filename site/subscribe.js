(function () {
  'use strict';
  var API = 'https://coinor.2-24-128-252.sslip.io/api/subscribe';
  var hosts = document.querySelectorAll('[data-subscribe]');
  if (!hosts.length) return;
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') n.textContent = attrs[k]; else n.setAttribute(k, attrs[k]);
    });
    (kids || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  hosts.forEach(function (host, i) {
    var id = 'sub' + i;
    var email = el('input', { type: 'email', id: id + '-email', name: 'email', required: '', autocomplete: 'email', placeholder: 'you@example.com', maxlength: '254' });
    var consent = el('input', { type: 'checkbox', id: id + '-consent', name: 'consent', required: '' });
    var trap = el('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true' });
    var btn = el('button', { type: 'submit', class: 'btn', text: 'Send me the Scroll' });
    var msg = el('p', { class: 'sub-msg', role: 'status', 'aria-live': 'polite' });
    var form = el('form', { class: 'sub-form', novalidate: '' }, [
      el('label', { for: id + '-email', class: 'sub-label', text: 'Your email' }), email,
      el('div', { class: 'sub-trap', 'aria-hidden': 'true' }, [trap]),
      el('label', { class: 'sub-consent', for: id + '-consent' }, [consent, ' I agree to get the weekly newsletter by email, and I know I can unsubscribe at any time. ',
        el('a', { href: '/privacy', text: 'How we treat your address' })]),
      btn, msg
    ]);
    host.className = 'subscribe';
    host.appendChild(el('h2', { text: 'The Scroll from the Realm' }));
    host.appendChild(el('p', { class: 'sub-lede', text: 'One short email on Sundays with the week’s new chapters. We never share your address, and leaving takes one click.' }));
    host.appendChild(form);
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      msg.className = 'sub-msg'; msg.textContent = '';
      if (!email.value || !email.checkValidity()) { msg.className = 'sub-msg bad'; msg.textContent = 'That email address does not look right.'; return; }
      if (!consent.checked) { msg.className = 'sub-msg bad'; msg.textContent = 'Please tick the box to agree to the newsletter.'; return; }
      btn.disabled = true; btn.textContent = 'Sending…';
      fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email.value.trim(), consent: true, website: trap.value }) })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok, j: j }; }); })
        .then(function (res) {
          msg.className = 'sub-msg ' + (res.ok ? 'good' : 'bad');
          msg.textContent = (res.j && res.j.msg) || (res.ok ? 'Check your inbox.' : 'Something went wrong. Please try again.');
          if (res.ok) { email.value = ''; consent.checked = false; }
        })
        .catch(function () { msg.className = 'sub-msg bad'; msg.textContent = 'We could not reach the server. Please try again in a moment.'; })
        .then(function () { btn.disabled = false; btn.textContent = 'Send me the Scroll'; });
    });
  });
})();
