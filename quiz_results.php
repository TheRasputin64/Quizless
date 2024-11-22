<?php
require_once 'config.php';
redirectIfNotAdmin();
$conn = getDBConnection();

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$quiz_id = $_GET['id'];

$quiz_query = "SELECT * FROM quizzes WHERE quiz_id = ? AND admin_id = ?";
$stmt = $conn->prepare($quiz_query);
$stmt->bind_param("ii", $quiz_id, $_SESSION['admin_id']);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();

if (!$quiz) {
    header('Location: dashboard.php');
    exit;
}

$total_students_query = "SELECT COUNT(*) as total FROM students WHERE is_approved = 1";
$total_students = $conn->query($total_students_query)->fetch_assoc()['total'];

$participated_query = "SELECT COUNT(DISTINCT sa.student_id) as count 
                      FROM student_attempts sa 
                      JOIN students s ON sa.student_id = s.student_id
                      WHERE sa.quiz_id = ? AND s.is_approved = 1";
$stmt = $conn->prepare($participated_query);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$participated_count = $stmt->get_result()->fetch_assoc()['count'];

$not_participated = $total_students - $participated_count;

$passing_score = 50;
$passed_query = "SELECT COUNT(DISTINCT sa.student_id) as count 
                FROM student_attempts sa 
                JOIN students s ON sa.student_id = s.student_id
                WHERE sa.quiz_id = ? AND sa.score >= ? AND s.is_approved = 1";
$stmt = $conn->prepare($passed_query);
$stmt->bind_param("ii", $quiz_id, $passing_score);
$stmt->execute();
$passed_count = $stmt->get_result()->fetch_assoc()['count'];

$pass_percentage = $participated_count > 0 ? round(($passed_count / $participated_count) * 100, 1) : 0;

$students_results_query = "SELECT s.name, s.student_code, sa.start_time, sa.end_time, sa.score,
                          TIMESTAMPDIFF(SECOND, sa.start_time, sa.end_time) as duration
                          FROM student_attempts sa
                          JOIN students s ON sa.student_id = s.student_id
                          WHERE sa.quiz_id = ? AND s.is_approved = 1
                          ORDER BY sa.score DESC, sa.end_time ASC";
$stmt = $conn->prepare($students_results_query);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$students_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$not_participated_students_query = "SELECT name, student_code 
                                  FROM students 
                                  WHERE is_approved = 1 
                                  AND student_id NOT IN (
                                      SELECT student_id 
                                      FROM student_attempts 
                                      WHERE quiz_id = ?
                                  )";
