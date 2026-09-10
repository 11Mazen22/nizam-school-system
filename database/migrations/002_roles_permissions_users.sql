-- Nizam School Management System
-- Migration 002: roles, permissions, role_permissions, users
-- Blueprint reference: §D, §J (Permission catalog), §O-16 (login throttling)

CREATE TABLE roles (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code     VARCHAR(30)  NOT NULL COMMENT 'admin / staff',
  name_en  VARCHAR(60)  NOT NULL,
  name_ar  VARCHAR(60)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code     VARCHAR(60)  NOT NULL COMMENT 'e.g. students.archive',
  name_en  VARCHAR(100) NOT NULL,
  name_ar  VARCHAR(100) NOT NULL,
  module   VARCHAR(40)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id        INT UNSIGNED NOT NULL,
  permission_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_role_permissions_permission
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Pure join table -- CASCADE per §D's index/constraint strategy: the relationship,
-- not the entity, disappears.

CREATE TABLE users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username        VARCHAR(50)  NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  full_name       VARCHAR(150) NOT NULL,
  role_id         INT UNSIGNED NOT NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME     NULL,
  last_login_at   DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  CONSTRAINT fk_users_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- role_id RESTRICT per §D: a reference table's row (here, a role) cannot be removed
-- while a user still points at it -- matches the "archive, never hard-delete" rule (O-10),
-- which applies to roles exactly like every other core reference entity.
