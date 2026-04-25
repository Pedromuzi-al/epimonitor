-- =====================================================
--  EpiMonitor — Schema do Banco de Dados
--  Monitoramento Comunitário de Sintomas
--  Grupo nº 01 | Ciência da Computação
-- =====================================================

CREATE DATABASE IF NOT EXISTS epidemio_monitor
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE epidemio_monitor;

-- ─────────────────────────────────────────
--  TABELA: bairros
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bairros (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(100)  NOT NULL,
  cidade     VARCHAR(100)  NOT NULL DEFAULT 'Caratinga',
  estado     CHAR(2)       NOT NULL DEFAULT 'MG',
  criado_em  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
--  TABELA: registros (anônimos)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS registros (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  bairro_id      INT          NOT NULL,
  dias_sintomas  TINYINT      NOT NULL DEFAULT 1 COMMENT 'Há quantos dias os sintomas começaram',
  ip_hash        VARCHAR(64)  NULL     COMMENT 'SHA256 do IP — sem armazenar IP real',
  criado_em      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_registro_bairro FOREIGN KEY (bairro_id) REFERENCES bairros(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
--  TABELA: registro_sintomas
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS registro_sintomas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  registro_id INT         NOT NULL,
  sintoma     VARCHAR(50) NOT NULL COMMENT 'febre | tosse | dor_corpo | dor_cabeca | diarreia | nausea | fadiga | manchas',
  CONSTRAINT fk_sintoma_registro FOREIGN KEY (registro_id) REFERENCES registros(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
--  TABELA: alertas
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS alertas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  bairro_id   INT          NOT NULL,
  nivel       ENUM('baixo','medio','alto','critico') NOT NULL DEFAULT 'baixo',
  mensagem    TEXT         NOT NULL,
  ativo       TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_alerta_bairro FOREIGN KEY (bairro_id) REFERENCES bairros(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
--  DADOS INICIAIS — Bairros de Caratinga
-- ─────────────────────────────────────────
INSERT INTO bairros (nome) VALUES
  ('Centro'),
  ('Santa Zita'),
  ('Esperança'),
  ('Dornelas'),
  ('Limoeiro'),
  ('Nossa Senhora Aparecida'),
  ('Bela Vista'),
  ('São João Batista'),
  ('Industrial'),
  ('Saúde');

-- ─────────────────────────────────────────
--  DADOS DE EXEMPLO — Registros simulados
-- ─────────────────────────────────────────
INSERT INTO registros (bairro_id, dias_sintomas, ip_hash) VALUES
  (1, 2, SHA2('192.168.1.1', 256)),
  (1, 3, SHA2('192.168.1.2', 256)),
  (1, 1, SHA2('192.168.1.3', 256)),
  (1, 4, SHA2('192.168.1.4', 256)),
  (1, 2, SHA2('192.168.1.5', 256)),
  (2, 1, SHA2('192.168.2.1', 256)),
  (2, 2, SHA2('192.168.2.2', 256)),
  (3, 1, SHA2('192.168.3.1', 256)),
  (5, 3, SHA2('192.168.5.1', 256)),
  (5, 4, SHA2('192.168.5.2', 256)),
  (5, 2, SHA2('192.168.5.3', 256));

INSERT INTO registro_sintomas (registro_id, sintoma) VALUES
  (1,'febre'),(1,'tosse'),(1,'dor_corpo'),
  (2,'febre'),(2,'dor_cabeca'),(2,'fadiga'),
  (3,'febre'),(3,'tosse'),
  (4,'febre'),(4,'manchas'),(4,'fadiga'),
  (5,'tosse'),(5,'dor_corpo'),
  (6,'tosse'),(6,'dor_cabeca'),
  (7,'febre'),(7,'diarreia'),
  (8,'fadiga'),(8,'dor_corpo'),
  (9,'febre'),(9,'tosse'),(9,'manchas'),
  (10,'febre'),(10,'dor_corpo'),(10,'diarreia'),
  (11,'febre'),(11,'nausea');

-- Alerta ativo de exemplo
INSERT INTO alertas (bairro_id, nivel, mensagem) VALUES
  (1, 'alto',   'Centro: aumento de 43% em sintomas gripais nas últimas 48h. Possível surto em desenvolvimento.'),
  (5, 'medio',  'Limoeiro: elevação de casos de febre acima da média esperada para o período.');

-- ─────────────────────────────────────────
--  VIEW: resumo por bairro (útil em relatórios)
-- ─────────────────────────────────────────
CREATE OR REPLACE VIEW vw_resumo_bairros AS
SELECT
  b.id,
  b.nome           AS bairro,
  COUNT(r.id)      AS total_registros,
  AVG(r.dias_sintomas) AS media_dias,
  MAX(r.criado_em)     AS ultimo_registro
FROM bairros b
LEFT JOIN registros r ON r.bairro_id = b.id
  AND r.criado_em >= NOW() - INTERVAL 7 DAY
GROUP BY b.id, b.nome;

-- ─────────────────────────────────────────
--  VIEW: contagem de sintomas por bairro
-- ─────────────────────────────────────────
CREATE OR REPLACE VIEW vw_sintomas_bairro AS
SELECT
  b.nome    AS bairro,
  rs.sintoma,
  COUNT(*)  AS quantidade,
  DATE(r.criado_em) AS data_registro
FROM registro_sintomas rs
JOIN registros r  ON r.id  = rs.registro_id
JOIN bairros   b  ON b.id  = r.bairro_id
WHERE r.criado_em >= NOW() - INTERVAL 7 DAY
GROUP BY b.nome, rs.sintoma, DATE(r.criado_em);
