<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Avela') ?> · Avela</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="plain-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<main id="main-content" tabindex="-1" class="plain-card"><?= $content ?></main>
</body>
</html>
