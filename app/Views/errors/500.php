<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Error · Avela</title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="plain-body">
  <main class="plain-card">
    <div class="error-page">
      <span class="eyebrow">ERROR</span>
      <h1><?= e($pageTitle ?? 'Something went wrong') ?></h1>
      <p><?= e($message ?? 'Please try again.') ?></p>
      <a class="btn btn-primary" href="<?= e(url('/')) ?>">Return to Avela</a>
    </div>
  </main>
</body>
</html>
