-- ============================================================================
-- Painel Pedagógico - Schema MySQL / MariaDB
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  document VARCHAR(32) NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(32) NULL,
  birth_date DATE NULL,
  enrolled_at DATE NULL,
  status ENUM('ativo','inativo','concluido') NOT NULL DEFAULT 'ativo',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_students_document (document),
  KEY idx_students_status (status),
  KEY idx_students_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','professor','aluno') NOT NULL DEFAULT 'professor',
  student_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  document VARCHAR(32) NULL,
  phone VARCHAR(32) NULL,
  qualification VARCHAR(150) NULL,
  notes TEXT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_users_email (email),
  CONSTRAINT fk_users_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  workload_hours INT UNSIGNED NULL,
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_courses_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(150) NULL,
  course_id INT UNSIGNED NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  period VARCHAR(40) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('planejada','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'em_andamento',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_classes_code (code),
  KEY idx_classes_course (course_id),
  CONSTRAINT fk_classes_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  started_at DATE NULL,
  ended_at DATE NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('ativo','transferido','concluido','trancado') NOT NULL DEFAULT 'ativo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_enrollments_student (student_id),
  KEY idx_enrollments_class (class_id),
  KEY idx_enrollments_current (student_id, is_current),
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  teacher_user_id INT UNSIGNED NULL,
  workload_hours INT UNSIGNED NULL,
  status ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_subjects_name (name),
  CONSTRAINT fk_subjects_teacher FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  teacher_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_class_subject (class_id, subject_id),
  CONSTRAINT fk_cs_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_cs_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_cs_teacher FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_topics_subject (subject_id),
  KEY idx_topics_parent (parent_id),
  CONSTRAINT fk_topics_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_topics_parent FOREIGN KEY (parent_id) REFERENCES topics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_subject_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  lesson_date DATE NOT NULL,
  content TEXT NULL,
  duration_minutes INT UNSIGNED NULL,
  materials TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lessons_cs (class_subject_id),
  KEY idx_lessons_date (lesson_date),
  CONSTRAINT fk_lessons_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_topics (
  lesson_id INT UNSIGNED NOT NULL,
  topic_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (lesson_id, topic_id),
  CONSTRAINT fk_lt_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  CONSTRAINT fk_lt_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendances (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  status ENUM('presente','falta','falta_justificada','atraso') NOT NULL DEFAULT 'presente',
  participation TINYINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  UNIQUE KEY uk_attendance (lesson_id, student_id),
  KEY idx_att_student (student_id),
  CONSTRAINT fk_att_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_subject_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  type ENUM('prova','simulado','atividade','exercicio','diagnostica','revisao') NOT NULL DEFAULT 'prova',
  assessment_date DATE NOT NULL,
  max_score DECIMAL(6,2) NOT NULL DEFAULT 10.00,
  weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  description TEXT NULL,
  status ENUM('planejada','aplicada','corrigida') NOT NULL DEFAULT 'planejada',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_assess_cs (class_subject_id),
  KEY idx_assess_date (assessment_date),
  CONSTRAINT fk_assess_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT UNSIGNED NULL,
  subject_id INT UNSIGNED NOT NULL,
  topic_id INT UNSIGNED NULL,
  number INT UNSIGNED NULL,
  statement TEXT NULL,
  type ENUM('objetiva','discursiva') NOT NULL DEFAULT 'objetiva',
  difficulty ENUM('facil','medio','dificil') NOT NULL DEFAULT 'medio',
  points DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  answer_key VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_q_assessment (assessment_id),
  KEY idx_q_subject (subject_id),
  KEY idx_q_topic (topic_id),
  KEY idx_q_difficulty (difficulty),
  CONSTRAINT fk_q_assessment FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  CONSTRAINT fk_q_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_q_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  letter VARCHAR(4) NOT NULL,
  content TEXT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_opt_question (question_id),
  CONSTRAINT fk_opt_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  given_answer VARCHAR(255) NULL,
  result ENUM('correta','incorreta','nao_respondida') NOT NULL DEFAULT 'nao_respondida',
  score_earned DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_answer (question_id, student_id),
  KEY idx_ans_student (student_id),
  KEY idx_ans_result (result),
  CONSTRAINT fk_ans_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_ans_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  percentage DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  correct_count INT UNSIGNED NOT NULL DEFAULT 0,
  wrong_count INT UNSIGNED NOT NULL DEFAULT 0,
  blank_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_manual TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_grade (assessment_id, student_id),
  KEY idx_grade_student (student_id),
  CONSTRAINT fk_grade_assessment FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  CONSTRAINT fk_grade_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alert_dismissals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_key VARCHAR(191) NOT NULL,
  dismissed_by INT UNSIGNED NULL,
  dismissed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_alert_key (alert_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempt (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  entity VARCHAR(64) NULL,
  entity_id INT UNSIGNED NULL,
  details VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS interventions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  class_subject_id INT UNSIGNED NULL,
  author_user_id INT UNSIGNED NULL,
  alert_key VARCHAR(191) NULL,
  type ENUM('conversa','reforco','material','contato_responsavel','encaminhamento','outro') NOT NULL DEFAULT 'conversa',
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  action_taken TEXT NULL,
  due_date DATE NULL,
  status ENUM('aberta','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'aberta',
  result_note TEXT NULL,
  baseline_media DECIMAL(6,2) NULL,
  baseline_frequencia DECIMAL(6,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  KEY idx_interv_student (student_id),
  KEY idx_interv_status (status),
  KEY idx_interv_cs (class_subject_id),
  CONSTRAINT fk_interv_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_interv_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE SET NULL,
  CONSTRAINT fk_interv_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(191) NOT NULL UNIQUE,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
