-- =====================================================
--  epimonitor — Índices para Performance
--  Execute este arquivo para melhorar performance
-- =====================================================

USE epidemio_monitor;

-- ─── Índices na tabela registros ──────────────────
ALTER TABLE registros
ADD INDEX idx_bairro_id (bairro_id),
ADD INDEX idx_criado_em (criado_em),
ADD INDEX idx_bairro_criado (bairro_id, criado_em);

-- ─── Índices na tabela registro_sintomas ─────────
ALTER TABLE registro_sintomas
ADD INDEX idx_registro_id (registro_id),
ADD INDEX idx_sintoma (sintoma),
ADD INDEX idx_registro_sintoma (registro_id, sintoma);

-- ─── Índices na tabela alertas ──────────────────
ALTER TABLE alertas
ADD INDEX idx_bairro_id (bairro_id),
ADD INDEX idx_bairro_ativo (bairro_id, ativo),
ADD INDEX idx_ativo_atualizado (ativo, atualizado);

-- ─── Índices na tabela bairros ──────────────────
ALTER TABLE bairros
ADD INDEX idx_nome (nome),
ADD INDEX idx_cidade (cidade);

-- ✅ Verificar índices criados:
-- SHOW INDEXES de registros;
-- SHOW INDEXES de registro_sintomas;
-- SHOW INDEXES de alertas;
-- SHOW INDEXES de bairros;
