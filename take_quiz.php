<?php
require_once 'config.php';
redirectIfNotStudent();
$conn = getDBConnection();
if (!isset($_GET['id'])) {header('Location: https://' . $_SERVER['HTTP_HOST'] . '/');exit();}

$quiz_id = $_GET['id'];
$student_id = $_SESSION['student_id'];

$stmt = $conn->prepare("
    SELECT q.*, TIMESTAMPDIFF(SECOND, NOW(), CONCAT(q.quiz_date, ' ', q.end_datetime)) as seconds_remaining,sa.attempt_id,sa.end_time FROM quizzes q
    LEFT JOIN student_attempts sa ON q.quiz_id = sa.quiz_id AND sa.student_id = ? WHERE q.quiz_id = ? AND q.status = 'published' AND CONCAT(q.quiz_date, ' ', q.end_datetime) > NOW()
    AND q.quiz_date = CURDATE()
");

$stmt->bind_param("ii", $student_id, $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz || $quiz['seconds_remaining'] <= 0 || $quiz['end_time'] !== null) {header('Location: https://' . $_SERVER['HTTP_HOST'] . '/');exit();}
if (!$quiz['attempt_id']) {
    $stmt = $conn->prepare("INSERT INTO student_attempts (student_id, quiz_id, start_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $student_id, $quiz_id);
    $stmt->execute();
    $attempt_id = $conn->insert_id;
} else {$attempt_id = $quiz['attempt_id'];}

$stmt = $conn->prepare("
    SELECT 
        q.*,
        GROUP_CONCAT(
            CONCAT(
                c.choice_id, '::',
                c.choice_text, '::',
                COALESCE(c.image_path, 'none'), '::', 
                COALESCE(
                    (SELECT 1 FROM student_answers sa 
                     WHERE sa.attempt_id = ? 
                     AND sa.question_id = q.question_id 
                     AND sa.selected_choice_id = c.choice_id), 
                    0
                )
            ) 
            SEPARATOR '||'
        ) as choices
    FROM questions q 
    LEFT JOIN choices c ON q.question_id = c.question_id 
    WHERE q.quiz_id = ?
    GROUP BY q.question_id 
    ORDER BY q.question_id
");

$stmt->bind_param("ii", $attempt_id, $quiz_id);
$stmt->execute();
$questions = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_quiz'])) {
        $stmt = $conn->prepare("
            UPDATE student_attempts 
            SET end_time = NOW(),
                score = (
                    SELECT ROUND(COUNT(CASE WHEN sa.is_correct = 1 THEN 1 END) * 100.0 / COUNT(*))
                    FROM student_answers sa
                    WHERE sa.attempt_id = ?
                )
            WHERE attempt_id = ?
        ");
        $stmt->bind_param("ii", $attempt_id, $attempt_id);
        $stmt->execute();
        
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['question_id']) && isset($_POST['choice_id'])) {
        $question_id = $_POST['question_id'];
        $choice_id = $_POST['choice_id'];
        
        // First check if an answer already exists
        $checkStmt = $conn->prepare("SELECT 1 FROM student_answers WHERE attempt_id = ? AND question_id = ?");
        $checkStmt->bind_param("ii", $attempt_id, $question_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing answer
            $stmt = $conn->prepare("
                UPDATE student_answers 
                SET selected_choice_id = ?,
                    is_correct = (SELECT is_correct FROM choices WHERE choice_id = ?)
                WHERE attempt_id = ? AND question_id = ?
            ");
            $stmt->bind_param("iiii", $choice_id, $choice_id, $attempt_id, $question_id);
        } else {
            // Insert new answer
            $stmt = $conn->prepare("
                INSERT INTO student_answers (attempt_id, question_id, selected_choice_id, is_correct)
                SELECT ?, ?, ?, is_correct
                FROM choices
                WHERE choice_id = ?
            ");
            $stmt->bind_param("iiii", $attempt_id, $question_id, $choice_id, $choice_id);
        }
        
        $stmt->execute();
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> - نظام الاختبارات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
:root {
    --primary-color: #7E57C2;
    --primary-hover: #6A48A8;
    --background-color: #F5F7FA;
    --card-color: #FFFFFF;
    --text-primary: #2C3E50;
    --text-secondary: #546E7A;
    --success-color: #66BB6A;
    --success-hover: #549E57;
    --danger-color: #EF5350;
    --danger-hover: #D64744;
    --pastel-green: #E8F5E9;
    --pastel-red: #FFEBEE;
    --pastel-purple: #F3E5F5;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Cairo', sans-serif;
}

body {
    background-color: var(--background-color);
    color: var(--text-primary);
    line-height: 1.6;
    padding-bottom: 80px; /* Added padding to prevent overlap with fixed submit button */
}

.quiz-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.quiz-header {
    background-color: var(--card-color);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: relative; /* Added for back button positioning */
}

/* New back button styles */
.back-button {
    position: absolute;
    left: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s;
}

.back-button:hover {
    background-color: var(--primary-hover);
}

.back-button i {
    font-size: 1rem;
}

.quiz-title {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
    padding-right: 2rem; /* Added to prevent overlap with back button */
}

.quiz-info {
    display: flex;
    gap: 2rem;
    color: var(--text-secondary);
}

.timer {
    background-color: #E3F2FD;
    color: #1976D2;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 2rem;
    font-size: 1.2rem;
    font-weight: 600;
}

.question-card {
    background-color: var(--card-color);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.question-text {
    font-size: 1.1rem;
    margin-bottom: 1rem;
}

.question-image {
    max-width: 100%;
    margin-bottom: 1rem;
    border-radius: 4px;
}

.choices {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.choice-label {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 2px solid #E0E0E0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.choice-label:hover {
    border-color: var(--primary-color);
    background-color: #F5F5F5;
}

.choice-input {
    margin-left: 1rem;
}

.choice-image {
    max-width: 200px;
    margin: 0.5rem 0;
    border-radius: 4px;
}

/* Updated submit container styles */
.submit-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: var(--card-color);
    padding: 1rem;
    box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
    text-align: center;
    z-index: 100;
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s;
}

.submit-btn:hover {
    background-color: var(--primary-hover);
}

.action-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

.overlay-content {
    background-color: var(--card-color);
    border-radius: 16px;
    padding: 2rem;
    width: 90%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: slideUp 0.3s ease;
}

.overlay-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.publish-icon {
    background-color: var(--pastel-green);
}

.delete-icon {
    background-color: var(--pastel-red);
}

.publish-icon::before,
.delete-icon::before {
    content: '';
    position: absolute;
    width: 32px;
    height: 32px;
}

.publish-icon::before {
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2366BB6A'%3E%3Cpath d='M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z'/%3E%3C/svg%3E") no-repeat center;
}

.delete-icon::before {
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23EF5350'%3E%3Cpath d='M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z'/%3E%3C/svg%3E") no-repeat center;
}

.overlay-content h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.overlay-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

.overlay-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn {
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-publish {
    background-color: var(--pastel-green);
    color: var(--success-color);
}

.btn-publish:hover {
    background-color: var(--success-color);
    color: white;
}

.btn-delete {
    background-color: var(--pastel-red);
    color: var(--danger-color);
}

.btn-delete:hover {
    background-color: var(--danger-color);
    color: white;
}

.btn-edit {
    background-color: var(--pastel-purple);
    color: var(--primary-color);
}

.btn-edit:hover {
    background-color: var(--primary-color);
    color: white;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (max-width: 768px) {
    .quiz-info {
        flex-direction: column;
        gap: 1rem;
    }
    
    .back-button {
        top: 1.5rem;
        transform: none;
    }
    
    .quiz-title {
        padding-top: 2.5rem;
    }
}
    </style>
</head>
<body>
    <div class="quiz-container">
        <div class="quiz-header">
        <button class="back-button" onclick="window.location.href='index.php'"><i class="fas fa-arrow-right"></i> رجوع</button>
            <h1 class="quiz-title"><?= htmlspecialchars($quiz['title']) ?></h1>
            <div class="quiz-info">
                <div><i class="fas fa-clock"></i> وقت النهاية: <?= date('h:i A', strtotime($quiz['end_datetime'])) ?></div>
                <div><i class="fas fa-question-circle"></i> عدد الأسئلة: <?= $questions->num_rows ?></div>
            </div>
        </div>
        
        <div class="timer" id="timer"></div>
        
        <form id="quizForm" method="POST">
            <?php while($question = $questions->fetch_assoc()): ?>
                <div class="question-card">
                    <div class="question-text"><?= htmlspecialchars($question['question_text']) ?></div>
                    
                    <?php if($question['image_path']): ?>
                        <img src="<?= htmlspecialchars($question['image_path']) ?>" alt="صورة السؤال" class="question-image">
                    <?php endif; ?>
                    
                    <div class="choices">
                        <?php
                        $choices = array_map(function($choice) {
                            return explode('::', $choice);
                        }, explode('||', $question['choices']));
                        
                        foreach($choices as $choice):
                            list($choice_id, $choice_text, $image_path, $is_selected) = $choice;
                        ?>
                            <label class="choice-label">
                                <input type="radio" 
                                       name="question_<?= $question['question_id'] ?>" 
                                       value="<?= $choice_id ?>"
                                       class="choice-input"
                                       data-question="<?= $question['question_id'] ?>"
                                       <?= $is_selected ? 'checked' : '' ?>>
                                <?= htmlspecialchars($choice_text) ?>
                                <?php if($image_path !== 'none'): ?>
                                    <img src="<?= htmlspecialchars($image_path) ?>" alt="صورة الإجابة" class="choice-image">
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endwhile; ?>
            
            <div class="submit-container">
                <button type="button" id="finishBtn" class="submit-btn">
                    <i class="fas fa-check-circle"></i> إنهاء الاختبار
                </button>
            </div>
        </form>
    </div>

    <div id="finishOverlay" class="action-overlay" style="display: none;">
        <div class="overlay-content">
            <div class="overlay-icon publish-icon"></div>
            <h3>هل أنت متأكد من إنهاء الاختبار؟</h3>
            <p>لن تتمكن من العودة وتعديل إجاباتك بعد الإنهاء</p>
            <div class="overlay-actions">
                <button class="btn btn-delete" id="cancelFinish">إلغاء</button>
                <button class="btn btn-publish" id="confirmFinish">تأكيد الإنهاء</button>
            </div>
        </div>
    </div>

    <div id="leaveOverlay" class="action-overlay" style="display: none;">
        <div class="overlay-content">
            <div class="overlay-icon delete-icon"></div>
            <h3>هل أنت متأكد من الخروج من الاختبار؟</h3>
            <p>سيتم حفظ إجاباتك الحالية ويمكنك العودة إلى الاختبار لاحقاً</p>
            <div class="overlay-actions">
                <button class="btn btn-edit" id="cancelLeave">البقاء في الاختبار</button>
                <button class="btn btn-delete" id="confirmLeave">تأكيد الخروج</button>
            </div>
        </div>
    </div>

    <script>
let secondsRemaining = <?= $quiz['seconds_remaining'] ?>;
let isSubmitting = false;
let hasUnsavedChanges = false;

if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

function updateTimer() {
    const hours = Math.floor(secondsRemaining / 3600);
    const minutes = Math.floor((secondsRemaining % 3600) / 60);
    const seconds = secondsRemaining % 60;
    
    document.getElementById('timer').textContent = 
        `الوقت المتبقي: ${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    if (secondsRemaining <= 0) {
        isSubmitting = true;
        document.querySelector('form').submit();
    } else {
        secondsRemaining--;
    }
}

const timerInterval = setInterval(updateTimer, 1000);
updateTimer();

async function saveAnswer(questionId, choiceId) {
    try {
        const formData = new FormData();
        formData.append('question_id', questionId);
        formData.append('choice_id', choiceId);
        
        const response = await fetch('take_quiz.php?id=<?= $quiz_id ?>', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            hasUnsavedChanges = false;
        }
    } catch (error) {
        hasUnsavedChanges = true;
    }
}

document.querySelectorAll('.choice-input').forEach(input => {
    input.addEventListener('change', function() {
        hasUnsavedChanges = true;
        saveAnswer(this.dataset.question, this.value);
    });
});

function scrollToFirstUnanswered() {
    const unansweredCard = Array.from(document.querySelectorAll('.question-card'))
        .find(card => !card.querySelector('.choice-input:checked'));
    
    if (unansweredCard) {
        unansweredCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        unansweredCard.style.transition = 'all 0.3s ease';
        unansweredCard.style.boxShadow = '0 0 15px rgba(255, 0, 0, 0.5)';
        setTimeout(() => unansweredCard.style.boxShadow = '', 1000);
        return true;
    }
    return false;
}

async function checkAndSaveAllQuestions() {
    const unsavedQuestions = Array.from(document.querySelectorAll('.question-card'))
        .filter(card => !card.querySelector('.choice-input:checked'));

    for (const card of unsavedQuestions) {
        const questionId = card.querySelector('.choice-input').dataset.question;
        const firstChoice = card.querySelector('.choice-input');
        firstChoice.checked = true;
        await saveAnswer(questionId, firstChoice.value);
    }
}

document.getElementById('finishBtn').addEventListener('click', e => {
    e.preventDefault();
    if (scrollToFirstUnanswered()) return;
    document.getElementById('finishOverlay').style.display = 'flex';
});

document.getElementById('cancelFinish').addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('finishOverlay').style.display = 'none';
});

document.getElementById('confirmFinish').addEventListener('click', async e => {
    e.preventDefault();
    await checkAndSaveAllQuestions();
    isSubmitting = true;
    hasUnsavedChanges = false;
    
    const form = document.getElementById('quizForm');
    const submitInput = document.createElement('input');
    submitInput.type = 'hidden';
    submitInput.name = 'submit_quiz';
    submitInput.value = '1';
    form.appendChild(submitInput);
    form.submit();
});

document.getElementById('cancelLeave').addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('leaveOverlay').style.display = 'none';
    history.pushState(null, '', window.location.href);
});

document.getElementById('confirmLeave').addEventListener('click', e => {
    e.preventDefault();
    isSubmitting = true;
    hasUnsavedChanges = false;
    window.location.href = 'index.php';
});

window.addEventListener('popstate', e => {
    e.preventDefault();
    document.getElementById('leaveOverlay').style.display = 'flex';
});

history.pushState(null, '', window.location.href);

window.addEventListener('beforeunload', e => {
    if (!isSubmitting && hasUnsavedChanges) {
        e.preventDefault();
        return e.returnValue = '';
    }
});

window.addEventListener('unload', () => clearInterval(timerInterval));
</script>

</body>
</html>