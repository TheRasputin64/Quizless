<?php
require_once 'config.php';
redirectIfNotAdmin();
$conn = getDBConnection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quiz_id = $_POST['quiz_id'];
    $admin_id = $_SESSION['admin_id'];
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("DELETE FROM student_attempts WHERE quiz_id = ?");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE sa FROM student_answers sa 
                                JOIN student_attempts sta ON sa.attempt_id = sta.attempt_id 
                                WHERE sta.quiz_id = ?");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE c FROM choices c 
                                JOIN questions q ON c.question_id = q.question_id 
                                WHERE q.quiz_id = ?");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM questions WHERE quiz_id = ?");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ? AND admin_id = ?");
        $stmt->bind_param("ii", $quiz_id, $admin_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            $_SESSION['message'] = 'تم حذف الاختبار بنجاح';
            $_SESSION['message_type'] = 'success';
        } else {
            $conn->rollback();
            $_SESSION['message'] = 'فشل حذف الاختبار';
            $_SESSION['message_type'] = 'error';
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = 'حدث خطأ أثناء حذف الاختبار';
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: admin.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حذف الاختبار - نظام الاختبارات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-profile">
                <img src="cat.gif" alt="" class="admin-avatar">
                <h3><?= htmlspecialchars($_SESSION['admin_name']) ?></h3>
            </div>
            <nav class="admin-nav">
                <a href="admin.php#active" class="nav-item">الاختبارات النشطة</a>
                <a href="admin.php#past" class="nav-item">الاختبارات السابقة</a>
                <a href="admin.php#drafts" class="nav-item">المسودات</a>
                <a href="create_quiz.php" class="nav-item">إنشاء اختبار جديد</a>
                <a href="student_results.php" class="nav-item">نتائج الطلاب</a>
                <a href="logout.php" class="nav-item logout">تسجيل الخروج</a>
            </nav>
        </aside>
        <main class="admin-main">
            <div class="action-overlay">
                <div class="overlay-content delete-modal">
                    <div class="overlay-icon delete-icon"></div>
                    <h3>هل أنت متأكد من حذف الاختبار؟</h3>
                    <p>سيتم إزالة الاختبار نهائياً ولن يمكن استعادته</p>
                    <div class="overlay-actions">
                        <form method="POST" action="">
                            <input type="hidden" name="quiz_id" value="<?= $_GET['id'] ?>">
                            <button type="submit" class="btn btn-delete">حذف</button>
                            <a href="admin.php" class="btn btn-results">إلغاء</a>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>