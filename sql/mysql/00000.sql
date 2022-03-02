-- ---------------------------------
-- Author: Michael Mifsud <info@tropotek.com>
-- ---------------------------------


-- ----------------------------
-- Assessment
-- ----------------------------
CREATE TABLE IF NOT EXISTS ca_assessment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(128) NOT NULL DEFAULT '',                   -- Reserved to be used via an external API identifier or for historic reporting, this could just = id for now.
    course_id INT UNSIGNED DEFAULT 0 NOT NULL,              -- In the EMS this will be the profile_id

    -- Assessors:
    --   `Student` After each placement the student will submit a self-assessment
    --   `Supervisor/External` Enables a public form that will be sent to the placement supervisor/company
    --   TODO: `Learner` Each learner/staff will have an icon to add/edit an assessment entry (leave this for future versions)

    name VARCHAR(128) DEFAULT '' NOT NULL,
    icon VARCHAR(32) DEFAULT 'fa fa-rebel' NOT NULL,        -- a bootstrap CSS icon for the collection EG: 'fa fa-pen'

    assessor_group VARCHAR(20) DEFAULT 'student' NOT NULL,  -- student = self assessment, Learner = staff student assessment, supervisor/External = company/mentor student assessment
    placement_status TEXT,                                  -- JSON array of status values when an assessment can be edited
    include_zero BOOL DEFAULT 0 NOT NULL,                   -- Should zero values be included in overall average calculations (Default: false)

    description TEXT,                                       -- Description will be placed on the top of the assessment submition form. (Put instructions here)
    notes TEXT,
    del BOOL NOT NULL DEFAULT 0,
    modified DATETIME NOT NULL,
    created DATETIME NOT NULL,

    KEY (uid),
    KEY (course_id),
    KEY del(del)
) ENGINE = InnoDB;

-- A place to enable entry views on a per subject level
CREATE TABLE IF NOT EXISTS ca_assessment_subject (
    assessment_id INT UNSIGNED NOT NULL DEFAULT 0,
    subject_id INT UNSIGNED NOT NULL DEFAULT 0,
    publish_student DATETIME,                                -- Can the student view the completed entries and result reports
    PRIMARY KEY (assessment_id, subject_id)
) ENGINE=InnoDB;

-- --------------------------------------------
-- Associate assessments with placement types
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS ca_assessment_placement_type (
  assessment_id INT UNSIGNED NOT NULL DEFAULT 0,
  placement_type_id INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (assessment_id, placement_type_id)
) ENGINE=InnoDB;


-- --------------------------------------------
-- Learning domains for the competancies
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS ca_domain (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(128) NOT NULL DEFAULT '',
    institution_id INT UNSIGNED NOT NULL DEFAULT 1,
    -- course_id INT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    label VARCHAR(16) NOT NULL,                         -- abbreviated label
    order_by INT UNSIGNED NOT NULL DEFAULT 0,
    del BOOL NOT NULL DEFAULT 0,
    modified DATETIME NOT NULL,
    created DATETIME NOT NULL,
    KEY (uid),
    KEY (institution_id),
    KEY del(del)
) ENGINE = InnoDB;

-- ---------------------------------------
-- Competency question setup tables
-- ---------------------------------------
CREATE TABLE IF NOT EXISTS ca_competency (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(128) NOT NULL DEFAULT '',               -- TODO: Use to create unique/updateable data
    institution_id INT UNSIGNED NOT NULL DEFAULT 1,
    -- course_id INT UNSIGNED NOT NULL DEFAULT 0,
    name TEXT,
    description TEXT,
    del BOOL NOT NULL DEFAULT 0,
    modified DATETIME NOT NULL,
    created DATETIME NOT NULL,
    KEY (uid),
    KEY (institution_id),
    KEY (del)
) ENGINE = InnoDB;

