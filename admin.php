<?php
require_once 'config.php';
redirectIfNotAdmin();
$conn = getDBConnection();

$active_quizzes_query = "SELECT q.*, COUNT(DISTINCT sa.student_id) as attempt_count 
                        FROM quizzes q 
                        LEFT JOIN student_attempts sa ON q.quiz_id = sa.quiz_id 
                        WHERE q.admin_id = ? 
                        AND q.status = 'published' 
                        AND CONCAT(q.quiz_date, ' ', q.end_datetime) > NOW()
                        GROUP BY q.quiz_id 
                        ORDER BY q.created_at DESC";

// Past quizzes query - Should show quizzes that have ended
$past_quizzes_query = "SELECT q.*, COUNT(DISTINCT sa.student_id) as attempt_count 
                       FROM quizzes q 
                       LEFT JOIN student_attempts sa ON q.quiz_id = sa.quiz_id 
                       WHERE q.admin_id = ? 
                       AND q.status = 'published' 
                       AND CONCAT(q.quiz_date, ' ', q.end_datetime) <= NOW()
                       GROUP BY q.quiz_id 
                       ORDER BY q.created_at DESC";

// Draft quizzes query remains the same as it doesn't depend on time
$draft_quizzes_query = "SELECT q.*, COUNT(DISTINCT sa.student_id) as attempt_count 
                        FROM quizzes q 
                        LEFT JOIN student_attempts sa ON q.quiz_id = sa.quiz_id 
                        WHERE q.admin_id = ? AND q.status = 'draft'
                        GROUP BY q.quiz_id 
                        ORDER BY q.created_at DESC";

$stmt = $conn->prepare($active_quizzes_query);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$active_quizzes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare($past_quizzes_query);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$past_quizzes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare($draft_quizzes_query);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$draft_quizzes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function getRemainingTime($end_datetime, $quiz_date) {
    date_default_timezone_set('Africa/Cairo');
    $end = new DateTime($quiz_date . ' ' . $end_datetime);
    $now = new DateTime();
    $interval = $now->diff($end);
    
    if ($interval->invert) {
        return false;
    }
    
    $total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    
    $days = floor($total_minutes / (24 * 60));
    $hours = floor(($total_minutes % (24 * 60)) / 60);
    $minutes = $total_minutes % 60;
    
    $daysText = '';
    if ($days > 0) {
        if ($days == 1) {
            $daysText = 'يوم';
        } elseif ($days == 2) {
            $daysText = 'يومان';
        } elseif ($days >= 3 && $days <= 10) {
            $daysText = $days . ' أيام';
        } else {
            $daysText = $days . ' يوماً';
        }
    }
    
    $hoursText = '';
    if ($hours > 0) {
        if ($hours == 1) {
            $hoursText = 'ساعة';
        } elseif ($hours == 2) {
            $hoursText = 'ساعتان';
        } elseif ($hours >= 3 && $hours <= 10) {
            $hoursText = $hours . ' ساعات';
        } else {
            $hoursText = $hours . ' ساعة';
        }
    }
    
    $minutesText = '';
    if ($minutes > 0) {
        if ($minutes == 1) {
            $minutesText = 'دقيقة';
        } elseif ($minutes == 2) {
            $minutesText = 'دقيقتان';
        } elseif ($minutes >= 3 && $minutes <= 10) {
            $minutesText = $minutes . ' دقائق';
        } else {
            $minutesText = $minutes . ' دقيقة';
        }
    }
    
    $parts = array_filter([$daysText, $hoursText, $minutesText]);
    return implode('، ', $parts);
}



?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام الاختبارات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script>
    function copyQuizLink(quizId, accessCode) {
        const link = `${window.location.protocol}//${window.location.host}/Quiz/take_quiz.php?id=${quizId}&code=${accessCode}`;
        navigator.clipboard.writeText(link).then(() => {
            const btn = document.querySelector(`#copy-btn-${quizId}`);
            btn.textContent = 'تم النسخ!';
            setTimeout(() => {
                btn.textContent = 'نسخ الرابط';
            }, 2000);
        });
    }
