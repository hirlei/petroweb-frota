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

-- ─────────────────────────────────────────────────────────────────────
-- 010350  pessoas, pessoa_papeis, enderecos, contatos
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE pessoas (
    id            bigserial PRIMARY KEY,
    empresa_id    bigint NOT NULL REFERENCES empresas(id),
    tipo          char(1) NOT NULL,
    documento     varchar(20) NOT NULL,
    documento_estrangeiro varchar(20),
    razao_social  varchar(150) NOT NULL,
    nome_fantasia varchar(150),
    ie            varchar(20),
    ie_indicador  char(1) NOT NULL,
    im            varchar(20),
    rntrc         varchar(10),
    rntrc_validade date,
    tp_transp     char(1),
    suframa       varchar(9),
    cnae          varchar(7),
    crt           char(1),
    email         varchar(150),
    telefone      varchar(20),
    observacoes   text,
    ativo         boolean NOT NULL DEFAULT true,
    created_at timestamp, updated_at timestamp, deleted_at timestamp,
    UNIQUE (empresa_id, documento),
    CONSTRAINT pessoas_tipo_valido         CHECK (tipo IN ('F','J','E')),
    CONSTRAINT pessoas_ie_indicador_valido CHECK (ie_indicador IN ('1','2','9')),
    CONSTRAINT pessoas_contribuinte_tem_ie CHECK (ie_indicador <> '1' OR ie IS NOT NULL)
);
CREATE INDEX pessoas_empresa_ativo ON pessoas (empresa_id, ativo);
CREATE INDEX pessoas_empresa_razao ON pessoas (empresa_id, razao_social);

CREATE TABLE pessoa_papeis (
    id         bigserial PRIMARY KEY,
    pessoa_id  bigint NOT NULL REFERENCES pessoas(id) ON DELETE CASCADE,
    papel      varchar(20) NOT NULL,
    dados      jsonb,
    ativo      boolean NOT NULL DEFAULT true,
    created_at timestamp, updated_at timestamp,
    UNIQUE (pessoa_id, papel),
    CONSTRAINT pessoa_papeis_papel_valido CHECK (papel IN
        ('cliente','fornecedor','motorista','proprietario','oficina','seguradora','posto'))
);

CREATE TABLE enderecos (
    id           bigserial PRIMARY KEY,
    pessoa_id    bigint NOT NULL REFERENCES pessoas(id) ON DELETE CASCADE,
    tipo         varchar(20) NOT NULL DEFAULT 'principal',
    logradouro   varchar(150) NOT NULL,
    numero       varchar(20),
    complemento  varchar(60),
    bairro       varchar(80),
    municipio_id bigint NOT NULL REFERENCES municipios(id),
    cep          char(8),
    latitude     numeric(10,7),
    longitude    numeric(10,7),
    principal    boolean NOT NULL DEFAULT false,
    created_at timestamp, updated_at timestamp
);
CREATE INDEX enderecos_pessoa_tipo ON enderecos (pessoa_id, tipo);
CREATE UNIQUE INDEX enderecos_principal_unico ON enderecos (pessoa_id) WHERE principal = true;

CREATE TABLE contatos (
    id         bigserial PRIMARY KEY,
    pessoa_id  bigint NOT NULL REFERENCES pessoas(id) ON DELETE CASCADE,
    nome       varchar(120) NOT NULL,
    cargo      varchar(60),
    email      varchar(150),
    telefone   varchar(20),
    setor      varchar(40),
    recebe_dfe boolean NOT NULL DEFAULT false,
    created_at timestamp, updated_at timestamp
);
CREATE INDEX contatos_pessoa ON contatos (pessoa_id);

-- ─────────────────────────────────────────────────────────────────────
-- 010400  tabelas de domínio da frota
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE naturezas_carga (
    id             bigserial PRIMARY KEY,
    empresa_id     bigint REFERENCES empresas(id),
    codigo         varchar(40) NOT NULL,
    nome           varchar(80) NOT NULL,
    tp_carga_base  varchar(2) NOT NULL,
    permite_perigosa   boolean NOT NULL DEFAULT true,
    exige_temperatura  boolean NOT NULL DEFAULT false,
    exige_aet      boolean NOT NULL DEFAULT false,
    exige_gta      boolean NOT NULL DEFAULT false,
    ativo          boolean NOT NULL DEFAULT true,
    created_at timestamp, updated_at timestamp,
    UNIQUE (empresa_id, codigo)
);
CREATE UNIQUE INDEX naturezas_carga_sistema_unica ON naturezas_carga (codigo) WHERE empresa_id IS NULL;

