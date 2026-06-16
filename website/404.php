<?php
http_response_code(404);
$page_title       = 'Lost in the Waiting Plains';
$page_description = 'The path you seek does not exist in these lands. Even Aragorn cannot find it.';
require_once __DIR__ . '/includes/header.php';
?>
<main class="error-page">
  <div class="error-inner">
    <div class="error-symbol" aria-hidden="true">⚔</div>
    <h1 class="error-title">Lost in the Waiting Plains</h1>
    <p class="error-body">
      The road you seek does not appear on any map of the realm.<br>
      Even Aragorn, who has walked every Heaven from the First Gate to the Tenth,<br>
      cannot find the path you named.
    </p>
    <p class="error-code">Error 404 — The Page Has Not Yet Been Forged</p>
    <div class="error-actions">
      <a href="/" class="btn btn-primary">Return to the Realm</a>
      <a href="/chronicle.php" class="btn btn-secondary">Read the Chronicle</a>
    </div>
  </div>
</main>

<style>
.error-page {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}
.error-inner {
  max-width: 600px;
}
.error-symbol {
  font-size: 5rem;
  margin-bottom: 1.5rem;
  opacity: .35;
  color: var(--gold);
}
.error-title {
  font-family: 'Cinzel', 'Palatino Linotype', serif;
  font-size: clamp(1.8rem, 5vw, 3rem);
  color: var(--gold);
  margin-bottom: 1.5rem;
}
.error-body {
  color: var(--cream);
  opacity: .75;
  line-height: 1.8;
  margin-bottom: 1rem;
}
.error-code {
  font-size: .8rem;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--gold);
  opacity: .4;
  margin-bottom: 2.5rem;
}
.error-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
