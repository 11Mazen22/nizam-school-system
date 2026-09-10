-- Nizam School Management System (Postgres/Supabase)
-- Migration 002: roles, permissions, role_permissions, users
-- Postgres translation of database/migrations/002_roles_permissions_users.sql

CREATE TABLE roles (
  id       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code     VARCHAR(30)  NOT NULL UNIQUE,  -- admin / staff
  name_en  VARCHAR(60)  NOT NULL,
  name_ar  VARCHAR(60)  NOT NULL
);

CREATE TABLE permissions (
  id       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code     VARCHAR(60)  NOT NULL UNIQUE,  -- e.g. students.archive
  name_en  VARCHAR(100) NOT NULL,
  name_ar  VARCHAR(100) NOT NULL,
  module   VARCHAR(40)  NOT NULL
);

CREATE TABLE role_permissions (
  role_id        INTEGER NOT NULL,
  permission_id  INTEGER NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_role_permissions_permission
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE users (
  id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username        VARCHAR(50)  NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  full_name       VARCHAR(150) NOT NULL,
  role_id         INTEGER      NOT NULL,
  -- SMALLINT (0/1), not native BOOLEAN: the PHP layer reads these as
  -- (int) $row['is_active'] === 1 throughout every repository. pdo_pgsql
  -- returns a native BOOLEAN as the string 't'/'f', not '1'/'0', which
  -- would silently break every one of those checks. SMALLINT keeps the
  -- exact same on-the-wire values MySQL's TINYINT(1) already produced --
  -- zero application code needs to change because of this column's type.
  is_active       SMALLINT     NOT NULL DEFAULT 1,
  failed_attempts SMALLINT     NOT NULL DEFAULT 0,
  locked_until    TIMESTAMP    NULL,
  last_login_at   TIMESTAMP    NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TRIGGER trg_users_updated_at
  BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();