CREATE TABLE carrocerias (
    id             bigserial PRIMARY KEY,
    empresa_id     bigint REFERENCES empresas(id),
    codigo         varchar(40) NOT NULL,
    nome           varchar(80) NOT NULL,
    tp_car_fiscal  char(2) NOT NULL,
    natureza_padrao varchar(40),
    exige_temperatura_controlada boolean NOT NULL DEFAULT false,
    exige_civ_cipp boolean NOT NULL DEFAULT false,
    exige_certificacao_inmetro boolean NOT NULL DEFAULT false,
    aceita_produto_perigoso boolean NOT NULL DEFAULT true,
    permite_conteiner boolean NOT NULL DEFAULT false,
    capacidade_m3_referencia numeric(10,2),
    ativo          boolean NOT NULL DEFAULT true,
    created_at timestamp, updated_at timestamp,
    UNIQUE (empresa_id, codigo)
);
CREATE UNIQUE INDEX carrocerias_sistema_unica ON carrocerias (codigo) WHERE empresa_id IS NULL;

CREATE TABLE cvc_configuracoes (
    id             bigserial PRIMARY KEY,
    empresa_id     bigint REFERENCES empresas(id),
    nome_popular   varchar(60) NOT NULL,
    slug           varchar(60) NOT NULL,
    eixos          smallint NOT NULL,
    qtd_unidades   smallint NOT NULL DEFAULT 1,
    tracao         varchar(5),
    pbtc_kg        numeric(15,4),
    comprimento_max_m numeric(8,3),
    capacidade_min_kg numeric(15,4),
    capacidade_max_kg numeric(15,4),
    exige_aet      boolean NOT NULL DEFAULT false,
    tp_rod_default char(2),
    cnh_minima     char(1),
    ativo          boolean NOT NULL DEFAULT true,
    created_at timestamp, updated_at timestamp,
    UNIQUE (empresa_id, slug)
);
CREATE UNIQUE INDEX cvc_configuracoes_sistema_unica ON cvc_configuracoes (slug) WHERE empresa_id IS NULL;

-- ─────────────────────────────────────────────────────────────────────
-- 010500  frota
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE veiculos (
    id             bigserial PRIMARY KEY,
    empresa_id     bigint NOT NULL REFERENCES empresas(id),
    filial_id      bigint REFERENCES filiais(id),
    placa          varchar(7) NOT NULL,
    renavam        varchar(11),
    chassi         varchar(17),
    tipo           varchar(20) NOT NULL,
    marca          varchar(60),
    modelo         varchar(60),
    ano_fabricacao smallint,
    ano_modelo     smallint,
    cor            varchar(20),
    propriedade    varchar(20) NOT NULL DEFAULT 'propria',
    proprietario_id bigint REFERENCES pessoas(id),
    proprietario_rntrc varchar(10),
    proprietario_tp_transp char(1),
    contrato_agregacao_id bigint,
    tp_rod         char(2),
    tp_car         char(2),
    carroceria_id  bigint REFERENCES carrocerias(id),
    cvc_configuracao_id bigint REFERENCES cvc_configuracoes(id),
    eixos          smallint NOT NULL,
    tracao         varchar(5),
    tara_kg        numeric(15,4) NOT NULL,
    pbt_kg         numeric(15,4),
    pbtc_kg        numeric(15,4),
    capacidade_kg  numeric(15,4),
    capacidade_m3  numeric(15,4),
    comprimento_m  numeric(8,3),
    largura_m      numeric(8,3),
    altura_m       numeric(8,3),
    qtd_compartimentos smallint,
    compartimentos jsonb,
    exige_aet      boolean NOT NULL DEFAULT false,
    uf_licenciamento char(2) NOT NULL,
    municipio_licenciamento_id bigint REFERENCES municipios(id),
    combustivel    varchar(20),
    capacidade_tanque_l numeric(10,2),
    media_referencia_kml numeric(8,3),
    odometro_atual numeric(12,2) NOT NULL DEFAULT 0,
    horimetro_atual numeric(12,2),
    custo_km_alvo  numeric(12,4),
    rastreador_id  varchar(50),
    status         varchar(20) NOT NULL DEFAULT 'ativo',
    created_at timestamp, updated_at timestamp, deleted_at timestamp,
    UNIQUE (empresa_id, placa),
    CONSTRAINT veiculos_eixos_min CHECK (eixos >= 1),
    CONSTRAINT veiculos_tracao_tem_rodado CHECK (tipo <> 'tracao' OR tp_rod IS NOT NULL),
    CONSTRAINT veiculos_terceiro_tem_proprietario CHECK (propriedade = 'propria' OR proprietario_id IS NOT NULL),
    CONSTRAINT veiculos_capacidade_coerente CHECK (capacidade_kg IS NULL OR pbtc_kg IS NULL
        OR capacidade_kg <= pbtc_kg - tara_kg)
);
CREATE INDEX veiculos_empresa_status_tipo ON veiculos (empresa_id, status, tipo);

