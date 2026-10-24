<?php
$currentUser = auth_user();
$currentRole = $currentUser['role'] ?? null;
$assessmentUrl = $currentRole === 'customer' ? url('/assessment') : url('/register');
?>
<main id="main-content" tabindex="-1" class="about-page">
  <section class="about-hero" aria-labelledby="about-title">
    <div class="about-hero-copy">
      <span class="eyebrow">ABOUT AVELA</span>
      <h1 id="about-title">Thoughtful care.<br>Individual hair.</h1>
      <p>Find hair care with a little more clarity. Explore products, understand why they might suit you, and build a routine that feels like yours.</p>
    </div>
    <figure class="about-product-photo"><img src="<?= e(asset('images/editorial/about-portrait.jpg')) ?>" alt="Black-and-white portrait with long hair moving across the face" width="2080" height="3241" loading="eager" decoding="async"></figure>
  </section>

  <section class="about-section about-principles" aria-labelledby="about-how-title">
    <div class="about-section-heading">
      <span class="eyebrow">HOW AVELA WORKS</span>
      <h2 id="about-how-title">Browse, compare, and keep track.</h2>
    </div>
    <div class="about-step-grid">
      <article>
        <span>01</span>
        <h3>Browse</h3>
        <p>Explore products by category, hair texture and the concerns that matter to you.</p>
      </article>
      <article>
        <span>02</span>
        <h3>Compare</h3>
        <p>Compare descriptions, prices and availability. Save favorites to come back to when you are ready.</p>
      </article>
      <article>
        <span>03</span>
        <h3>Order</h3>
        <p>Review your bag and delivery details at Checkout. Your account keeps an order confirmation and history.</p>
      </article>
    </div>
  </section>

  <section id="our-approach" class="about-section about-balance" aria-labelledby="about-purpose-title">
    <div>
      <span class="eyebrow">THE IDEA</span>
      <h2 id="about-purpose-title">A more personal shortlist, with clear reasons.</h2>
    </div>
    <div><img class="about-texture" src="<?= e(asset('images/editorial/about-texture.jpg')) ?>" alt="An editorial cream-texture study, not an Avela formulation" width="1200" height="1277" loading="lazy" decoding="async"><p>Start with your hair texture, scalp and lengths. Your answers shape a shortlist with clear reasons for each match. Choose what fits your day, and revisit your routine whenever you need to.</p></div>
  </section>

  <section class="about-cta">
    <div>
      <span class="eyebrow">START WHERE YOU WANT</span>
      <h2>Find your next everyday essential.</h2>
    </div>
    <div class="about-cta-actions">
      <a class="btn btn-primary" href="<?= e(url('/shop')) ?>">Shop hair care</a>
      <?php if ($currentRole === 'customer' || $currentRole === null): ?><a class="btn btn-secondary" href="<?= e($assessmentUrl) ?>"><?= $currentRole === 'customer' ? 'Your hair profile' : 'Create an account' ?></a><?php endif; ?>
    </div>
  </section>
</main>