-- --------------------------------------------
-- Scale
--  TODO: initially I do not think we will make these fully editable by general staff. Only admins
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS ca_scale (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(128) NOT NULL DEFAULT '',
    institution_id INT UNSIGNED NOT NULL DEFAULT 1,     -- TODO: I think we will keep this option globalfor now....
    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    type VARCHAR(255) NOT NULL DEFAULT 'text',          -- `text`, `points`, `percentage` OR a `choice` using options in the the ca_option table ???
    multiple BOOL NOT NULL DEFAULT 0,                   -- if using a list: when true then use checkboxes when false use radio boxes
                                                        -- NOTE: This also means there will be many values per item when true.
    calc_type VARCHAR(16) NOT NULL DEFAULT 'avg',       -- avg, add .... ?? (this would be the calculation method for one item when using multiple values)
    max_value DECIMAL(6, 2) NOT NULL DEFAULT 0,         -- Used for when entering a grade `points` or `percentage` and when averaging out overall item results
    del BOOL NOT NULL DEFAULT 0,
    modified DATETIME NOT NULL,
    created DATETIME NOT NULL,
    KEY (institution_id),
    KEY del(del)
) ENGINE = InnoDB;

-- --------------------------------------------
--
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS ca_option (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scale_id INT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    value DECIMAL(6, 2) NOT NULL DEFAULT 0,             -- Typical values (0, 1, 2, 3, 4) ???
    del BOOL NOT NULL DEFAULT 0,
    modified DATETIME NOT NULL,
    created DATETIME NOT NULL,
    KEY (scale_id),
    KEY del(del)
) ENGINE = InnoDB;

-- --------------------------------------------
-- Item
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS ca_item (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(128) NOT NULL DEFAULT '',
    assessment_id INT UNSIGNED NOT NULL DEFAULT 0,
    scale_id INT UNSIGNED NOT NULL DEFAULT 0,
    domain_id INT UNSIGNED NOT NULL DEFAULT 0,  -- If domain Id is 0, then it is assumed this item is related directly to the assessment and should be rendered at the end of the form.

    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    gradable BOOL NOT NULL DEFAULT 0,           -- When true whatever the calculated score is, will be used in an average for the overall assessment
    required BOOL NOT NULL DEFAULT 0,           --
    -- NOTE: Enable only one `avg` or `percent` item to allow the user to enter a score/grade manually.
    order_by INT UNSIGNED NOT NULL DEFAULT 0,
    del BOOL NOT NULL DEFAULT 0,
    modified DATETIME NOT NULL,
    created DATETIME NOT NULL,

    KEY (uid),
    KEY (assessment_id),
    KEY (scale_id),
    KEY del(del)
) ENGINE = InnoDB;

-- --------------------------------------------
--
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS ca_item_competency (
    item_id INT UNSIGNED NOT NULL DEFAULT 0,
    competency_id INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (item_id, competency_id)
) ENGINE = InnoDB;


-- ------------------------------------------------------
--
--
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS ca_entry (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT UNSIGNED NOT NULL DEFAULT 0,
    subject_id INT UNSIGNED NOT NULL DEFAULT 0,
    student_id INT UNSIGNED NOT NULL DEFAULT 0,          -- The student user id the entry belongs to
    assessor_id INT UNSIGNED NOT NULL DEFAULT 0,         -- (optional) The assessor user id the entry belongs to if a local user
    placement_id INT UNSIGNED NOT NULL DEFAULT 0,        -- (optional) The placement this entry is linked to if 0 then assume self-assessment

    title VARCHAR(255) NOT NULL DEFAULT '',              -- A title for the assessment instance
    assessor_name VARCHAR(128) DEFAULT '' NOT NULL,      -- Name of person assessing the student if not supervisor.
    assessor_email VARCHAR(128) DEFAULT '' NOT NULL,     -- Email of assesor
    absent INT(4) DEFAULT 0 NOT NULL,                    -- Number of days absent from placement.
    average DECIMAL(6, 2) NOT NULL DEFAULT 0.0,          -- Average calculated from all item values
    status VARCHAR(64) NOT NULL DEFAULT '',              -- pending, approved, not-approved
    notes TEXT,                                          -- Staff only notes
    del BOOL NOT NULL DEFAULT 0,
    modified DATETIME NOT NULL,
    created DATETIME NOT NULL,
    KEY (assessment_id),
    KEY (subject_id),
    KEY (student_id),
    KEY (assessor_id),
    KEY (placement_id),
    KEY (del)
) ENGINE = InnoDB;

