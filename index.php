<?php
declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| JO Shop - Single File Shop Frontend
| - Public product display
| - Horizontal scrollable products with NEXT / PREV
| - ADMIN menu links to login.php
| - If admin session exists, NEW ITEM / UPDATE / DELETE controls become visible
|--------------------------------------------------------------------------
*/

const JO_DB = __DIR__ . '/shop.sqlite';

function joDb(): PDO
{
    $pdo = new PDO('sqlite:' . JO_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function joInitDb(): void
{
    $pdo = joDb();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            short_description TEXT NOT NULL,
            full_description TEXT NOT NULL,
            price REAL NOT NULL,
            category TEXT NOT NULL,
            image_path TEXT NOT NULL,
            stock_quantity INTEGER NOT NULL DEFAULT 0,
            is_featured INTEGER NOT NULL DEFAULT 0,
            is_published INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
    ");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['AI Notebook', 'Smart planning notebook', 'A premium notebook for ideas and planning.', 24.99, 'Stationery', 'https://via.placeholder.com/800x600?text=AI+Notebook', 10, 1, 1],
            ['Black Hoodie', 'Minimal premium hoodie', 'Heavyweight hoodie with a clean modern fit.', 49.99, 'Apparel', 'https://via.placeholder.com/800x600?text=Black+Hoodie', 8, 1, 1],
            ['Desk Lamp', 'Focused work lighting', 'A sleek desk lamp for coding and reading.', 39.50, 'Office', 'https://via.placeholder.com/800x600?text=Desk+Lamp', 5, 0, 1],
            ['Wireless Mouse', 'Comfort and control', 'Ergonomic mouse for long productive sessions.', 29.00, 'Tech', 'https://via.placeholder.com/800x600?text=Wireless+Mouse', 15, 0, 1],
            ['Travel Bottle', 'Insulated steel bottle', 'Keeps drinks hot or cold throughout the day.', 19.95, 'Lifestyle', 'https://via.placeholder.com/800x600?text=Travel+Bottle', 20, 0, 1],
            ['Developer Backpack', 'Commuter-ready bag', 'A stylish laptop backpack with smart compartments.', 74.00, 'Accessories', 'https://via.placeholder.com/800x600?text=Backpack', 3, 1, 1]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO products
            (title, slug, short_description, full_description, price, category, image_path, stock_quantity, is_featured, is_published, created_at, updated_at)
            VALUES
            (:title, :slug, :short_description, :full_description, :price, :category, :image_path, :stock_quantity, :is_featured, :is_published, :created_at, :updated_at)
        ");

        foreach ($seed as $item) {
            [$title, $short, $full, $price, $category, $image, $stock, $featured, $published] = $item;
            $now = date('c');
            $stmt->execute([
                ':title' => $title,
                ':slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')),
                ':short_description' => $short,
                ':full_description' => $full,
                ':price' => $price,
                ':category' => $category,
                ':image_path' => $image,
                ':stock_quantity' => $stock,
                ':is_featured' => $featured,
                ':is_published' => $published,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }
}

function joCsrfToken(): string
{
    if (!isset($_SESSION['_token'])) {
        $_SESSION['_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_token'];
}

function joCsrfValid(?string $token): bool
{
    return isset($_SESSION['_token']) && is_string($token) && hash_equals($_SESSION['_token'], $token);
}

function joIsAdmin(): bool
{
    return !empty($_SESSION['admin_logged_in']) && ($_SESSION['admin_email'] ?? '') === 'admin@shop.com';
}

function joSafeFileName(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'file';
    return trim($name, '._') ?: 'file';
}

function joUploadImage(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return null;
    }

    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $name = date('YmdHis') . '_' . joSafeFileName((string)$file['name']);
    $target = $dir . '/' . $name;

    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        return null;
    }

    return 'uploads/' . $name;
}

function joAllProducts(): array
{
    $pdo = joDb();
    $stmt = $pdo->query("SELECT * FROM products WHERE is_published = 1 ORDER BY is_featured DESC, id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function joFindProduct(int $id): ?array
{
    $pdo = joDb();
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

joInitDb();

$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && joIsAdmin()) {
    if (!joCsrfValid($_POST['_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $pdo = joDb();
        $action = (string)($_POST['action'] ?? '');

        try {
            if ($action === 'new_item') {
                $title = trim((string)($_POST['title'] ?? ''));
                if ($title === '') {
                    throw new RuntimeException('Title is required.');
                }

                $imagePath = joUploadImage($_FILES['image'] ?? []) ?: 'https://via.placeholder.com/800x600?text=Product';

                $stmt = $pdo->prepare("
                    INSERT INTO products
                    (title, slug, short_description, full_description, price, category, image_path, stock_quantity, is_featured, is_published, created_at, updated_at)
                    VALUES
                    (:title, :slug, :short_description, :full_description, :price, :category, :image_path, :stock_quantity, :is_featured, :is_published, :created_at, :updated_at)
                ");

                $slugBase = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
                $slug = $slugBase ?: 'item-' . time();
                $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = :slug");
                $i = 2;
                while (true) {
                    $check->execute([':slug' => $slug]);
                    if ((int)$check->fetchColumn() === 0) {
                        break;
                    }
                    $slug = $slugBase . '-' . $i;
                    $i++;
                }

                $now = date('c');
                $stmt->execute([
                    ':title' => $title,
                    ':slug' => $slug,
                    ':short_description' => trim((string)($_POST['short_description'] ?? '')),
                    ':full_description' => trim((string)($_POST['full_description'] ?? '')),
                    ':price' => (float)($_POST['price'] ?? 0),
                    ':category' => trim((string)($_POST['category'] ?? 'General')),
                    ':image_path' => $imagePath,
                    ':stock_quantity' => max(0, (int)($_POST['stock_quantity'] ?? 0)),
                    ':is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                    ':is_published' => isset($_POST['is_published']) ? 1 : 0,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);

                $flash = 'New item created.';
            }

            if ($action === 'update_item') {
                $id = (int)($_POST['id'] ?? 0);
                $existing = joFindProduct($id);
                if (!$existing) {
                    throw new RuntimeException('Product not found.');
                }

                $imagePath = $existing['image_path'];
                $uploaded = joUploadImage($_FILES['image'] ?? []);
                if ($uploaded) {
                    $imagePath = $uploaded;
                }

                $stmt = $pdo->prepare("
                    UPDATE products SET
                        title = :title,
                        short_description = :short_description,
                        full_description = :full_description,
                        price = :price,
                        category = :category,
                        image_path = :image_path,
                        stock_quantity = :stock_quantity,
                        is_featured = :is_featured,
                        is_published = :is_published,
                        updated_at = :updated_at
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':title' => trim((string)($_POST['title'] ?? $existing['title'])),
                    ':short_description' => trim((string)($_POST['short_description'] ?? $existing['short_description'])),
                    ':full_description' => trim((string)($_POST['full_description'] ?? $existing['full_description'])),
                    ':price' => (float)($_POST['price'] ?? $existing['price']),
                    ':category' => trim((string)($_POST['category'] ?? $existing['category'])),
                    ':image_path' => $imagePath,
                    ':stock_quantity' => max(0, (int)($_POST['stock_quantity'] ?? $existing['stock_quantity'])),
                    ':is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                    ':is_published' => isset($_POST['is_published']) ? 1 : 0,
                    ':updated_at' => date('c'),
                    ':id' => $id,
                ]);

                $flash = 'Item updated.';
            }

            if ($action === 'delete_item') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $flash = 'Item deleted.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$products = joAllProducts();
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editProduct = $editId ? joFindProduct($editId) : null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>JO Shop</title>

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
    html{scroll-behavior:smooth;}
    body{background:var(--jo-bg);color:var(--jo-text);}
    .jo-nav{background:rgba(0,0,0,.82);backdrop-filter:blur(10px);border-bottom:1px solid var(--jo-border);}
    .jo-card{background:var(--jo-card);border:1px solid var(--jo-border);border-radius:18px;backdrop-filter:blur(8px);}
    .btn-jo{background:var(--jo-green)!important;color:#000!important;font-weight:800!important;border:none!important;}
    .btn-outline-jo{border:1px solid var(--jo-green)!important;color:var(--jo-green)!important;background:transparent!important;font-weight:800!important;}
    .btn-outline-jo:hover{background:rgba(0,200,83,.14)!important;color:#fff!important;}
    .jo-muted{color:rgba(255,255,255,.72);}
    .jo-link{color:#fff;text-decoration:none;}
    .jo-link:hover{color:var(--jo-green);}
    .product-strip{display:flex;gap:1rem;overflow-x:auto;scroll-behavior:smooth;padding-bottom:.5rem;}
    .product-strip::-webkit-scrollbar{height:10px;}
    .product-strip::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:999px;}
    .product-card{min-width:320px;max-width:320px;}
    .product-img{height:220px;object-fit:cover;border-radius:14px;}
    input,textarea,select{background:rgba(255,255,255,.06)!important;color:#fff!important;border:1px solid rgba(255,255,255,.16)!important;}
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg jo-nav sticky-top">
  <div class="container">
    <a class="navbar-brand text-white fw-bold" href="#">
      <i class="fa-solid fa-bag-shopping me-2 text-success"></i>JO Shop
    </a>

    <button class="navbar-toggler text-white" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <i class="fa-solid fa-bars"></i>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto gap-2 align-items-lg-center">
        <li class="nav-item"><a class="nav-link text-white" href="#shop">Shop</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="#cart">Cart <span class="badge bg-success text-dark" id="cartCount">0</span></a></li>
        <li class="nav-item"><a class="btn btn-outline-jo btn-sm" href="login.php">ADMIN</a></li>
      </ul>
    </div>
  </div>
</nav>

<header class="container py-5">
  <div class="jo-card p-4 p-lg-5">
    <div class="small text-success fw-bold mb-2">JO Shop</div>
    <h1 class="display-5 fw-bold">Scrollable product showcase</h1>
    <p class="jo-muted mb-4">Products scroll left to right. Use NEXT and PREV to browse. Admin actions become visible only after successful login.</p>

    <?php if ($flash): ?>
      <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (joIsAdmin()): ?>
      <div class="alert alert-success mb-0">
        Admin session active. NEW ITEM, UPDATE and DELETE controls are now visible.
      </div>
    <?php endif; ?>
  </div>
</header>

<?php if (joIsAdmin()): ?>
<section class="container pb-4">
  <div class="jo-card p-4">
    <h2 class="h4 mb-3"><?= $editProduct ? 'Update Item' : 'New Item' ?></h2>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_token" value="<?= htmlspecialchars(joCsrfToken()) ?>">
      <input type="hidden" name="action" value="<?= $editProduct ? 'update_item' : 'new_item' ?>">
      <?php if ($editProduct): ?>
        <input type="hidden" name="id" value="<?= (int)$editProduct['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editProduct['title'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Price</label>
          <input type="number" step="0.01" name="price" class="form-control" required value="<?= htmlspecialchars((string)($editProduct['price'] ?? '0')) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Stock</label>
          <input type="number" name="stock_quantity" class="form-control" required value="<?= htmlspecialchars((string)($editProduct['stock_quantity'] ?? '0')) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" required value="<?= htmlspecialchars($editProduct['category'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Image</label>
          <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        </div>

        <div class="col-12">
          <label class="form-label">Short Description</label>
          <textarea name="short_description" class="form-control" rows="2" required><?= htmlspecialchars($editProduct['short_description'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Full Description</label>
          <textarea name="full_description" class="form-control" rows="4" required><?= htmlspecialchars($editProduct['full_description'] ?? '') ?></textarea>
        </div>

        <div class="col-md-3">
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" <?= !empty($editProduct['is_featured']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_featured">Featured</label>
          </div>
        </div>

        <div class="col-md-3">
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_published" id="is_published" <?= !isset($editProduct) || !empty($editProduct['is_published']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_published">Published</label>
          </div>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-jo"><?= $editProduct ? 'UPDATE ITEM' : 'NEW ITEM' ?></button>
          <?php if ($editProduct): ?>
            <a href="index.php" class="btn btn-outline-jo">Cancel</a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
</section>
<?php endif; ?>

<section id="shop" class="container pb-5">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="h3 mb-0">Products</h2>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-jo btn-sm" type="button" onclick="scrollProducts(-1)">PREV</button>
      <button class="btn btn-jo btn-sm" type="button" onclick="scrollProducts(1)">NEXT</button>
    </div>
  </div>

  <div id="productStrip" class="product-strip">
    <?php foreach ($products as $product): ?>
      <div class="jo-card p-3 product-card d-flex flex-column">
        <img src="<?= htmlspecialchars($product['image_path']) ?>" class="w-100 product-img mb-3" alt="<?= htmlspecialchars($product['title']) ?>">

        <div class="d-flex justify-content-between align-items-start mb-2">
          <h3 class="h5 mb-0"><?= htmlspecialchars($product['title']) ?></h3>
          <?php if ((int)$product['stock_quantity'] === 0): ?>
            <span class="badge bg-danger">Out</span>
          <?php elseif ((int)$product['stock_quantity'] <= 5): ?>
            <span class="badge bg-warning text-dark">Low</span>
          <?php else: ?>
            <span class="badge bg-success text-dark">In</span>
          <?php endif; ?>
        </div>

        <div class="small jo-muted mb-2"><?= htmlspecialchars($product['category']) ?></div>
        <p class="jo-muted"><?= htmlspecialchars($product['short_description']) ?></p>
        <div class="small jo-muted mb-3">Stock: <?= (int)$product['stock_quantity'] ?></div>

        <div class="mt-auto d-flex justify-content-between align-items-center flex-wrap gap-2">
          <strong>£<?= number_format((float)$product['price'], 2) ?></strong>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-jo btn-sm" data-bs-toggle="modal" data-bs-target="#productModal<?= (int)$product['id'] ?>">View</button>
            <button class="btn btn-jo btn-sm" onclick='addToCart(<?= json_encode($product, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>Add</button>

            <?php if (joIsAdmin()): ?>
              <a href="index.php?edit=<?= (int)$product['id'] ?>" class="btn btn-outline-warning btn-sm">UPDATE</a>
              <form method="post" class="d-inline">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(joCsrfToken()) ?>">
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this item?')">DELETE</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="modal fade" id="productModal<?= (int)$product['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content bg-black border border-secondary">
            <div class="modal-header border-secondary">
              <h5 class="modal-title text-white"><?= htmlspecialchars($product['title']) ?></h5>
              <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <img src="<?= htmlspecialchars($product['image_path']) ?>" class="w-100 rounded mb-3" alt="<?= htmlspecialchars($product['title']) ?>">
              <div class="small jo-muted mb-2"><?= htmlspecialchars($product['category']) ?></div>
              <p><?= nl2br(htmlspecialchars($product['full_description'])) ?></p>
              <div class="d-flex justify-content-between align-items-center">
                <strong>£<?= number_format((float)$product['price'], 2) ?></strong>
                <button class="btn btn-jo btn-sm" onclick='addToCart(<?= json_encode($product, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>Add to Cart</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section id="cart" class="container pb-5">
  <div class="jo-card p-4">
    <h2 class="h4 mb-3">Cart</h2>
    <div id="cartItems" class="jo-muted">Your cart is empty.</div>
    <div class="d-flex justify-content-between mt-3">
      <strong>Total</strong>
      <strong id="cartTotal">£0.00</strong>
    </div>
  </div>
</section>

<footer class="container py-4 border-top border-secondary">
  <a class="jo-link" href="https://www.raiiarcomio.com" target="_self" rel="noreferrer">
    Another Website by Julius Olatokunbo
  </a>
<div style="float: right;">
      <li class="nav-item"><a class="nav-link text-white" href="#shop">Shop</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="#cart">Cart <span class="badge bg-success text-dark" id="cartCount">0</span></a></li>
        <li class="nav-item"><a class="btn btn-outline-jo btn-sm" href="login.php">ADMIN</a></li>
</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CART_KEY = 'jo_shop_cart_v2';

function scrollProducts(direction) {
  const strip = document.getElementById('productStrip');
  const amount = 360;
  strip.scrollBy({ left: direction * amount, behavior: 'smooth' });
}

function loadCart() {
  try {
    return JSON.parse(localStorage.getItem(CART_KEY) || '[]');
  } catch {
    return [];
  }
}

function saveCart(cart) {
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
}

function addToCart(product) {
  const cart = loadCart();
  const found = cart.find(item => Number(item.id) === Number(product.id));

  if (found) {
    found.qty += 1;
  } else {
    cart.push({
      id: product.id,
      title: product.title,
      price: Number(product.price),
      qty: 1
    });
  }

  saveCart(cart);
  renderCart();
}

function removeFromCart(id) {
  const cart = loadCart().filter(item => Number(item.id) !== Number(id));
  saveCart(cart);
  renderCart();
}

function changeQty(id, delta) {
  const cart = loadCart();
  const item = cart.find(i => Number(i.id) === Number(id));
  if (!item) return;

  item.qty += delta;
  if (item.qty <= 0) {
    saveCart(cart.filter(i => Number(i.id) !== Number(id)));
  } else {
    saveCart(cart);
  }
  renderCart();
}

function renderCart() {
  const cart = loadCart();
  const cartItems = document.getElementById('cartItems');
  const cartTotal = document.getElementById('cartTotal');
  const cartCount = document.getElementById('cartCount');

  cartCount.textContent = cart.reduce((sum, item) => sum + item.qty, 0);

  if (!cart.length) {
    cartItems.innerHTML = '<div class="jo-muted">Your cart is empty.</div>';
    cartTotal.textContent = '£0.00';
    return;
  }

  let total = 0;
  cartItems.innerHTML = cart.map(item => {
    const row = item.price * item.qty;
    total += row;
    return `
      <div class="jo-card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <div class="fw-bold">${item.title}</div>
            <div class="small jo-muted">£${item.price.toFixed(2)}</div>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-outline-jo btn-sm" onclick="changeQty(${item.id}, -1)">-</button>
            <span>${item.qty}</span>
            <button class="btn btn-outline-jo btn-sm" onclick="changeQty(${item.id}, 1)">+</button>
            <button class="btn btn-outline-danger btn-sm" onclick="removeFromCart(${item.id})">Remove</button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  cartTotal.textContent = '£' + total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', renderCart);
</script>
</body>
</html>