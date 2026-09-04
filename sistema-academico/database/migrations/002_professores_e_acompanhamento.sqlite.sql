-- Perfil do professor sobre a identidade de acesso (users).
ALTER TABLE users ADD COLUMN document TEXT NULL;
ALTER TABLE users ADD COLUMN phone TEXT NULL;
ALTER TABLE users ADD COLUMN qualification TEXT NULL;
ALTER TABLE users ADD COLUMN notes TEXT NULL;

-- Acompanhamento pedagógico: o que foi FEITO a partir de um alerta.
CREATE TABLE IF NOT EXISTS interventions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  class_subject_id INTEGER NULL REFERENCES class_subjects(id) ON DELETE SET NULL,
  author_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  alert_key TEXT NULL,
  type TEXT NOT NULL DEFAULT 'conversa'
    CHECK (type IN ('conversa','reforco','material','contato_responsavel','encaminhamento','outro')),
  title TEXT NOT NULL,
  description TEXT NULL,
  action_taken TEXT NULL,
  due_date TEXT NULL,
  status TEXT NOT NULL DEFAULT 'aberta'
    CHECK (status IN ('aberta','em_andamento','concluida','cancelada')),
  result_note TEXT NULL,
  baseline_media REAL NULL,
  baseline_frequencia REAL NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  closed_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_interv_student ON interventions(student_id);
CREATE INDEX IF NOT EXISTS idx_interv_status ON interventions(status);
CREATE INDEX IF NOT EXISTS idx_interv_cs ON interventions(class_subject_id);
