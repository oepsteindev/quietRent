<?php

use QuietRent\Core\{Vite, Auth};
$csrfToken = Auth::csrf();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
  <title>Quiet Rent</title>
  <?= Vite::tags('src/main.js') ?>
</head>
<body>
  <div id="app"></div>
</body>
</html>
