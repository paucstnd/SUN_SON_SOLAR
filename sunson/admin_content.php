<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';

/* Website content: products, customer testimonials and news/blog posts shown on the public homepage. */
$auth = require_auth(['admin']);
$tab = $_GET['tab'] ?? 'products';
if (!in_array($tab, ['products', 'testimonials', 'news'], true)) { $tab = 'products'; }
$self = 'admin_content.php';
$back = $self . '?tab=' . $tab;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($back);
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save_product') {
        $cat = $_POST['category'] ?? '';
        $name = clean_text($_POST['name'] ?? '', 120);
        $desc = clean_text($_POST['description'] ?? '', 255);
        $priceRaw = trim($_POST['price'] ?? '');
        if (!in_array($cat, PRODUCT_CATEGORIES, true) || $name === '') {
            flash_error('Please choose a category and enter a product name.');
        } elseif ($priceRaw !== '' && (!is_numeric($priceRaw) || (float) $priceRaw < 0 || (float) $priceRaw > 99999999)) {
            flash_error('Please enter a valid price, or leave it empty to show "Request a quote".');
        } else {
            $price = $priceRaw === '' ? null : round((float) $priceRaw, 2);
            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE products SET category = ?, name = ?, description = ?, price = ? WHERE id = ?');
                $stmt->bind_param('sssdi', $cat, $name, $desc, $price, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO products (category, name, description, price) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssd', $cat, $name, $desc, $price);
            }
            $stmt->execute();
            $stmt->close();
            flash_success($id > 0 ? 'Product updated.' : 'Product added.');
        }
    } elseif ($action === 'toggle_product') {
        $stmt = $conn->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Product visibility changed.');
    } elseif ($action === 'delete_product') {
        $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Product deleted.');
    } elseif ($action === 'save_testimonial') {
        $name = clean_text($_POST['customer_name'] ?? '', 100);
        $loc = clean_text($_POST['location'] ?? '', 100);
        $msg = clean_text($_POST['message'] ?? '', 600);
        $pub = isset($_POST['is_published']) ? 1 : 0;
        if ($name === '' || $msg === '') {
            flash_error('Please enter the customer name and their message.');
        } else {
            $stmt = $conn->prepare('INSERT INTO testimonials (customer_name, location, message, is_published) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sssi', $name, $loc, $msg, $pub);
            $stmt->execute();
            $stmt->close();
            flash_success('Testimonial saved.');
        }
    } elseif ($action === 'toggle_testimonial') {
        $stmt = $conn->prepare('UPDATE testimonials SET is_published = 1 - is_published WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Testimonial visibility changed.');
    } elseif ($action === 'delete_testimonial') {
        $stmt = $conn->prepare('DELETE FROM testimonials WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Testimonial deleted.');
    } elseif ($action === 'save_news') {
        $title = clean_text($_POST['title'] ?? '', 150);
        $body = clean_text($_POST['body'] ?? '', 5000);
        if ($title === '' || $body === '') {
            flash_error('Please enter a title and the article text.');
        } elseif ($id > 0) {
            $stmt = $conn->prepare('UPDATE news_posts SET title = ?, body = ? WHERE id = ?');
            $stmt->bind_param('ssi', $title, $body, $id);
            $stmt->execute();
            $stmt->close();
            flash_success('Post updated.');
        } else {
            $stmt = $conn->prepare('INSERT INTO news_posts (title, body) VALUES (?, ?)');
            $stmt->bind_param('ss', $title, $body);
            $stmt->execute();
            $stmt->close();
            flash_success('Post published.');
        }
    } elseif ($action === 'toggle_news') {
        $stmt = $conn->prepare('UPDATE news_posts SET is_published = 1 - is_published WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Post visibility changed.');
    } elseif ($action === 'delete_news') {
        $stmt = $conn->prepare('DELETE FROM news_posts WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_success('Post deleted.');
    }
    redirect($back);
}