CREATE TABLE composicoes (
    id             bigserial PRIMARY KEY,
    empresa_id     bigint NOT NULL REFERENCES empresas(id),
    descricao      varchar(120) NOT NULL,
    veiculo_tracao_id bigint NOT NULL REFERENCES veiculos(id),
    eixos_total    smallint,
    tara_total_kg  numeric(15,4),
    pbtc_kg        numeric(15,4),
    capacidade_kg  numeric(15,4),
    categ_comb_veic char(2),
    exige_aet      boolean NOT NULL DEFAULT false,
    ativa          boolean NOT NULL DEFAULT true,
    created_at timestamp, updated_at timestamp
);
CREATE INDEX composicoes_empresa_ativa ON composicoes (empresa_id, ativa);

CREATE TABLE composicao_itens (
    id            bigserial PRIMARY KEY,
    composicao_id bigint NOT NULL REFERENCES composicoes(id) ON DELETE CASCADE,
    veiculo_id    bigint NOT NULL REFERENCES veiculos(id),
    ordem         smallint NOT NULL,
    UNIQUE (composicao_id, veiculo_id),
    UNIQUE (composicao_id, ordem)
);

CREATE TABLE motoristas (
    id             bigserial PRIMARY KEY,
    empresa_id     bigint NOT NULL REFERENCES empresas(id),
    pessoa_id      bigint NOT NULL REFERENCES pessoas(id),
    cnh_numero     varchar(11) NOT NULL,
    cnh_categoria  varchar(5) NOT NULL,
    cnh_validade   date NOT NULL,
    cnh_primeira_habilitacao date,
    cnh_ear        boolean NOT NULL DEFAULT true,
    vinculo        varchar(20) NOT NULL,
    tp_transp      char(1),
    rntrc          varchar(10),
    rntrc_validade date,
    contrato_agregacao_id bigint,
    admissao       date,
    demissao       date,
    toxicologico_data date,
    toxicologico_validade date,
    mopp_validade  date,
    curso_carga_indivisivel_validade date,
    valor_diaria   numeric(15,2),
    percentual_comissao numeric(7,4),
    valor_por_km   numeric(12,4),
    conta_pagamento jsonb,
    status         varchar(20) NOT NULL DEFAULT 'ativo',
    created_at timestamp, updated_at timestamp, deleted_at timestamp,
    UNIQUE (empresa_id, pessoa_id),
    CONSTRAINT motoristas_vinculo_valido CHECK (vinculo IN ('clt','agregado','autonomo','terceiro'))
);
CREATE INDEX motoristas_empresa_status_vinculo ON motoristas (empresa_id, status, vinculo);
CREATE INDEX motoristas_empresa_cnh_validade ON motoristas (empresa_id, cnh_validade);

CREATE TABLE contratos_agregacao (
    id             bigserial PRIMARY KEY,
    empresa_id     bigint NOT NULL REFERENCES empresas(id),
    pessoa_id      bigint NOT NULL REFERENCES pessoas(id),
    veiculo_id     bigint REFERENCES veiculos(id),
    numero         varchar(30) NOT NULL,
    inicio         date NOT NULL,
    fim            date,
    exclusividade  boolean NOT NULL DEFAULT true,
    modalidade_remuneracao varchar(30) NOT NULL,
    valor_base     numeric(15,2),
    percentual     numeric(7,4),
    rntrc          varchar(10),
    apolice_rctrc  varchar(40),
    apolice_rcdc   varchar(40),
    apolice_rcv    varchar(40),
    validade_apolices date,
    conta_pagamento jsonb,
    arquivo_contrato_path varchar(255),
    status         varchar(20) NOT NULL DEFAULT 'vigente',
    created_at timestamp, updated_at timestamp,
    UNIQUE (empresa_id, numero)
);
CREATE INDEX contratos_agregacao_empresa_status ON contratos_agregacao (empresa_id, status);

CREATE TABLE veiculo_documentos (
    id               bigserial PRIMARY KEY,
    empresa_id       bigint NOT NULL REFERENCES empresas(id),
    documentavel_type varchar(255) NOT NULL,
    documentavel_id  bigint NOT NULL,
    tipo             varchar(40) NOT NULL,
    numero           varchar(40),
    emissao          date,
    vencimento       date NOT NULL,
    valor            numeric(15,2),
    arquivo_path     varchar(255),
    bloqueia_operacao boolean NOT NULL DEFAULT true,
    observacoes      text,
    created_at timestamp, updated_at timestamp
);
CREATE INDEX veiculo_documentos_morph ON veiculo_documentos (documentavel_type, documentavel_id);
CREATE INDEX veiculo_documentos_painel ON veiculo_documentos (empresa_id, vencimento, bloqueia_operacao);
