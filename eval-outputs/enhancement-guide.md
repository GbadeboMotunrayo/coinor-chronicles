# From Flat to Alive: Additive Enhancement Guide

Your dark-themed site doesn't need a rebuild. Everything here is **additive** — drop each layer in independently, keep what works for your aesthetic, discard what doesn't. The technique stack is: **GSAP** for scroll and mouse animation, **Three.js** for atmospheric WebGL backgrounds, and **vanilla CSS** for depth layering.

---

## Library Stack (CDN — paste into `<head>`)

```html
<!-- GSAP core + ScrollTrigger + utility plugins -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/CustomEase.min.js"></script>

<!-- Three.js for WebGL atmospheric layer -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
```

Register the GSAP plugin once at the top of your JS:

```js
gsap.registerPlugin(ScrollTrigger, CustomEase);

// Custom easing that feels organic, not mechanical
CustomEase.create("smokeRise", "M0,0 C0.126,0.382 0.282,0.674 0.44,0.822 0.632,1.0 0.818,1.001 1,1");
```

---

## Layer 1: Atmospheric WebGL Background

This adds a living particle fog behind everything. It reacts subtly to mouse movement and never distracts from your content.

### Step 1 — Add the canvas element

Place this as the **first child** of `<body>`:

```html
<canvas id="bg-canvas" style="
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  z-index: 0;
  pointer-events: none;
"></canvas>
```

Make sure your content wrapper has `position: relative; z-index: 1;` so it sits above the canvas.

### Step 2 — Particle fog system

```js
(function initAtmosphere() {
  const canvas = document.getElementById('bg-canvas');
  const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: false });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5)); // cap for perf
  renderer.setSize(window.innerWidth, window.innerHeight);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 100);
  camera.position.z = 30;

  // Particle geometry
  const COUNT = 1200;
  const positions = new Float32Array(COUNT * 3);
  const speeds    = new Float32Array(COUNT);

  for (let i = 0; i < COUNT; i++) {
    positions[i * 3]     = (Math.random() - 0.5) * 80;  // x
    positions[i * 3 + 1] = (Math.random() - 0.5) * 50;  // y
    positions[i * 3 + 2] = (Math.random() - 0.5) * 40;  // z
    speeds[i]            = 0.003 + Math.random() * 0.008;
  }

  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));

  const mat = new THREE.PointsMaterial({
    color: 0x8855cc,        // deep violet — change to suit your palette
    size: 0.12,
    transparent: true,
    opacity: 0.35,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    sizeAttenuation: true,
  });

  const particles = new THREE.Points(geo, mat);
  scene.add(particles);

  // Subtle mouse parallax target
  let mouseX = 0, mouseY = 0;
  document.addEventListener('mousemove', e => {
    mouseX = (e.clientX / window.innerWidth  - 0.5) * 2;
    mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
  });

  // Drift particles upward, wrap at top
  function animate() {
    requestAnimationFrame(animate);

    const pos = geo.attributes.position.array;
    for (let i = 0; i < COUNT; i++) {
      pos[i * 3 + 1] += speeds[i];
      if (pos[i * 3 + 1] > 25) pos[i * 3 + 1] = -25; // wrap
    }
    geo.attributes.position.needsUpdate = true;

    // Camera drifts with mouse — very gently
    camera.position.x += (mouseX * 3 - camera.position.x) * 0.02;
    camera.position.y += (-mouseY * 2 - camera.position.y) * 0.02;

    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });
})();
```

**Performance note:** The `pointer-events: none` on the canvas means zero interference with your existing click handlers. The `requestAnimationFrame` loop is paused automatically by browsers when the tab is hidden.

---

## Layer 2: Mouse Parallax on Cards

Add depth to your existing card elements with a 3D tilt that tracks the cursor. This works on any element — cards, hero sections, featured items.

### Step 1 — Add the class to your cards

```html
<!-- Just add class="tilt-card" to your existing card markup -->
<div class="tilt-card your-existing-class">
  <!-- your content unchanged -->
</div>
```

### Step 2 — Tilt system with inner glow

