<?php
$currentUser = auth_user();
$currentRole = $currentUser['role'] ?? null;
$assessmentUrl = $currentRole === 'customer' ? url('/assessment') : ($currentRole === null ? url('/register') : url('/shop'));
?>
<main class="about-page">
  <section class="about-hero" aria-labelledby="about-title">
    <div class="about-hero-copy">
      <span class="eyebrow">ABOUT AVELA</span>
      <h1 id="about-title">Hair care shopping, with a little more direction.</h1>
      <p>Avela is a hair care e-commerce system designed to make everyday product discovery clearer. You can browse the catalog normally, then use your hair profile when you want a more focused shortlist.</p>
    </div>
    <div class="about-hero-note" aria-label="Avela approach">
      <span>01</span>
      <strong>Shop first.</strong>
      <p>Browse categories, products, and availability like a regular online store.</p>
    </div>
  </section>

  <section class="about-section about-principles" aria-labelledby="about-how-title">
    <div class="about-section-heading">
      <span class="eyebrow">HOW AVELA WORKS</span>
      <h2 id="about-how-title">Simple when you want it. Personal when you need it.</h2>
    </div>
    <div class="about-step-grid">
      <article>
        <span>01</span>
        <h3>Browse</h3>
        <p>Explore hair care products by category and the details already stored in the catalog.</p>
      </article>
      <article>
        <span>02</span>
        <h3>Understand</h3>
        <p>Use the optional hair assessment to record texture, scalp type, condition, and concerns.</p>
      </article>
      <article>
        <span>03</span>
        <h3>Narrow</h3>
        <p>Use those profile details to support clearer product matching and routine building.</p>
      </article>
    </div>
  </section>

  <section class="about-section about-balance" aria-labelledby="about-purpose-title">
    <div>
      <span class="eyebrow">THE IDEA</span>
      <h2 id="about-purpose-title">Personalization supports the store. It does not replace it.</h2>
    </div>
    <p>Avela remains usable even if you skip the assessment. The personalization features are there to make a large catalog easier to navigate when you want help deciding what may fit your hair needs.</p>
  </section>

  <section class="about-cta">
    <div>
      <span class="eyebrow">START WHERE YOU WANT</span>
      <h2>Browse the catalog or build your hair profile.</h2>
    </div>
    <div class="about-cta-actions">
      <a class="btn btn-primary" href="<?= e(url('/shop')) ?>">Shop hair care</a>
      <a class="btn btn-secondary" href="<?= e($assessmentUrl) ?>">Take hair assessment</a>
    </div>
  </section>
</main>
