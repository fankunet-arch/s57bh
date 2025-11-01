<?php
/**
 * Toptea Store - KDS Helper Shim
 * Provides necessary functions for moved KDS APIs.
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 8.1 (Full Functionality)
 */

if (!function_exists('getMaterialById')) {
    /**
     * CORE FIX: This function is now a complete copy of the original one from the HQ helper,
     * ensuring all required fields like expiry_rule_type and expiry_duration are always available.
     */
    function getMaterialById(PDO $pdo, int $id) {
        $sql = "
            SELECT 
                m.id, m.material_code, m.material_type, m.base_unit_id, m.large_unit_id, m.conversion_rate,
                m.expiry_rule_type, m.expiry_duration,
                mt_zh.material_name AS name_zh, 
                mt_es.material_name AS name_es,
                ut_base.unit_name AS base_unit_name,
                ut_large.unit_name AS large_unit_name
            FROM kds_materials m 
            LEFT JOIN kds_material_translations mt_zh ON m.id = mt_zh.material_id AND mt_zh.language_code = 'zh-CN' 
            LEFT JOIN kds_material_translations mt_es ON m.id = mt_es.material_id AND mt_es.language_code = 'es-ES'
            LEFT JOIN kds_unit_translations ut_base ON m.base_unit_id = ut_base.unit_id AND ut_base.language_code = 'zh-CN'
            LEFT JOIN kds_unit_translations ut_large ON m.large_unit_id = ut_large.unit_id AND ut_large.language_code = 'zh-CN'
            WHERE m.id = ? AND m.deleted_at IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}