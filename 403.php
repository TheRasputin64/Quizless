<?php
// 403.php - Forbidden Access Error Page
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ممنوع الدخول - 403</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #7E57C2;
            --background-color: #F5F7FA;
            --card-color: #FFFFFF;
            --text-primary: #2C3E50;
            --text-secondary: #546E7A;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }
        body {
            background-color: var(--background-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .error-container {
            background-color: var(--card-color);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            padding: 3rem;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        .error-icon {
            width: 120px;
            height: 120px;
            background-color: rgba(126, 87, 194, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            position: relative;
        }
        .error-icon::before {
            content: '⛔';
            font-size: 4rem;
            position: absolute;
        }
        h1 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .btn-home {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }
        .btn-home:hover {
            transform: translateY(-3px);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon"></div>
        <h1>ممنوع الدخول</h1>
        <p>عذراً، ليس لديك إذن للوصول إلى هذه الصفحة. يرجى التأكد من صلاحياتك أو الاتصال بالمسؤول.</p>
        <a href="/" class="btn-home">العودة للصفحة الرئيسية</a>
    </div>
</body>
</html>