-- ------------------------------------------------------
--
-- ------------------------------------------------------
CREATE TABLE IF NOT EXISTS ca_value (
    entry_id INT UNSIGNED NOT NULL DEFAULT 0,
    item_id INT UNSIGNED NOT NULL DEFAULT 0,
    value TEXT,
    PRIMARY KEY (entry_id, item_id)
) ENGINE = InnoDB;




-- ------------------------------------------------------------------------------------------------------------------------------------


TRUNCATE ca_assessment;
INSERT INTO ca_assessment (uid, course_id, name, icon, placement_status, assessor_group, include_zero, description, modified, created) VALUES
--    (1, 2, 'GOALS', 'tk tk-goals', 'assessing,evaluating,completed,failed', 'company', 0, '', NOW(), NOW()),
    (1, 2, 'Supervisor Evaluation', 'fa fa-user-md', 'assessing,evaluating,completed,failed', 'company', 0, '', NOW(), NOW()),
    (2, 2, 'Self Assessment', 'fa fa-user-circle-o', 'assessing,evaluating,completed,failed', 'student', 0, '', NOW(), NOW()),
    (3, 2, 'Supervisor Evaluation', 'fa fa-user-md', 'assessing,evaluating,completed,failed', 'company', 0, '', NOW(), NOW())
;

TRUNCATE ca_assessment_placement_type;
INSERT INTO ca_assessment_placement_type (assessment_id, placement_type_id) VALUES (1, 8);
INSERT INTO ca_assessment_placement_type (assessment_id, placement_type_id) VALUES (2, 8);
INSERT INTO ca_assessment_placement_type (assessment_id, placement_type_id) VALUES (2, 9);
INSERT INTO ca_assessment_placement_type (assessment_id, placement_type_id) VALUES (3, 9);

TRUNCATE ca_scale;
INSERT INTO ca_scale (name, description, type, multiple, calc_type, max_value, modified, created) VALUES
('Text', 'Prompt the user for an answer as a text value', 'text', 0, 'avg', 0, NOW(), NOW()),
('Points', 'Allow the user to enter a value. [0-1000]', 'value', 0, 'avg', 1000, NOW(), NOW()),
('Percent', 'Allow the user to add a percentage value. [0-100]', 'value', 0, 'avg', 100, NOW(), NOW()),
('EMS Assessment', 'The EMS evaluation criteria as of 2020 as radio selections. [0-3]', 'choice', 0, 'avg', 3, NOW(), NOW()),
('Yes/No', 'Prompt the user for a yes or no question. [0-1]', 'choice', 0, 'avg', 0, NOW(), NOW()),
('Rotation Assessment', 'The rotation evaluation criteria as of 2020 as radio selections. [0-4]', 'choice', 0, 'avg', 4, NOW(), NOW())
;

TRUNCATE ca_option;
INSERT INTO ca_option (scale_id, name, description, value, modified, created) VALUES
(4, 'Not Observed', '', 0, NOW(), NOW()),
-- (4, 'Needs Further Development', '', 1, NOW(), NOW()),
(4, 'Needs Development', '', 1, NOW(), NOW()),
(4, 'Meets Expectations', '', 2, NOW(), NOW()),
(4, 'Exceeds Expectations', '', 3, NOW(), NOW())
;
INSERT INTO ca_option (scale_id, name, description, value, modified, created) VALUES
(5, 'No', '', 0, NOW(), NOW()),
(5, 'Yes', '', 1, NOW(), NOW())
;
INSERT INTO ca_option (scale_id, name, description, value, modified, created) VALUES
(6, 'Unable', '', 0, NOW(), NOW()),
(6, 'Almost There', '', 1, NOW(), NOW()),
(6, 'Competent', '', 2, NOW(), NOW()),
(6, 'Very Good', '', 3, NOW(), NOW()),
(6, 'Excels', '', 4, NOW(), NOW())
;