</script>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-profile">
                <img src="cat.gif" alt="" class="admin-avatar">
                <h3><?= htmlspecialchars($_SESSION['admin_name']) ?></h3>
            </div>
            <nav class="admin-nav">
                <a href="#active" class="nav-item active">الاختبارات النشطة</a>
                <a href="#past" class="nav-item">الاختبارات السابقة</a>
                <a href="#drafts" class="nav-item">المسودات</a>
                <a href="create_quiz.php" class="nav-item">إنشاء اختبار جديد</a>
                <a href="student_results.php" class="nav-item">نتائج الطلاب</a>
                <a href="logout.php" class="nav-item logout">تسجيل الخروج</a>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <h1>لوحة التحكم</h1>
                <div class="quick-stats">
                    <div class="stat-card">
                        <h4>الاختبارات النشطة</h4>
                        <span><?= count($active_quizzes) ?></span>
                    </div>
                    <div class="stat-card">
                        <h4>إجمالي الاختبارات</h4>
                        <span><?= count($active_quizzes) + count($past_quizzes) + count($draft_quizzes) ?></span>
                    </div>
                </div>
            </header>

            <?php if (empty($active_quizzes) && empty($past_quizzes) && empty($draft_quizzes)): ?>
            <div class="main-empty-state">
                <div class="empty-content">
                    <h2>لا توجد اختبارات حتى الآن</h2>
                    <p>قم بإنشاء اختبار جديد لبدء العمل</p>
                    <a href="create_quiz.php" class="btn-create">إنشاء اختبار جديد</a>
                </div>
            </div>
            <?php else: ?>
            <section id="active" class="quiz-section">
                <div class="section-header">
                    <h2>الاختبارات النشطة</h2>
                </div>
                <?php if (empty($active_quizzes)): ?>
                <div class="section-empty-state">
                    <h3>لا توجد اختبارات نشطة</h3>
                    <p>يمكنك إنشاء اختبار جديد أو نشر اختبار من المسودات</p>
                </div>
                <?php else: ?>
                <div class="quiz-grid">
                    <?php foreach ($active_quizzes as $quiz): 
                        $remaining_time = getRemainingTime($quiz['end_datetime'], $quiz['quiz_date']);
                    ?>
                    <div class="quiz-card active">
                        <div class="quiz-card-header">
                            <h3><?= htmlspecialchars($quiz['title']) ?></h3>
                            <span class="status active">نشط</span>
                        </div>
                        <div class="quiz-stats">
                            <div class="stat">
                                <span>المشاركين</span>
                                <strong><?= $quiz['attempt_count'] ?></strong>
                            </div>
                            <div class="stat">
                                <span>وقت البدء</span>
                                <strong><?= date('h:i A', strtotime($quiz['start_datetime'])) ?></strong>
                            </div>
                        </div>
                        <div class="time-info">
                            <div class="countdown">
                                <span>الوقت المتبقي:</span>
                                <strong><?= $remaining_time ? $remaining_time : 'انتهى' ?></strong>
                            </div>
                            <div class="date">
                                <span>التاريخ:</span>
                                <strong><?= date('Y/m/d', strtotime($quiz['quiz_date'])) ?></strong>
                            </div>
                        </div>
                        <div class="quiz-actions">
                            <button id="copy-btn-<?= $quiz['quiz_id'] ?>" 
                                    onclick="copyQuizLink('<?= $quiz['quiz_id'] ?>', '<?= $quiz['access_code'] ?>')" 
                                    class="btn btn-copy">نسخ الرابط</button>
                            <a href="edit_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="btn btn-edit">تعديل</a>
                            <a href="quiz_results.php?id=<?= $quiz['quiz_id'] ?>" class="btn btn-results">النتائج</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <section id="past" class="quiz-section">
                <div class="section-header">
                    <h2>الاختبارات السابقة</h2>
                </div>
                <?php if (empty($past_quizzes)): ?>
                <div class="section-empty-state">
                    <h3>لا توجد اختبارات سابقة</h3>
                    <p>ستظهر هنا الاختبارات المنتهية بمجرد اكتمال موعد انتهائها</p>
                </div>
                <?php else: ?>
                <div class="quiz-grid">
                    <?php foreach ($past_quizzes as $quiz): ?>
                    <div class="quiz-card past">
                        <div class="quiz-card-header">
                            <h3><?= htmlspecialchars($quiz['title']) ?></h3>
                            <span class="status past">منتهي</span>
                        </div>
                        <div class="quiz-stats">
                            <div class="stat">
                                <span>إجمالي المشاركين</span>
                                <strong><?= $quiz['attempt_count'] ?></strong>
                            </div>
                            <div class="stat">
                                <span>تاريخ الانتهاء</span>
                                <strong><?= date('Y/m/d', strtotime($quiz['end_datetime'])) ?></strong>
                            </div>
                        </div>
                        <div class="quiz-actions">
                            <a href="quiz_results.php?id=<?= $quiz['quiz_id'] ?>" class="btn btn-results">عرض النتائج</a>
                            <a href="quiz_statistics.php?id=<?= $quiz['quiz_id'] ?>" class="btn btn-stats">الإحصائيات</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <section id="drafts" class="quiz-section">
                <div class="section-header">
                    <h2>المسودات</h2>
                </div>
                <?php if (empty($draft_quizzes)): ?>
                <div class="section-empty-state">
                    <h3>لا توجد مسودات</h3>
                    <p>يمكنك حفظ الاختبارات كمسودة للعمل عليها لاحقاً</p>
                    <a href="create_quiz.php" class="btn-create">إنشاء اختبار جديد</a>
                </div>
                <?php else: ?>
                <div class="quiz-grid">
                    <?php foreach ($draft_quizzes as $quiz): ?>
                    <div class="quiz-card draft">
                        <div class="quiz-card-header">
                            <h3><?= htmlspecialchars($quiz['title']) ?></h3>
                            <span class="status draft">مسودة</span>
                        </div>
                        <div class="quiz-stats">
                            <div class="stat">
                                <span>موعد البدء</span>
                                <strong><?= date('h:i A', strtotime($quiz['start_datetime'])) ?></strong>
                            </div>
                            <div class="stat">
                                <span>موعد الانتهاء</span>
                                <strong><?= date('h:i A', strtotime($quiz['end_datetime'])) ?></strong>
                            </div>
                        </div>
                        <div class="time-info">
                            <div class="date">
                                <span>تاريخ الاختبار:</span>
                                <strong><?= date('Y/m/d', strtotime($quiz['quiz_date'])) ?></strong>
                            </div>
                            <div class="duration">
                                <span>المدة:</span>
                                <strong><?= round((strtotime($quiz['end_datetime']) - strtotime($quiz['start_datetime'])) / 60) ?> دقيقة</strong>
                            </div>
                        </div>
                        <div class="quiz-actions">
                            <a href="edit_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="btn btn-edit">تعديل</a>
                            <a href="publish_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="btn btn-publish">نشر</a>
                            <a href="delete_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="btn btn-delete">حذف</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>