-- 1. Verifica e Cria o Banco de Dados
-- (Substitua 'mariusbd' pelo nome que você deseja, se for diferente)
CREATE DATABASE IF NOT EXISTS mariusbd
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- 2. Seleciona o Banco de Dados para a Criação da Tabela
USE mariusbd;

-- 3. Verifica e Cria a Tabela de Usuários ('users')
-- Esta tabela armazena as credenciais para o seu painel de login.
CREATE TABLE IF NOT EXISTS users (
    -- ID único e auto-incrementável para cada usuário
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Nome de usuário, deve ser único para login
    username VARCHAR(50) NOT NULL UNIQUE,

    -- Senha armazenada como um hash seguro (VARCHAR(255) é o ideal para o hash PHP)
    password VARCHAR(255) NOT NULL,

    -- Email (opcional, mas bom para recuperação de senha)
    email VARCHAR(100) NULL,

    -- Data de criação do registro
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Coluna opcional para determinar o nível de acesso (ex: admin, moderador, padrão)
    role VARCHAR(20) DEFAULT 'user'
);


-- 4. Tabela para Rastrear Tentativas de Login
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- O endereço IP que tentou o login
    ip_address VARCHAR(45) NOT NULL,
    -- A hora da última tentativa. Usamos UNIX_TIMESTAMP() para facilitar a comparação no PHP.
    last_attempt_time INT(11) NOT NULL,
    -- O número de tentativas falhas consecutivas
    attempts_count TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
    -- Garante que teremos apenas uma linha por IP
    UNIQUE KEY (ip_address)
);

-- 5. Tabela para Banimentos de IP
CREATE TABLE IF NOT EXISTS ip_bans (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- O endereço IP banido
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    -- O carimbo de data/hora (timestamp) em que o banimento deve expirar
    ban_expires_at INT(11) NOT NULL,
    -- Data/Hora em que o banimento foi registrado
    banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Exemplo de Inserção de Usuário (OPCIONAL: apenas para testes iniciais)
-- ATENÇÃO: Nunca armazene a senha em texto puro (como 'sua_senha_segura') em um sistema real.
-- Use o PHP para gerar o hash seguro (password_hash()) e insira esse hash na coluna 'password'.
--
-- Exemplo de como um usuário seria inserido APÓS o hash da senha:
-- INSERT INTO users (username, password, email, role) VALUES (
--    'admin',
--    '$2y$10$seu_hash_seguro_aqui_gerado_pelo_php', -- Exemplo de hash (255 caracteres)
--    'admin@exemplo.com',
--    'admin'
-- );