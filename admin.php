<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>Sun Son Solar — Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --navy-950: #0c1524;
            --navy-900: #101d31;
            --navy-800: #182a45;

            --gold-500: #f2a900;
            --gold-400: #ffc233;
            --amber-100: #fff3d9;

            --paper: #fbfaf7;
            --ink: #151a22;
            --ink-soft: #5b6472;
            --line: #e4e1da;

            --danger-bg: #fdece9;
            --danger-ink: #8a2c1f;

            font-family: 'Inter', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;

            padding-top: env(safe-area-inset-top, 0px);
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }

        .container {
            min-height: 100vh;
            display: flex;
        }


        .left-side {
            position: relative;
            width: 44%;
            min-height: 100vh;

            background:
                radial-gradient(560px 560px at 26% 30%,
                    rgba(255, 194, 51, 0.35),
                    rgba(255, 194, 51, 0) 60%),
                linear-gradient(165deg,
                    var(--navy-950) 0%,
                    var(--navy-900) 55%,
                    var(--navy-800) 100%);

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            padding: 48px 44px;

            overflow: hidden;
        }

        .left-side::after {
            content: "";

            position: absolute;
            left: -18%;
            bottom: -22%;

            width: 120%;
            height: 60%;

            background:
                repeating-linear-gradient(100deg,
                    rgba(255, 255, 255, 0.035) 0px,
                    rgba(255, 255, 255, 0.035) 1px,
                    transparent 1px,
                    transparent 46px);

            transform: rotate(-6deg);

            pointer-events: none;
        }

        .sun {
            position: relative;

            width: 140px;
            height: auto;

            margin-bottom: 10px;
        }

        .sun img {
            width: 75%;
            height: auto;

            margin-left: -5%;

            display: block;
        }


        .brand-word {
            margin-top: 0;

            font-family: 'Space Grotesk', sans-serif;

            font-weight: 700;
            font-size: 30px;

            letter-spacing: 0.2px;

            color: #fff;

            line-height: 1.15;
        }

        .brand-word span {
            color: var(--gold-400);
        }

        .tagline {
            margin-top: 10px;

            font-size: 15px;

            color: #a9b6c9;

            max-width: 30ch;

            line-height: 1.5;
        }

        .admin-badge {
            display: inline-block;

            margin-top: 26px;

            padding: 6px 14px;

            border-radius: 999px;

            background: rgba(255, 194, 51, 0.14);

            border: 1px solid rgba(255, 194, 51, 0.35);

            color: var(--gold-400);

            font-size: 12.5px;

            font-weight: 600;

            letter-spacing: 0.4px;

            text-transform: uppercase;
        }

        .right-side {
            width: 56%;

            min-height: 100vh;

            display: flex;

            justify-content: center;
            align-items: center;

            padding: 48px clamp(28px, 6vw, 84px);

            overflow-y: auto;
        }

        .form-container {
            width: 100%;

            max-width: 420px;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-family: 'Space Grotesk', sans-serif;

            font-size: 28px;

            font-weight: 600;

            color: var(--ink);

            margin-bottom: 6px;
        }

        .form-header p {
            color: var(--ink-soft);

            font-size: 14.5px;
        }

        .input-group {
            display: flex;

            flex-direction: column;

            margin-bottom: 18px;
        }

        label {
            font-size: 12.5px;

            font-weight: 600;

            color: var(--ink-soft);

            margin-bottom: 6px;
        }

        input {
            width: 100%;

            height: 46px;

            border: 1.5px solid var(--line);

            border-radius: 10px;

            padding: 0 14px;

            font-size: 14.5px;

            font-family: 'Inter', sans-serif;

            background: #fff;

            color: var(--ink);

            outline: none;

            transition:
                border-color .15s ease,
                box-shadow .15s ease;
        }

        input:focus {
            border-color: var(--gold-500);

            box-shadow:
                0 0 0 3px rgba(242, 169, 0, 0.16);
        }

        input::placeholder {
            color: #a7adb8;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 64px;
        }

        .show-password {
            position: absolute;

            right: 6px;
            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: none;

            color: var(--ink-soft);

            font-size: 12.5px;

            font-weight: 600;

            cursor: pointer;

            padding: 6px 8px;

            border-radius: 6px;
        }

        .show-password:hover {
            background: #f1efe9;

            color: var(--ink);
        }

        .remember-row {
            display: flex;

            align-items: center;

            gap: 8px;

            margin: -4px 0 4px;

            font-size: 13px;

            color: var(--ink-soft);
        }

        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;

            accent-color: var(--gold-500);
        }

        .btn {
            width: 100%;

            height: 48px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(180deg,
                    var(--gold-400),
                    var(--gold-500));

            color: #241900;

            font-size: 15px;

            font-weight: 700;

            font-family: 'Inter', sans-serif;

            cursor: pointer;

            margin-top: 8px;

            box-shadow:
                0 8px 20px -8px rgba(242, 169, 0, 0.55);

            transition:
                transform .12s ease,
                box-shadow .12s ease,
                filter .12s ease;
        }

        .btn:hover {
            filter: brightness(0.97);

            box-shadow:
                0 6px 14px -8px rgba(242, 169, 0, 0.55);
        }

        .btn:active {
            transform: translateY(1px);
        }

        .message {
            display: none;

            padding: 12px 14px;

            margin-top: 16px;

            border-radius: 8px;

            background: var(--amber-100);

            color: #7a5600;

            font-size: 13px;

            border: 1px solid #f0d68a;
        }

        .message.error {
            background: var(--danger-bg);

            color: var(--danger-ink);

            border-color: #f2c2b8;
        }

        .message.success {
            background: #e8f5e9;

            color: #246b2b;

            border-color: #b9dfbd;
        }

        .back-link {
            display: block;

            text-align: center;

            margin-top: 22px;

            font-size: 13.5px;

            color: var(--ink-soft);

            text-decoration: none;
        }

        .back-link:hover {
            color: var(--ink);
        }

        .modal-overlay {
            display: none;

            position: fixed;

            inset: 0;

            background: rgba(12, 21, 36, 0.55);

            align-items: center;

            justify-content: center;

            padding: 20px;

            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #fff;

            border-radius: 16px;

            padding: 32px 28px;

            max-width: 360px;

            width: 100%;

            text-align: center;

            box-shadow:
                0 20px 60px -20px rgba(12, 21, 36, 0.4);

            animation: modalPop .18s ease;
        }

        @keyframes modalPop {

            from {
                opacity: 0;

                transform:
                    translateY(8px) scale(.98);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0) scale(1);
            }
        }

        .modal-icon {
            width: 52px;
            height: 52px;

            border-radius: 50%;

            background: var(--amber-100);

            display: flex;

            align-items: center;
            justify-content: center;

            margin: 0 auto 16px;
        }

        .modal-icon svg {
            width: 26px;
            height: 26px;
        }

        .modal-box p {
            font-size: 15px;

            color: var(--ink);

            font-weight: 600;

            margin-bottom: 20px;
        }

        .modal-close-btn {
            width: 100%;

            height: 44px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(180deg,
                    var(--gold-400),
                    var(--gold-500));

            color: #241900;

            font-size: 14.5px;

            font-weight: 700;

            font-family: 'Inter', sans-serif;

            cursor: pointer;
        }

        .modal-close-btn:hover {
            filter: brightness(0.97);
        }

        @media (max-width:850px) {

            .container {
                flex-direction: column;
            }

            .left-side {
                width: 100%;

                min-height: auto;

                padding: 36px 28px;

                flex-direction: column;

                align-items: flex-start;

                gap: 0;
            }

            .left-side::after {
                display: none;
            }

            .sun {
                width: 64px;

                margin-bottom: 12px;
            }

            .brand-word {
                margin-top: 0;

                font-size: 22px;
            }

            .tagline {
                display: none;
            }

            .right-side {
                width: 100%;

                min-height: auto;

                padding: 36px 24px;
            }
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="left-side">

            <div>

                <div class="sun" aria-hidden="true">

                    <img src="transL.png" alt="Sun Son Solar Logo" width="600" height="400">

                </div>

                <h1 class="brand-word">
                    Sun Son<br>
                    <span>Solar</span>
                </h1>

                <p class="tagline">
                    <i>When light becomes power.</i>
                </p>

                <span class="admin-badge">
                    Admin Portal
                </span>

            </div>

        </div>


        <div class="right-side">

            <div class="form-container">

                <div class="form-header">

                    <h2>
                        Admin login
                    </h2>

                    <p>
                        Restricted access.
                        Authorized administrators only.
                    </p>

                </div>


                <form id="adminLogin" action="admin_login.php" method="post" novalidate>


                    <div class="input-group">

                        <label for="adminUsername">
                            Admin username
                        </label>

                        <input type="text" id="adminUsername" name="username" placeholder="Enter admin username"
                            required autocomplete="username">

                    </div>


                    <div class="input-group">

                        <label for="adminPassword">
                            Password
                        </label>

                        <div class="password-wrapper">

                            <input type="password" id="adminPassword" name="password" placeholder="Enter password"
                                required autocomplete="current-password">

                            <button type="button" class="show-password" onclick="togglePassword(
                                'adminPassword',
                                this
                            )" aria-label="Show password">
                                Show
                            </button>

                        </div>

                    </div>


                    <label class="remember-row">

                        <input type="checkbox" id="rememberMe">
                        Keep me signed in on this device

                    </label>


                    <button type="submit" class="btn">
                        Log in
                    </button>



                    <div class="message" id="adminMessage" role="status"></div>

                </form>

                <a href="index.php" class="back-link">
                    ← Back to customer / employee login
                </a>

            </div>

        </div>

    </div>


    <div class="modal-overlay" id="successModal" role="dialog" aria-modal="true" aria-labelledby="modalMessage">

        <div class="modal-box">

            <div class="modal-icon" aria-hidden="true">

                <svg viewBox="0 0 24 24" fill="none">

                    <circle cx="12" cy="12" r="11" fill="#ffc233" />

                    <path d="M7 12.5l3 3 7-7" stroke="#241900" stroke-width="2.2" stroke-linecap="round"
                        stroke-linejoin="round" />

                </svg>

            </div>


            <p id="modalMessage"></p>


            <button type="button" class="modal-close-btn" onclick="closeModal()">
                Continue
            </button>

        </div>

    </div>


    <script>


        function togglePassword(inputId, button) {

            const input =
                document.getElementById(inputId);

            if (input.type === "password") {

                input.type = "text";

                button.textContent = "Hide";

            } else {

                input.type = "password";

                button.textContent = "Show";

            }

        }

        function openModal(text) {

            document.getElementById(
                "modalMessage"
            ).textContent = text;

            document.getElementById(
                "successModal"
            ).classList.add("active");

        }


        function closeModal() {

            document.getElementById(
                "successModal"
            ).classList.remove("active");

        }

        document
            .getElementById("successModal")
            .addEventListener(
                "click",
                function (event) {

                    if (event.target === this) {

                        closeModal();

                    }

                }
            );


        document.addEventListener(
            "keydown",
            function (event) {

                if (event.key === "Escape") {

                    closeModal();

                }

            }
        );


        document
            .getElementById("adminLogin")
            .addEventListener(
                "submit",
                function (event) {

                    event.preventDefault();


                    const username =
                        document
                            .getElementById("adminUsername")
                            .value
                            .trim();


                    const password =
                        document
                            .getElementById("adminPassword")
                            .value;


                    const rememberMe =
                        document
                            .getElementById("rememberMe")
                            .checked;


                    const message =
                        document
                            .getElementById("adminMessage");

                    if (!username || !password) {

                        message.classList.add("error");

                        message.classList.remove("success");

                        message.style.display = "block";

                        message.textContent =
                            "Please enter your admin username and password.";

                        return;

                    }


                    fetch(this.action, {
                        method: "POST",
                        body: new FormData(this),
                        headers: { "Accept": "application/json" }
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (result) {
                            message.classList.toggle("error", !result.success);
                            message.classList.toggle("success", result.success);
                            message.style.display = "block";
                            message.textContent = result.message;

                            if (result.success) {
                                if (rememberMe) {
                                    localStorage.setItem("sunSonAdminLoggedIn", "true");
                                    localStorage.setItem("sunSonAdminUsername", username);
                                } else {
                                    sessionStorage.setItem("sunSonAdminLoggedIn", "true");
                                }
                                openModal(result.message);
                            }
                        })
                        .catch(function () {
                            message.classList.remove("success");
                            message.classList.add("error");
                            message.style.display = "block";
                            message.textContent = "Unable to contact the server. Please try again.";
                        });

                }
            );

    </script>

</body>

</html>