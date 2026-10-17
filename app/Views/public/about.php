<?php
$currentUser = auth_user();
$currentRole = $currentUser['role'] ?? null;
$assessmentUrl = $currentRole === 'customer' ? url('/assessment') : url('/register');
?>
<main id="main-content" tabindex="-1" class="about-page">
  <section class="about-hero" aria-labelledby="about-title">
    <div class="about-hero-copy">
      <span class="eyebrow">ABOUT AVELA</span>
      <h1 id="about-title">Hair care shopping, with a little more direction.</h1>
      <p>Avela is a hair care e-commerce system designed to make everyday product discovery clearer. Browse by category, compare product details, save favorites and place a demonstration Cash on Delivery order.</p>
    </div>
    <figure class="about-product-photo"><img src="<?= e(asset('images/products/demo/avela-ave-smp-002.webp')) ?>" alt="Approved Avela Everyday Fresh Shampoo demonstration packaging" width="1200" height="1500" loading="eager" decoding="async"></figure>
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
        <p>Explore hair care products by category and the details already stored in the catalog.</p>
      </article>
      <article>
        <span>02</span>
        <h3>Compare</h3>
        <p>Check descriptions, prices, availability and catalog suitability tags, then save favorites to revisit.</p>
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
    <div><img class="about-texture" src="<?= e(asset('images/editorial/about-texture.jpg')) ?>" alt="An editorial cream-texture study, not an Avela formulation" width="1200" height="1277" loading="lazy" decoding="async"><p>Structured hair profiles, lightweight text interpretation and explainable rules help organize a personal shortlist. The current fictional catalog uses reviewed demonstration metadata; it does not establish ingredient benefits, medical advice or product effectiveness.</p></div>
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
