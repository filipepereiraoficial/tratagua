-- ============================================================================
-- Painel Pedagógico - Schema SQLite 3 (espelho do schema MySQL)
-- ============================================================================
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS students (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  full_name TEXT NOT NULL,
  document TEXT NULL UNIQUE,
  email TEXT NULL,
  phone TEXT NULL,
  birth_date TEXT NULL,
  enrolled_at TEXT NULL,
  status TEXT NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo','inativo','concluido')),
  notes TEXT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_students_status ON students(status);
CREATE INDEX IF NOT EXISTS idx_students_name ON students(full_name);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'professor' CHECK (role IN ('admin','professor','aluno')),
  student_id INTEGER NULL REFERENCES students(id) ON DELETE SET NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  must_change_password INTEGER NOT NULL DEFAULT 0,
  last_login_at TEXT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS courses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  description TEXT NULL,
  workload_hours INTEGER NULL,
  status TEXT NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo','inativo')),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS classes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NULL,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE RESTRICT,
  year INTEGER NOT NULL,
  period TEXT NULL,
  start_date TEXT NULL,
  end_date TEXT NULL,
  status TEXT NOT NULL DEFAULT 'em_andamento' CHECK (status IN ('planejada','em_andamento','concluida','cancelada')),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_classes_course ON classes(course_id);

CREATE TABLE IF NOT EXISTS enrollments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE RESTRICT,
  started_at TEXT NULL,
  ended_at TEXT NULL,
  is_current INTEGER NOT NULL DEFAULT 1,
  status TEXT NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo','transferido','concluido','trancado')),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_enrollments_student ON enrollments(student_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_class ON enrollments(class_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_current ON enrollments(student_id, is_current);

CREATE TABLE IF NOT EXISTS subjects (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  description TEXT NULL,
  teacher_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  workload_hours INTEGER NULL,
  status TEXT NOT NULL DEFAULT 'ativa' CHECK (status IN ('ativa','inativa')),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS class_subjects (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
  subject_id INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
  teacher_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  UNIQUE (class_id, subject_id)
);

CREATE TABLE IF NOT EXISTS topics (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  subject_id INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
  parent_id INTEGER NULL REFERENCES topics(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  description TEXT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_topics_subject ON topics(subject_id);
CREATE INDEX IF NOT EXISTS idx_topics_parent ON topics(parent_id);

CREATE TABLE IF NOT EXISTS lessons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  class_subject_id INTEGER NOT NULL REFERENCES class_subjects(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  lesson_date TEXT NOT NULL,
  content TEXT NULL,
  duration_minutes INTEGER NULL,
  materials TEXT NULL,
  notes TEXT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_lessons_cs ON lessons(class_subject_id);
CREATE INDEX IF NOT EXISTS idx_lessons_date ON lessons(lesson_date);

CREATE TABLE IF NOT EXISTS lesson_topics (
  lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
  topic_id INTEGER NOT NULL REFERENCES topics(id) ON DELETE CASCADE,
  PRIMARY KEY (lesson_id, topic_id)
);

CREATE TABLE IF NOT EXISTS attendances (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
  student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'presente' CHECK (status IN ('presente','falta','falta_justificada','atraso')),
  participation INTEGER NULL,
  notes TEXT NULL,
  UNIQUE (lesson_id, student_id)
);
CREATE INDEX IF NOT EXISTS idx_att_student ON attendances(student_id);

CREATE TABLE IF NOT EXISTS assessments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  class_subject_id INTEGER NOT NULL REFERENCES class_subjects(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  type TEXT NOT NULL DEFAULT 'prova' CHECK (type IN ('prova','simulado','atividade','exercicio','diagnostica','revisao')),
  assessment_date TEXT NOT NULL,
  max_score REAL NOT NULL DEFAULT 10.0,
  weight REAL NOT NULL DEFAULT 1.0,
  description TEXT NULL,
  status TEXT NOT NULL DEFAULT 'planejada' CHECK (status IN ('planejada','aplicada','corrigida')),
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_assess_cs ON assessments(class_subject_id);
CREATE INDEX IF NOT EXISTS idx_assess_date ON assessments(assessment_date);

CREATE TABLE IF NOT EXISTS questions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  assessment_id INTEGER NULL REFERENCES assessments(id) ON DELETE CASCADE,
  subject_id INTEGER NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
  topic_id INTEGER NULL REFERENCES topics(id) ON DELETE SET NULL,
  number INTEGER NULL,
  statement TEXT NULL,
  type TEXT NOT NULL DEFAULT 'objetiva' CHECK (type IN ('objetiva','discursiva')),
  difficulty TEXT NOT NULL DEFAULT 'medio' CHECK (difficulty IN ('facil','medio','dificil')),
  points REAL NOT NULL DEFAULT 1.0,
  answer_key TEXT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_q_assessment ON questions(assessment_id);
CREATE INDEX IF NOT EXISTS idx_q_subject ON questions(subject_id);
CREATE INDEX IF NOT EXISTS idx_q_topic ON questions(topic_id);
CREATE INDEX IF NOT EXISTS idx_q_difficulty ON questions(difficulty);

CREATE TABLE IF NOT EXISTS question_options (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
  letter TEXT NOT NULL,
  content TEXT NULL,
  is_correct INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_opt_question ON question_options(question_id);

CREATE TABLE IF NOT EXISTS student_answers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
  student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  given_answer TEXT NULL,
  result TEXT NOT NULL DEFAULT 'nao_respondida' CHECK (result IN ('correta','incorreta','nao_respondida')),
  score_earned REAL NOT NULL DEFAULT 0.0,
  answered_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  UNIQUE (question_id, student_id)
);
CREATE INDEX IF NOT EXISTS idx_ans_student ON student_answers(student_id);
CREATE INDEX IF NOT EXISTS idx_ans_result ON student_answers(result);

CREATE TABLE IF NOT EXISTS grades (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  assessment_id INTEGER NOT NULL REFERENCES assessments(id) ON DELETE CASCADE,
  student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  score REAL NOT NULL DEFAULT 0.0,
  percentage REAL NOT NULL DEFAULT 0.0,
  correct_count INTEGER NOT NULL DEFAULT 0,
  wrong_count INTEGER NOT NULL DEFAULT 0,
  blank_count INTEGER NOT NULL DEFAULT 0,
  is_manual INTEGER NOT NULL DEFAULT 0,
  notes TEXT NULL,
  updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  UNIQUE (assessment_id, student_id)
);
CREATE INDEX IF NOT EXISTS idx_grade_student ON grades(student_id);

CREATE TABLE IF NOT EXISTS settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS alert_dismissals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  alert_key TEXT NOT NULL UNIQUE,
  dismissed_by INTEGER NULL,
  dismissed_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL,
  ip TEXT NOT NULL,
  success INTEGER NOT NULL DEFAULT 0,
  attempted_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_attempt ON login_attempts(email, attempted_at);

CREATE TABLE IF NOT EXISTS activity_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NULL,
  action TEXT NOT NULL,
  entity TEXT NULL,
  entity_id INTEGER NULL,
  details TEXT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_log_created ON activity_log(created_at);
