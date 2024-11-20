<?php
require_once 'config.php';
$conn = getDBConnection();
if (session_status() === PHP_SESSION_NONE) {session_start();}
if (isStudentLoggedIn()) {header('Location: http://localhost/Quiz/');exit();}

$error = '';
$success = '';
$showPasswordField = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $student_code = trim($_POST['student_code']);
        $password = $_POST['password'];
        
        if (empty($name) || empty($student_code) || empty($password)) {
            $error = "الاسم وكود الطالب وكلمة المرور مطلوبة";
        } else {
            try {
                $stmt = $conn->prepare("SELECT student_code FROM students WHERE student_code = ?");
                $stmt->bind_param("s", $student_code);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "كود الطالب مستخدم بالفعل";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO students (name, phone, email, student_code, password, is_approved) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param("sssss", $name, $phone, $email, $student_code, $hashed_password);
                    if ($stmt->execute()) {
                        $success = "تم التسجيل بنجاح. يمكنك تسجيل الدخول الآن";
                    } else {
                        $error = "حدث خطأ في التسجيل";
                    }
                }
                $stmt->close();
            } catch (Exception $e) {
                $error = "حدث خطأ في النظام";
                error_log("Registration error: " . $e->getMessage());
            }
        }
    }

    if (isset($_POST['email_submit'])) {
        $identifier = trim($_POST['identifier']);
        
        try {
            $stmt = $conn->prepare("SELECT name, is_approved FROM students WHERE (email = ? OR student_code = ?) LIMIT 1");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($student = $result->fetch_assoc()) {
                if ($student['is_approved'] != 1) {
                    $error = "الحساب قيد المراجعة من قبل المسؤول";
                } else {
                    $_SESSION['login_identifier'] = $identifier;
                    $_SESSION['login_name'] = $student['name'];
                    $showPasswordField = true;
                }
            } else {
                $error = "البريد الإلكتروني أو كود الطالب غير مسجل";
            }
            $stmt->close();
        } catch (Exception $e) {
            $error = "حدث خطأ في النظام";
            error_log("Login check error: " . $e->getMessage());
        }
    }

    if (isset($_POST['login'])) {
        $identifier = $_SESSION['login_identifier'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($identifier) || empty($password)) {
            $error = "جميع الحقول مطلوبة";
        } else {
            try {
                $stmt = $conn->prepare("SELECT student_id, password, is_approved FROM students WHERE email = ? OR student_code = ? LIMIT 1");
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($student = $result->fetch_assoc()) {
                    if ($student['is_approved'] != 1) {
                        $error = "الحساب قيد المراجعة من قبل المسؤول";
                    } elseif (password_verify($password, $student['password'])) {
                        $_SESSION['student_id'] = $student['student_id'];
                        header('Location: http://localhost/Quiz/');
                        exit();
                    } else {
                        $error = "كلمة المرور غير صحيحة";
                    }
                }
                $stmt->close();
            } catch (Exception $e) {
                $error = "حدث خطأ في النظام";
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام الاختبارات - تسجيل الدخول</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="container">
        <div class="form-header">
            <h1>نظام الاختبارات</h1>
            <p id="form-subtitle">تسجيل دخول الطلاب</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div id="login-section" class="form-section active">
            <?php if (!$showPasswordField): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <input type="text" name="identifier" class="form-control" placeholder="البريد الإلكتروني أو كود الطالب" required>
                    </div>
                    <button type="submit" name="email_submit" class="submit-btn">متابعة</button>
                </form>
            <?php else: ?>
                <div class="username-display">
                    مرحبًا، <?= htmlspecialchars($_SESSION['login_name']) ?>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <input type="password" name="password" class="form-control" placeholder="كلمة المرور" required autofocus>
                    </div>
                    <button type="submit" name="login" class="submit-btn">تسجيل الدخول</button>
                </form>
                <div class="toggle-form">
                    <a href="?reset=1" class="toggle-btn">تغيير البيانات</a>
                </div>
            <?php endif; ?>
        </div>

        <div id="register-section" class="form-section">
            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" name="name" class="form-control" placeholder="اسم الطالب" required>
                </div>
                <div class="form-group">
                    <input type="tel" name="phone" class="form-control" placeholder="رقم الهاتف (اختياري)">
                </div>
                <div class="form-group">
                    <input type="email" name="email" class="form-control" placeholder="البريد الإلكتروني (اختياري)">
                </div>
                <div class="form-group">
                    <input type="text" name="student_code" class="form-control" placeholder="كود الطالب" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" class="form-control" placeholder="كلمة المرور" required>
                </div>
                <button type="submit" name="register" class="submit-btn">تسجيل</button>
            </form>
        </div>

        <div class="toggle-form">
            <a href="#" class="toggle-btn" id="toggleForm">إنشاء حساب جديد</a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginSection = document.getElementById('login-section');
        const registerSection = document.getElementById('register-section');
        const toggleBtn = document.getElementById('toggleForm');
        const subtitle = document.getElementById('form-subtitle');
        let isLoginView = true;

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            isLoginView = !isLoginView;
            
            if (isLoginView) {
                loginSection.classList.add('active');
                registerSection.classList.remove('active');
                toggleBtn.textContent = 'إنشاء حساب جديد';
                subtitle.textContent = 'تسجيل دخول الطلاب';
            } else {
                loginSection.classList.remove('active');
                registerSection.classList.add('active');
                toggleBtn.textContent = 'تسجيل الدخول';
                subtitle.textContent = 'تسجيل حساب جديد';
            }
        });

        <?php if (!empty($success)): ?>
        loginSection.classList.add('active');
        registerSection.classList.remove('active');
        toggleBtn.textContent = 'إنشاء حساب جديد';
        subtitle.textContent = 'تسجيل دخول الطلاب';
        <?php endif; ?>
    });
    </script>
</body>
</html>