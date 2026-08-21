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


-- ═════════════════════════════════════════════════════════════════════
-- Cadastros e frota
-- ═════════════════════════════════════════════════════════════════════

INSERT INTO pessoas (empresa_id, tipo, documento, razao_social, ie, ie_indicador)
VALUES (1,'J','11222333000181','Agro Vale do Rio Ltda','111222333','1');
INSERT INTO pessoas (empresa_id, tipo, documento, razao_social, ie_indicador, rntrc, tp_transp)
VALUES (1,'F','52998224725','José Carlos da Silva','9','1234567','2');

\echo '--- 9. Contribuinte (ie_indicador=1) SEM inscrição estadual deve FALHAR ---'
INSERT INTO pessoas (empresa_id, tipo, documento, razao_social, ie_indicador)
VALUES (1,'J','44555666000177','Sem IE Ltda','1');

\echo '--- 10. Indicador de IE fora de (1,2,9) deve FALHAR ---'
INSERT INTO pessoas (empresa_id, tipo, documento, razao_social, ie_indicador)
VALUES (1,'J','44555666000177','Indicador Errado Ltda','0');

\echo '--- 11. Mesmo documento na MESMA empresa deve FALHAR ---'
INSERT INTO pessoas (empresa_id, tipo, documento, razao_social, ie_indicador)
VALUES (1,'J','11222333000181','Duplicada Ltda','9');

\echo '--- 12. Mesmo documento em OUTRA empresa deve PASSAR ---'
INSERT INTO pessoas (empresa_id, tipo, documento, razao_social, ie_indicador)
VALUES (2,'J','11222333000181','Agro Vale do Rio Ltda','9');

\echo '--- 13. Papel inválido deve FALHAR ---'
INSERT INTO pessoa_papeis (pessoa_id, papel) VALUES (1,'despachante');

\echo '--- 14. Mesma pessoa acumulando papéis deve PASSAR (cliente + proprietario) ---'
INSERT INTO pessoa_papeis (pessoa_id, papel) VALUES (1,'cliente');
INSERT INTO pessoa_papeis (pessoa_id, papel) VALUES (1,'proprietario');

\echo '--- 15. O MESMO papel repetido na mesma pessoa deve FALHAR ---'
INSERT INTO pessoa_papeis (pessoa_id, papel) VALUES (1,'cliente');

INSERT INTO enderecos (pessoa_id, logradouro, numero, bairro, municipio_id, cep, principal)
VALUES (1,'Rod. BA-052','km 12','Zona Rural',1,'44050000',true);

\echo '--- 16. Segundo endereço PRINCIPAL da mesma pessoa deve FALHAR ---'
INSERT INTO enderecos (pessoa_id, tipo, logradouro, numero, bairro, municipio_id, cep, principal)
VALUES (1,'coleta','Av. Presidente Dutra','900','Centro',1,'44001000',true);

\echo '--- 17. Endereço secundário (principal=false) deve PASSAR ---'
INSERT INTO enderecos (pessoa_id, tipo, logradouro, numero, bairro, municipio_id, cep, principal)
VALUES (1,'coleta','Av. Presidente Dutra','900','Centro',1,'44001000',false);

-- Domínio do sistema (empresa_id NULL) e domínio do cliente convivendo
INSERT INTO carrocerias (empresa_id, codigo, nome, tp_car_fiscal) VALUES (NULL,'sider','Sider','05');
INSERT INTO naturezas_carga (empresa_id, codigo, nome, tp_carga_base) VALUES (NULL,'granel_solido','Granel sólido','01');
INSERT INTO cvc_configuracoes (empresa_id, nome_popular, slug, eixos) VALUES (NULL,'Bitrem','bitrem',7);

\echo '--- 18. Carroceria PADRÃO do sistema repetida deve FALHAR (índice parcial) ---'
INSERT INTO carrocerias (empresa_id, codigo, nome, tp_car_fiscal) VALUES (NULL,'sider','Sider duplicada','05');

\echo '--- 19. Cliente criar carroceria com o MESMO código do sistema deve PASSAR ---'
INSERT INTO carrocerias (empresa_id, codigo, nome, tp_car_fiscal) VALUES (1,'sider','Sider da casa','05');

-- Veículo de tração próprio
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, tp_rod, eixos, tara_kg, pbtc_kg,
                      capacidade_kg, uf_licenciamento)
VALUES (1,'PWF1A23','tracao','propria','06',3,9500,74000,45000,'BA');

\echo '--- 20. Veículo de TRAÇÃO sem tp_rod deve FALHAR ---'
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, eixos, tara_kg, uf_licenciamento)
VALUES (1,'PWF1A24','tracao','propria',3,9500,'BA');

\echo '--- 21. Reboque sem tp_rod deve PASSAR (tp_rod só vale para tração) ---'
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, eixos, tara_kg, uf_licenciamento)
VALUES (1,'PWF1A25','semirreboque','propria',3,6200,'BA');

