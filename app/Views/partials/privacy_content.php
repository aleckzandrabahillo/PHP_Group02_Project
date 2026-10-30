  <article class="privacy-content" aria-labelledby="privacy-title">
    <span class="eyebrow">YOUR INFORMATION</span>
    <?php $privacyHeading=($privacyEmbedded??false)?'h2':'h1'; ?><<?= $privacyHeading ?> id="privacy-title">Privacy &amp; cookies</<?= $privacyHeading ?>>
    <p class="muted"><strong>Policy for review.</strong> Contact details and final terms require owner review before public commercial use.</p>

    <<?= ($privacyEmbedded??false)?'h3':'h2' ?>>What Avela collects</<?= ($privacyEmbedded??false)?'h3':'h2' ?>>
    <ul>
      <li>Account details: name, username, email address, contact number, password hash and account status.</li>
      <li>Shopping information: favorites, bag items, delivery/contact details, orders and order status. Order records retain the details used at checkout.</li>
      <li>Hair assessment: selected characteristics, concerns, treatments and an optional description, together with saved routines and product matches.</li>
      <li>An optional profile photograph, if you upload one. Uploaded photographs are served as website files and may be accessible to someone who knows their URL; avoid uploading an image you do not want shared.</li>
      <li>Security records: sign-in/verification events, network address and browser information, account-lock incidents and recorded business changes.</li>
    </ul>

    <<?= ($privacyEmbedded??false)?'h3':'h2' ?>>How the information is used</<?= ($privacyEmbedded??false)?'h3':'h2' ?>>
    <p>We use account details to authenticate you, protect access and send verification messages. Shopping and delivery information supports checkout and your order history. Hair-profile information supports explainable product matching and routine building.</p>
    <p>The optional description is processed on Avela's server by lightweight Python text rules. It is not sent to a chatbot or a trained-model provider. Describe cosmetic hair-care preferences only; do not include medical information or other sensitive details. Product suggestions are not medical advice.</p>
    <p>Available payment methods are shown at checkout. Avela does not collect or store complete payment-card numbers or card security codes.</p>

    <<?= ($privacyEmbedded??false)?'h3':'h2' ?>>Cookies and third-party services</<?= ($privacyEmbedded??false)?'h3':'h2' ?>>
    <p>Avela uses an essential session cookie to keep you signed in and support security checks and form submissions. It is HTTP-only and uses SameSite protection. HTTPS is required to protect information in transit.</p>
    <p>Google reCAPTCHA loads on configured registration and sign-in pages for bot prevention. Google may process network/browser information and use its own cookies under its <a href="https://policies.google.com/privacy" rel="noopener noreferrer">Privacy Policy</a> and <a href="https://policies.google.com/terms" rel="noopener noreferrer">Terms</a>. The configured email provider processes verification messages and recipient addresses. Avela retains verification-delivery records for security.</p>
    <p>Avela uses no optional advertising or analytics trackers. Essential cookies support account and shopping features. Any future optional tracking requires a separate choice before it is enabled.</p>
    <p>You can clear or block cookies in your browser. Blocking the essential session cookie prevents normal sign-in and protected account features.</p>

    <<?= ($privacyEmbedded??false)?'h3':'h2' ?>>Your choices and retention</<?= ($privacyEmbedded??false)?'h3':'h2' ?>>
    <p>You can edit profile details through email verification, choose whether to upload a photograph, replace a photograph you supplied, update your hair assessment and manage favorites and bag items. The assessment description is optional.</p>
    <p>The system has no fixed automatic deletion schedule or self-service account-erasure workflow. The owner must review privacy requests, necessary order/security evidence and backup handling. Historical records or backups may remain until reviewed disposal; we do not promise immediate removal from every backup.</p>
    <p>Access is limited by application roles, and recorded changes support investigation. Hosting, operational retention and any future sharing arrangements need owner review.</p>

    <<?= ($privacyEmbedded??false)?'h3':'h2' ?>>Privacy inquiries</<?= ($privacyEmbedded??false)?'h3':'h2' ?>>
    <?php if($privacyEmail): ?>
      <p>For questions about your information, contact <a href="<?= e(\App\Services\PrivacyContact::href($privacyEmail)) ?>"><?= e($privacyEmail) ?></a>. This inbox has been marked as monitored by the owner.</p>
    <?php else: ?>
      <p role="status">A monitored privacy inbox has not yet been configured. Contact details require owner confirmation; do not send personal information to an unverified address.</p>
    <?php endif; ?>
    <p>Do not send passwords, verification codes, 2-step verification setup keys, recovery codes or card details. Do not post personal information in public project issues.</p>
  </article>
