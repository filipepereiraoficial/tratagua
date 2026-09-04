-- Perfil do professor sobre a identidade de acesso (users).
ALTER TABLE users ADD COLUMN document VARCHAR(32) NULL;
ALTER TABLE users ADD COLUMN phone VARCHAR(32) NULL;
ALTER TABLE users ADD COLUMN qualification VARCHAR(150) NULL;
ALTER TABLE users ADD COLUMN notes TEXT NULL;

-- Acompanhamento pedagógico: o que foi FEITO a partir de um alerta.
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
