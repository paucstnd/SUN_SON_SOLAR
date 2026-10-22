<?php
session_start();
require_once 'config.php';
require_once 'security.php';
require_once 'auth.php';
require_once 'functions.php';

/* ---------- Company details shown on the page (edit here) ---------- */
$company = [
    'name'    => 'Sun Son Solar',
    'city'    => 'Pasig City, Philippines',
    'phone'   => '0929 123 0983',
    'email'   => 'katherine.sinagaraw@sunsonsolar.com',
    'hours'   => 'Mon–Fri 8:00 AM – 5:00 PM · Sat 8:00 AM – 12:00 NN',
    // Replace the '#' with the real social media page links
    'social'  => ['Facebook' => '#', 'Instagram' => '#', 'YouTube' => '#'],
];

$loginError = $_SESSION['error'] ?? '';
$registerError = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
$hasAccountFlash = isset($_SESSION['active_form']);
$activeForm = $_SESSION['active_form'] ?? 'login';
$quoteOk = $_SESSION['quote_ok'] ?? '';
$quoteErr = $_SESSION['quote_err'] ?? '';

unset($_SESSION['error'], $_SESSION['success'], $_SESSION['active_form'], $_SESSION['quote_ok'], $_SESSION['quote_err']);

$csrfToken = csrf_token();

function showError($error){
  return !empty($error) ? "<p class='error-message'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>" : '';
}

/* Already logged in? show a dashboard shortcut instead of the login section. */
$dashboardLink = '';
if (!empty($_SESSION['admin_logged_in'])) {
    $dashboardLink = 'admin_dashboard.php';
} elseif (!empty($_SESSION['user_id'])) {
    $dashboardLink = role_home(role_from_user($_SESSION['account_type'] ?? 'Customer', $_SESSION['department'] ?? null));
}

