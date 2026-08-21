-- Tradução fiel das migrations do tenant para SQL puro.
-- Serve para provar, contra um PostgreSQL 16 real, que o schema é válido:
-- tipos, FKs, índices parciais e constraints. Não substitui as migrations —
-- é o teste delas enquanto o vendor/ não existe no ambiente.

CREATE TABLE municipios (
    id            bigserial PRIMARY KEY,
    codigo_ibge   char(7) NOT NULL UNIQUE,
    nome          varchar(120) NOT NULL,
    uf            char(2) NOT NULL,
    latitude      numeric(10,7),
    longitude     numeric(10,7)
);
CREATE INDEX municipios_uf_nome ON municipios (uf, nome);

CREATE TABLE empresas (
    id            bigserial PRIMARY KEY,
    razao_social  varchar(150) NOT NULL,
    nome_fantasia varchar(150),
    cnpj          varchar(14) NOT NULL UNIQUE,
    timezone      varchar(40) NOT NULL DEFAULT 'America/Sao_Paulo',
    parametros    jsonb,
    ativa         boolean NOT NULL DEFAULT true,
    vigencia_ate  date,
    created_at    timestamp, updated_at timestamp, deleted_at timestamp
);

CREATE TABLE filiais (
    id                bigserial PRIMARY KEY,
    empresa_id        bigint NOT NULL REFERENCES empresas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    codigo            varchar(10) NOT NULL,
    razao_social      varchar(150) NOT NULL,
    nome_fantasia     varchar(150),
    cnpj              varchar(14) NOT NULL,
    ie                varchar(20) NOT NULL,
    im                varchar(20),
    crt               char(1) NOT NULL DEFAULT '3',
    rntrc             varchar(10),
    rntrc_validade    date,
    tp_transp         char(1),
    matriz            boolean NOT NULL DEFAULT false,
    logradouro        varchar(150) NOT NULL,
    numero            varchar(20) NOT NULL,
    complemento       varchar(80),
    bairro            varchar(80) NOT NULL,
    municipio_id      bigint NOT NULL REFERENCES municipios(id),
    cep               char(8) NOT NULL,
    telefone          varchar(20),
    email             varchar(120),
    ambiente_sefaz    smallint NOT NULL DEFAULT 2,
    uf_autorizadora   char(2),
    contingencia_automatica boolean NOT NULL DEFAULT true,
    certificado_id    bigint,
    logo_path         varchar(255),
    ativa             boolean NOT NULL DEFAULT true,
    created_at timestamp, updated_at timestamp, deleted_at timestamp,
    UNIQUE (empresa_id, codigo),
    UNIQUE (empresa_id, cnpj)
);
CREATE INDEX filiais_empresa_ativa ON filiais (empresa_id, ativa);
CREATE UNIQUE INDEX filiais_matriz_unica ON filiais (empresa_id)
    WHERE matriz = true AND deleted_at IS NULL;

CREATE TABLE users (
    id          bigserial PRIMARY KEY,
    name        varchar(255) NOT NULL,
    email       varchar(255) NOT NULL UNIQUE,
    email_verified_at timestamp,
    password    varchar(255) NOT NULL,
    empresa_id  bigint REFERENCES empresas(id),
    filial_id   bigint REFERENCES filiais(id),
    ativo       boolean NOT NULL DEFAULT true,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp,
    remember_token varchar(100),
    created_at timestamp, updated_at timestamp
);
CREATE INDEX users_empresa_ativo ON users (empresa_id, ativo);

CREATE TABLE certificados_digitais (
    id            bigserial PRIMARY KEY,
    empresa_id    bigint NOT NULL REFERENCES empresas(id),
    filial_id     bigint NOT NULL REFERENCES filiais(id),
    apelido       varchar(80) NOT NULL,
    arquivo_path  varchar(255) NOT NULL,
    senha_encriptada text NOT NULL,
    cnpj_titular  varchar(14) NOT NULL,
    valido_de     timestamp NOT NULL,
    valido_ate    timestamp NOT NULL,
    status        varchar(20) NOT NULL DEFAULT 'ativo',
    created_at timestamp, updated_at timestamp
);
CREATE INDEX certificados_empresa_validade ON certificados_digitais (empresa_id, valido_ate);
CREATE UNIQUE INDEX certificado_ativo_unico ON certificados_digitais (filial_id)
    WHERE status = 'ativo';

CREATE TABLE sequencias_documento (
    id              bigserial PRIMARY KEY,
    empresa_id      bigint NOT NULL REFERENCES empresas(id),
    filial_id       bigint NOT NULL REFERENCES filiais(id),
    modelo          char(2) NOT NULL,
    serie           integer NOT NULL,
    proximo_numero  bigint NOT NULL DEFAULT 1,
    created_at timestamp, updated_at timestamp,
    UNIQUE (filial_id, modelo, serie)
);
