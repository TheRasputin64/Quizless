<?php
require_once 'config.php';
redirectIfNotAdmin();
$conn = getDBConnection();

$quiz_id = $_GET['id'] ?? 0;
$quiz = $conn->query("SELECT * FROM quizzes WHERE quiz_id = $quiz_id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $quiz_date = $_POST['quiz_date'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $access_code = $_POST['access_code'] ?? null;
    $status = $_POST['status'] ?? 'published';
    $uploadDir = 'uploads/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    function handleImageUpload($file, $uploadDir) {
        if (!empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
            $fileName = uniqid() . '_' . basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return $targetPath;
            }
        }
        return null;
    }

    $stmt = $conn->prepare("UPDATE quizzes SET title=?, description=?, quiz_date=?, start_datetime=?, end_datetime=?, access_code=?, status=? WHERE quiz_id=?");
    $stmt->bind_param("sssssssi", $title, $description, $quiz_date, $start_time, $end_time, $access_code, $status, $quiz_id);
    $stmt->execute();

    if (isset($_POST['existing_questions'])) {
        foreach ($_POST['existing_questions'] as $q_id => $q) {
            if (isset($q['delete']) && $q['delete'] == 1) {
                $question = $conn->query("SELECT image_path FROM questions WHERE question_id = $q_id")->fetch_assoc();
                if ($question && !empty($question['image_path']) && file_exists($question['image_path'])) {
                    unlink($question['image_path']);
                }
                
                $choices = $conn->query("SELECT image_path FROM choices WHERE question_id = $q_id");
                while ($choice = $choices->fetch_assoc()) {
                    if (!empty($choice['image_path']) && file_exists($choice['image_path'])) {
                        unlink($choice['image_path']);
                    }
                }
                
                $conn->query("DELETE FROM choices WHERE question_id = $q_id");
                $conn->query("DELETE FROM questions WHERE question_id = $q_id");
                continue;
            }

            $stmt = $conn->prepare("UPDATE questions SET question_text=? WHERE question_id=?");
            $stmt->bind_param("si", $q['text'], $q_id);
            $stmt->execute();

            if (!empty($_FILES['existing_questions']['name'][$q_id]['image'])) {
                $file = [
                    'name' => $_FILES['existing_questions']['name'][$q_id]['image'],
                    'type' => $_FILES['existing_questions']['type'][$q_id]['image'],
                    'tmp_name' => $_FILES['existing_questions']['tmp_name'][$q_id]['image'],
                    'error' => $_FILES['existing_questions']['error'][$q_id]['image'],
                    'size' => $_FILES['existing_questions']['size'][$q_id]['image']
                ];
                $imagePath = handleImageUpload($file, $uploadDir);
                if ($imagePath) {
                    $oldImage = $conn->query("SELECT image_path FROM questions WHERE question_id = $q_id")->fetch_assoc();
                    if ($oldImage && !empty($oldImage['image_path']) && file_exists($oldImage['image_path'])) {
                        unlink($oldImage['image_path']);
                    }
                    
                    $stmt = $conn->prepare("UPDATE questions SET image_path=? WHERE question_id=?");
                    $stmt->bind_param("si", $imagePath, $q_id);
                    $stmt->execute();
                }
            }

            if (isset($q['choices'])) {
                foreach ($q['choices'] as $c_id => $choice) {
                    if (isset($choice['delete']) && $choice['delete'] == 1) {
                        $oldChoice = $conn->query("SELECT image_path FROM choices WHERE choice_id = $c_id")->fetch_assoc();
                        if ($oldChoice && !empty($oldChoice['image_path']) && file_exists($oldChoice['image_path'])) {
                            unlink($oldChoice['image_path']);
                        }
                        $conn->query("DELETE FROM choices WHERE choice_id = $c_id");
                        continue;
                    }

                    $is_correct = isset($q['correct_answer']) && $q['correct_answer'] == $c_id ? 1 : 0;
                    
                    if (strpos($c_id, 'new_') === 0) {
                        $stmt = $conn->prepare("INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->bind_param("isi", $q_id, $choice['text'], $is_correct);
                        $stmt->execute();
                        $new_choice_id = $conn->insert_id;
                        
                        if (!empty($_FILES['existing_questions']['name'][$q_id]['choices'][$c_id]['image'])) {
                            $file = [
                                'name' => $_FILES['existing_questions']['name'][$q_id]['choices'][$c_id]['image'],
                                'type' => $_FILES['existing_questions']['type'][$q_id]['choices'][$c_id]['image'],
                                'tmp_name' => $_FILES['existing_questions']['tmp_name'][$q_id]['choices'][$c_id]['image'],
                                'error' => $_FILES['existing_questions']['error'][$q_id]['choices'][$c_id]['image'],
                                'size' => $_FILES['existing_questions']['size'][$q_id]['choices'][$c_id]['image']
                            ];
                            $imagePath = handleImageUpload($file, $uploadDir);
                            if ($imagePath) {
                                $stmt = $conn->prepare("UPDATE choices SET image_path=? WHERE choice_id=?");
                                $stmt->bind_param("si", $imagePath, $new_choice_id);
                                $stmt->execute();
                            }
                        }
                    } else {
                        $stmt = $conn->prepare("UPDATE choices SET choice_text=?, is_correct=? WHERE choice_id=?");
                        $stmt->bind_param("sii", $choice['text'], $is_correct, $c_id);
                        $stmt->execute();
                        
                        if (!empty($_FILES['existing_questions']['name'][$q_id]['choices'][$c_id]['image'])) {
                            $file = [
                                'name' => $_FILES['existing_questions']['name'][$q_id]['choices'][$c_id]['image'],
                                'type' => $_FILES['existing_questions']['type'][$q_id]['choices'][$c_id]['image'],
                                'tmp_name' => $_FILES['existing_questions']['tmp_name'][$q_id]['choices'][$c_id]['image'],
                                'error' => $_FILES['existing_questions']['error'][$q_id]['choices'][$c_id]['image'],
                                'size' => $_FILES['existing_questions']['size'][$q_id]['choices'][$c_id]['image']
                            ];
                            $imagePath = handleImageUpload($file, $uploadDir);
                            if ($imagePath) {
                                $oldChoice = $conn->query("SELECT image_path FROM choices WHERE choice_id = $c_id")->fetch_assoc();
                                if ($oldChoice && !empty($oldChoice['image_path']) && file_exists($oldChoice['image_path'])) {
                                    unlink($oldChoice['image_path']);
                                }
                                
                                $stmt = $conn->prepare("UPDATE choices SET image_path=? WHERE choice_id=?");
                                $stmt->bind_param("si", $imagePath, $c_id);
                                $stmt->execute();
                            }
                        }
                    }
                }
            }
        }
    }

    if (isset($_POST['new_questions'])) {
        foreach ($_POST['new_questions'] as $index => $q) {
            if (!empty($q['text'])) {
                $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text) VALUES (?, ?)");
                $stmt->bind_param("is", $quiz_id, $q['text']);
                $stmt->execute();
                $question_id = $conn->insert_id;

                if (!empty($_FILES['new_questions']['name'][$index]['image'])) {
                    $file = [
                        'name' => $_FILES['new_questions']['name'][$index]['image'],
                        'type' => $_FILES['new_questions']['type'][$index]['image'],
                        'tmp_name' => $_FILES['new_questions']['tmp_name'][$index]['image'],
                        'error' => $_FILES['new_questions']['error'][$index]['image'],
                        'size' => $_FILES['new_questions']['size'][$index]['image']
                    ];
                    $imagePath = handleImageUpload($file, $uploadDir);
                    if ($imagePath) {
                        $stmt = $conn->prepare("UPDATE questions SET image_path=? WHERE question_id=?");
                        $stmt->bind_param("si", $imagePath, $question_id);
                        $stmt->execute();
                    }
                }

                if (isset($q['choices'])) {
                    foreach ($q['choices'] as $choiceIndex => $choice) {
                        if (!empty($choice)) {
                            $is_correct = isset($q['correct_answer']) && $q['correct_answer'] == $choiceIndex ? 1 : 0;
                            $stmt = $conn->prepare("INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                            $stmt->bind_param("isi", $question_id, $choice, $is_correct);
                            $stmt->execute();
                            $choice_id = $conn->insert_id;

                            if (!empty($_FILES['new_questions']['name'][$index]['choice_images'][$choiceIndex])) {
                                $file = [
                                    'name' => $_FILES['new_questions']['name'][$index]['choice_images'][$choiceIndex],
                                    'type' => $_FILES['new_questions']['type'][$index]['choice_images'][$choiceIndex],
                                    'tmp_name' => $_FILES['new_questions']['tmp_name'][$index]['choice_images'][$choiceIndex],
                                    'error' => $_FILES['new_questions']['error'][$index]['choice_images'][$choiceIndex],
                                    'size' => $_FILES['new_questions']['size'][$index]['choice_images'][$choiceIndex]
                                ];
                                $imagePath = handleImageUpload($file, $uploadDir);
                                if ($imagePath) {
                                    $stmt = $conn->prepare("UPDATE choices SET image_path=? WHERE choice_id=?");
                                    $stmt->bind_param("si", $imagePath, $choice_id);
                                    $stmt->execute();
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    header('Location: admin.php');
    exit;
}

$questions = $conn->query("SELECT * FROM questions WHERE quiz_id = $quiz_id");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الاختبار - نظام الاختبارات</title>
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
                <a href="create_quiz.php" class="nav-item">إنشاء اختبار جديد</a>
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
                                <input type="text" id="title" name="title" value="<?= htmlspecialchars($quiz['title']) ?>" required>
                            </div>
                            <div class="form-group code-input">
                                <label for="access_code">رمز الدخول</label>
                                <input type="text" id="access_code" name="access_code" value="<?= htmlspecialchars($quiz['access_code']) ?>" maxlength="10">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">وصف الاختبار</label>
                            <textarea id="description" name="description" rows="4"><?= htmlspecialchars($quiz['description']) ?></textarea>
                        </div>
                        <div class="quiz-time-row">
                            <div class="form-group">
                                <label for="quiz_date">تاريخ الاختبار</label>
                                <input type="date" id="quiz_date" name="quiz_date" value="<?= $quiz['quiz_date'] ?>">
                            </div>
                            <div class="form-group">
                                <label for="start_time">وقت البدء</label>
                                <input type="time" id="start_time" name="start_time" value="<?= substr($quiz['start_datetime'], 0, 5) ?>">
                            </div>
                            <div class="form-group">
                                <label for="end_time">وقت الانتهاء</label>
                                <input type="time" id="end_time" name="end_time" value="<?= substr($quiz['end_datetime'], 0, 5) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>الأسئلة الحالية</h2>
                        <?php
                        $q_index = 0;
                        while ($question = $questions->fetch_assoc()):
                            $choices = $conn->query("SELECT * FROM choices WHERE question_id = " . $question['question_id']);
                        ?>
                        <div class="question-card">
                            <div class="question-header">
                                <h3>السؤال <?= ++$q_index ?></h3>
                                <input type="hidden" name="existing_questions[<?= $question['question_id'] ?>][delete]" value="0" class="delete-flag">
                                <button type="button" class="btn btn-delete" onclick="markQuestionForDeletion(this)">حذف</button>
                            </div>
                            <div class="question-content">
                                <div class="form-group">
                                    <label>نص السؤال</label>
                                    <div class="question-input-row">
                                        <textarea name="existing_questions[<?= $question['question_id'] ?>][text]" required><?= htmlspecialchars($question['question_text']) ?></textarea>
                                        <div class="image-upload">
                                            <label for="question_image_<?= $question['question_id'] ?>">
                                                <img src="upload-icon.png" alt="Upload" class="upload-icon">
                                            </label>
                                            <input type="file" id="question_image_<?= $question['question_id'] ?>" 
                                                   name="existing_questions[<?= $question['question_id'] ?>][image]" 
                                                   accept="image/*" onchange="previewImage(this, 'question_preview_<?= $question['question_id'] ?>')">
                                            <?php if ($question['image_path']): ?>
                                                <img src="<?= htmlspecialchars($question['image_path']) ?>" class="image-preview" style="display: block;">
                                            <?php else: ?>
                                                <img id="question_preview_<?= $question['question_id'] ?>" class="image-preview" style="display: none;">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="choices">
                                    <?php while ($choice = $choices->fetch_assoc()): ?>
                                    <div class="choice">
                                        <input type="radio" name="existing_questions[<?= $question['question_id'] ?>][correct_answer]" 
                                               value="<?= $choice['choice_id'] ?>" <?= $choice['is_correct'] ? 'checked' : '' ?>>
                                        <input type="text" name="existing_questions[<?= $question['question_id'] ?>][choices][<?= $choice['choice_id'] ?>][text]" 
                                               value="<?= htmlspecialchars($choice['choice_text']) ?>" required>
                                        <div class="image-upload">
                                            <label for="choice_image_<?= $question['question_id'] ?>_<?= $choice['choice_id'] ?>">
                                                <img src="upload-icon.png" alt="Upload" class="upload-icon">
                                            </label>
                                            <input type="file" id="choice_image_<?= $question['question_id'] ?>_<?= $choice['choice_id'] ?>"
                                                   name="existing_questions[<?= $question['question_id'] ?>][choices][<?= $choice['choice_id'] ?>][image]"
                                                   accept="image/*" onchange="previewImage(this, 'choice_preview_<?= $question['question_id'] ?>_<?= $choice['choice_id'] ?>')">
                                            <?php if ($choice['image_path']): ?>
                                                <img src="<?= htmlspecialchars($choice['image_path']) ?>" class="image-preview" style="display: block;">
                                            <?php else: ?>
                                                <img id="choice_preview_<?= $question['question_id'] ?>_<?= $choice['choice_id'] ?>" class="image-preview" style="display: none;">
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="existing_questions[<?= $question['question_id'] ?>][choices][<?= $choice['choice_id'] ?>][delete]" value="0" class="delete-flag">
                                        <button type="button" class="btn btn-delete-choice" onclick="markChoiceForDeletion(this)">حذف</button>
                                    </div>
                                    <?php endwhile; ?>
                                    <button type="button" class="btn btn-add-choice" onclick="addNewChoice(<?= $question['question_id'] ?>)">إضافة خيار</button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="form-section">
                        <h2>أسئلة جديدة</h2>
                        <div id="new_questions"></div>
                        <button type="button" class="btn btn-add" onclick="addNewQuestion()">إضافة سؤال جديد</button>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="status" value="draft" class="btn btn-draft">حفظ كمسودة</button>
                        <button type="submit" name="status" value="published" class="btn btn-publish">نشر الاختبار</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
let newQuestionCount = 0;

const markQuestionForDeletion = button => {
    const card = button.closest('.question-card');
    const deleteFlag = card.querySelector('.delete-flag');
    deleteFlag.value = '1';
    card.style.display = 'none';
};

const markChoiceForDeletion = button => {
    const choice = button.closest('.choice');
    const questionCard = choice.closest('.question-card');
    const choicesContainer = choice.parentElement;
    const visibleChoices = choicesContainer.querySelectorAll('.choice:not([style*="display: none"])');
    
    // Prevent deletion if this is the last visible choice
    if (visibleChoices.length <= 1) {
        return;
    }

    const deleteFlag = choice.querySelector('.delete-flag');
    
    // Handle deletion based on whether it's an existing or new choice
    if (deleteFlag) {
        deleteFlag.value = '1';
        choice.style.display = 'none';
        
        const radio = choice.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            const remainingChoices = questionCard.querySelectorAll('.choice:not([style*="display: none"]) input[type="radio"]');
            if (remainingChoices.length > 0) {
                remainingChoices[0].checked = true;
            }
        }
    } else {
        const radio = choice.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            const remainingChoices = questionCard.querySelectorAll('.choice:not([style*="display: none"]) input[type="radio"]');
            if (remainingChoices.length > 0) {
                remainingChoices[0].checked = true;
            }
        }
        choice.remove();
    }
    
    // Update the placeholders for remaining visible choices
    updateChoicePlaceholders(choicesContainer);
};

const updateChoicePlaceholders = container => {
    const visibleChoices = container.querySelectorAll('.choice:not([style*="display: none"])');
    visibleChoices.forEach((choice, index) => {
        const textInput = choice.querySelector('input[type="text"]');
        if (textInput) {
            textInput.placeholder = `الخيار ${index + 1}`;
        }
    });
};

const addNewChoice = (questionId, isNewQuestion = false) => {
    const questionCard = isNewQuestion ? 
        document.getElementById(`new_question_${questionId}`) :
        document.querySelector(`[name="existing_questions[${questionId}][text]"]`).closest('.question-card');
    
    const choicesContainer = questionCard.querySelector('.choices');
    const visibleChoices = choicesContainer.querySelectorAll('.choice:not([style*="display: none"])');
    const choiceCount = visibleChoices.length;
    const timestamp = Date.now();
    
    const choiceDiv = document.createElement('div');
    choiceDiv.className = 'choice';
    
    if (isNewQuestion) {
        choiceDiv.innerHTML = `
            <input type="radio" name="new_questions[${questionId}][correct_answer]" value="${choiceCount}" ${choiceCount === 0 ? 'checked' : ''}>
            <input type="text" name="new_questions[${questionId}][choices][]" placeholder="الخيار ${choiceCount + 1}" required>
            <div class="image-upload">
                <label for="new_choice_image_${questionId}_${timestamp}">
                    <img src="upload-icon.png" alt="Upload" class="upload-icon">
                </label>
                <input type="file" name="new_questions[${questionId}][choice_images][]" id="new_choice_image_${questionId}_${timestamp}" 
                       accept="image/*" onchange="previewImage(this)">
                <img class="image-preview" style="display: none;">
            </div>
            <button type="button" class="btn btn-delete-choice" onclick="markChoiceForDeletion(this)">حذف</button>
        `;
    } else {
        const newChoiceId = `new_${timestamp}`;
        const existingRadios = questionCard.querySelectorAll('input[type="radio"]:checked');
        const shouldCheck = existingRadios.length === 0;
        
        choiceDiv.innerHTML = `
            <input type="radio" name="existing_questions[${questionId}][correct_answer]" value="${newChoiceId}" ${shouldCheck ? 'checked' : ''}>
            <input type="text" name="existing_questions[${questionId}][choices][${newChoiceId}][text]" placeholder="الخيار ${choiceCount + 1}" required>
            <div class="image-upload">
                <label for="choice_image_${questionId}_${newChoiceId}">
                    <img src="upload-icon.png" alt="Upload" class="upload-icon">
                </label>
                <input type="file" name="existing_questions[${questionId}][choices][${newChoiceId}][image]" 
                       id="choice_image_${questionId}_${newChoiceId}" 
                       accept="image/*" onchange="previewImage(this)">
                <img class="image-preview" style="display: none;">
            </div>
            <button type="button" class="btn btn-delete-choice" onclick="markChoiceForDeletion(this)">حذف</button>
        `;
    }
    
    choicesContainer.insertBefore(choiceDiv, choicesContainer.querySelector('.btn-add-choice'));
};

const addNewQuestion = () => {
    const container = document.getElementById('new_questions');
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-card';
    questionDiv.id = `new_question_${newQuestionCount}`;
    
    questionDiv.innerHTML = `
        <div class="question-header">
            <h3>سؤال جديد</h3>
            <button type="button" class="btn btn-delete" onclick="this.closest('.question-card').remove()">حذف</button>
        </div>
        <div class="question-content">
            <div class="form-group">
                <label>نص السؤال</label>
                <div class="question-input-row">
                    <textarea name="new_questions[${newQuestionCount}][text]" required></textarea>
                    <div class="image-upload">
                        <label for="new_question_image_${newQuestionCount}">
                            <img src="upload-icon.png" alt="Upload" class="upload-icon">
                        </label>
                        <input type="file" id="new_question_image_${newQuestionCount}" 
                               name="new_questions[${newQuestionCount}][image]" 
                               accept="image/*" onchange="previewImage(this)">
                        <img class="image-preview" style="display: none;">
                    </div>
                </div>
            </div>
            <div class="choices">
                <button type="button" class="btn btn-add-choice" onclick="addNewChoice(${newQuestionCount}, true)">إضافة خيار</button>
            </div>
        </div>
    `;
    
    container.appendChild(questionDiv);
    addNewChoice(newQuestionCount, true);
    newQuestionCount++;
};

const previewImage = (input) => {
    const imagePreview = input.parentElement.querySelector('.image-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            imagePreview.src = e.target.result;
            imagePreview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
};

const setupFormSubmit = () => {
    document.getElementById('quizForm')?.addEventListener('submit', function(e) {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        if (startTime && endTime && startTime >= endTime) {
            e.preventDefault();
            alert('يجب أن يكون وقت الانتهاء بعد وقت البدء');
            return;
        }
        
        // Check all visible questions for correct answers
        const questions = document.querySelectorAll('.question-card:not([style*="display: none"])');
        for (const question of questions) {
            const visibleChoices = question.querySelectorAll('.choice:not([style*="display: none"])');
            if (visibleChoices.length === 0) {
                e.preventDefault();
                alert('يجب أن يحتوي كل سؤال على خيار واحد على الأقل');
                return;
            }
            
            const hasCheckedRadio = question.querySelector('.choice:not([style*="display: none"]) input[type="radio"]:checked');
            if (!hasCheckedRadio) {
                e.preventDefault();
                alert('يجب تحديد إجابة صحيحة لكل سؤال');
                return;
            }
        }
    });
};

window.addEventListener('DOMContentLoaded', () => {
    setupFormSubmit();
    
    // Set up image preview handlers
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            previewImage(this);
        });
    });
    
    // Ensure each question has at least one choice selected as correct
    document.querySelectorAll('.question-card').forEach(card => {
        const visibleChoices = card.querySelectorAll('.choice:not([style*="display: none"])');
        const radios = Array.from(visibleChoices).map(choice => choice.querySelector('input[type="radio"]'));
        if (radios.length > 0 && !card.querySelector('input[type="radio"]:checked')) {
            radios[0].checked = true;
        }
    });
});
    </script>
</body>
</html>