$services = active_services($conn);
$products = $conn->query("SELECT category, name, description, price FROM products WHERE is_active = 1 ORDER BY category, name")->fetch_all(MYSQLI_ASSOC);
$testimonials = $conn->query("SELECT customer_name, location, message FROM testimonials WHERE is_published = 1 ORDER BY created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
$news = $conn->query("SELECT title, body, created_at FROM news_posts WHERE is_published = 1 ORDER BY created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

$productCategories = [
    'Solar Panels' => 'High-efficiency panels that turn sunlight into clean power.',
    'Inverters' => 'Convert solar DC into the AC electricity your home uses.',
    'Batteries' => 'Store extra energy for the night or during outages.',
    'Racking & Mounting' => 'Strong, weather-ready mounts for roofs and ground installs.',
    'Wires & Cables' => 'Solar-rated wiring and connectors for safe, lasting systems.',
];

$faqs = [
    ['How much can I save with solar?', 'Savings depend on your electricity use, roof space and system size. During a free consultation we review your bills and give you an estimate before you decide anything.'],
    ['How long does installation take?', 'Most residential installations take only a few days once permits are approved. We will give you a clear schedule after the design is finalized.'],
    ['Do you handle permits and paperwork?', 'Yes. Permitting is one of our services, so we prepare and file the required permits and utility paperwork for you.'],
    ['What happens if something goes wrong with my system?', 'You can book a repair or maintenance visit from your customer dashboard, or send us a message through the Support section.'],
    ['Do solar panels work on cloudy or rainy days?', 'Yes, but they produce less. Batteries and a properly sized system help keep your power steady.'],
    ['How do I schedule a service?', 'Create a free customer account, log in, and use “Book a service” in your dashboard. Our team will confirm your appointment and you will get a notification.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Sun Son Solar · Power Your Future with Solar</title>
<meta name="description" content="Solar panels, inverters, batteries and professional installation in Pasig City. Request a free quote today.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/home.css">
</head>
<body>

<header class="nav">
  <div class="wrap">
    <a href="#top" class="nav-brand"><img src="transL.png" alt="" width="40" height="40">Sun Son <span>Solar</span></a>
    <button type="button" class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false">☰</button>
    <nav class="nav-links" id="navLinks" aria-label="Main">
      <a href="#services">Services</a>
      <a href="#why-solar">Why solar</a>
      <a href="#about">About</a>
      <a href="#stories">Stories</a>
      <a href="#faq">FAQ</a>
      <a href="#news">News</a>
      <a href="#contact">Contact</a>
      <?php if ($dashboardLink): ?>
        <a href="<?= e($dashboardLink) ?>" class="cta">My dashboard</a>
      <?php else: ?>
        <a href="#account" class="cta" data-show="login">Log in</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main id="top">
<!-- ===== Hero ===== -->
<section class="hero" style="padding:0">
  <div class="wrap">
    <div>
      <span class="eyebrow">When light becomes power</span>
      <h1>Power Your Future <span>with Solar</span></h1>
      <p class="lead">Sun Son Solar designs, installs and maintains solar systems for homes and businesses. Manage your solar account, installations and service in one place.</p>
      <div class="hero-actions">
        <a class="btn-gold" href="#quote">Request a free quote</a>
        <?php if ($dashboardLink): ?>
          <a class="btn-line" href="<?= e($dashboardLink) ?>">Go to my dashboard</a>
        <?php else: ?>
          <a class="btn-line" href="#account" data-show="register">Create an account &amp; schedule a service</a>
        <?php endif; ?>
      </div>
      <div class="hero-points">
        <span><b>Free</b> consultation</span>
        <span><b>Certified</b> installers</span>
        <span><b>Permits</b> handled for you</span>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true"><img src="transL.png" alt="" width="340" height="340"></div>
  </div>
</section>

<!-- ===== Products ===== -->
<section id="products">
  <div class="wrap">
    <div class="sec-head">
      <h2>Everything your solar system needs</h2>
      <p>We supply quality solar-related products, from the panels on your roof to the wires behind the wall.</p>
    </div>
    <div class="cards c5">
      <?php foreach ($productCategories as $cat => $desc): ?>
        <div class="tile"><h3><?= e($cat) ?></h3><p><?= e($desc) ?></p></div>
      <?php endforeach; ?>
    </div>
    <?php if ($products): ?>
      <div class="catalog">
        <h3>Available now</h3>
        <ul>
          <?php foreach ($products as $p): ?>
            <li><strong><?= e($p['name']) ?></strong><small><?= e($p['category']) ?><?= $p['price'] !== null ? ' · ' . e(peso($p['price'])) : '' ?></small></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ===== Services ===== -->
<section id="services" class="alt">
  <div class="wrap">
    <div class="sec-head">
      <h2>Our services, start to finish</h2>
      <p>From your first consultation to long-term monitoring, one team takes care of everything.</p>
    </div>
    <div class="cards c4">
      <?php foreach ($services as $s): ?>
        <div class="tile">
          <h3><?= e($s['name']) ?></h3>
          <p><?= e($s['description']) ?></p>
          <?php if ((float) $s['base_price'] > 0): ?><span class="price">From <?= e(peso($s['base_price'])) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:28px;display:flex;gap:12px;flex-wrap:wrap">
      <a class="btn-gold" href="<?= $dashboardLink ? e($dashboardLink) : '#account' ?>" <?= $dashboardLink ? '' : 'data-show="register"' ?>>Schedule a service</a>
      <a class="btn-line dark" href="#quote">Request a free quote</a>
    </div>
  </div>
</section>

<!-- ===== Why solar ===== -->
<section id="why-solar">
  <div class="wrap">
    <div class="sec-head">
      <h2>Why go solar?</h2>
      <p>Making the switch is good for your wallet, your community and your peace of mind.</p>
    </div>
    <div class="cards c3">
      <div class="tile dark"><div class="big">01</div><h3>Cost savings</h3><p>Generate your own power and cut down your monthly electricity bill for years to come.</p></div>
      <div class="tile dark"><div class="big">02</div><h3>Sustainability</h3><p>Solar is clean, renewable energy that reduces your carbon footprint every single day.</p></div>
      <div class="tile dark"><div class="big">03</div><h3>Energy independence</h3><p>Rely less on the grid and rising rates. Add batteries to stay powered even during outages.</p></div>
    </div>
  </div>
</section>

<!-- ===== About ===== -->
<section id="about" class="alt">
  <div class="wrap about">
    <div>
      <div class="sec-head" style="margin-bottom:0">
        <h2>A family business that grew with the sun</h2>
      </div>
      <p>Sun Son Solar was founded by Katherine Sinagaraw together with her father, Santino “Sonny” Sinagaraw, starting from our very first office in Pasig City.</p>
      <p>We believe going solar should feel simple and trustworthy. That is why we explain every step, handle the paperwork, and stay with you after installation with maintenance, repair and monitoring.</p>
    </div>
    <div class="cards" style="grid-template-columns:1fr 1fr">
      <div class="tile"><h3>Consult</h3><p>We assess your needs and roof for free.</p></div>
      <div class="tile"><h3>Design</h3><p>A custom system sized for your home.</p></div>
      <div class="tile"><h3>Install</h3><p>Certified technicians do the work safely.</p></div>
      <div class="tile"><h3>Support</h3><p>Maintenance, repair and monitoring.</p></div>
    </div>
  </div>
</section>

<!-- ===== Testimonials ===== -->
<section id="stories">
  <div class="wrap">
    <div class="sec-head">
      <h2>Customer stories</h2>
      <p>Real experiences from the people we have worked with.</p>
    </div>
    <?php if ($testimonials): ?>
      <div class="cards c3">
        <?php foreach ($testimonials as $t): ?>
          <figure class="quote-card">
            <p>“<?= e($t['message']) ?>”</p>
            <footer><?= e($t['customer_name']) ?><?php if ($t['location'] !== ''): ?><small><?= e($t['location']) ?></small><?php endif; ?></footer>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-note">Customer stories are coming soon. Finished a project with us? Tell us about your experience through the Support section of your account.</div>
    <?php endif; ?>
  </div>
</section>

<!-- ===== Quote CTA ===== -->
<section id="quote" class="cta-band">
  <div class="wrap cta-grid">
    <div>
      <span class="eyebrow">Free quote</span>
      <h2>Ready to power your future?</h2>
      <p>Tell us a little about your property and what you need. Our team will contact you with a free, no-obligation quote.</p>
    </div>
    <div class="panel">
      <?php if ($quoteOk): ?><div class="message show" role="status"><?= e($quoteOk) ?></div><?php endif; ?>
      <?php if ($quoteErr): ?><div class="message error show" role="alert"><?= e($quoteErr) ?></div><?php endif; ?>
      <form action="quote_request.php" method="post" novalidate style="margin-top:<?= ($quoteOk || $quoteErr) ? '16px' : '0' ?>">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-grid">
          <div class="input-group"><label for="qName">Full name</label><input type="text" id="qName" name="name" placeholder="Your name" required autocomplete="name"></div>
          <div class="input-group"><label for="qPhone">Phone number</label><input type="tel" id="qPhone" name="phone" placeholder="09XXXXXXXXX" inputmode="numeric" maxlength="11" required autocomplete="tel"></div>
          <div class="input-group"><label for="qEmail">Email</label><input type="email" id="qEmail" name="email" placeholder="example@email.com" required autocomplete="email"></div>
          <div class="input-group">
            <label for="qService">I am interested in</label>
            <select id="qService" name="service_id">
              <option value="">Not sure yet</option>
              <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="input-group full"><label for="qMsg">Message (optional)</label><textarea id="qMsg" name="message" maxlength="500" placeholder="Tell us about your property or monthly electricity bill"></textarea></div>
        </div>
        <button type="submit" class="btn">Request free quote</button>
      </form>
    </div>
  </div>
</section>

<!-- ===== Login / register ===== -->
<section id="account">
  <div class="wrap account-grid">
    <div class="account-copy">
      <span class="eyebrow" style="color:#a26a00">Account</span>
      <div class="sec-head" style="margin-bottom:0"><h2>Customer &amp; employee login</h2></div>
      <p class="muted" style="color:var(--ink-soft);margin-top:10px">Customers can create an account and manage their solar services online. Employee accounts are created by HR or an administrator.</p>
      <ul>
        <li>Book and manage consultations, installations and maintenance</li>
        <li>Get reminders, schedule updates and offers</li>
        <li>View service history, payments and receipts</li>
        <li>Chat with our support team</li>
      </ul>
      <p style="margin-top:18px;font-size:13.5px;color:var(--ink-soft)">Administrator? <a href="admin.php">Admin login</a></p>
    </div>

    <div class="panel" style="border:1px solid var(--line)">
      <?php if ($dashboardLink): ?>
        <div class="form-header"><h3>You are logged in</h3><p>Continue to your dashboard.</p></div>
        <a class="btn-gold" style="width:100%" href="<?= e($dashboardLink) ?>">Go to my dashboard</a>
      <?php else: ?>

      <div class="form-box <?= $activeForm === 'login' ? 'active' : '' ?>" id="loginForm">
        <div class="form-header">
          <h3>Welcome back</h3>
          <p>Log in to your Sun Son Solar account.</p>
        </div>

        <form id="login" action="login_register.php" method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <div class="input-group">
            <label for="loginUsername">Username</label>
            <input type="text" name="username" id="loginUsername" placeholder="Enter your username" required autocomplete="username">
          </div>
          <br>
          <div class="input-group">
            <label for="loginPassword">Password</label>
            <div class="password-wrapper">
              <input type="password" name="password" id="loginPassword" placeholder="Enter your password" required autocomplete="current-password">
              <button type="button" class="show-password" onclick="togglePassword('loginPassword', this)" aria-label="Show password">Show</button>
            </div>
          </div>
          <button type="submit" name="login" value="1" class="btn">Log in</button>
          <div class="message <?= ($activeForm === 'login' && $loginError) ? 'error show' : (($activeForm === 'login' && $success) ? 'show' : '') ?>" id="loginMessage" role="status">
            <?= e($activeForm === 'login' ? ($loginError ?: $success) : '') ?>
          </div>
        </form>

        <div class="switch-text" style="margin-top:14px"><a class="switch-link" href="forgot_password.php" style="text-decoration:none">Forgot your password?</a></div>

        <div class="switch-text">
          Don't have an account?
          <span class="switch-link" onclick="showRegister()" role="button" tabindex="0" onkeydown="if(event.key==='Enter')showRegister()">Sign up</span>
        </div>
      </div>

      <div class="form-box <?= $activeForm === 'register' ? 'active' : '' ?>" id="registerForm">
        <div class="form-header">
          <h3>Create an account</h3>
          <p>Register to get started with Sun Son Solar.</p>
        </div>
        <?= showError($activeForm === 'register' ? $registerError : '') ?>
        <form id="registration" action="login_register.php" method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <div class="form-grid">
            <div class="input-group"><label for="firstName">First name</label><input type="text" name="firstName" id="firstName" placeholder="First name" required autocomplete="given-name"></div>
            <div class="input-group"><label for="middleName">Middle name</label><input type="text" name="middleName" id="middleName" placeholder="Middle name" autocomplete="additional-name"></div>
            <div class="input-group"><label for="lastName">Last name</label><input type="text" name="lastName" id="lastName" placeholder="Last name" required autocomplete="family-name"></div>
            <div class="input-group"><label for="birthdate">Birthdate</label><input type="date" name="birthdate" id="birthdate" required autocomplete="bday"></div>
            <div class="input-group">
              <label for="gender">Gender</label>
              <select name="gender" id="gender" required>
                <option value="">Select gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="input-group"><label for="email">Email</label><input type="email" name="email" id="email" placeholder="example@email.com" required autocomplete="email"></div>
            <div class="input-group"><label for="phone">Phone number</label><input type="tel" name="phone" id="phone" placeholder="09XXXXXXXXX" required autocomplete="tel" inputmode="numeric" pattern="[0-9]{11}" maxlength="11"></div>
            <div class="input-group full"><label for="address">Address</label><input type="text" name="address" id="address" placeholder="Complete address" required autocomplete="street-address"></div>
            <div class="input-group"><label for="username">Username</label><input type="text" name="username" id="username" placeholder="Create username" required autocomplete="username"></div>
            <div class="input-group">
              <label for="password">Password</label>
              <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Create password" required autocomplete="new-password">
                <button type="button" class="show-password" onclick="togglePassword('password', this)" aria-label="Show password">Show</button>
              </div>
            </div>
            <div class="input-group">
              <label for="confirmPassword">Confirm password</label>
              <div class="password-wrapper">
                <input type="password" name="confirmPassword" id="confirmPassword" placeholder="Confirm password" required autocomplete="new-password">
                <button type="button" class="show-password" onclick="togglePassword('confirmPassword', this)" aria-label="Show password">Show</button>
              </div>
            </div>
          </div>
          <button type="submit" name="register" value="1" class="btn">Sign up</button>
        </form>
        <div class="switch-text">
          Already have an account?
          <span class="switch-link" onclick="showLogin()" role="button" tabindex="0" onkeydown="if(event.key==='Enter')showLogin()">Log in</span>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===== FAQ ===== -->
<section id="faq" class="alt">
  <div class="wrap">
    <div class="sec-head"><h2>Frequently asked questions</h2><p>Quick answers to common concerns about going solar.</p></div>
    <div class="faq">
      <?php foreach ($faqs as $f): ?>
        <details><summary><?= e($f[0]) ?></summary><p><?= e($f[1]) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== News ===== -->
<section id="news">
  <div class="wrap">
    <div class="sec-head"><h2>News &amp; updates</h2><p>Solar tips and updates from the Sun Son Solar team.</p></div>
    <?php if ($news): ?>
      <div class="cards c3">
        <?php foreach ($news as $n): ?>
          <article class="tile">
            <span class="news-date"><?= e(fmt_date($n['created_at'])) ?></span>
            <h3><?= e($n['title']) ?></h3>
            <p><?= e(clean_text($n['body'], 220)) ?><?= strlen($n['body']) > 220 ? '…' : '' ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-note">No news yet. Check back soon.</div>
    <?php endif; ?>
  </div>
</section>
</main>

<!-- ===== Footer ===== -->
<footer class="site" id="contact">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <div class="foot-brand"><img src="transL.png" alt="" width="40" height="40">Sun Son <span>Solar</span></div>
        <p>Power Your Future with Solar.</p>
        <p>When light becomes power.</p>
      </div>
      <div>
        <h4>Contact</h4>
        <p><?= e($company['city']) ?></p>
        <p><?= e($company['phone']) ?></p>
        <p><a href="mailto:<?= e($company['email']) ?>" style="display:inline"><?= e($company['email']) ?></a></p>
        <p><?= e($company['hours']) ?></p>
      </div>
      <div>
        <h4>Explore</h4>
        <a href="#services">Services</a><a href="#why-solar">Why solar</a><a href="#faq">FAQ</a><a href="#news">News</a>
      </div>
      <div>
        <h4>Follow us</h4>
        <?php foreach ($company['social'] as $label => $url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> Sun Son Solar. All rights reserved.</span>
      <span><a href="#" data-open="legalPrivacy" style="display:inline">Privacy notice</a> · <a href="#" data-open="legalTerms" style="display:inline">Terms of use</a></span>
    </div>
  </div>
</footer>

<!-- Legal notices (have a lawyer review and replace this wording before launch) -->
<div class="modal-overlay" id="legalPrivacy" role="dialog" aria-modal="true" aria-labelledby="lpT">
  <div class="modal-box">
    <h3 id="lpT">Privacy notice</h3>
    <p>Sun Son Solar collects the personal information you give us (such as your name, contact details, address and service requests) only to create and manage your account, schedule and deliver services, send notifications you can control in your profile, and provide support.</p>
    <p>We do not sell your personal information. Access is limited to authorized Sun Son Solar staff who need it for their work. You can update your information from your profile or contact us to ask about your data.</p>
    <button type="button" class="modal-close-btn" data-close>Close</button>
  </div>
</div>
<div class="modal-overlay" id="legalTerms" role="dialog" aria-modal="true" aria-labelledby="ltT">
  <div class="modal-box">
    <h3 id="ltT">Terms of use</h3>
    <p>By using this website and creating an account you agree to provide accurate information, keep your login details private, and use the system only for its intended purpose of requesting and managing Sun Son Solar products and services.</p>
    <p>Quotes and schedules are subject to confirmation by Sun Son Solar. Prices, availability and services may change without prior notice.</p>
    <button type="button" class="modal-close-btn" data-close>Close</button>
  </div>
</div>

<?php if ($success && $activeForm === 'login' && !$loginError): ?>
<div class="modal-overlay active" id="successModal" role="dialog" aria-modal="true" aria-labelledby="modalMessage">
  <div class="modal-box center">
    <div class="modal-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" fill="#ffc233"/><path d="M7 12.5l3 3 7-7" stroke="#241900" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <p id="modalMessage"><?= e($success) ?></p>
    <button type="button" class="modal-close-btn" onclick="closeModal()">Continue</button>
  </div>
</div>
<?php endif; ?>

<script>
  function showRegister(){
    document.getElementById("loginForm").classList.remove("active");
    document.getElementById("registerForm").classList.add("active");
  }
  function showLogin(){
    document.getElementById("registerForm").classList.remove("active");
    document.getElementById("loginForm").classList.add("active");
  }
  function togglePassword(inputId, button){
    var input = document.getElementById(inputId);
    if(input.type === "password"){ input.type = "text"; button.textContent = "Hide"; }
    else { input.type = "password"; button.textContent = "Show"; }
  }
  function startLockoutCountdown(message){
    if(!message) return;
    var match = message.textContent.match(/Please try again in (\d+) seconds?\./i);
    if(!match) return;
    var deadline = Date.now() + Number(match[1]) * 1000;
    var interval = window.setInterval(function(){
      var s = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      if(s === 0){ message.textContent = "You can try logging in again."; window.clearInterval(interval); return; }
      message.textContent = "Too many failed attempts. Please try again in " + s + " second" + (s === 1 ? "" : "s") + ".";
    }, 250);
  }
  function digitsOnly(el){ if(el) el.addEventListener("input", function(){ this.value = this.value.replace(/[^0-9]/g, ""); }); }
  digitsOnly(document.getElementById("phone"));
  digitsOnly(document.getElementById("qPhone"));
  startLockoutCountdown(document.getElementById("loginMessage"));

  function closeModal(){ var m = document.getElementById("successModal"); if(m) m.classList.remove("active"); }
  var overlay = document.getElementById("successModal");
  if(overlay){ overlay.addEventListener("click", function(e){ if(e.target === this) closeModal(); }); }

  // Legal modals
  document.querySelectorAll("[data-open]").forEach(function(a){
    a.addEventListener("click", function(e){ e.preventDefault(); document.getElementById(a.getAttribute("data-open")).classList.add("active"); });
  });
  document.querySelectorAll(".modal-overlay").forEach(function(m){
    m.addEventListener("click", function(e){ if(e.target === m || e.target.hasAttribute("data-close")) m.classList.remove("active"); });
  });
  document.addEventListener("keydown", function(e){
    if(e.key === "Escape") document.querySelectorAll(".modal-overlay.active").forEach(function(m){ m.classList.remove("active"); });
  });

  // Mobile nav
  var toggle = document.getElementById("navToggle"), links = document.getElementById("navLinks");
  toggle.addEventListener("click", function(){
    var open = links.classList.toggle("open"); toggle.setAttribute("aria-expanded", open ? "true" : "false");
  });
  links.addEventListener("click", function(e){ if(e.target.tagName === "A") links.classList.remove("open"); });

  // Buttons that jump to the account section and pick login / register
  document.querySelectorAll("[data-show]").forEach(function(a){
    a.addEventListener("click", function(){ if(document.getElementById("loginForm")){ a.getAttribute("data-show") === "register" ? showRegister() : showLogin(); } });
  });

  <?php if ($hasAccountFlash): ?>
  // After a login / sign-up attempt, bring the visitor back to the form
  window.addEventListener("load", function(){ var s = document.getElementById("account"); if(s) s.scrollIntoView(); });
  <?php endif; ?>
</script>
</body>
</html>
