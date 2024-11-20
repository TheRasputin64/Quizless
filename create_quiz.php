<?php
require_once 'config.php';
redirectIfNotAdmin();
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $quiz_date = $_POST['quiz_date'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $access_code = $_POST['access_code'] ?? null;
    $status = $_POST['submit_type'] ?? 'published';

    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $stmt = $conn->prepare("INSERT INTO quizzes (admin_id, title, description, quiz_date, start_datetime, end_datetime, access_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $_SESSION['admin_id'], $title, $description, $quiz_date, $start_time, $end_time, $access_code, $status);
    
    if ($stmt->execute()) {
        $quiz_id = $conn->insert_id;
        
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $index => $q) {
                if (!empty($q['text'])) {
                    $question_image = null;
                    if (isset($_FILES['questions']['name'][$index]['image']) && $_FILES['questions']['name'][$index]['image'] != '') {
                        $question_image = $uploadDir . uniqid() . '_' . basename($_FILES['questions']['name'][$index]['image']);
                        move_uploaded_file($_FILES['questions']['tmp_name'][$index]['image'], $question_image);
                    }

                    $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, image_path) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $quiz_id, $q['text'], $question_image);
                    $stmt->execute();
                    
                    $question_id = $conn->insert_id;
                    
                    if (isset($q['choices']) && is_array($q['choices'])) {
                        foreach ($q['choices'] as $choiceIndex => $choice) {
                            if (!empty($choice)) {
                                $choice_image = null;
                                if (isset($_FILES['questions']['name'][$index]['choice_images'][$choiceIndex]) && $_FILES['questions']['name'][$index]['choice_images'][$choiceIndex] != '') {
                                    $choice_image = $uploadDir . uniqid() . '_' . basename($_FILES['questions']['name'][$index]['choice_images'][$choiceIndex]);
                                    move_uploaded_file($_FILES['questions']['tmp_name'][$index]['choice_images'][$choiceIndex], $choice_image);
                                }

                                $is_correct = ($q['correct_answer'] == $choiceIndex) ? 1 : 0;
                                $stmt = $conn->prepare("INSERT INTO choices (question_id, choice_text, image_path, is_correct) VALUES (?, ?, ?, ?)");
                                $stmt->bind_param("issi", $question_id, $choice, $choice_image, $is_correct);
                                $stmt->execute();
                            }
                        }
                    }
                }
            }
        }
        header('Location: admin.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء اختبار جديد - نظام الاختبارات</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="quiz.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-profile">
                <img src="cat.gif" alt="" class="admin-avatar">
                <h3><?= htmlspecialchars($_SESSION['admin_name']) ?></h3>
            </div>
            <nav class="admin-nav">
                <a href="admin.php" class="nav-item">الرئيسية</a>
                <a href="create_quiz.php" class="nav-item active">إنشاء اختبار جديد</a>
                <a href="student_results.php" class="nav-item">نتائج الطلاب</a>
                <a href="logout.php" class="nav-item logout">تسجيل الخروج</a>
            </nav>
        </aside>
        <main class="admin-main">
            <div class="quiz-editor">
                <form id="quizForm" method="POST" enctype="multipart/form-data">
                    <div class="form-section">
                        <h2>معلومات الاختبار</h2>
                        <div class="quiz-header-row">
                            <div class="form-group">
                                <label for="title">عنوان الاختبار</label>
                                <input type="text" id="title" name="title" required>
                            </div>
                            <div class="form-group code-input">
                                <label for="access_code">رمز الدخول</label>
                                <input type="text" id="access_code" name="access_code" maxlength="10">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">وصف الاختبار</label>
                            <textarea id="description" name="description" rows="4"></textarea>
                        </div>
                        <div class="quiz-time-row">
                            <div class="form-group">
                                <label for="quiz_date">تاريخ الاختبار</label>
                                <input type="date" id="quiz_date" name="quiz_date">
                            </div>
                            <div class="form-group">
                                <label for="start_time">وقت البدء</label>
                                <select name="start_time" id="start_time" class="time-select"></select>
                            </div>
                            <div class="form-group">
                                <label for="end_time">وقت الانتهاء</label>
                                <select name="end_time" id="end_time" class="time-select"></select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2>الأسئلة</h2>
                        <div id="questions"></div>
                        <button type="button" class="btn btn-add" onclick="addQuestion()">إضافة سؤال جديد</button>
                    </div>
                    
                    <div class="form-actions">
                       <button type="submit" name="submit_type" value="draft" class="btn btn-draft">حفظ كمسودة</button>
                       <button type="submit" name="submit_type" value="published" class="btn btn-publish">نشر الاختبار</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
const questionCount = { value: 0 };

const formatTime = h => `${h > 12 ? h - 12 : (h === 0 ? 12 : h)}:00 ${h >= 12 ? 'مساءً' : 'صباحاً'}`;

const parseTo24Hour = t => {
    const [time, period] = t.split(' ');
    let [h] = time.split(':').map(Number);
    if (period === 'مساءً' && h !== 12) h += 12;
    if (period === 'صباحاً' && h === 12) h = 0;
    return `${h.toString().padStart(2, '0')}:00:00`;
};

const populateTimeSelects = () => {
    const startSelect = document.getElementById('start_time');
    const endSelect = document.getElementById('end_time');
    const now = new Date();
    const startHour = (now.getHours() + 1) % 24;
    
    [...Array(24)].forEach((_, h) => {
        const time = formatTime(h);
        startSelect.add(new Option(time, time));
        endSelect.add(new Option(time, time));
    });
    
    startSelect.value = formatTime(startHour);
    endSelect.value = formatTime((startHour + 1) % 24);
};

const createQuestionHTML = index => `
    <div class="question-header">
        <h3>السؤال ${index + 1}</h3>
        <button type="button" class="btn btn-delete" onclick="removeQuestion(this)">حذف</button>
    </div>
    <div class="question-content">
        <div class="form-group">
            <label>نص السؤال</label>
            <div class="question-input-row">
                <textarea name="questions[${index}][text]" required></textarea>
                <div class="image-upload">
                    <label for="question_image_${index}">
                        <img src="upload-icon.png" alt="Upload" class="upload-icon">
                    </label>
                    <input type="file" id="question_image_${index}" name="questions[${index}][image]" accept="image/*" onchange="previewImage(this, 'question_preview_${index}')">
                    <img id="question_preview_${index}" class="image-preview" style="display: none;">
                </div>
            </div>
        </div>
        <div class="choices">
            <div id="choices_container_${index}"></div>
            <button type="button" class="btn btn-add-choice" onclick="addChoice(${index})">إضافة خيار</button>
        </div>
    </div>
`;

const createChoiceHTML = (qIndex, cIndex) => `
    <input type="radio" name="questions[${qIndex}][correct_answer]" value="${cIndex}" ${cIndex === 0 ? 'checked' : ''}>
    <input type="text" name="questions[${qIndex}][choices][]" placeholder="الخيار ${cIndex + 1}" required>
    <div class="image-upload">
        <label for="choice_image_${qIndex}_${cIndex}">
            <img src="upload-icon.png" alt="Upload" class="upload-icon">
        </label>
        <input type="file" id="choice_image_${qIndex}_${cIndex}" name="questions[${qIndex}][choice_images][]" accept="image/*" onchange="previewImage(this, 'choice_preview_${qIndex}_${cIndex}')">
        <img id="choice_preview_${qIndex}_${cIndex}" class="image-preview" style="display: none;">
    </div>
    <button type="button" class="btn btn-delete-choice" onclick="removeChoice(this)">حذف</button>
`;

const addQuestion = () => {
    const div = document.createElement('div');
    div.className = 'question-card';
    div.innerHTML = createQuestionHTML(questionCount.value);
    document.getElementById('questions').appendChild(div);
    addChoice(questionCount.value);
    questionCount.value++;
};

const addChoice = qIndex => {
    const container = document.getElementById(`choices_container_${qIndex}`);
    const div = document.createElement('div');
    div.className = 'choice';
    div.innerHTML = createChoiceHTML(qIndex, container.children.length);
    container.appendChild(div);
};

const removeChoice = btn => {
    const choice = btn.parentElement;
    const container = choice.parentElement;
    const questionCard = choice.closest('.question-card');
    
    // Check if this is the correct choice being removed
    const radio = choice.querySelector('input[type="radio"]');
    const wasCorrect = radio && radio.checked;
    
    if (container.children.length > 1) {
        choice.remove();
        
        // Update indices for remaining choices
        Array.from(container.children).forEach((c, i) => {
            const radio = c.querySelector('input[type="radio"]');
            radio.value = i;
            c.querySelector('input[type="text"]').placeholder = `الخيار ${i + 1}`;
        });
        
        // If we removed the correct choice, select the first remaining choice
        if (wasCorrect) {
            const firstRadio = container.querySelector('input[type="radio"]');
            if (firstRadio) {
                firstRadio.checked = true;
            }
        }
    }
};

const removeQuestion = btn => {
    btn.closest('.question-card').remove();
    document.querySelectorAll('.question-card').forEach((q, i) => {
        q.querySelector('h3').textContent = `السؤال ${i + 1}`;
    });
    questionCount.value = document.querySelectorAll('.question-card').length;
};

const previewImage = (input, previewId) => {
    const preview = document.getElementById(previewId);
    if (input.files?.[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('quiz_date').value = new Date().toISOString().split('T')[0];
    populateTimeSelects();
    addQuestion();
    
    // Initialize radio buttons for existing questions
    document.querySelectorAll('.question-card').forEach(card => {
        const radios = card.querySelectorAll('input[type="radio"]');
        if (radios.length > 0 && !card.querySelector('input[type="radio"]:checked')) {
            radios[0].checked = true;
        }
    });
    
    document.getElementById('quizForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!document.querySelectorAll('.question-card').length) {
            alert('يجب إضافة سؤال واحد على الأقل');
            return;
        }
        
        // Validate each question has a correct answer selected
        const questions = document.querySelectorAll('.question-card');
        for (const question of questions) {
            const hasCheckedRadio = question.querySelector('input[type="radio"]:checked');
            if (!hasCheckedRadio) {
                alert('يجب تحديد إجابة صحيحة لكل سؤال');
                return;
            }
        }
        
        const quizDate = document.getElementById('quiz_date').value;
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        if (quizDate && (startTime || endTime)) {
            if (!startTime || !endTime) {
                alert('يجب تحديد وقت البدء والانتهاء معاً');
                return;
            }
            if (startTime >= endTime) {
                alert('يجب أن يكون وقت الانتهاء بعد وقت البدء');
                return;
            }
        }
        
        const formData = new FormData(this);
        formData.set('start_time', parseTo24Hour(startTime));
        formData.set('end_time', parseTo24Hour(endTime));
        formData.set('submit_type', e.submitter.value);
        
        fetch(this.action || window.location.href, {
            method: 'POST',
            body: formData
        }).then(() => window.location.href = 'admin.php');
    });
});
    </script>
    
</body>
</html>