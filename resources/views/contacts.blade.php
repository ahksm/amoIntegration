<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Second Task</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: sans-serif;
        }

        body {
            margin: auto;
            display: flex;
            height: 100vh;
            align-items: center;
            justify-content: center;
            background-image: url("https://st3.depositphotos.com/15802718/32365/i/450/depositphotos_323654270-stock-photo-watercolor-tropical-background-monstera-leaves.jpg");
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
            overflow: hidden;
        }

        .container {
            width: 650px;
            padding: 25px;
            margin: 0 25px;
            background: transparent;
            overflow: hidden;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.4);
        }

        .container h2 {
            font-size: 25px;
            font-weight: 500;
            text-align: left;
            color: white;
            padding-bottom: 5px;
            border-bottom: 1px solid #fff;
        }

        .content {
            display: flex;
            flex-wrap: wrap;
            padding: 20px 0;
            justify-content: space-between;
        }

        .box-content {
            display: flex;
            flex-wrap: wrap;
            width: 50%;
            padding-bottom: 15px;
        }

        .box-content:nth-child(2n) {
            justify-content: end;
        }

        .box-content label,
        .gender-title {
            width: 95%;
            color: #fff;
            margin: 5px 0;
            font-weight: bold;
        }

        .gender-title {
            font-size: 18px;
        }

        .box-content input {
            height: 40px;
            width: 95%;
            border-radius: 10px;
            border: 1px solid #fff;
            outline: none;
            padding: 0 10px;
        }

        .gender label {
            padding: 0 20px 0 5px;
            font-size: 15px;
            color: #fff;
        }

        .gender label,
        input:hover {
            cursor: pointer;
        }

        .alert p {
            font-size: 18px;
            color: #fff;
        }

        .alert a {
            text-decoration: none;
            color: aqua;
            line-height: 1.5;
            text-align: justify;
            margin-bottom: 10px;
        }

        .alert a:hover {
            cursor: pointer;
            text-decoration: underline;
            font-style: italic;
        }

        .button-container button {
            width: 100%;
            padding: 15px 0;
            background-image: linear-gradient(to right, blue, skyblue);
            border: none;
            outline: none;
            border-radius: 10px;
            color: #fff;
            font-size: 18px;
            margin: 10px 0;
        }

        .button-container button:hover {
            cursor: pointer;
            background-image: linear-gradient(to right, skyblue, blue);
        }

        .toast {
            position: absolute;
            top: 25px;
            right: 30px;
            border-radius: 12px;
            background: #fff;
            padding: 20px 35px 20px 25px;
            box-shadow: 0 6px 20px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transform: translateX(calc(100% + 30px));
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.35);
        }

        .toast.active {
            transform: translateX(0%);
        }

        .toast .toast-content {
            display: flex;
            align-items: center;
        }

        @if (session('error'))
            .toast-content .check {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 35px;
                min-width: 35px;
                background-color: #ff3927;
                color: #fff;
                font-size: 20px;
                border-radius: 50%;
            }
        @endif

        @if (session('success'))
            .toast-content .check {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 35px;
                min-width: 35px;
                background-color: #27ff68;
                color: #fff;
                font-size: 20px;
                border-radius: 50%;
            }
        @endif

        .toast-content .message {
            display: flex;
            flex-direction: column;
            margin: 0 20px;
        }

        .message .text {
            font-size: 16px;
            font-weight: 400;
            color: #666666;
        }

        .message .text.text-1 {
            font-weight: 600;
            color: #333;
        }

        .toast .close {
            position: absolute;
            top: 10px;
            right: 15px;
            padding: 5px;
            cursor: pointer;
            opacity: 0.7;
        }

        .toast .close:hover {
            opacity: 1;
        }

        .toast .progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;

        }

        @if (session('error'))
            .toast .progress:before {
                content: "";
                position: absolute;
                bottom: 0;
                right: 0;
                height: 100%;
                width: 100%;
                background-color: #ff3927;
            }
        @endif

        @if (session('success'))
            .toast .progress:before {
                content: "";
                position: absolute;
                bottom: 0;
                right: 0;
                height: 100%;
                width: 100%;
                background-color: #27ff68;
            }
        @endif

        .progress.active:before {
            animation: progress 5s linear forwards;
        }

        @keyframes progress {
            100% {
                right: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <form action="/get-contacts" method="POST">
            @csrf
            <h2>Регистрация Контакта</h2>
            <div class="content">

                <div class="box-content">
                    <label for="first_name">Имя</label>
                    <input type="text" name="first_name" placeholder="Введите своё имя" required>
                </div>

                <div class="box-content">
                    <label for="last_name">Фамилия</label>
                    <input type="text" name="last_name" placeholder="Введите свою фамилию" required>
                </div>

                <div class="box-content">
                    <label for="age">Возраст</label>
                    <input type="number" name="age" placeholder="Введите свой возраст" min="0" required>
                </div>

                <div class="box-content">
                    <label for="fullname">Email</label>
                    <input type="email" name="email" placeholder="Введите свой Email" required>
                </div>

                <div class="box-content">
                    <label for="phone">Номер Телефона</label>
                    <input type="text" name="phone" placeholder="Введите свой номер телефона" required>
                </div>

                <span class="gender-title">Пол</span>
                <div class="gender">
                    <input type="radio" name="gender" value="Мужской"><label>Мужской</label>
                    <input type="radio" name="gender" value="Женский"><label>Женский</label>
                    <input type="radio" name="gender" value="Другой"><label>Другой</label>
                </div>
            </div>

            <div class="button-container">
                <button type="submit">Зарегистрировать</button>
            </div>
        </form>
    </div>

    @if (session('success') || session('error'))
        <div class="toast">
            <div class="toast-content">
                <i class="fas fa-solid fa-check check"></i>
                <div class="message">
                    @if (session('success'))
                        <span class="text text-1">Успех</span>
                        <span class="text text-2">{{ session('success') }}</span>
                    @elseif (session('error'))
                        <span class="text text-1">Ошибка</span>
                        <span class="text text-2">{{ session('error') }}</span>
                    @endif
                </div>
            </div>
            <i class="fa-solid fa-xmark close"></i>
            <div class="progress"></div>
        </div>
        <script>
            const toast = document.querySelector(".toast");
            const closeIcon = document.querySelector(".close");
            const progress = document.querySelector(".progress");

            let timer;

            const showToast = () => {
                toast.classList.add("active");
                progress.classList.add("active");

                timer = setTimeout(() => {
                    toast.classList.remove("active");
                    clearTimeout(timer);
                }, 5000);
            };

            document.addEventListener("DOMContentLoaded", () => {
                showToast();
            });

            closeIcon.addEventListener("click", () => {
                toast.classList.remove("active");

                setTimeout(() => {
                    progress.classList.remove("active");
                }, 300);

                clearTimeout(timer);
            });
        </script>
    @endif

</body>

</html>
