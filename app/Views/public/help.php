<main id="main-content" tabindex="-1" class="help-page" data-reading-page data-animate-disclosures>
  <header class="help-heading"><p class="eyebrow">A LITTLE MORE CLARITY</p><h1>Here to help.</h1><p>Shopping, your account, and how we care for your information.</p></header>
  <div class="help-layout">
    <nav class="help-navigation" aria-label="Help topics">
      <a href="#shopping">Shopping &amp; your account</a><a href="#delivery">Delivery &amp; orders</a><a href="#returns">Returns &amp; refunds</a><a href="#hair-care">Your hair &amp; routine</a><a href="#terms">Terms of service</a><a href="#privacy-policy">Privacy policy</a>
    </nav>
    <div class="help-content">
      <section id="shopping" class="help-section"><h2>Shopping &amp; your account</h2>
        <details><summary>How do I place an order?</summary><div><p>Explore the collection, open a product for its details, and add available items to your bag. Sign in to continue to checkout, then review your delivery information, available payment options and the final total before placing your order.</p><a class="text-link" href="<?= e(url('/shop')) ?>">Explore the collection</a></div></details>
        <details><summary>Can I change my account details?</summary><div><p>Open Profile and choose Edit. Your username must be unique and contain 4–40 letters, numbers, dots, underscores or hyphens. Changing your email requires a verification code sent to your current email address before the change is saved.</p><a class="text-link" href="<?= e(url('/profile')) ?>">Your profile</a></div></details>
        <details><summary>How can I protect my account?</summary><div><p>Use a unique password that satisfies the requirements shown on screen. Account Settings provides password changes and authenticator-based 2-step verification. Never share verification or recovery codes. Account activation and any required verification remain part of signing in.</p><a class="text-link" href="<?= e(url('/settings')) ?>">Account Settings</a></div></details>
      </section>
      <section id="delivery" class="help-section"><h2>Delivery &amp; orders</h2>
        <details><summary>Where can I see delivery charges?</summary><div><p>Enter your complete delivery address at checkout. The available shipping options and their charges are included in the total before you submit the order. Delivery availability follows the options actually offered for your address.</p></div></details>
        <details><summary>How do I follow my order?</summary><div><p>Open Orders in your account and select an order. Its timeline reflects saved fulfillment updates: confirmed, shipped, out for delivery and delivered. It is not live courier tracking, and an unrecorded stage is not an estimated delivery promise.</p><a class="text-link" href="<?= e(url('/orders')) ?>">Your orders</a></div></details>
        <details><summary>What does a sandbox payment mean?</summary><div><p>A sandbox checkout is a demonstration payment flow, not evidence of a real bank settlement. Only use the payment options and disclosures shown at checkout. A return to the website alone does not confirm payment; the application checks the provider's recorded payment result.</p></div></details>
      </section>
      <section id="returns" class="help-section"><h2>Returns &amp; refunds</h2><div class="help-policy"><?php $policyKind='refund'; $policyEmbedded=true; require dirname(__DIR__).'/partials/policy_content.php'; ?></div></section>
      <section id="hair-care" class="help-section"><h2>Your hair &amp; routine</h2>
        <details><summary>How are my matches chosen?</summary><div><p>Your selected answers, supported concern wording and reviewed product attributes inform explainable matching rules. You can see matching reasons and save your chosen routine. Suggestions support cosmetic product discovery; they do not diagnose or treat medical conditions.</p></div></details>
        <details><summary>Will retaking the assessment replace my profile?</summary><div><p>Your existing profile stays as it is until you save. You can review and edit answers before submission. Answers and saved routines belong to your signed-in account.</p><a class="text-link" href="<?= e(url('/assessment')) ?>">Hair Profile</a></div></details>
      </section>
      <section id="terms" class="help-section"><h2>Terms of service</h2><div class="help-policy"><?php $policyKind='terms'; $policyEmbedded=true; require dirname(__DIR__).'/partials/policy_content.php'; ?></div></section>
      <section id="privacy-policy" class="help-section"><?php $privacyEmbedded=true; require dirname(__DIR__).'/partials/privacy_content.php'; ?></section>
    </div>
  </div>
</main>

<script src="<?= e(asset('js/reading.js')) ?>" defer></script>
