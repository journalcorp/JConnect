<?php
// public/api/update_incentive_rules.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'dblocal.php';

if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// รับข้อมูล JSON จาก request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit();
}

try {
    sqlsrv_begin_transaction($conn); // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล

    // อัปเดตกฎทั่วไป (incentive_rules)
    if (isset($data['rules']) && is_array($data['rules'])) {
        foreach ($data['rules'] as $rule) {
            if (isset($rule['rule_id'], $rule['rule_value'])) {
                $sql = "UPDATE incentive_rules SET rule_value = ?, updated_at = GETDATE() WHERE rule_id = ?";
                $params = [$rule['rule_value'], $rule['rule_id']];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    throw new Exception("Error updating rule: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    // อัปเดตหรือเพิ่ม Tiers (incentive_target_tiers)
    // ตรรกะนี้จะสมมติว่าถ้า tier_id มีอยู่คืออัปเดต ถ้าไม่มีคือเพิ่มใหม่
    if (isset($data['tiers']) && is_array($data['tiers'])) {
        // ดึง tier_id ที่มีอยู่จากฐานข้อมูล
        $existingTierIds = [];
        $sql_existing_tiers = "SELECT tier_id FROM incentive_target_tiers";
        $stmt_existing_tiers = sqlsrv_query($conn, $sql_existing_tiers);
        while ($row = sqlsrv_fetch_array($stmt_existing_tiers, SQLSRV_FETCH_ASSOC)) {
            $existingTierIds[] = $row['tier_id'];
        }
        sqlsrv_free_stmt($stmt_existing_tiers);

        foreach ($data['tiers'] as $tier) {
            if (isset($tier['tier_id']) && in_array($tier['tier_id'], $existingTierIds)) {
                // อัปเดต Tier ที่มีอยู่
                $sql = "UPDATE incentive_target_tiers SET tier_name = ?, min_percentage_of_target = ?, base_rate = ?, notes = ?, is_active = ?, updated_at = GETDATE() WHERE tier_id = ?";
                $params = [
                    $tier['tier_name'],
                    (float)$tier['min_percentage_of_target'],
                    (float)$tier['base_rate'],
                    $tier['notes'],
                    (int)($tier['is_active'] ?? 1),
                    $tier['tier_id']
                ];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    throw new Exception("Error updating existing tier: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($stmt);
            } else {
                // เพิ่ม Tier ใหม่
                $sql = "INSERT INTO incentive_target_tiers (tier_name, min_percentage_of_target, base_rate, notes, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, GETDATE(), GETDATE())";
                $params = [
                    $tier['tier_name'],
                    (float)$tier['min_percentage_of_target'],
                    (float)$tier['base_rate'],
                    $tier['notes'],
                    (int)($tier['is_active'] ?? 1) // default to active if not provided
                ];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    throw new Exception("Error inserting new tier: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($stmt);
            }
        }

        // ลบ Tiers ที่ไม่ได้ส่งมา (ถือว่าถูกลบจาก UI)
        $sentTierIds = array_column($data['tiers'], 'tier_id');
        $tiersToDelete = array_diff($existingTierIds, $sentTierIds);
        if (!empty($tiersToDelete)) {
            $placeholders = implode(',', array_fill(0, count($tiersToDelete), '?'));
            $sql_delete = "DELETE FROM incentive_target_tiers WHERE tier_id IN ($placeholders)";
            $stmt_delete = sqlsrv_query($conn, $sql_delete, $tiersToDelete);
            if ($stmt_delete === false) {
                throw new Exception("Error deleting tiers: " . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stmt_delete);
        }
    }

    // อัปเดตกฎ Incentive รายบิล (per_bill_rules) - New logic for per-bill rules
    if (isset($data['per_bill_rules']) && is_array($data['per_bill_rules'])) {
        foreach ($data['per_bill_rules'] as $rule) {
            if (isset($rule['rule_id'], $rule['rule_value'])) {
                // Update existing per-bill rule
                $sql = "UPDATE incentive_rules SET rule_value = ?, updated_at = GETDATE() WHERE rule_id = ?";
                $params = [$rule['rule_value'], $rule['rule_id']];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    throw new Exception("Error updating per-bill rule: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($stmt);
            } else {
                // Insert new per-bill rule (assuming rule_key and rule_name are provided for new rules)
                // This assumes rule_key and rule_name are consistent and unique for per-bill rules
                $sql = "INSERT INTO incentive_rules (rule_name, rule_key, rule_value, data_type, description, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, GETDATE(), GETDATE())";
                $params = [
                    $rule['rule_name'],
                    $rule['rule_key'],
                    $rule['rule_value'],
                    $rule['data_type'] ?? 'decimal', // Default to decimal
                    $rule['description'] ?? '',
                ];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    throw new Exception("Error inserting new per-bill rule: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($stmt);
            }
        }
    }


    sqlsrv_commit($conn); // ยืนยันการเปลี่ยนแปลง
    sqlsrv_close($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Incentive rules and tiers updated successfully.'
    ]);

} catch (Exception $e) {
    sqlsrv_rollback($conn); // ยกเลิกการเปลี่ยนแปลงหากเกิดข้อผิดพลาด
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}