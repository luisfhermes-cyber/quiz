-- =====================================================================
-- Quiz SENAI — banco de dados (MySQL / MariaDB)
-- Como usar: phpMyAdmin > Importar > escolher este arquivo.
-- Ele cria o banco "quiz_senai", as tabelas e os dados iniciais.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS quiz_senai
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE quiz_senai;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- CONTEÚDO DO QUIZ (copiado do java.js; serve de referência para o painel)
-- ---------------------------------------------------------------------

-- Áreas / cursos (5 áreas)
CREATE TABLE areas (
  id           TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo       VARCHAR(20)  NOT NULL,            -- chave usada no java.js (eletro, eletrom, autom, seg, ds)
  nome         VARCHAR(80)  NOT NULL,
  cor          CHAR(7)      NOT NULL,            -- cor da área, ex.: #d97706
  descricao    VARCHAR(500) NOT NULL,            -- texto "Por que essa área combina com você?"
  sobre_curso  VARCHAR(600) NULL,                -- parágrafo "O SENAI descreve..."
  ordem        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_areas_codigo (codigo)
) ENGINE=InnoDB;

-- Profissões que cada área abre
CREATE TABLE profissoes (
  id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_id  TINYINT UNSIGNED  NOT NULL,
  nome     VARCHAR(120)      NOT NULL,
  ordem    TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_profissoes_area (area_id),
  CONSTRAINT fk_profissoes_area FOREIGN KEY (area_id) REFERENCES areas (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Banco de perguntas (9 por área; o quiz sorteia 3 de cada)
CREATE TABLE perguntas (
  id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_id  TINYINT UNSIGNED  NOT NULL,
  texto    VARCHAR(255)      NOT NULL,           -- precisa ser igual ao texto do java.js
  ordem    TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  ativa    TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_perguntas_texto (texto),
  KEY idx_perguntas_area (area_id),
  CONSTRAINT fk_perguntas_area FOREIGN KEY (area_id) REFERENCES areas (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- DADOS DE QUEM RESPONDE
-- ---------------------------------------------------------------------

-- Quem fez o quiz (tela de nome / celular / Instagram). Um celular = uma pessoa.
CREATE TABLE participantes (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome           VARCHAR(100) NOT NULL,
  celular        VARCHAR(11)  NOT NULL,          -- só dígitos, com DDD
  instagram      VARCHAR(31)  NULL,              -- opcional, começa com @
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_participantes_celular (celular)
) ENGINE=InnoDB;

-- Cada vez que a pessoa termina o quiz (quem clica em "Refazer o teste" gera outra)
CREATE TABLE tentativas (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  participante_id    INT UNSIGNED NOT NULL,
  area_vencedora_id  TINYINT UNSIGNED NOT NULL,   -- área que mais combina
  houve_desempate    TINYINT(1)       NOT NULL DEFAULT 0,
  rodadas_desempate  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  criado_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tentativas_participante (participante_id),
  KEY idx_tentativas_area (area_vencedora_id),
  KEY idx_tentativas_data (criado_em),
  CONSTRAINT fk_tentativas_participante FOREIGN KEY (participante_id) REFERENCES participantes (id) ON DELETE CASCADE,
  CONSTRAINT fk_tentativas_area         FOREIGN KEY (area_vencedora_id) REFERENCES areas (id)
) ENGINE=InnoDB;

-- Cada resposta dada (nota de 1 a 5), inclusive as perguntas extras de desempate
CREATE TABLE respostas (
  id           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  tentativa_id INT UNSIGNED      NOT NULL,
  pergunta_id  SMALLINT UNSIGNED NOT NULL,
  area_id      TINYINT UNSIGNED  NOT NULL,
  valor        TINYINT UNSIGNED  NOT NULL,        -- 1 (não me identifico) a 5 (me identifico totalmente)
  fase         ENUM('normal','desempate') NOT NULL DEFAULT 'normal',
  rodada       TINYINT UNSIGNED  NOT NULL DEFAULT 0, -- 0 = quiz normal; 1, 2... = rodada de desempate
  ordem        SMALLINT UNSIGNED NOT NULL,         -- ordem em que a pergunta apareceu
  PRIMARY KEY (id),
  KEY idx_respostas_tentativa (tentativa_id),
  KEY idx_respostas_pergunta (pergunta_id),
  CONSTRAINT fk_respostas_tentativa FOREIGN KEY (tentativa_id) REFERENCES tentativas (id) ON DELETE CASCADE,
  CONSTRAINT fk_respostas_pergunta  FOREIGN KEY (pergunta_id)  REFERENCES perguntas (id),
  CONSTRAINT fk_respostas_area      FOREIGN KEY (area_id)      REFERENCES areas (id),
  CONSTRAINT ck_respostas_valor CHECK (valor BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- Placar final de cada área em cada tentativa (as barras coloridas da tela final)
CREATE TABLE pontuacoes (
  tentativa_id      INT UNSIGNED      NOT NULL,
  area_id           TINYINT UNSIGNED  NOT NULL,
  pontos            TINYINT UNSIGNED  NOT NULL DEFAULT 0,  -- 0 a 15 (3 perguntas x 5)
  pontos_desempate  SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- soma nas perguntas extras
  PRIMARY KEY (tentativa_id, area_id),
  KEY idx_pontuacoes_area (area_id),
  CONSTRAINT fk_pontuacoes_tentativa FOREIGN KEY (tentativa_id) REFERENCES tentativas (id) ON DELETE CASCADE,
  CONSTRAINT fk_pontuacoes_area      FOREIGN KEY (area_id)      REFERENCES areas (id)
) ENGINE=InnoDB;

-- Cliques no botão "Compartilhar no Instagram"
CREATE TABLE compartilhamentos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tentativa_id  INT UNSIGNED NOT NULL,
  canal         VARCHAR(20)  NOT NULL DEFAULT 'instagram',
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_compartilhamentos_tentativa (tentativa_id),
  CONSTRAINT fk_compartilhamentos_tentativa FOREIGN KEY (tentativa_id) REFERENCES tentativas (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Visão pronta para o painel e para exportar
CREATE OR REPLACE VIEW v_resultados AS
SELECT
  t.id                 AS tentativa_id,
  t.criado_em          AS data_hora,
  p.id                 AS participante_id,
  p.nome               AS nome,
  p.celular            AS celular,
  p.instagram          AS instagram,
  a.id                 AS area_id,
  a.nome               AS area_vencedora,
  a.cor                AS area_cor,
  t.houve_desempate    AS houve_desempate,
  t.rodadas_desempate  AS rodadas_desempate,
  (SELECT COUNT(*) FROM compartilhamentos c WHERE c.tentativa_id = t.id) AS compartilhamentos
FROM tentativas t
JOIN participantes p ON p.id = t.participante_id
JOIN areas a         ON a.id = t.area_vencedora_id;

-- Dados iniciais (gerados a partir do java.js)

INSERT INTO areas (codigo, nome, cor, descricao, sobre_curso, ordem) VALUES
('ds', 'Desenvolvimento de Sistemas', '#dc2626', 'Suas respostas mostram interesse por lógica, tecnologia, programação e criação de soluções digitais para resolver problemas.', 'O curso do SENAI inclui lógica de programação, banco de dados, aplicativos, IoT, testes, implantação, modelagem, manutenção e desenvolvimento de sistemas.', 1),
('seg', 'Segurança do Trabalho', '#16a34a', 'Suas respostas mostram atenção aos detalhes, prevenção de riscos, organização e interesse em tornar os ambientes de trabalho mais seguros.', 'O SENAI descreve atuação envolvendo prevenção, identificação e avaliação de riscos, higiene ocupacional, ações educativas, auditorias, documentação e análise de acidentes.', 2),
('eletrom', 'Eletromecânica', '#7c3aed', 'Suas respostas mostram interesse por máquinas, ferramentas, manutenção e pela combinação entre eletricidade e mecânica.', 'O SENAI relaciona o curso a montagem, manutenção e projetos de sistemas mecânicos, elétricos e automatizados, além de instalações e manutenção de equipamentos industriais.', 3),
('eletro', 'Eletrotécnica', '#d97706', 'Suas respostas mostram interesse por energia, eletricidade, equipamentos e soluções para tornar o uso da energia mais eficiente.', 'A formação do SENAI inclui instalações e projetos prediais e industriais, acionamentos, manutenção, eficiência energética e sistemas elétricos de potência.', 4),
('autom', 'Automação Industrial', '#0891b2', 'Suas respostas mostram interesse por tecnologia, máquinas automáticas, robótica e processos que podem funcionar de forma mais eficiente.', 'O SENAI relaciona Automação Industrial a sistemas lógicos programáveis, instrumentação e controle de processos, sistemas microcontrolados, acionamentos, supervisão, integração, manutenção e comissionamento.', 5);

INSERT INTO profissoes (area_id, nome, ordem) VALUES
((SELECT id FROM areas WHERE codigo='ds'), 'Desenvolvedor de sistemas', 1),
((SELECT id FROM areas WHERE codigo='ds'), 'Desenvolvedor web', 2),
((SELECT id FROM areas WHERE codigo='ds'), 'Desenvolvedor de aplicativos', 3),
((SELECT id FROM areas WHERE codigo='ds'), 'Técnico/analista de suporte e implantação de sistemas', 4),
((SELECT id FROM areas WHERE codigo='ds'), 'Testador de software / QA', 5),
((SELECT id FROM areas WHERE codigo='ds'), 'Profissional de banco de dados', 6),
((SELECT id FROM areas WHERE codigo='ds'), 'Profissional de manutenção de sistemas', 7),
((SELECT id FROM areas WHERE codigo='ds'), 'Profissional ligado a IoT', 8),
((SELECT id FROM areas WHERE codigo='seg'), 'Técnico em Segurança do Trabalho', 1),
((SELECT id FROM areas WHERE codigo='seg'), 'Profissional de prevenção de riscos ocupacionais', 2),
((SELECT id FROM areas WHERE codigo='seg'), 'Assistente/analista de Saúde, Segurança e Meio Ambiente (SSMA/EHS)', 3),
((SELECT id FROM areas WHERE codigo='seg'), 'Profissional de higiene ocupacional', 4),
((SELECT id FROM areas WHERE codigo='seg'), 'Profissional de apoio a auditorias e documentação de segurança', 5),
((SELECT id FROM areas WHERE codigo='seg'), 'Profissional de treinamento e ações educativas de segurança', 6),
((SELECT id FROM areas WHERE codigo='seg'), 'Profissional de prevenção e análise de acidentes', 7),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Técnico em Eletromecânica', 1),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Técnico/profissional de manutenção eletromecânica', 2),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Profissional de manutenção de máquinas e equipamentos', 3),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Técnico de montagem de equipamentos industriais', 4),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Técnico de instalação de sistemas eletromecânicos', 5),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Profissional de assistência técnica/serviços de campo', 6),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Profissional de projetos de sistemas eletromecânicos', 7),
((SELECT id FROM areas WHERE codigo='eletro'), 'Técnico em Eletrotécnica', 1),
((SELECT id FROM areas WHERE codigo='eletro'), 'Técnico de instalações elétricas', 2),
((SELECT id FROM areas WHERE codigo='eletro'), 'Técnico de manutenção elétrica', 3),
((SELECT id FROM areas WHERE codigo='eletro'), 'Técnico de projetos elétricos', 4),
((SELECT id FROM areas WHERE codigo='eletro'), 'Técnico de sistemas elétricos industriais', 5),
((SELECT id FROM areas WHERE codigo='eletro'), 'Profissional de manutenção predial/industrial', 6),
((SELECT id FROM areas WHERE codigo='eletro'), 'Profissional de sistemas elétricos de potência', 7),
((SELECT id FROM areas WHERE codigo='eletro'), 'Profissional ligado à eficiência energética', 8),
((SELECT id FROM areas WHERE codigo='autom'), 'Técnico em Automação Industrial', 1),
((SELECT id FROM areas WHERE codigo='autom'), 'Técnico de automação', 2),
((SELECT id FROM areas WHERE codigo='autom'), 'Técnico de instrumentação', 3),
((SELECT id FROM areas WHERE codigo='autom'), 'Instrumentista industrial', 4),
((SELECT id FROM areas WHERE codigo='autom'), 'Técnico de controle de processos', 5),
((SELECT id FROM areas WHERE codigo='autom'), 'Profissional de programação/configuração de CLPs', 6),
((SELECT id FROM areas WHERE codigo='autom'), 'Técnico de manutenção de sistemas automatizados', 7),
((SELECT id FROM areas WHERE codigo='autom'), 'Técnico de comissionamento', 8),
((SELECT id FROM areas WHERE codigo='autom'), 'Profissional de integração de sistemas automatizados', 9);

INSERT INTO perguntas (area_id, texto, ordem) VALUES
((SELECT id FROM areas WHERE codigo='ds'), 'Você gosta de transformar uma ideia em algo que outras pessoas possam utilizar no dia a dia?', 1),
((SELECT id FROM areas WHERE codigo='ds'), 'Quando encontra um problema, você costuma tentar dividi-lo em pequenas partes para encontrar uma solução?', 2),
((SELECT id FROM areas WHERE codigo='ds'), 'Você teria interesse em criar algo que pudesse funcionar de maneira diferente dependendo das escolhas de cada pessoa?', 3),
((SELECT id FROM areas WHERE codigo='ds'), 'Você se sente confortável passando bastante tempo concentrado em uma tarefa que exige atenção aos detalhes?', 4),
((SELECT id FROM areas WHERE codigo='ds'), 'Você gosta da ideia de encontrar erros em algo e investigar até descobrir exatamente onde está o problema?', 5),
((SELECT id FROM areas WHERE codigo='ds'), 'Você teria curiosidade em entender como grandes quantidades de informações podem ser organizadas e encontradas rapidamente?', 6),
((SELECT id FROM areas WHERE codigo='ds'), 'Você gosta de aprender ferramentas novas sozinho, explorando e testando suas funções?', 7),
((SELECT id FROM areas WHERE codigo='ds'), 'Você se interessaria por criar soluções que pudessem ser utilizadas em computadores, celulares ou outros dispositivos?', 8),
((SELECT id FROM areas WHERE codigo='ds'), 'Você prefere atividades nas quais seja possível testar uma solução, perceber o resultado e fazer melhorias várias vezes?', 9),
((SELECT id FROM areas WHERE codigo='seg'), 'Você costuma perceber situações que podem causar problemas antes que eles realmente aconteçam?', 1),
((SELECT id FROM areas WHERE codigo='seg'), 'Você se sente confortável chamando a atenção de alguém quando percebe que uma regra importante não está sendo seguida?', 2),
((SELECT id FROM areas WHERE codigo='seg'), 'Você gosta de observar como as pessoas realizam suas atividades e pensar em maneiras de torná-las mais seguras?', 3),
((SELECT id FROM areas WHERE codigo='seg'), 'Você teria facilidade para explicar uma orientação importante para um grupo de pessoas?', 4),
((SELECT id FROM areas WHERE codigo='seg'), 'Você gosta de atividades que envolvem observar, registrar informações e depois analisar o que foi encontrado?', 5),
((SELECT id FROM areas WHERE codigo='seg'), 'Quando acontece um problema, você prefere investigar suas possíveis causas antes de simplesmente procurar um culpado?', 6),
((SELECT id FROM areas WHERE codigo='seg'), 'Você se interessa por situações que exigem organização, acompanhamento de procedimentos e cumprimento de regras?', 7),
((SELECT id FROM areas WHERE codigo='seg'), 'Você teria interesse em participar de treinamentos e campanhas voltados para melhorar os hábitos das pessoas em um ambiente de trabalho?', 8),
((SELECT id FROM areas WHERE codigo='seg'), 'Você gosta da ideia de ajudar uma organização a prevenir problemas e melhorar continuamente suas práticas?', 9),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Você gosta de entender como diferentes partes de uma máquina trabalham juntas para realizar uma tarefa?', 1),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Quando um equipamento deixa de funcionar, você teria curiosidade de investigar o que provocou o problema?', 2),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Você prefere atividades que envolvam tanto raciocínio quanto trabalho prático?', 3),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Você se interessa por observar movimentos, peças e mecanismos para entender como tudo funciona?', 4),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Você gosta de montar, desmontar ou ajustar objetos para descobrir como eles funcionam?', 5),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Você teria interesse em acompanhar máquinas e equipamentos para evitar que apresentem problemas inesperados?', 6),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Você gosta de seguir desenhos ou instruções para montar algo seguindo determinadas medidas e especificações?', 7),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Você se interessaria por encontrar maneiras de melhorar o funcionamento ou a eficiência de equipamentos?', 8),
((SELECT id FROM areas WHERE codigo='eletrom'), 'Você gostaria de trabalhar em atividades nas quais fosse necessário combinar diferentes tipos de conhecimento para resolver um problema?', 9),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você gosta de entender como a energia é distribuída para fazer diferentes equipamentos funcionarem?', 1),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você se sente confortável seguindo esquemas, desenhos ou representações para descobrir como algo deve ser montado?', 2),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você teria interesse em trabalhar com projetos que precisam seguir medidas, padrões e especificações bem definidas?', 3),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você gosta de procurar maneiras de fazer algo funcionar gastando menos recursos?', 4),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você prefere atividades em que pequenos detalhes podem fazer uma grande diferença no resultado final?', 5),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você teria interesse em acompanhar equipamentos e instalações para identificar problemas antes que eles causem uma interrupção?', 6),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você gosta de pensar em como uma estrutura poderia ser planejada antes de sua execução?', 7),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você se interessaria por atividades relacionadas ao funcionamento e à manutenção de grandes sistemas que fornecem energia?', 8),
((SELECT id FROM areas WHERE codigo='eletro'), 'Você gostaria de trabalhar com soluções que precisam funcionar de forma confiável durante longos períodos?', 9),
((SELECT id FROM areas WHERE codigo='autom'), 'Você gosta da ideia de fazer uma tarefa acontecer automaticamente depois que determinadas condições são definidas?', 1),
((SELECT id FROM areas WHERE codigo='autom'), 'Você se interessa por descobrir como máquinas podem tomar determinadas decisões a partir de informações recebidas?', 2),
((SELECT id FROM areas WHERE codigo='autom'), 'Você gosta de situações em que é necessário acompanhar várias informações ao mesmo tempo para perceber se tudo está funcionando corretamente?', 3),
((SELECT id FROM areas WHERE codigo='autom'), 'Você teria curiosidade em entender como sensores conseguem perceber mudanças no ambiente ou em uma máquina?', 4),
((SELECT id FROM areas WHERE codigo='autom'), 'Você gosta de procurar maneiras de tornar um processo mais rápido, preciso ou organizado?', 5),
((SELECT id FROM areas WHERE codigo='autom'), 'Você se interessaria por controlar equipamentos utilizando sistemas digitais?', 6),
((SELECT id FROM areas WHERE codigo='autom'), 'Você gosta de atividades que misturam raciocínio lógico, tecnologia e resolução de problemas práticos?', 7),
((SELECT id FROM areas WHERE codigo='autom'), 'Você teria interesse em acompanhar o funcionamento de um processo e fazer ajustes quando alguma variável sair do esperado?', 8),
((SELECT id FROM areas WHERE codigo='autom'), 'Você gostaria de participar da criação ou implantação de sistemas que fazem máquinas e equipamentos trabalharem de forma integrada?', 9);

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