TRUNCATE ca_competency;
INSERT INTO ca_competency (institution_id, name, description, modified, created) VALUES
    (1, 'Demonstrates honesty, integrity, commitment, compassion, respect, teamwork and inclusivity', '', NOW(), NOW()),
    (1, 'Communicates effectively with clients, students, veterinary colleagues and staff through a range of media', '', NOW(), NOW()),
    (1, 'Actively participates in case management including prioritisation and completion of tasks', '', NOW(), NOW()),
    (1, 'Engages in self-directed learning, demonstrates an ability to reflect upon their performance and invites feedback', '', NOW(), NOW()),
    (1, 'Demonstrates an understanding of legal, ethical and welfare issues encountered in the veterinary profession and animal production systems', '', NOW(), NOW()),
    (1, 'Demonstrates an understanding of responsible dispensing of medications that complies with professional, regulatory and legal requirements', '', NOW(), NOW()),
    (1, 'Demonstrates an understanding of the need for clinical records to comply with professional, regulatory and legal requirements', '', NOW(), NOW()),
    (1, 'Recognises actual or potential animal pain or distress', '', NOW(), NOW()),
    (1, 'Recognises actual or potential animal pain or distress and discusses relevant methods of management', '', NOW(), NOW()),
    (1, 'Recognises when euthanasia is appropriate and discusses relevant humane techniques for euthanasia', '', NOW(), NOW()),
    (1, 'Demonstrates safe, low stress, animal handling techniques', '', NOW(), NOW()),
    (1, 'Recognises patients requiring emergency care', '', NOW(), NOW()),
    (1, 'Recognises patients requiring emergency care and discusses appropriate management', '', NOW(), NOW()),
    (1, 'Observes basic diagnostic procedures and practical skills', '', NOW(), NOW()),
    (1, 'Observes sedation and general anaesthesia and recovery of a patient ', '', NOW(), NOW()),
    (1, 'Observes common surgical procedures', '', NOW(), NOW()),
    (1, 'Gathers a history, performs a clinical examination and develops a diagnostic plan', '', NOW(), NOW()),
    (1, 'Performs basic diagnostic procedures and practical skills', '', NOW(), NOW()),
    (1, 'Participates in sedation and general anesthesia including recovery of a stable patient, monitoring and supportive treatments', '', NOW(), NOW()),
    (1, 'Performs common surgical procedures on a stable patient', '', NOW(), NOW()),
    (1, 'Develops a pre and post-operative management plan for common surgical procedures', '', NOW(), NOW()),
    (1, 'Discusses a pre and post-operative management plan for common surgical procedures', '', NOW(), NOW()),
    (1, 'Demonstrates an understanding of health, safety and biosecurity in a veterinary setting', '', NOW(), NOW()),
    (1, 'Demonstrates ability to calculate an appropriate drug dose and knowledge of withholding periods where appropriate', '', NOW(), NOW()),
    (1, 'Demonstrates an understanding of anatomy, physiology and pathophysiology', '', NOW(), NOW()),
    (1, 'Demonstrates ability to find information in the literature', '', NOW(), NOW()),
    (1, 'Applies principles of anatomy, physiology and pathophysiology to the diagnosis, prevention, and treatment of clinical cases', '', NOW(), NOW()),
    (1, 'Creates a prioritised differential diagnosis list assimilating information from the history, clinical examination and diagnostic tests', '', NOW(), NOW()),
    (1, 'Develops an adaptive management plan that respects client wishes and finances and available resources (including personal skills and equipment)', '', NOW(), NOW()),
    (1, 'Demonstrates an understanding of appropriate preventative health care programs', '', NOW(), NOW()),
    (1, 'Demonstrates an understanding of an appropriate response to possible zoonotic, exotic or notifiable diseases', '', NOW(), NOW()),
    (1, 'Demonstrates an understanding of appropriate plans to maintain and/or investigate breakdowns in food safety and public health', '', NOW(), NOW())
;

TRUNCATE ca_domain;
INSERT INTO ca_domain (institution_id, name, description, label, modified, created) VALUES
    (1, 'Personal And Professional Development', '', 'PD', NOW(), NOW()),
    (1, 'Clinical And Technical Skills', '', 'CS', NOW(), NOW()),
    (1, 'Communication Skills', '', 'COM', NOW(), NOW()),
