<?php
require_once 'config.php';
$conn = getDBConnection();
if (session_status() === PHP_SESSION_NONE) {session_start();}
if (isAdminLoggedIn()) {header('Location: admin.php');exit();} $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {$error = "جميع الحقول مطلوبة";} else {
        try {
            $stmt = $conn->prepare("SELECT admin_id, name, password, created_at FROM admin WHERE email = ? LIMIT 1");
            if ($stmt === false) {throw new Exception("Database preparation failed: " . $conn->error);}
            $stmt->bind_param("s", $email);
            if (!$stmt->execute()) {throw new Exception("Query execution failed: " . $stmt->error);}
            $result = $stmt->get_result();
            if ($admin = $result->fetch_assoc()) {
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['created_at'] = $admin['created_at'];
                    session_regenerate_id(true);
                    $admin = null;
                    $password = null;
                    header('Location: admin.php');exit();
                }
            }
            $error = "بيانات تسجيل الدخول غير صحيحة";
            $password = null;
        } catch (Exception $e) {error_log("Login error: " . $e->getMessage());
            $error = "حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً";
        } finally {
            if (isset($stmt)) {$stmt->close();}
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول المسؤول - نظام الاختبارات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
:root {
    --primary-pastel: #E3F2FD;
    --secondary-pastel: #F3E5F5;
    --accent-pastel: #E8F5E9;
    --text-color: #2C3E50;
    --error-color: #FFEBEE;
    --error-text: #EF5350;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Cairo', sans-serif;
}

body {
    background: url('login.png') no-repeat center center;
    background-size: cover;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-color);
    line-height: 1.6;
    padding: 1rem;
    position: relative;
    overflow-x: hidden;
}

.login-container {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    padding: 2.5rem;
    width: 100%;
    max-width: 400px;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 1;
}

.login-header {
    text-align: center;
    margin-bottom: 2rem;
}

.login-header h1 {
    font-size: 1.8rem;
    color: var(--text-color);
    margin-bottom: 0.5rem;
}

.login-header p {
    color: #546E7A;
    font-size: 0.9rem;
}

.form-group {
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-color);
    font-weight: 600;
    transition: all 0.3s ease;
}

.form-control {
    width: 100%;
    padding: 0.8rem 1rem;
    border: 2px solid #E0E7FF;
    border-radius: 10px;
    font-size: 1rem;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    background: white;
    color: var(--text-color);
}

.form-control:focus {
    outline: none;
    border-color: #5C6BC0;
    box-shadow: 0 0 6px rgba(92, 107, 192, 0.5);
}

.form-group.focused label {
    color: #5C6BC0;
    transform: translateY(-2px);
}

.submit-btn {
    width: 100%;
    padding: 0.8rem;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #7E57C2, #5C6BC0);
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(126, 87, 194, 0.2);
}

.submit-btn:active {
    transform: translateY(0);
}

.error-message {
    background-color: var(--error-color);
    color: var(--error-text);
    padding: 0.8rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    text-align: center;
    font-size: 0.9rem;
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-5px); }
    40%, 80% { transform: translateX(5px); }
}

.decorative-shape {
    position: absolute;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--accent-pastel), var(--primary-pastel));
    opacity: 0.6;
    filter: blur(40px);
    z-index: 0;
    animation: float 6s ease-in-out infinite;
}

.shape-1 { 
    top: 10%; 
    right: 15%; 
    animation-delay: 0s;
}

.shape-2 { 
    bottom: 20%; 
    left: 10%; 
    animation-delay: -3s;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(10deg); }
}

@media (max-width: 480px) {
    .login-container {
        padding: 2rem;
        margin: 1rem;
    }

    .login-header h1 {
        font-size: 1.5rem;
    }

    .form-control {
        padding: 0.7rem 0.9rem;
    }

    .decorative-shape {
        width: 80px;
        height: 80px;
    }
}

@media (max-height: 600px) {
    body {
        min-height: auto;
        padding: 2rem 1rem;
    }

    .login-container {
        margin: 1rem auto;
    }
}


    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>مرحباً بعودتك</h1>
            <p>قم بتسجيل الدخول للوصول إلى لوحة التحكم</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    autocomplete="off"
                    placeholder="أدخل بريدك الإلكتروني"
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    autocomplete="off"
                    placeholder="أدخل كلمة المرور"
                >
            </div>

            <button type="submit" class="submit-btn">
                تسجيل الدخول
            </button>
        </form>
    </div>

    <script>document.querySelectorAll('.form-control').forEach(input=>{input.addEventListener('focus',()=>input.parentElement.classList.add('focused'));input.addEventListener('blur',()=>{if(!input.value)input.parentElement.classList.remove('focused');});if(input.value){input.parentElement.classList.add('focused');}});document.querySelector('form').addEventListener('submit',e=>{let isValid=true;e.target.querySelectorAll('input[required]').forEach(input=>{if(!input.value.trim()){isValid=false;input.classList.add('error');}else{input.classList.remove('error');}});if(!isValid)e.preventDefault();});</script>
</body>
</html>
