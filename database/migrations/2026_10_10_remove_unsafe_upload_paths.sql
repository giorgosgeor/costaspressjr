-- Security audit S1. A saved design could name any file on the server as one
-- of its images, and deleting the design then deleted that file.
-- CustomDesign now keeps only files the customer uploaded, inside
-- public/images/designs/uploads/. This removes artwork rows saved before the
-- fix that name anything else. Only the rows go; no file is touched.
DELETE FROM custom_design_uploads
WHERE stored_file_path IS NOT NULL
  AND stored_file_path <> ''
  AND stored_file_path NOT REGEXP '^public/images/designs/uploads/[A-Za-z0-9_-]+/[A-Za-z0-9_-][A-Za-z0-9._-]*[.](png|jpe?g|gif|webp)$';
