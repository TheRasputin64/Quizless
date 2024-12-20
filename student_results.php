<?php
require_once 'config.php';
redirectIfNotAdmin();
$conn = getDBConnection();

// Handle student approval/unapproval
if (isset($_GET['action']) && isset($_GET['id'])) {
    $student_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $update_query = "UPDATE students SET is_approved = 1 WHERE student_id = ?";
    } elseif ($action === 'unapprove') {
        $update_query = "UPDATE students SET is_approved = 0 WHERE student_id = ?";
    }
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    
    header("Location: student_results.php?action_status=" . ($action === 'approve' ? 'approved' : 'unapproved'));
    exit();
}

$students_query = "
    SELECT 
        s.student_id, 
        s.name, 
        s.email, 
        s.student_code, 
        s.is_approved,
        s.phone,
        COUNT(DISTINCT sa.quiz_id) as total_quizzes_attempted,
        AVG(sa.score) as average_score
    FROM 
        students s
    LEFT JOIN 
        student_attempts sa ON s.student_id = sa.student_id
    GROUP BY 
        s.student_id
    ORDER BY 
        s.created_at DESC
";

$stmt = $conn->prepare($students_query);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_students = count($students);
$approved_students = count(array_filter($students, function($student) { return $student['is_approved'] == 1; }));
$unapproved_students = $total_students - $approved_students;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتائج الطلاب - نظام الاختبارات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="results.css">
    <script>
function filterStudents() {
    const searchTerm = document.getElementById('student-search').value.toLowerCase();
    const approvedFilter = document.getElementById('approved-filter').value;
    const rows = document.querySelectorAll('.student-row');

    rows.forEach(row => {
        const name = row.querySelector('.student-name').textContent.toLowerCase();
        const code = row.querySelector('.student-code').textContent.toLowerCase();
        const isApproved = row.getAttribute('data-approved') === '1';
        
        const nameMatch = name.includes(searchTerm) || code.includes(searchTerm);
        const approvedMatch = approvedFilter === 'all' || 
            (approvedFilter === 'approved' && isApproved) || 
            (approvedFilter === 'unapproved' && !isApproved);

        row.style.display = nameMatch && approvedMatch ? '' : 'none';
    });
}

function showActionOverlay(action, studentId, studentName) {
    const overlay = document.createElement('div');
    overlay.classList.add('action-overlay');
    
    const title = action === 'approve' ? 'اعتماد الطالب' : 'إلغاء اعتماد الطالب';
    const iconClass = action === 'approve' ? 'publish-icon' : 'delete-icon';
    const message = action === 'approve' 
        ? `هل أنت متأكد من اعتماد الطالب ${studentName}؟`
        : `هل أنت متأكد من إلغاء اعتماد الطالب ${studentName}؟`;

    overlay.innerHTML = `
        <div class="overlay-content">
            <div class="overlay-icon ${iconClass}"></div>
            <h3>${title}</h3>
            <p>${message}</p>
            <div class="overlay-actions">
                <a href="student_results.php?action=${action}&id=${studentId}" class="btn btn-${action === 'approve' ? 'publish' : 'delete'}">
                    ${title}
                </a>
                <button onclick="this.closest('.action-overlay').remove()" class="btn" style="background-color: var(--background-color); color: var(--text-primary);">
                    إلغاء
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.remove();
        }
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
                <a href="admin.php" class="nav-item">الاختبارات</a>
                <a href="student_results.php" class="nav-item active">نتائج الطلاب</a>
                <a href="create_quiz.php" class="nav-item">إنشاء اختبار جديد</a>
                <a href="logout.php" class="nav-item logout">تسجيل الخروج</a>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <h1>نتائج الطلاب</h1>
                <div class="quick-stats">
                    <div class="stat-card">
                        <h4>إجمالي الطلاب</h4>
                        <span><?= $total_students ?></span>
                    </div>
                    <div class="stat-card">
                        <h4>الطلاب المعتمدين</h4>
                        <span><?= $approved_students ?></span>
                    </div>
                    <div class="stat-card">
                        <h4>الطلاب غير المعتمدين</h4>
                        <span><?= $unapproved_students ?></span>
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
                    id="approved-filter" 
                    onchange="filterStudents()"
                    class="form-control"
                >
                    <option value="all">كل الطلاب</option>
                    <option value="approved">الطلاب المعتمدين</option>
                    <option value="unapproved">الطلاب غير المعتمدين</option>
                </select>
            </div>

            <?php if (empty($students)): ?>
            <div class="main-empty-state">
                <div class="empty-content">
                    <h2>لا يوجد طلاب مسجلين</h2>
                    <p>لم يتم تسجيل أي طالب حتى الآن</p>
                </div>
            </div>
            <?php else: ?>
            <div class="students-table-wrapper">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>اسم الطالب</th>
                            <th>كود الطالب</th>
                            <th>البريد الإلكتروني</th>
                            <th>رقم الهاتف</th>
                            <th>الاختبارات</th>
                            <th>متوسط الدرجات</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr class="student-row" data-approved="<?= $student['is_approved'] ?>">
                            <td class="text-right">
                                <span class="student-name">
                                    <?= htmlspecialchars($student['name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="student-code">
                                    <?= htmlspecialchars($student['student_code']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($student['email'] ?: 'غير محدد') ?></td>
                            <td><?= htmlspecialchars($student['phone'] ?: 'غير محدد') ?></td>
                            <td><?= $student['total_quizzes_attempted'] ?></td>
                            <td><?= number_format($student['average_score'] ?? 0, 2) ?></td>
                            <td>
                                <span class="status <?= $student['is_approved'] ? 'active' : 'draft' ?>">
                                    <?= $student['is_approved'] ? 'معتمد' : 'غير معتمد' ?>
                                </span>
                            </td>
                            <td>
                                <div class="student-actions">
                                    <a href="student_details.php?id=<?= $student['student_id'] ?>" class="btn btn-edit">التفاصيل</a>
                                    <a href="student_quizzes.php?id=<?= $student['student_id'] ?>" class="btn btn-stats">الاختبارات</a>
                                    <?php if ($student['is_approved']): ?>
                                        <button 
                                            onclick="showActionOverlay('unapprove', <?= $student['student_id'] ?>, '<?= htmlspecialchars($student['name']) ?>')" 
                                            class="btn btn-delete">
                                            إلغاء الاعتماد
                                        </button>
                                    <?php else: ?>
                                        <button 
                                            onclick="showActionOverlay('approve', <?= $student['student_id'] ?>, '<?= htmlspecialchars($student['name']) ?>')" 
                                            class="btn btn-publish">
                                            اعتماد
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>