$products = $conn->query('SELECT * FROM products ORDER BY category, name')->fetch_all(MYSQLI_ASSOC);
$testimonials = $conn->query('SELECT * FROM testimonials ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);
$news = $conn->query('SELECT * FROM news_posts ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);

function content_toggle(string $action, int $id, bool $isOn, string $onLabel = 'Hide', string $offLabel = 'Show'): string {
    return '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="button button-ghost button-sm">' . ($isOn ? $onLabel : $offLabel) . '</button></form>';
}
function content_delete(string $action, int $id): string {
    return '<form method="post" class="inline-form" data-confirm="Delete this permanently?">' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="button button-danger button-sm">Delete</button></form>';
}
$json = fn(array $a) => e(json_encode($a, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));

render_header($auth, 'Website content', 'admin_content.php', 'Manage what visitors see on the public homepage.');
?>
<div class="tabs">
  <a class="tab <?= $tab === 'products' ? 'active' : '' ?>" href="?tab=products">Products</a>
  <a class="tab <?= $tab === 'testimonials' ? 'active' : '' ?>" href="?tab=testimonials">Testimonials</a>
  <a class="tab <?= $tab === 'news' ? 'active' : '' ?>" href="?tab=news">News &amp; blog</a>
  <a class="tab" href="index.php" target="_blank" rel="noopener">View homepage ↗</a>
</div>

<?php if ($tab === 'products'): ?>
<div class="card">
  <div class="card-head"><h2>Products</h2><button class="button" type="button" data-open-modal="productModal" id="newProductBtn">+ Add product</button></div>
  <?php if (!$products): ?><div class="empty">No products yet.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Category</th><th>Product</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($products as $p): ?><tr>
      <td><?= e($p['category']) ?></td>
      <td><strong><?= e($p['name']) ?></strong><span class="sub"><?= e($p['description']) ?></span></td>
      <td><?= $p['price'] !== null ? e(peso($p['price'])) : '<span class="muted">Request a quote</span>' ?></td>
      <td><?= badge($p['is_active'] ? 'active' : 'inactive') ?></td>
      <td><div class="actions">
        <button type="button" class="button button-ghost button-sm js-edit-product" data-product="<?= $json(['id' => (int) $p['id'], 'category' => $p['category'], 'name' => $p['name'], 'description' => $p['description'], 'price' => $p['price']]) ?>">Edit</button>
        <?= content_toggle('toggle_product', (int) $p['id'], (bool) $p['is_active']) ?>
        <?= content_delete('delete_product', (int) $p['id']) ?>
      </div></td>
    </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>

<div class="modal-overlay" id="productModal" role="dialog" aria-modal="true" aria-labelledby="pdT">
  <div class="modal-box">
    <div class="modal-head"><h2 id="pdT">Add product</h2><button type="button" class="modal-x" data-close-modal aria-label="Close">×</button></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" id="pd_id" value="0">
      <div class="form-grid">
        <div class="field full"><label for="pd_cat">Category</label><select id="pd_cat" name="category" required><?php foreach (PRODUCT_CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label for="pd_name">Product name</label><input id="pd_name" name="name" maxlength="120" required></div>
        <div class="field full"><label for="pd_desc">Short description</label><input id="pd_desc" name="description" maxlength="255"></div>
        <div class="field full"><label for="pd_price">Price (₱)</label><input id="pd_price" name="price" type="number" step="0.01" min="0" placeholder="Leave empty to show “Request a quote”"></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Save product</button><button type="button" class="button button-ghost" data-close-modal>Cancel</button></div>
    </form>
  </div>
</div>
<script>
(function () {
  function fill(d) {
    document.getElementById('pdT').textContent = d ? 'Edit product' : 'Add product';
    document.getElementById('pd_id').value = d ? d.id : 0;
    document.getElementById('pd_cat').value = d ? d.category : document.getElementById('pd_cat').options[0].value;
    document.getElementById('pd_name').value = d ? d.name : '';
    document.getElementById('pd_desc').value = d ? d.description : '';
    document.getElementById('pd_price').value = d && d.price !== null ? d.price : '';
  }
  document.getElementById('newProductBtn').addEventListener('click', function () { fill(null); });
  document.querySelectorAll('.js-edit-product').forEach(function (b) {
    b.addEventListener('click', function () { fill(JSON.parse(b.getAttribute('data-product'))); SSS.openModal('productModal'); });
  });
})();
</script>

<?php elseif ($tab === 'testimonials'): ?>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);align-items:start">
  <div class="card">
    <div class="card-head"><h2>Add a testimonial</h2></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_testimonial">
      <div class="form-grid">
        <div class="field"><label for="t_name">Customer name</label><input id="t_name" name="customer_name" maxlength="100" required></div>
        <div class="field"><label for="t_loc">Location</label><input id="t_loc" name="location" maxlength="100" placeholder="e.g. Pasig City"></div>
        <div class="field full"><label for="t_msg">Their words</label><textarea id="t_msg" name="message" maxlength="600" required></textarea></div>
        <div class="field full"><label class="check"><input type="checkbox" name="is_published" checked> Show on the homepage</label></div>
      </div>
      <p class="hint" style="margin-top:8px">Only add real feedback, with the customer's permission.</p>
      <div class="form-actions"><button class="button" type="submit">Save testimonial</button></div>
    </form>
  </div>
  <div>
    <?php if (!$testimonials): ?><div class="card"><div class="empty">No testimonials yet. The homepage hides this section until one is published.</div></div><?php endif; ?>
    <?php foreach ($testimonials as $t): ?>
      <div class="card" style="margin-bottom:14px">
        <div class="card-head" style="margin-bottom:6px"><h2 style="font-size:16px"><?= e($t['customer_name']) ?><?= $t['location'] !== '' ? ' <span class="muted small">· ' . e($t['location']) . '</span>' : '' ?></h2><?= badge($t['is_published'] ? 'active' : 'pending') ?></div>
        <p>“<?= e($t['message']) ?>”</p>
        <div class="actions" style="margin-top:10px"><?= content_toggle('toggle_testimonial', (int) $t['id'], (bool) $t['is_published'], 'Unpublish', 'Publish') ?><?= content_delete('delete_testimonial', (int) $t['id']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<style>@media (max-width:980px){.grid[style*="1.3fr"]{grid-template-columns:1fr !important;}}</style>

<?php else: ?>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);align-items:start">
  <div class="card">
    <div class="card-head"><h2 id="nwT">New post</h2></div>
    <form method="post" id="newsForm">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_news"><input type="hidden" name="id" id="nw_id" value="0">
      <div class="form-grid">
        <div class="field full"><label for="nw_title">Title</label><input id="nw_title" name="title" maxlength="150" required></div>
        <div class="field full"><label for="nw_body">Article</label><textarea id="nw_body" name="body" maxlength="5000" style="min-height:160px" required></textarea></div>
      </div>
      <div class="form-actions"><button class="button" type="submit">Publish</button><button type="button" class="button button-ghost" id="nwReset">Clear</button></div>
    </form>
  </div>
  <div>
    <?php if (!$news): ?><div class="card"><div class="empty">No posts yet.</div></div><?php endif; ?>
    <?php foreach ($news as $n): ?>
      <div class="card" style="margin-bottom:14px">
        <div class="card-head" style="margin-bottom:6px"><h2 style="font-size:16px"><?= e($n['title']) ?></h2><?= badge($n['is_published'] ? 'active' : 'inactive') ?></div>
        <p style="white-space:pre-wrap"><?= e(mb_strimwidth($n['body'], 0, 220, '…')) ?></p>
        <div class="actions" style="margin-top:10px;justify-content:space-between"><span class="muted small"><?= e(fmt_date($n['created_at'])) ?></span>
          <div class="actions">
            <button type="button" class="button button-ghost button-sm js-edit-news" data-news="<?= $json(['id' => (int) $n['id'], 'title' => $n['title'], 'body' => $n['body']]) ?>">Edit</button>
            <?= content_toggle('toggle_news', (int) $n['id'], (bool) $n['is_published']) ?><?= content_delete('delete_news', (int) $n['id']) ?>
          </div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<style>@media (max-width:980px){.grid[style*="1.3fr"]{grid-template-columns:1fr !important;}}</style>
<script>
(function () {
  function fill(d) {
    document.getElementById('nwT').textContent = d ? 'Edit post' : 'New post';
    document.getElementById('nw_id').value = d ? d.id : 0;
    document.getElementById('nw_title').value = d ? d.title : '';
    document.getElementById('nw_body').value = d ? d.body : '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  document.getElementById('nwReset').addEventListener('click', function () { fill(null); });
  document.querySelectorAll('.js-edit-news').forEach(function (b) { b.addEventListener('click', function () { fill(JSON.parse(b.getAttribute('data-news'))); }); });
})();
</script>
<?php endif; ?>
<?php render_footer(); ?>
