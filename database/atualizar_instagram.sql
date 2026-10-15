-- =====================================================================
-- Quiz SENAI — atualização do banco para a publicação no Instagram
-- Use SOMENTE se você já tinha importado o schema.sql antes.
-- phpMyAdmin > selecione o banco "quiz_senai" > Importar > este arquivo.
-- É seguro rodar mais de uma vez: não apaga nenhum dado.
-- =====================================================================

USE quiz_senai;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- INSTAGRAM: conta da escola que publica e fila de publicações
-- (se você já tinha importado o banco antes, use atualizar_instagram.sql)
-- ---------------------------------------------------------------------

-- Configuração da conta que publica (sempre uma única linha, id = 1)
CREATE TABLE IF NOT EXISTS instagram_config (
  id                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ativo                TINYINT(1)   NOT NULL DEFAULT 0,
  usuario              VARCHAR(31)  NULL,                    -- @ da conta que publica, ex.: @senai.maravilha
  ig_user_id           VARCHAR(40)  NULL,                    -- ID numérico da conta profissional
  access_token         TEXT         NULL,                    -- token de acesso (nunca é mostrado no painel)
  api_host             VARCHAR(40)  NOT NULL DEFAULT 'graph.instagram.com',
  api_versao           VARCHAR(10)  NOT NULL DEFAULT 'v21.0',
  url_publica          VARCHAR(255) NULL,                    -- endereço público do site (https://...)
  token_atualizado_em  DATETIME     NULL,
  atualizado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT IGNORE INTO instagram_config (id) VALUES (1);

-- Resultados enviados pelos participantes para serem publicados nos Stories
CREATE TABLE IF NOT EXISTS publicacoes (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tentativa_id      INT UNSIGNED NOT NULL,
  instagram_pessoa  VARCHAR(31)  NOT NULL,                   -- @ que será marcado
  arquivo           VARCHAR(60)  NULL,                       -- imagem guardada em publicacoes/arquivos (apagada depois)
  status            ENUM('pendente','publicando','publicada','rejeitada','erro') NOT NULL DEFAULT 'pendente',
  marcou            TINYINT(1)   NULL,                       -- 1 = a marcação do @ foi aplicada
  ig_media_id       VARCHAR(40)  NULL,
  erro              VARCHAR(500) NULL,
  criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  publicado_em      DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_publicacoes_tentativa (tentativa_id),
  KEY idx_publicacoes_status (status, criado_em),
  CONSTRAINT fk_publicacoes_tentativa FOREIGN KEY (tentativa_id) REFERENCES tentativas (id) ON DELETE CASCADE
) ENGINE=InnoDB;
