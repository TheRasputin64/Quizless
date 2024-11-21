<?php
require_once 'config.php';
redirectIfNotStudent();
$conn = getDBConnection();

$student_id = $_SESSION['student_id'];

$stmt = $conn->prepare("SELECT name, student_code, email, phone FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

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

$active_quizzes_query = "
    SELECT 
        q.*,
        COUNT(DISTINCT qu.question_id) as question_count,
        sa.attempt_id,
        sa.score,
        CASE 
            WHEN sa.attempt_id IS NULL THEN 'not_started'
            WHEN sa.end_time IS NULL THEN 'in_progress'
            ELSE 'completed'
        END as attempt_status
    FROM quizzes q
    LEFT JOIN questions qu ON q.quiz_id = qu.quiz_id
    LEFT JOIN student_attempts sa ON q.quiz_id = sa.quiz_id AND sa.student_id = ?
    WHERE q.status = 'published' 
    AND q.quiz_date = CURDATE()
    AND CONCAT(q.quiz_date, ' ', q.end_datetime) > NOW()
    GROUP BY q.quiz_id
    ORDER BY q.start_datetime ASC";

$stmt = $conn->prepare($active_quizzes_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$active_quizzes = $stmt->get_result();

$upcoming_quizzes_query = "
    SELECT 
        q.*,
        COUNT(DISTINCT qu.question_id) as question_count
    FROM quizzes q
    LEFT JOIN questions qu ON q.quiz_id = qu.quiz_id
    WHERE q.status = 'published' 
    AND q.quiz_date > CURDATE()
    GROUP BY q.quiz_id
    ORDER BY q.quiz_date ASC, q.start_datetime ASC
    LIMIT 5";

$upcoming_quizzes = $conn->query($upcoming_quizzes_query);

$finished_quizzes_query = "
    SELECT 
        q.*,
        COUNT(DISTINCT qu.question_id) as question_count,
        sa.score,
        sa.end_time,
        (SELECT COUNT(*) FROM student_answers WHERE attempt_id = sa.attempt_id AND is_correct = 1) as correct_answers,
        (SELECT COUNT(*) FROM student_answers WHERE attempt_id = sa.attempt_id) as total_answers
    FROM quizzes q
    LEFT JOIN questions qu ON q.quiz_id = qu.quiz_id
    INNER JOIN student_attempts sa ON q.quiz_id = sa.quiz_id AND sa.student_id = ?
    WHERE sa.end_time IS NOT NULL
    GROUP BY q.quiz_id, sa.score, sa.end_time
    ORDER BY sa.end_time DESC
    LIMIT 10";

$stmt = $conn->prepare($finished_quizzes_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$finished_quizzes = $stmt->get_result();

$performance_stats_query = "
    SELECT 
        COUNT(*) as total_quizzes,
        ROUND(AVG(score), 1) as avg_score,
        MAX(score) as highest_score,
        MIN(score) as lowest_score,
        COUNT(CASE WHEN score >= 90 THEN 1 END) as excellent_count,
        COUNT(CASE WHEN score >= 75 AND score < 90 THEN 1 END) as good_count,
        COUNT(CASE WHEN score < 75 THEN 1 END) as needs_improvement_count
    FROM student_attempts
    WHERE student_id = ? AND end_time IS NOT NULL";

$stmt = $conn->prepare($performance_stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$performance_stats = $stmt->get_result()->fetch_assoc();

$missing_quizzes_query = "
    SELECT 
        q.*,
        COUNT(DISTINCT qu.question_id) as question_count
    FROM quizzes q
    LEFT JOIN questions qu ON q.quiz_id = qu.quiz_id
    LEFT JOIN student_attempts sa ON q.quiz_id = sa.quiz_id AND sa.student_id = ?
    WHERE q.status = 'published' 
    AND CONCAT(q.quiz_date, ' ', q.end_datetime) < NOW()  /* Check if quiz has ended */
    AND sa.attempt_id IS NULL  /* No attempt exists */
    GROUP BY q.quiz_id
    ORDER BY q.quiz_date DESC
    LIMIT 5";

$stmt = $conn->prepare($missing_quizzes_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$missing_quizzes = $stmt->get_result();

function getScoreClass($score) {
    if ($score === null) return '';
    if ($score >= 90) return 'excellent';
    if ($score >= 75) return 'good';
    return 'needs-improvement';
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
    <link rel="stylesheet" href="student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="top-nav">
        <div class="nav-content">
            <div class="nav-brand">
                <i class="fas fa-graduation-cap"></i>
                نظام الاختبارات
            </div>
            <div class="nav-user">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($student['name']) ?></span>
                    <span class="user-details">
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($student['student_code']) ?>
                        <?php if($student['phone']): ?>
                            <i class="fas fa-phone"></i> <?= htmlspecialchars($student['phone']) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <a href="profile.php" class="profile-btn"><i class="fas fa-user"></i> الملف الشخصي</a>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
            </div>
        </div>
    </nav>

    <main class="dashboard-content">
        <div class="welcome-banner">
            <h1>مرحباً، <?= htmlspecialchars($student['name']) ?></h1>
            <p>نتمنى لك يوماً دراسياً موفقاً</p>
        </div>

        <div class="stats-cards">
    <div class="stat-card">
        <i class="fas fa-check-circle"></i>
        <h3>الاختبارات المكتملة</h3>
        <p class="stat-value"><?= $performance_stats['total_quizzes'] ?></p>
    </div>
    <div class="stat-card">
        <i class="fas fa-chart-line"></i>
        <h3>متوسط الدرجات</h3>
        <p class="stat-value <?= getScoreClass($performance_stats['avg_score']) ?>">
            <?= number_format($performance_stats['avg_score'], 1) ?>%
        </p>
    </div>
    <div class="stat-card">
        <i class="fas fa-trophy"></i>
        <h3>أعلى درجة</h3>
        <p class="stat-value <?= getScoreClass($performance_stats['highest_score']) ?>">
            <?= number_format($performance_stats['highest_score'], 1) ?>%
        </p>
    </div>
    <div class="stat-card">
        <i class="fas fa-award"></i>
        <h3>اختبارات ممتازة</h3>
        <p class="stat-value excellent"><?= $performance_stats['excellent_count'] ?></p>
    </div>
</div>

        <section class="quiz-section">
            <div class="section-header">
                <h2><i class="fas fa-clock"></i> الاختبارات النشطة</h2>
                <div class="active-indicator">متاح الآن</div>
            </div>
            <div class="quiz-grid">
                <?php if ($active_quizzes->num_rows > 0): ?>
                    <?php while ($quiz = $active_quizzes->fetch_assoc()): ?>
                        <div class="quiz-card <?= $quiz['attempt_status'] ?>">
                            <div class="quiz-header">
                                <h3><?= htmlspecialchars($quiz['title']) ?></h3>
                                <?php if ($quiz['attempt_status'] == 'completed'): ?>
                                    <span class="score-badge <?= getScoreClass($quiz['score']) ?>">
                                        <?= $quiz['score'] ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="time-remaining">
                                        <?php
                                        $remainingTime = getRemainingTime($quiz['end_datetime'], $quiz['quiz_date']);
                                        echo $remainingTime ? 'متبقي ' . $remainingTime : 'انتهى الوقت';
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="quiz-description"><?= htmlspecialchars($quiz['description']) ?></p>
                            <div class="quiz-info">
                                <div class="info-item">
                                    <i class="fas fa-question-circle"></i>
                                    <span>عدد الأسئلة</span>
                                    <strong><?= $quiz['question_count'] ?></strong>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-hourglass-end"></i>
                                    <span>وقت النهاية</span>
                                    <strong><?= date('h:i A', strtotime($quiz['end_datetime'])) ?></strong>
                                </div>
                            </div>
                            <?php if ($quiz['attempt_status'] == 'not_started'): ?>
                                <a href="take_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="quiz-btn start-btn">
                                    <i class="fas fa-play"></i> بدء الاختبار
                                </a>
                            <?php elseif ($quiz['attempt_status'] == 'in_progress'): ?>
                                <a href="take_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="quiz-btn continue-btn">
                                    <i class="fas fa-redo"></i> متابعة الاختبار
                                </a>
                            <?php else: ?>
                                <div class="quiz-completed">
                                    <span class="completed-text">
                                        <i class="fas fa-check-circle"></i> تم الانتهاء
                                    </span>
                                    <a href="review_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="review-link">
                                        مراجعة النتائج
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-quizzes">
                        <i class="fas fa-info-circle"></i>
                        <p>لا توجد اختبارات نشطة حالياً</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="quiz-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-alt"></i> الاختبارات القادمة</h2>
            </div>
            <div class="quiz-grid">
                <?php if ($upcoming_quizzes->num_rows > 0): ?>
                    <?php while ($quiz = $upcoming_quizzes->fetch_assoc()): ?>
                        <div class="quiz-card upcoming">
                            <div class="quiz-header">
                                <h3><?= htmlspecialchars($quiz['title']) ?></h3>
                                <span class="date-badge">
                                    <?= date('Y/m/d', strtotime($quiz['quiz_date'])) ?>
                                </span>
                            </div>
                            <p class="quiz-description"><?= htmlspecialchars($quiz['description']) ?></p>
                            <div class="quiz-info">
                                <div class="info-item">
                                    <i class="fas fa-question-circle"></i>
                                    <span>عدد الأسئلة</span>
                                    <strong><?= $quiz['question_count'] ?></strong>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <span>موعد البدء</span>
                                    <strong><?= date('h:i A', strtotime($quiz['start_datetime'])) ?></strong>
                                </div>
                            </div>
                            <div class="upcoming-notice">
                                <i class="fas fa-info-circle"></i>
                                سيكون متاحاً في الموعد المحدد
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-quizzes">
                        <i class="fas fa-calendar-times"></i>
                        <p>لا توجد اختبارات قادمة</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="quiz-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> الاختبارات السابقة</h2>
                <a href="all_quizzes.php" class="view-all-btn">عرض الكل</a>
            </div>
            <div class="quiz-grid">
                <?php if ($finished_quizzes->num_rows > 0): ?>
                    <?php while ($quiz = $finished_quizzes->fetch_assoc()): ?>
                        <div class="quiz-card finished">
                            <div class="quiz-header">
                                <h3><?= htmlspecialchars($quiz['title']) ?></h3>
                                <span class="score-badge <?= getScoreClass($quiz['score']) ?>">
                                    <?= $quiz['score'] ?>%
                                </span>
                            </div>
                            <div class="quiz-stats">
                                <div class="stat">
                                    <span>الإجابات الصحيحة</span>
                                    <strong><?= $quiz['correct_answers'] ?>/<?= $quiz['total_answers'] ?></strong>
                                </div>
                                <div class="stat">
                                    <span>تاريخ الإنهاء</span>
                                    <strong><?= date('Y/m/d h:i A', strtotime($quiz['end_time'])) ?></strong>
                                </div>
                            </div>
                            <a href="review_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="quiz-btn review-btn">
                                <i class="fas fa-search"></i> مراجعة الاختبار
                            </a>
                        </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <div class="no-quizzes">
                        <i class="fas fa-history"></i>
                        <p>لا توجد اختبارات سابقة</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="quiz-section">
    <div class="section-header">
        <h2><i class="fas fa-exclamation-triangle"></i> اختبارات لم يتم أداؤها</h2>
    </div>
    <div class="quiz-grid">
        <?php if ($missing_quizzes && $missing_quizzes->num_rows > 0): ?>
            <?php while ($quiz = $missing_quizzes->fetch_assoc()): ?>
                <div class="quiz-card missed">
                    <div class="quiz-header">
                        <h3><?= htmlspecialchars($quiz['title']) ?></h3>
                        <span class="score-badge needs-improvement">0%</span>
                    </div>
                    <p class="quiz-description"><?= htmlspecialchars($quiz['description']) ?></p>
                    <div class="quiz-stats">
                        <div class="stat">
                            <span>تاريخ الاختبار</span>
                            <strong><?= date('Y/m/d', strtotime($quiz['quiz_date'])) ?></strong>
                        </div>
                        <div class="stat">
                            <span>عدد الأسئلة</span>
                            <strong><?= $quiz['question_count'] ?></strong>
                        </div>
                    </div>
                    <div class="missed-notice">
                        <i class="fas fa-times-circle"></i>
                        لم يتم أداء هذا الاختبار
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-quizzes">
                <i class="fas fa-check-circle"></i>
                <p>لا توجد اختبارات فائتة</p>
            </div>
        <?php endif; ?>
    </div>
</section>

    </main>

    <script>
        // Update remaining time every minute
        setInterval(function() {
            const timeElements = document.querySelectorAll('.time-remaining');
            timeElements.forEach(element => {
                let hours = parseInt(element.textContent.match(/\d+/)[0]);
                if (hours > 0) {
                    if (hours === 1) {
                        element.textContent = `متبقي ${59} دقيقة`;
                    } else {
                        element.textContent = `متبقي ${hours - 1} ساعة`;
                    }
                } else {
                    // Refresh page when time is up
                    window.location.reload();
                }
            });
        }, 60000);
    </script>
</body>
</html>