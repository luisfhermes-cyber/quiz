-- =====================================================================
-- Quiz SENAI — atualização para VÁRIAS contas do Instagram (até 10)
-- Use se você já tinha importado o schema.sql / atualizar_instagram.sql antes.
-- phpMyAdmin > selecione o seu banco > Importar > este arquivo.
-- É seguro rodar mais de uma vez: não apaga nenhum dado.
-- A conta que você já tinha cadastrada é copiada para a nova lista.
--
-- Se o seu banco NÃO se chama quiz_senai, apague a linha "USE quiz_senai;"
-- e selecione o banco antes de importar.
-- =====================================================================

USE quiz_senai;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS instagram_contas (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ordem                SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  ativo                TINYINT(1)   NOT NULL DEFAULT 1,
  usuario              VARCHAR(31)  NOT NULL,
  ig_user_id           VARCHAR(40)  NULL,
  access_token         TEXT         NULL,
  api_host             VARCHAR(40)  NOT NULL DEFAULT 'graph.instagram.com',
  api_versao           VARCHAR(10)  NOT NULL DEFAULT 'v21.0',
  limite_dia           SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  bloqueada_ate        DATETIME     NULL,
  ultimo_erro          VARCHAR(500) NULL,
  token_atualizado_em  DATETIME     NULL,
  criado_em            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_instagram_contas_usuario (usuario)
) ENGINE=InnoDB;

-- Copia a conta única antiga (se existir e ainda não foi copiada)
INSERT INTO instagram_contas (ordem, ativo, usuario, ig_user_id, access_token, api_host, api_versao, token_atualizado_em)
SELECT 1, 1, c.usuario, c.ig_user_id, c.access_token, c.api_host, c.api_versao, c.token_atualizado_em
FROM instagram_config c
WHERE c.id = 1 AND c.usuario IS NOT NULL AND c.usuario <> '' AND c.access_token IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM instagram_contas x WHERE x.usuario = c.usuario);

-- Novas colunas em "publicacoes" (só adiciona se ainda não existirem)
SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publicacoes' AND COLUMN_NAME = 'conta_id');
SET @q := IF(@t = 0, 'ALTER TABLE publicacoes ADD COLUMN conta_id INT UNSIGNED NULL, ADD COLUMN conta_usuario VARCHAR(31) NULL, ADD KEY idx_publicacoes_conta (conta_id, status, publicado_em)', 'SELECT 1');
PREPARE stmt FROM @q;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
