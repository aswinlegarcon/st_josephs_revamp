-- C4 — school_sections: the events-block heading prefix varies per page
-- ("Events of" on KG, "Exams and Events of" on the other three).
-- Additive-only (deploy rule): safe to apply to a live database.

ALTER TABLE school_sections
  ADD COLUMN events_heading VARCHAR(60) NOT NULL DEFAULT 'Exams and Events of' AFTER timeline_heading;
