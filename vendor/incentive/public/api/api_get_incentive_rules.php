<?php // Make sure there is NO whitespace or comments before this tag

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

try {
    $rules = [];
    $tiers = [];
    $perBillRules = []; // New array for per-bill rules

    // ดึงกฎทั่วไปจาก incentive_rules
    $sql_rules = "SELECT rule_id, rule_name, rule_key, rule_value, data_type, description, is_active FROM incentive_rules WHERE is_active = 1 ORDER BY rule_id";
    $stmt_rules = sqlsrv_query($conn, $sql_rules);
    if ($stmt_rules === false) {
        throw new Exception("SQL query for rules failed: " . print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmt_rules, SQLSRV_FETCH_ASSOC)) {
        // แปลงค่าตาม data_type
        if ($row['data_type'] === 'decimal' || $row['data_type'] === 'integer') {
            $row['rule_value'] = (float)$row['rule_value'];
        }

        // Separate per-bill rules from general rules
        if (strpos($row['rule_key'], 'per_bill_') === 0) {
            $perBillRules[] = $row;
        } else {
            $rules[] = $row;
        }
    }
    sqlsrv_free_stmt($stmt_rules);

    // ดึง Tiers สำหรับเป้าหมายจาก incentive_target_tiers
    $sql_tiers = "SELECT tier_id, tier_name, min_percentage_of_target, base_rate, notes, is_active FROM incentive_target_tiers WHERE is_active = 1 ORDER BY min_percentage_of_target DESC";
    $stmt_tiers = sqlsrv_query($conn, $sql_tiers);
    if ($stmt_tiers === false) {
        throw new Exception("SQL query for tiers failed: " . print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmt_tiers, SQLSRV_FETCH_ASSOC)) {
        $row['min_percentage_of_target'] = (float)$row['min_percentage_of_target'];
        $row['base_rate'] = (float)$row['base_rate'];
        $tiers[] = $row;
    }
    sqlsrv_free_stmt($stmt_tiers);

    sqlsrv_close($conn);

    echo json_encode([
        'success' => true,
        'rules' => $rules,
        'tiers' => $tiers,
        'per_bill_rules' => $perBillRules // Include per-bill rules in the response
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn !== false) {
        sqlsrv_close($conn);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}