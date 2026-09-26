<?php
session_start();

$loginError = $_SESSION['error'] ?? '';
$registerError = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
$activeForm = $_SESSION['active_form'] ?? 'login';

unset($_SESSION['error'], $_SESSION['success'], $_SESSION['active_form']);

function showError($error){
  return !empty($error) ? "<p class='error-message'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>" : '';
}

function isActiveForm($formName, $activeForm){
  return $formName === $activeForm ? 'active' : '';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Sun Son Solar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --navy-950:#0c1524;
    --navy-900:#101d31;
    --navy-800:#182a45;
    --gold-500:#f2a900;
    --gold-400:#ffc233;
    --amber-100:#fff3d9;
    --paper:#fbfaf7;
    --ink:#151a22;
    --ink-soft:#5b6472;
    --line:#e4e1da;
    --danger-bg:#fdece9;
    --danger-ink:#8a2c1f;
    font-family:'Inter',sans-serif;
  }
  *{margin:0;padding:0;box-sizing:border-box;}
  html,body{height:100%;}
  body{
    background:var(--paper);
    color:var(--ink);
    min-height:100vh;
    padding-top:env(safe-area-inset-top,0px);
    padding-bottom:env(safe-area-inset-bottom,0px);
  }

  .container{
    min-height:100vh;
    display:flex;
  }

  /* LEFT — energy panel */
  .left-side{
    position:relative;
    width:44%;
    min-height:100vh;
    background:
      radial-gradient(560px 560px at 26% 30%, rgba(255,194,51,0.35), rgba(255,194,51,0) 60%),
      linear-gradient(165deg, var(--navy-950) 0%, var(--navy-900) 55%, var(--navy-800) 100%);
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    padding:48px 44px;
    overflow:hidden;
  }

  .left-side::after{
    content:"";
    position:absolute;
    left:-18%;
    bottom:-22%;
    width:120%;
    height:60%;
    background:repeating-linear-gradient(
      100deg,
      rgba(255,255,255,0.035) 0px,
      rgba(255,255,255,0.035) 1px,
      transparent 1px,
      transparent 46px
    );
    transform:rotate(-6deg);
    pointer-events:none;
  }

  .sun{
    position:relative;
    width:140px;
    height:auto;
    margin-bottom:10px;
  }
  .sun img{
    width:75%;
    height:auto;
    margin-left:-5%;
    display:block;
  }

  .brand-word{
    margin-top:0;
    font-family:'Space Grotesk',sans-serif;
    font-weight:700;
    font-size:30px;
    letter-spacing:0.2px;
    color:#fff;
    line-height:1.15;
  }
  .brand-word span{color:var(--gold-400);}

  .tagline{
    margin-top:10px;
    font-size:15px;
    color:#a9b6c9;
    max-width:30ch;
    line-height:1.5;
  }

  /* RIGHT — form panel */
  .right-side{
    width:56%;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:48px clamp(28px, 6vw, 84px);
    overflow-y:auto;
  }

  .form-container{width:100%;max-width:460px;}
  .form-box{display:none;}
  .form-box.active{display:block;}

  .form-header{margin-bottom:30px;}
  .form-header h2{
    font-family:'Space Grotesk',sans-serif;
    font-size:28px;
    font-weight:600;
    color:var(--ink);
    margin-bottom:6px;
  }
  .form-header p{color:var(--ink-soft);font-size:14.5px;}

  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 16px;}
  .input-group{display:flex;flex-direction:column;}
  .input-group.full{grid-column:1 / -1;}

  label{
    font-size:12.5px;
    font-weight:600;
    color:var(--ink-soft);
    margin-bottom:6px;
  }

  input,select{
    width:100%;
    height:46px;
    border:1.5px solid var(--line);
    border-radius:10px;
    padding:0 14px;
    font-size:14.5px;
    font-family:'Inter',sans-serif;
    background:#fff;
    color:var(--ink);
    outline:none;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  select{appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235b6472' stroke-width='1.6' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center;}

  input:focus,select:focus{
    border-color:var(--gold-500);
    box-shadow:0 0 0 3px rgba(242,169,0,0.16);
  }
  input::placeholder{color:#a7adb8;}

  .password-wrapper{position:relative;}
  .password-wrapper input{padding-right:64px;}
  .show-password{
    position:absolute;
    right:6px;
    top:50%;
    transform:translateY(-50%);
    border:none;
    background:none;
    color:var(--ink-soft);
    font-size:12.5px;
    font-weight:600;
    cursor:pointer;
    padding:6px 8px;
    border-radius:6px;
  }
  .show-password:hover{background:#f1efe9;color:var(--ink);}
  .show-password:focus-visible,
  input:focus-visible,
  select:focus-visible,
  .btn:focus-visible,
  .switch-link:focus-visible{outline:2px solid var(--gold-500); outline-offset:2px;}

  .btn{
    width:100%;
    height:48px;
    border:none;
    border-radius:10px;
    background:linear-gradient(180deg, var(--gold-400), var(--gold-500));
    color:#241900;
    font-size:15px;
    font-weight:700;
    font-family:'Inter',sans-serif;
    cursor:pointer;
    margin-top:22px;
    box-shadow:0 8px 20px -8px rgba(242,169,0,0.55);
    transition:transform .12s ease, box-shadow .12s ease, filter .12s ease;
  }
  .btn:hover{filter:brightness(0.97); box-shadow:0 6px 14px -8px rgba(242,169,0,0.55);}
  .btn:active{transform:translateY(1px);}

  .switch-text{
    text-align:center;
    margin-top:22px;
    font-size:13.5px;
    color:var(--ink-soft);
  }
  .switch-link{
    color:#a26a00;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
    border-bottom:1px solid transparent;
  }
  .switch-link:hover{border-bottom-color:#a26a00;}

  .message{
    display:none;
    padding:12px 14px;
    margin-top:16px;
    border-radius:8px;
    background:var(--amber-100);
    color:#7a5600;
    font-size:13px;
    border:1px solid #f0d68a;
  }
  .message.error{
    background:var(--danger-bg);
    color:var(--danger-ink);
    border-color:#f2c2b8;
  }
  .message.show{display:block;}

  .login-form{max-width:400px;margin:auto;}

  #departmentGroup{display:none;}

  /* SUCCESS MODAL */
  .modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(12,21,36,0.55);
    align-items:center;
    justify-content:center;
    padding:20px;
    z-index:1000;
  }
  .modal-overlay.active{display:flex;}
  .modal-box{
    background:#fff;
    border-radius:16px;
    padding:32px 28px;
    max-width:360px;
    width:100%;
    text-align:center;
    box-shadow:0 20px 60px -20px rgba(12,21,36,0.4);
    animation:modalPop .18s ease;
  }
  @keyframes modalPop{
    from{opacity:0; transform:translateY(8px) scale(.98);}
    to{opacity:1; transform:translateY(0) scale(1);}
  }
  .modal-icon{
    width:52px;
    height:52px;
    border-radius:50%;
    background:var(--amber-100);
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 16px;
  }
  .modal-icon svg{width:26px;height:26px;}
  .modal-box p{
    font-size:15px;
    color:var(--ink);
    font-weight:600;
    margin-bottom:20px;
  }
  .modal-close-btn{
    width:100%;
    height:44px;
    border:none;
    border-radius:10px;
    background:linear-gradient(180deg, var(--gold-400), var(--gold-500));
    color:#241900;
    font-size:14.5px;
    font-weight:700;
    font-family:'Inter',sans-serif;
    cursor:pointer;
  }
  .modal-close-btn:hover{filter:brightness(0.97);}
  .modal-close-btn:focus-visible{outline:2px solid var(--gold-500); outline-offset:2px;}

  @media (prefers-reduced-motion: reduce){
    *{transition:none !important;}
    .modal-box{animation:none;}
  }

  /* MOBILE */
  @media (max-width:850px){
    .container{flex-direction:column;}
    .left-side{
      width:100%;
      min-height:auto;
      padding:36px 28px;
      flex-direction:column;
      align-items:flex-start;
      gap:0;
    }
    .left-side::after{display:none;}
    .sun{width:64px;margin-bottom:12px;}
    .brand-word{margin-top:0;font-size:22px;}
    .tagline{display:none;}
    .right-side{width:100%;min-height:auto;padding:36px 24px;}
  }

  @media (max-width:600px){
    .form-grid{grid-template-columns:1fr;}
    .input-group.full{grid-column:auto;}
    .form-header h2{font-size:24px;}
  }
</style>
</head>
<body>

<div class="container">

  <!-- LEFT SIDE -->
  <div class="left-side">
    <div>
      <div class="sun" aria-hidden="true">
        <img src="transL.png" alt="sun" width="600" height="400">
      </div>

      <h1 class="brand-word">Sun Son<br><span>Solar</span></h1>
      <p class="tagline">When light becomes power.</p>
    </div>
  </div>


  <!-- RIGHT SIDE -->
  <div class="right-side">
    <div class="form-container">

      <!-- LOGIN FORM -->
      <div class="form-box <?php echo $activeForm === 'login' ? 'active' : ''; ?>" id="loginForm">
        <div class="login-form">

          <div class="form-header">
            <h2>Welcome back</h2>
            <p>Log in to your Sun Son Solar account.</p>
          </div>

          <form id="login" action="login_register.php" method="post" novalidate>
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
            <div class="message <?php echo ($activeForm === 'login' && $loginError) ? 'error show' : (($activeForm === 'login' && $success) ? 'show' : ''); ?>" id="loginMessage" role="status">
              <?php echo htmlspecialchars($activeForm === 'login' ? ($loginError ?: $success) : '', ENT_QUOTES); ?>
            </div>
          </form>

          <div class="switch-text">
            Don't have an account?
            <span class="switch-link" onclick="showRegister()" role="button" tabindex="0" onkeydown="if(event.key==='Enter')showRegister()">Sign up</span>
          </div>

        </div>
      </div>


      <!-- REGISTRATION FORM -->
      <div class="form-box <?php echo $activeForm === 'register' ? 'active' : ''; ?>" id="registerForm">

        <div class="form-header">
          <h2>Create an account</h2>
          <p>Register to get started with Sun Son Solar.</p>
        </div>
        <?= showError($activeForm === 'register' ? $registerError : '') ?>
        <form id="registration" action="login_register.php" method="post" novalidate>
          <div class="form-grid">

            <div class="input-group full">
              <label for="accountType">Account type</label>
              <select name="accountType" id="accountType" onchange="toggleDepartment()" required>
                <option value="">Select account type</option>
                <option value="Customer">Customer</option>
                <option value="Employee">Employee</option>
              </select>
            </div>

            <div class="input-group">
              <label for="firstName">First name</label>
              <input type="text" name="firstName" id="firstName" placeholder="First name" required autocomplete="given-name">
            </div>

            <div class="input-group">
              <label for="middleName">Middle name</label>
              <input type="text" name="middleName" id="middleName" placeholder="Middle name" autocomplete="additional-name">
            </div>

            <div class="input-group">
              <label for="lastName">Last name</label>
              <input type="text" name="lastName" id="lastName" placeholder="Last name" required autocomplete="family-name">
            </div>

            <div class="input-group">
              <label for="birthdate">Birthdate</label>
              <input type="date" name="birthdate" id="birthdate" required autocomplete="bday">
            </div>

            <div class="input-group">
              <label for="gender">Gender</label>
              <select name="gender" id="gender" required>
                <option value="">Select gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="input-group">
              <label for="email">Email</label>
              <input type="email" name="email" id="email" placeholder="example@email.com" required autocomplete="email">
            </div>

            <div class="input-group">
              <label for="phone">Phone number</label>
              <input type="tel" name="phone" id="phone" placeholder="09XXXXXXXXX" required autocomplete="tel" inputmode="numeric" pattern="[0-9]*" maxlength="11">
            </div>

            <div class="input-group" id="departmentGroup">
              <label for="department">Department</label>
               <select name="department" id="department">
                <option value="">Select department</option>
                <option value="IT">IT</option>
                <option value="Technician">Technician</option>
                <option value="Dispatcher">Dispatcher</option>
              </select>
            </div>

            <div class="input-group full">
              <label for="address">Address</label>
              <input type="text" name="address" id="address" placeholder="Complete address" required autocomplete="street-address">
            </div>

            <div class="input-group">
              <label for="username">Username</label>
              <input type="text" name="username" id="username" placeholder="Create username" required autocomplete="username">
            </div>

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
          <div class="message <?php echo ($activeForm === 'register' && $registerError) ? 'error show' : (($activeForm === 'register' && $success) ? 'show' : ''); ?>" id="registerMessage" role="status">
            <?php echo htmlspecialchars($activeForm === 'register' ? ($registerError ?: $success) : '', ENT_QUOTES); ?>
          </div>
        </form>

        <div class="switch-text">
          Already have an account?
          <span class="switch-link" onclick="showLogin()" role="button" tabindex="0" onkeydown="if(event.key==='Enter')showLogin()">Log in</span>
        </div>

      </div>

    </div>
  </div>

</div>

<?php if ($success): ?>
<!-- SUCCESS MODAL -->
<div class="modal-overlay active" id="successModal" role="dialog" aria-modal="true" aria-labelledby="modalMessage">
  <div class="modal-box">
    <div class="modal-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="11" fill="#ffc233"/>
        <path d="M7 12.5l3 3 7-7" stroke="#241900" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <p id="modalMessage"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></p>
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
    const input = document.getElementById(inputId);
    if(input.type === "password"){
      input.type = "text";
      button.textContent = "Hide";
    } else {
      input.type = "password";
      button.textContent = "Show";
    }
  }
  function toggleDepartment(){
    const accountType = document.getElementById("accountType").value;
    const departmentGroup = document.getElementById("departmentGroup");
    const department = document.getElementById("department");
    if(accountType === "Employee"){
      departmentGroup.style.display = "flex";
      department.required = true;
    } else {
      departmentGroup.style.display = "none";
      department.required = false;
      department.value = "";
    }
  }

  
  document.getElementById("phone").addEventListener("input", function(){
    this.value = this.value.replace(/[^0-9]/g, "");
  });

  function closeModal(){
    document.getElementById("successModal").classList.remove("active");
  }
  const overlay = document.getElementById("successModal");
  if(overlay){
    overlay.addEventListener("click", function(e){
      if(e.target === this) closeModal();
    });
  }
  document.addEventListener("keydown", function(e){
    if(e.key === "Escape") closeModal();
  });
</script>

</body>
</html>