$stmt = $conn->prepare($not_participated_students_query);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$not_participated_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function formatDuration($seconds) {
    if ($seconds < 60) {
        if ($seconds == 1) {
            return "ثانية واحدة";
        } elseif ($seconds == 2) {
            return "ثانيتان";
        } elseif ($seconds >= 3 && $seconds <= 10) {
            return $seconds . " ثوانٍ";
        } else {
            return $seconds . " ثانية";
        }
    } else {
        $minutes = floor($seconds / 60);
        if ($minutes == 1) {
            return "دقيقة واحدة";
        } elseif ($minutes == 2) {
            return "دقيقتان";
        } elseif ($minutes >= 3 && $minutes <= 10) {
            return $minutes . " دقائق";
        } else {
            return $minutes . " دقيقة";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="results.css">
    <script>
    function filterStudents() {
        const searchTerm = document.getElementById('student-search').value.toLowerCase();
        const statusFilter = document.getElementById('status-filter').value;
        const rows = document.querySelectorAll('.student-row');
        const tableWrapper = document.querySelector('.students-table-wrapper');
        const emptyState = document.querySelector('.main-empty-state');
        const emptyTitle = document.querySelector('.empty-content h2');
        const emptyText = document.querySelector('.empty-content p');
        
        let visibleRows = 0;

        rows.forEach(row => {
            const name = row.querySelector('[data-student-name]').getAttribute('data-student-name').toLowerCase();
            const code = row.querySelector('[data-student-code]').getAttribute('data-student-code').toLowerCase();
            const score = parseInt(row.querySelector('[data-score]').getAttribute('data-score'));
            const participated = row.hasAttribute('data-participated');

            const nameMatch = name.includes(searchTerm) || code.includes(searchTerm);
            const statusMatch = statusFilter === 'all' || 
                (statusFilter === 'passed' && score >= 50) || 
                (statusFilter === 'failed' && score < 50) ||
                (statusFilter === 'not_participated' && !participated);

            if (nameMatch && statusMatch) {
                row.style.display = '';
                visibleRows++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleRows === 0) {
            tableWrapper.style.display = 'none';
            emptyState.style.display = 'flex';

            if (searchTerm && statusFilter !== 'all') {
                emptyTitle.textContent = 'لا توجد نتائج للبحث';
                emptyText.textContent = 'لا توجد نتائج تطابق معايير البحث والتصفية المحددة';
            } else if (searchTerm) {
                emptyTitle.textContent = 'لا توجد نتائج للبحث';
                emptyText.textContent = 'لا توجد نتائج تطابق كلمة البحث';
            } else if (statusFilter === 'passed') {
                emptyTitle.textContent = 'لا يوجد طلاب ناجحون';
                emptyText.textContent = 'لا يوجد طلاب حققوا درجة النجاح في هذا الاختبار';
            } else if (statusFilter === 'failed') {
                emptyTitle.textContent = 'لا يوجد طلاب راسبون';
                emptyText.textContent = 'جميع الطلاب المشاركين نجحوا في هذا الاختبار';
            } else if (statusFilter === 'not_participated') {
                emptyTitle.textContent = 'لا يوجد طلاب غير مشاركين';
                emptyText.textContent = 'جميع الطلاب شاركوا في هذا الاختبار';
            }
        } else {
            tableWrapper.style.display = 'block';
            emptyState.style.display = 'none';
        }
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
                <a href="admin.php" class="nav-item">الاختبارات</a>
                <a href="" class="nav-item active">نتيجة الإختبار</a>
                <a href="student_results.php" class="nav-item">نتائج الطلاب</a>
                <a href="create_quiz.php" class="nav-item">إنشاء اختبار جديد</a>
                <a href="logout.php" class="nav-item logout">تسجيل الخروج</a>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <h1>(<?= htmlspecialchars($quiz['title']) ?>)</h1>
                <div class="quick-stats">
                    <div class="stat-card">
                        <h4>إجمالي الطلاب المشاركين</h4>
                        <span><?= $participated_count ?></span>
                    </div>
                    <div class="stat-card">
                        <h4>الطلاب الغير مشاركين</h4>
                        <span><?= $not_participated ?></span>
                    </div>
                    <div class="stat-card">
                        <h4>عدد الناجحين</h4>
                        <span><?= $passed_count ?></span>
                    </div>
                    <div class="stat-card">
                        <h4>نسبة النجاح</h4>
                        <span><?= $pass_percentage ?>%</span>
                    </div>
                </div>
            </header>

            <div class="student-filters">
                <input 
                    type="text" 
                    id="student-search" 
                    placeholder="البحث عن طالب..." 
                    onkeyup="filterStudents()"
                    class="form-control"
                >
                <select 
                    id="status-filter" 
                    onchange="filterStudents()"
                    class="form-control"
                >
                    <option value="all">كل الطلاب</option>
                    <option value="passed">الطلاب الناجحين</option>
                    <option value="failed">الطلاب الراسبين</option>
                    <option value="not_participated">الطلاب الغير مشاركين</option>
                </select>
            </div>

            <div class="main-empty-state" style="display: <?= empty($students_results) && empty($not_participated_students) ? 'flex' : 'none' ?>">
                <div class="empty-content">
                    <h2>لا توجد نتائج</h2>
                    <p>لم يشارك أي طالب في هذا الاختبار حتى الآن</p>
                </div>
            </div>

            <div class="students-table-wrapper" style="display: <?= !empty($students_results) || !empty($not_participated_students) ? 'block' : 'none' ?>">
                <h2>تفاصيل نتائج الطلاب</h2>
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>اسم الطالب</th>
                            <th>كود الطالب</th>
                            <th>مدة الاختبار</th>
                            <th>النتيجة</th>
                            <th>الحالة</th>
                            <th>التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_results as $result): ?>
                        <tr class="student-row" data-participated>
                            <td class="text-right" data-student-name="<?= htmlspecialchars($result['name']) ?>">
                                <?= htmlspecialchars($result['name']) ?>
                            </td>
                            <td data-student-code="<?= htmlspecialchars($result['student_code']) ?>">
                                <?= htmlspecialchars($result['student_code']) ?>
                            </td>
                            <td><?= formatDuration($result['duration']) ?></td>
                            <td data-score="<?= $result['score'] ?>"><?= $result['score'] ?>%</td>
                            <td>
                                <span class="status <?= $result['score'] >= $passing_score ? 'active' : 'draft' ?>">
                                    <?= $result['score'] >= $passing_score ? 'ناجح' : 'راسب' ?>
                                </span>
                            </td>
                            <td>
                                <div class="student-actions">
                                    <a href="student_details.php?quiz_id=<?= $quiz_id ?>&student_code=<?= $result['student_code'] ?>" 
                                       class="btn btn-edit">التفاصيل</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php foreach ($not_participated_students as $student): ?>
                        <tr class="student-row">
                            <td class="text-right" data-student-name="<?= htmlspecialchars($student['name']) ?>">
                                <?= htmlspecialchars($student['name']) ?>
                            </td>
                            <td data-student-code="<?= htmlspecialchars($student['student_code']) ?>">
                                <?= htmlspecialchars($student['student_code']) ?>
                            </td>
                            <td>-</td>
                            <td data-score="0">-</td>
                            <td>
                                <span class="status draft">لم يشارك</span>
                            </td>
                            <td>-</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>