```js
// Card tilt — mouse parallax
document.querySelectorAll('.tilt-card').forEach(card => {
  card.addEventListener('mouseenter', () => {
    gsap.to(card, { duration: 0.3, ease: 'power2.out', '--glow-opacity': 1 });
  });

  card.addEventListener('mousemove', e => {
    const rect  = card.getBoundingClientRect();
    const normX = (e.clientX - rect.left) / rect.width  - 0.5; // -0.5 to 0.5
    const normY = (e.clientY - rect.top)  / rect.height - 0.5;

    gsap.to(card, {
      duration: 0.4,
      ease: 'power2.out',
      rotationY:  normX * 14,   // max 14deg horizontal tilt
      rotationX: -normY * 10,   // max 10deg vertical tilt
      transformPerspective: 800,
      transformOrigin: 'center center',
    });

    // Move highlight spot with cursor
    gsap.to(card, {
      '--glow-x': `${(normX + 0.5) * 100}%`,
      '--glow-y': `${(normY + 0.5) * 100}%`,
      duration: 0.3,
      ease: 'none',
    });
  });

  card.addEventListener('mouseleave', () => {
    gsap.to(card, {
      duration: 0.6,
      ease: 'elastic.out(1, 0.4)',
      rotationX: 0,
      rotationY: 0,
    });
    gsap.to(card, { duration: 0.4, '--glow-opacity': 0 });
  });
});
```

### Step 3 — CSS for the radial glow

```css
.tilt-card {
  --glow-x: 50%;
  --glow-y: 50%;
  --glow-opacity: 0;

  position: relative;
  transform-style: preserve-3d;
  will-change: transform;
  cursor: default;
}

.tilt-card::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: radial-gradient(
    circle 120px at var(--glow-x) var(--glow-y),
    rgba(136, 85, 204, 0.25),
    transparent 70%
  );
  opacity: var(--glow-opacity);
  pointer-events: none;
  z-index: 1;
  transition: opacity 0.3s;
}
```

---

## Layer 3: Scroll-Triggered Section Reveals

Sections animate in as they enter the viewport. Each uses a different motion signature so the page feels varied rather than repetitive.

### Step 1 — Add data attributes to your sections

```html
<!-- Hero / main heading -->
<section data-reveal="fade-up">...</section>

<!-- Content blocks that enter from the side -->
<section data-reveal="slide-left">...</section>
<section data-reveal="slide-right">...</section>

<!-- Cards grid — staggers children -->
<section data-reveal="stagger-up">
  <div class="card">...</div>
  <div class="card">...</div>
  <div class="card">...</div>
</section>
```

### Step 2 — Scroll reveal system

```js
gsap.set('[data-reveal]', { autoAlpha: 0 });

const REVEALS = {
  'fade-up': {
    from: { autoAlpha: 0, y: 60, skewY: 1.5 },
    to:   { autoAlpha: 1, y: 0,  skewY: 0, duration: 0.9, ease: 'smokeRise' },
  },
  'slide-left': {
    from: { autoAlpha: 0, x: -80, rotationY: -8 },
    to:   { autoAlpha: 1, x: 0,   rotationY: 0,  duration: 0.8, ease: 'power3.out' },
  },
  'slide-right': {
    from: { autoAlpha: 0, x: 80,  rotationY: 8 },
    to:   { autoAlpha: 1, x: 0,   rotationY: 0,  duration: 0.8, ease: 'power3.out' },
  },
};

document.querySelectorAll('[data-reveal]').forEach(el => {
  const type = el.dataset.reveal;

  if (type === 'stagger-up') {
    const children = el.children;
    gsap.set(children, { autoAlpha: 0, y: 50 });
    ScrollTrigger.create({
      trigger: el,
      start: 'top 80%',
      once: true,
      onEnter: () => {
        gsap.to(children, {
          autoAlpha: 1, y: 0, duration: 0.7, ease: 'power3.out',
          stagger: { amount: 0.5, from: 'start' },
        });
      },
    });
    return;
  }

  const config = REVEALS[type];
  if (!config) return;
  gsap.set(el, config.from);
  ScrollTrigger.create({
    trigger: el,
    start: 'top 78%',
    once: true,
    onEnter: () => gsap.to(el, config.to),
  });
});
```

---

## Layer 4: Parallax Depth on Scroll

