<?php
declare(strict_types=1);

require_once __DIR__ . '/connector.php';

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    exit("Database connection failed.\n");
}

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0) > 0;
}

function addColumn(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!hasColumn($conn, $table, $column)) {
        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
        if (!$conn->query($sql)) {
            throw new RuntimeException("Failed adding {$table}.{$column}: {$conn->error}");
        }
    }
}

$conn->begin_transaction();
try {
    $queries = [
        "CREATE TABLE IF NOT EXISTS course_topics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id BIGINT UNSIGNED NOT NULL,
            course_unit_id BIGINT UNSIGNED NOT NULL,
            intake_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            order_number INT NOT NULL DEFAULT 1,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            class_mode ENUM('online','physical','both') NOT NULL DEFAULT 'both',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_by VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX idx_course_topics_module (course_unit_id),
            INDEX idx_course_topics_course (course_id),
            INDEX idx_course_topics_intake (intake_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS topic_materials (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            topic_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            material_type ENUM('video','document','powerpoint','link','text','audio','image') NOT NULL,
            document VARCHAR(255) NULL,
            external_url TEXT NULL,
            reading_content LONGTEXT NULL,
            mime_type VARCHAR(150) NULL,
            original_filename VARCHAR(255) NULL,
            file_size BIGINT UNSIGNED NULL,
            order_number INT NOT NULL DEFAULT 1,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            uploaded_by VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX idx_topic_materials_topic (topic_id),
            CONSTRAINT fk_topic_materials_topic FOREIGN KEY (topic_id) REFERENCES course_topics(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS assignment_resources (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            assignment_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NULL,
            resource_type ENUM('file','link','text') NOT NULL,
            document VARCHAR(255) NULL,
            resource_url TEXT NULL,
            resource_text LONGTEXT NULL,
            original_filename VARCHAR(255) NULL,
            uploaded_by VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_assignment_resources_assignment (assignment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS module_quizzes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id BIGINT UNSIGNED NOT NULL,
            course_unit_id BIGINT UNSIGNED NOT NULL,
            intake_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            instructions TEXT NULL,
            total_points DECIMAL(10,2) NOT NULL DEFAULT 0,
            passing_score DECIMAL(5,2) NOT NULL DEFAULT 70,
            max_attempts INT NOT NULL DEFAULT 1,
            deadline DATETIME NULL,
            required_for_completion TINYINT(1) NOT NULL DEFAULT 0,
            class_mode ENUM('online','physical','both') NOT NULL DEFAULT 'both',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_by VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX idx_module_quizzes_unit (course_unit_id),
            INDEX idx_module_quizzes_intake (intake_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS quiz_questions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quiz_id BIGINT UNSIGNED NOT NULL,
            question_type ENUM('multiple_choice','true_false','short_answer') NOT NULL,
            question_text TEXT NOT NULL,
            points DECIMAL(10,2) NOT NULL DEFAULT 1,
            order_number INT NOT NULL DEFAULT 1,
            correct_answer TEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_quiz_questions_quiz (quiz_id),
            CONSTRAINT fk_quiz_questions_quiz FOREIGN KEY (quiz_id) REFERENCES module_quizzes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS quiz_options (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question_id BIGINT UNSIGNED NOT NULL,
            option_text TEXT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            order_number INT NOT NULL DEFAULT 1,
            INDEX idx_quiz_options_question (question_id),
            CONSTRAINT fk_quiz_options_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS quiz_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quiz_id BIGINT UNSIGNED NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            attempt_number INT NOT NULL DEFAULT 1,
            score DECIMAL(10,2) NOT NULL DEFAULT 0,
            percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            status ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'submitted',
            started_at DATETIME NOT NULL,
            submitted_at DATETIME NULL,
            INDEX idx_quiz_attempts_quiz_user (quiz_id,user_id),
            CONSTRAINT fk_quiz_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES module_quizzes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS quiz_answers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            option_id BIGINT UNSIGNED NULL,
            answer_text TEXT NULL,
            awarded_points DECIMAL(10,2) NOT NULL DEFAULT 0,
            is_correct TINYINT(1) NULL,
            INDEX idx_quiz_answers_attempt (attempt_id),
            CONSTRAINT fk_quiz_answers_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS topic_completion (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            topic_id BIGINT UNSIGNED NOT NULL,
            completed_at DATETIME NOT NULL,
            UNIQUE KEY uq_topic_completion (user_id, topic_id),
            INDEX idx_topic_completion_topic (topic_id),
            CONSTRAINT fk_topic_completion_topic FOREIGN KEY (topic_id) REFERENCES course_topics(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $sql) {
        if (!$conn->query($sql)) throw new RuntimeException($conn->error);
    }

    addColumn($conn, 'assignments', 'submission_type', "ENUM('file','text','link','file_text','any') NOT NULL DEFAULT 'file'");
    addColumn($conn, 'assignments', 'required_for_completion', "TINYINT(1) NOT NULL DEFAULT 0");
    addColumn($conn, 'assignments', 'allow_late_submission', "TINYINT(1) NOT NULL DEFAULT 0");
    addColumn($conn, 'assignments', 'max_attempts', "INT NULL DEFAULT 1");
    addColumn($conn, 'assignments', 'published_at', "DATETIME NULL");

    addColumn($conn, 'handed_in', 'submission_type', "ENUM('file','text','link') NOT NULL DEFAULT 'file'");
    addColumn($conn, 'handed_in', 'text_response', "LONGTEXT NULL");
    addColumn($conn, 'handed_in', 'submission_url', "TEXT NULL");
    addColumn($conn, 'handed_in', 'feedback', "TEXT NULL");
    addColumn($conn, 'handed_in', 'status', "ENUM('draft','submitted','late','graded','returned') NOT NULL DEFAULT 'submitted'");
    addColumn($conn, 'handed_in', 'attempt_number', "INT NOT NULL DEFAULT 1");
    addColumn($conn, 'handed_in', 'graded_by', "VARCHAR(100) NULL");
    addColumn($conn, 'handed_in', 'graded_at', "DATETIME NULL");

    addColumn($conn, 'course_unit', 'access_mode', "ENUM('immediate','after_previous') NOT NULL DEFAULT 'immediate'");
    addColumn($conn, 'course_unit', 'prerequisite_module_id', "BIGINT UNSIGNED NULL");

    $conn->commit();
    echo "Course structure migration completed successfully.\n";
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
}
