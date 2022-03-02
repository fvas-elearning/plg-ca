-- --------------------------------------------
-- @version 3.2.48
-- @author: Michael Mifsud <info@tropotek.com>
--
-- --------------------------------------------


INSERT INTO ca_scale (institution_id, name, description, type, multiple, calc_type, max_value, del, modified, created)
  VALUES (1, 'Species', 'Provide a list of species the student has worked on during placement', 'choice', 0, 'avg', 3.0, 0, NOW(), NOW())
;

INSERT INTO ca_option (scale_id, name, description, value, del, modified, created) VALUES
    (LAST_INSERT_ID(), 'Small Animal', 'Cats, Dogs, etc', 1, 0, NOW(), NOW()),
    (LAST_INSERT_ID(), 'Production Animals', 'Cattle, Sheep, Chickens, etc', 2, 0, NOW(), NOW()),
    (LAST_INSERT_ID(), 'Equine', 'Horses', 3, 0, NOW(), NOW()),
    (LAST_INSERT_ID(), 'Other', 'Exotics and animals that do not fit into the other categories.', 4, 0, NOW(), NOW())
;

-- TODD you need to add the new species to the Assessment forms manually...