```html
<!-- Slow drift — background elements -->
<div data-parallax="0.2">...</div>
<!-- Medium drift — decorative images -->
<img data-parallax="0.5" src="..." alt="">
<!-- Fast drift — foreground accents -->
<div data-parallax="0.8">...</div>
```

```js
document.querySelectorAll('[data-parallax]').forEach(el => {
  const speed = parseFloat(el.dataset.parallax) || 0.3;
  gsap.to(el, {
    y: () => window.innerHeight * speed * -1,
    ease: 'none',
    scrollTrigger: {
      trigger: el,
      start: 'top bottom',
      end: 'bottom top',
      scrub: 1.5,
    },
  });
});
```

---

## Layer 5: Ambient Text Glimmer

```css
@keyframes textGlimmer {
  0%, 100% { text-shadow: 0 0 8px rgba(136, 85, 204, 0.0); }
  50%       { text-shadow: 0 0 18px rgba(136, 85, 204, 0.5), 0 0 40px rgba(80, 40, 120, 0.2); }
}

.glimmer {
  animation: textGlimmer 4s ease-in-out infinite;
  animation-play-state: paused;
}
```

```js
document.querySelectorAll('h1, h2, h3').forEach(heading => {
  heading.classList.add('glimmer');
  ScrollTrigger.create({
    trigger: heading,
    start: 'top 85%',
    once: true,
    onEnter: () => { heading.style.animationPlayState = 'running'; },
  });
});
```

---

## Layer 6: Scroll Progress Bar

```html
<div id="scroll-progress" style="
  position: fixed; top: 0; left: 0;
  width: 0%; height: 2px;
  background: linear-gradient(90deg, #4422aa, #8855cc, #cc44aa);
  z-index: 9999; pointer-events: none;
"></div>
```

```js
gsap.to('#scroll-progress', {
  width: '100%',
  ease: 'none',
  scrollTrigger: { start: 'top top', end: 'bottom bottom', scrub: 0.3 },
});
```

---

## CSS Depth Without JavaScript

```css
/* Vignette — draws focus to center content */
body::after {
  content: '';
  position: fixed; inset: 0;
  background: radial-gradient(
    ellipse 80% 80% at 50% 50%,
    transparent 50%,
    rgba(0, 0, 0, 0.45) 100%
  );
  pointer-events: none;
  z-index: 9998;
}

/* Card border glow on hover */
.your-card-class {
  border: 1px solid rgba(136, 85, 204, 0.12);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.4),
              inset 0 1px 0 rgba(255,255,255,0.04);
  transition: border-color 0.3s, box-shadow 0.3s;
}

.your-card-class:hover {
  border-color: rgba(136, 85, 204, 0.4);
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6),
              0 0 20px rgba(136, 85, 204, 0.12),
              inset 0 1px 0 rgba(255,255,255,0.06);
}
```

---

## Performance Rules

| Rule | Reason |
|------|--------|
| Only animate `transform` and `opacity` | GPU composited — skip layout/paint |
| Set `will-change: transform` on tilt cards | Pre-promotes to compositor layer |
| Use `scrub` not `onScroll` callbacks | GSAP batches scrub updates efficiently |
| Cap `devicePixelRatio` at 1.5 for Three.js | Halves fill-rate cost on Retina |
| Use `once: true` on entrance animations | Prevents re-triggering on scroll back |
| Avoid animating `width`, `height`, `top`, `left` | These trigger layout recalculation |

---

## Recommended Implementation Order

Apply in order — each layer is useful on its own:

1. **CSS depth** (vignette, card borders) — 10 min, zero risk
2. **Scroll progress bar** — 5 min, immediate payoff
3. **Text glimmer** — 10 min, atmospheric without distraction
4. **Scroll reveals** — 30 min, biggest quality impact
5. **Mouse parallax on cards** — 20 min, makes page feel interactive
6. **Parallax scroll depth** — 20 min, requires markup tagging
7. **WebGL atmospheric background** — 45 min, maximum atmosphere

---

## Color Palette Reference

```
Deep violet (primary glow):  #8855cc  / rgba(136, 85, 204, ...)
Dark indigo (accent):        #4422aa  / rgba(68, 34, 170, ...)
Magenta accent:              #cc44aa  / rgba(204, 68, 170, ...)
Shadow base:                 rgba(0, 0, 0, 0.4–0.6)
```
