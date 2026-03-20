<?php
declare(strict_types=1);

session_start();

const JO_ADMIN_EMAIL = 'admin@shop.com';
const JO_ADMIN_PASSWORD = 'p4ssw0rd';

if (!isset($_SESSION['_token'])) {
    $_SESSION['_token'] = bin2hex(random_bytes(16));
}

function joCsrfValid(?string $token): bool
{
    return isset($_SESSION['_token']) && is_string($token) && hash_equals($_SESSION['_token'], $token);
}

function joAlreadyLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in']) && ($_SESSION['admin_email'] ?? '') === JO_ADMIN_EMAIL;
}

$error = null;
$success = null;

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_email']);
    $success = 'You have been logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!joCsrfValid($_POST['_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === JO_ADMIN_EMAIL && hash_equals(JO_ADMIN_PASSWORD, $password)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = $email;
            header('Location: index.php');
            exit;
        }

        $error = 'Invalid admin credentials.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>JO Shop Admin Login</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root{
      --jo-bg:#000;
      --jo-text:#fff;
      --jo-green:#00c853;
      --jo-border:rgba(255,255,255,.12);
      --jo-card:rgba(255,255,255,.05);
    }
    body{background:var(--jo-bg);color:var(--jo-text);min-height:100vh;}
    .jo-card{background:var(--jo-card);border:1px solid var(--jo-border);border-radius:18px;backdrop-filter:blur(8px);}
    .btn-jo{background:var(--jo-green)!important;color:#000!important;font-weight:800!important;border:none!important;}
    .btn-outline-jo{border:1px solid var(--jo-green)!important;color:var(--jo-green)!important;background:transparent!important;font-weight:800!important;}
    .btn-outline-jo:hover{background:rgba(0,200,83,.14)!important;color:#fff!important;}
    input{background:rgba(255,255,255,.06)!important;color:#fff!important;border:1px solid rgba(255,255,255,.16)!important;}
    .jo-muted{color:rgba(255,255,255,.72);}
    .jo-link{color:#fff;text-decoration:none;}
    .jo-link:hover{color:var(--jo-green);}
  </style>
</head>
<body class="d-flex align-items-center">
  <div class="container" style="max-width:560px;">
    <div class="jo-card p-4 p-lg-5">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="fa-solid fa-lock text-success"></i>
        <h1 class="h3 mb-0">Admin Login</h1>
      </div>

      <p class="jo-muted">
        Login with the admin account. Once authenticated, the hidden buttons on <code>index.php</code> become visible:
        <strong>NEW ITEM</strong>, <strong>UPDATE</strong>, and <strong>DELETE</strong>.
      </p>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (joAlreadyLoggedIn()): ?>
        <div class="alert alert-success">Admin session is already active.</div>
        <div class="d-flex gap-2">
          <a href="index.php" class="btn btn-jo">Go to Shop</a>
          <a href="login.php?logout=1" class="btn btn-outline-jo">Logout</a>
        </div>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['_token']) ?>">

          <div class="mb-3">
            <label class="form-label">Admin Email</label>
            <input type="email" name="email" class="form-control" value="admin@shop.com" required>
          </div>

          <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" value="p4ssw0rd" required>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-jo">Login</button>
            <a href="index.php" class="btn btn-outline-jo">Back to Shop</a>
          </div>
        </form>
      <?php endif; ?>

      <div class="mt-4 pt-3 border-top border-secondary">
        <a class="jo-link" href="https://www.raiiarcomio.com" target="_self" rel="noreferrer">
          Another Website by Julius Olatokunbo
        </a>
      </div>
    </div>
  </div>
</body>
</html>