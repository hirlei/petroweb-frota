-- Testes das regras que o schema deve garantir sozinho, sem aplicação.
\set ON_ERROR_STOP off
\pset pager off

INSERT INTO municipios (codigo_ibge, nome, uf) VALUES ('2910800','Feira de Santana','BA'),('5208707','Goiânia','GO');
INSERT INTO empresas (razao_social, cnpj) VALUES ('Transportes Serra Azul Ltda','12345678000190');
INSERT INTO empresas (razao_social, cnpj) VALUES ('Outra Transportadora Ltda','98765432000110');

INSERT INTO filiais (empresa_id, codigo, razao_social, cnpj, ie, logradouro, numero, bairro, municipio_id, cep, matriz)
VALUES (1,'FIL-01','Serra Azul Matriz','12345678000190','103456789','Av. Getúlio Vargas','1200','Centro',1,'44001000',true);

\echo '--- 1. Segunda matriz na MESMA empresa deve FALHAR ---'
INSERT INTO filiais (empresa_id, codigo, razao_social, cnpj, ie, logradouro, numero, bairro, municipio_id, cep, matriz)
VALUES (1,'FIL-02','Serra Azul Filial','12345678000270','103456790','Rua B','50','Centro',1,'44001001',true);

\echo '--- 2. Filial NÃO matriz na mesma empresa deve PASSAR ---'
INSERT INTO filiais (empresa_id, codigo, razao_social, cnpj, ie, logradouro, numero, bairro, municipio_id, cep, matriz)
VALUES (1,'FIL-02','Serra Azul Filial','12345678000270','103456790','Rua B','50','Centro',1,'44001001',false);

\echo '--- 3. Matriz em OUTRA empresa deve PASSAR (unicidade é por empresa) ---'
INSERT INTO filiais (empresa_id, codigo, razao_social, cnpj, ie, logradouro, numero, bairro, municipio_id, cep, matriz)
VALUES (2,'FIL-01','Outra Matriz','98765432000110','203456789','Rua C','10','Centro',2,'74000000',true);

\echo '--- 4. Mesmo CNPJ de filial repetido na MESMA empresa deve FALHAR ---'
INSERT INTO filiais (empresa_id, codigo, razao_social, cnpj, ie, logradouro, numero, bairro, municipio_id, cep, matriz)
VALUES (1,'FIL-03','Duplicada','12345678000190','103456791','Rua D','5','Centro',1,'44001002',false);

\echo '--- 5. Dois certificados ATIVOS na mesma filial deve FALHAR ---'
INSERT INTO certificados_digitais (empresa_id, filial_id, apelido, arquivo_path, senha_encriptada, cnpj_titular, valido_de, valido_ate, status)
VALUES (1,1,'A1 2026','empresa/1/cert/a.pfx','enc','12345678000190','2026-02-18','2027-02-18','ativo');
INSERT INTO certificados_digitais (empresa_id, filial_id, apelido, arquivo_path, senha_encriptada, cnpj_titular, valido_de, valido_ate, status)
VALUES (1,1,'A1 2027','empresa/1/cert/b.pfx','enc','12345678000190','2027-02-18','2028-02-18','ativo');

\echo '--- 6. Um ativo e um vencido na mesma filial deve PASSAR ---'
INSERT INTO certificados_digitais (empresa_id, filial_id, apelido, arquivo_path, senha_encriptada, cnpj_titular, valido_de, valido_ate, status)
VALUES (1,1,'A1 antigo','empresa/1/cert/old.pfx','enc','12345678000190','2025-02-18','2026-02-18','vencido');

\echo '--- 7. Numeração duplicada (filial+modelo+série) deve FALHAR ---'
INSERT INTO sequencias_documento (empresa_id, filial_id, modelo, serie) VALUES (1,1,'57',1);
INSERT INTO sequencias_documento (empresa_id, filial_id, modelo, serie) VALUES (1,1,'57',1);

\echo '--- 8. Mesma série em modelo diferente deve PASSAR ---'
INSERT INTO sequencias_documento (empresa_id, filial_id, modelo, serie) VALUES (1,1,'58',1);

\echo '--- Resultado final ---'
SELECT (SELECT count(*) FROM filiais) AS filiais,
       (SELECT count(*) FROM certificados_digitais) AS certificados,
       (SELECT count(*) FROM sequencias_documento) AS sequencias;
