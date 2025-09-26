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