\echo '--- 22. Veículo de TERCEIRO sem proprietário deve FALHAR ---'
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, tp_rod, eixos, tara_kg, uf_licenciamento)
VALUES (1,'PWF1A26','tracao','terceiro','06',3,9500,'BA');

\echo '--- 23. Veículo de TERCEIRO com proprietário deve PASSAR ---'
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, proprietario_id, tp_rod, eixos, tara_kg, uf_licenciamento)
VALUES (1,'PWF1A27','tracao','terceiro',2,'06',3,9500,'BA');

\echo '--- 24. Capacidade acima de (pbtc - tara) deve FALHAR ---'
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, tp_rod, eixos, tara_kg, pbtc_kg,
                      capacidade_kg, uf_licenciamento)
VALUES (1,'PWF1A28','tracao','propria','06',3,9500,74000,70000,'BA');

\echo '--- 25. Zero eixos deve FALHAR ---'
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, tp_rod, eixos, tara_kg, uf_licenciamento)
VALUES (1,'PWF1A29','tracao','propria','06',0,9500,'BA');

\echo '--- 26. Placa repetida na MESMA empresa deve FALHAR ---'
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, tp_rod, eixos, tara_kg, uf_licenciamento)
VALUES (1,'PWF1A23','tracao','propria','06',3,9500,'BA');

\echo '--- 27. Mesma placa em OUTRA empresa deve PASSAR ---'
INSERT INTO veiculos (empresa_id, placa, tipo, propriedade, tp_rod, eixos, tara_kg, uf_licenciamento)
VALUES (2,'PWF1A23','tracao','propria','06',3,9500,'BA');

INSERT INTO composicoes (empresa_id, descricao, veiculo_tracao_id, eixos_total, categ_comb_veic)
SELECT 1,'Bitrem 7 eixos — cavalo 1A23', id, 7, '11' FROM veiculos WHERE empresa_id=1 AND placa='PWF1A23';
INSERT INTO composicao_itens (composicao_id, veiculo_id, ordem)
SELECT 1, id, 1 FROM veiculos WHERE empresa_id=1 AND placa='PWF1A23';
INSERT INTO composicao_itens (composicao_id, veiculo_id, ordem)
SELECT 1, id, 2 FROM veiculos WHERE empresa_id=1 AND placa='PWF1A25';

\echo '--- 28. Mesmo veículo duas vezes na composição deve FALHAR ---'
INSERT INTO composicao_itens (composicao_id, veiculo_id, ordem)
SELECT 1, id, 3 FROM veiculos WHERE empresa_id=1 AND placa='PWF1A25';

\echo '--- 29. Duas unidades na MESMA ordem deve FALHAR ---'
INSERT INTO composicao_itens (composicao_id, veiculo_id, ordem)
SELECT 1, id, 2 FROM veiculos WHERE empresa_id=1 AND placa='PWF1A27';

INSERT INTO motoristas (empresa_id, pessoa_id, cnh_numero, cnh_categoria, cnh_validade, vinculo)
VALUES (1,2,'04512378900','E','2029-06-30','agregado');

\echo '--- 30. Vínculo fora do domínio deve FALHAR ---'
INSERT INTO motoristas (empresa_id, pessoa_id, cnh_numero, cnh_categoria, cnh_validade, vinculo)
VALUES (1,1,'04512378901','E','2029-06-30','pj');

\echo '--- 31. Mesma pessoa cadastrada duas vezes como motorista deve FALHAR ---'
INSERT INTO motoristas (empresa_id, pessoa_id, cnh_numero, cnh_categoria, cnh_validade, vinculo)
VALUES (1,2,'04512378902','E','2030-06-30','clt');

INSERT INTO contratos_agregacao (empresa_id, pessoa_id, veiculo_id, numero, inicio, modalidade_remuneracao)
SELECT 1, 2, id, 'AGR-2026-001','2026-03-01','percentual_frete' FROM veiculos WHERE empresa_id=1 AND placa='PWF1A27';

\echo '--- 32. Número de contrato repetido na MESMA empresa deve FALHAR ---'
INSERT INTO contratos_agregacao (empresa_id, pessoa_id, numero, inicio, modalidade_remuneracao)
VALUES (1,2,'AGR-2026-001','2026-04-01','percentual_frete');

\echo '--- Resultado final ---'
SELECT (SELECT count(*) FROM filiais) AS filiais,
       (SELECT count(*) FROM certificados_digitais) AS certificados,
       (SELECT count(*) FROM sequencias_documento) AS sequencias,
       (SELECT count(*) FROM pessoas) AS pessoas,
       (SELECT count(*) FROM pessoa_papeis) AS papeis,
       (SELECT count(*) FROM enderecos) AS enderecos,
       (SELECT count(*) FROM carrocerias) AS carrocerias,
       (SELECT count(*) FROM veiculos) AS veiculos,
       (SELECT count(*) FROM composicao_itens) AS comp_itens,
       (SELECT count(*) FROM motoristas) AS motoristas,
       (SELECT count(*) FROM contratos_agregacao) AS contratos;