--    (1, 'Scientific Basis Of Clinical Practice', '', 'SB', NOW(), NOW()),
    (1, 'Knowledge And Problem Solving', '', 'KPS', NOW(), NOW()),
    (1, 'Safely Approach, Handle And Restrain Animals (Exotic)', '', 'EX', NOW(), NOW()),
    (1, 'Ethics And Animal Welfare', '', 'AW', NOW(), NOW()),
    (1, 'Biosecurity And Population Health', '', 'BIOS', NOW(), NOW())
;


TRUNCATE ca_item;
TRUNCATE ca_item_competency;

-- GOALS (Weeks 5-21)
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 1);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 2);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 3);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 4);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 5);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 6);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 7);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 9);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 10);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 1, 1, 'Please provide specific feedback to the student regarding areas of professionalism and ethics that require further development.', '', 1, 0, NOW(), NOW());

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 11);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 13);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 17);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 18);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 19);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 20);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 21);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 23);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 1, 2, 'Please provide specific feedback to the student regarding areas of clinical and technical skills that require further development.', '', 1, 0, NOW(), NOW());

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 27);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 26);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 28);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 29);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 30);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 31);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 32);

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 1, 4, ' Please provide specific feedback to the student regarding areas of knowledge and problem solving that require further development.', '', 1, 0, NOW(), NOW());

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 1, 0, 'What did the student do well on this placement?', '', 1, 0, NOW(), NOW());
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 1, 0, 'What areas do you think the student should focus on improving to be day-one ready by the end of DVM4?', '', 1, 0, NOW(), NOW());
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (1, 5, 0, 'Holistic Assessment: Do you believe this student is on track to being day one practice ready by the end of DVM4?', '', 1, 0, NOW(), NOW());


-- Self Assessment
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 1);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 2);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 3);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 4);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 5);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 6);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 7);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 9);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 10);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 1, 'What areas of professionalism and ethics do you feel you need to further develop?', '', 1, 0, NOW(), NOW());
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 1, 'How do you plan to further develop your competency in these areas of professionalism and ethics?', '', 1, 0, NOW(), NOW());

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 11);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 13);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 17);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 18);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 19);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 20);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 21);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 23);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 4, 'What procedures did you perform on this placement?', '', 1, 0, NOW(), NOW());
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 2, 'What areas of clinical and technical skills do you feel you need to further develop?', '', 1, 0, NOW(), NOW());
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 2, 'How do you plan to further develop your competency in these areas of clinical and technical skills?', '', 1, 0, NOW(), NOW());

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 27);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 26);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 28);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 29);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 30);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 31);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 32);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 4, 'What areas of knowledge and problem solving do you feel you need to further develop?', '', 1, 0, NOW(), NOW());
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 4, 'How do you plan to further develop your competency in these areas of knowledge and problem solving?', '', 1, 0, NOW(), NOW());

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 0, 'What where the highlights of this placement?', '', 1, 0, NOW(), NOW());
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 1, 0, 'Briefly describe a case you dealt with during a placement where you were actively involved in working up, managing and treating the case (1000 words or less).', '', 1, 0, NOW(), NOW());
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (2, 5, 0, 'Holistic Assessment: Do you belive you are on track to being day one practice ready by the end of DVM 4?', '', 1, 0, NOW(), NOW());

-- Supervisor Feedback (Weeks 1-4)
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 1);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 2);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 4);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 5);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 8);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 1, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 10);

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 11);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 12);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 14);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 15);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 16);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 22);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 24);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 2, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 23);

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 25);
INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 4, 4, '', '', 0, 1, NOW(), NOW());
INSERT INTO ca_item_competency (item_id, competency_id) VALUES (LAST_INSERT_ID(), 26);

INSERT INTO ca_item (assessment_id, scale_id, domain_id, name, description, required, gradable, modified, created) VALUES (3, 1, 0, 'What did the student do well on this placement?', '', 1, 0, NOW(), NOW());

UPDATE ca_item SET order_by = id WHERE 1;




