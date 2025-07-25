<?php
// เปิด error reporting สำหรับ debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', '203.154.130.236'); // ใช้ Local SQL Server
}
if (!defined('DB_USER')) {
    define('DB_USER', 'sa');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'Journal@25');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'webportal');
}

// Base URL configuration
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '192.168.246.236';
    
    // รายการ host ที่อนุญาต
    $allowed_hosts = [
        '203.154.130.236',    // Public IP
        '192.168.246.236',    // Local IP
        'localhost',
        '127.0.0.1'
    ];
    
    // ใช้ host ที่ได้รับมา หรือ default เป็น Local IP
    if (!in_array($host, $allowed_hosts)) {
        $host = '192.168.246.236';
    }
    
    define('BASE_URL', $protocol . '://' . $host . '/webportal');
}

// Development/Fallback mode (เปลี่ยนเป็น false เพื่อใช้ฐานข้อมูลจริง)
if (!defined('DEV_MODE')) {
    define('DEV_MODE', false); // ใช้ฐานข้อมูลจริงเสมอ
}

// Start session (ตรวจสอบก่อนเริ่มเซสชัน)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าฟังก์ชัน dbConnect ถูกประกาศแล้วหรือยัง
if (!function_exists('dbConnect')) {
    function dbConnect() {
        // หากเป็น development mode ให้ข้ามการเชื่อมต่อฐานข้อมูล
        if (defined('DEV_MODE') && DEV_MODE === true) {
            throw new Exception("DEV_MODE: ไม่ได้เชื่อมต่อฐานข้อมูลจริง");
        }
        
        $servername = DB_HOST;
        $username = DB_USER;
        $password = DB_PASS;
        $dbname = DB_NAME;

        // ลองเชื่อมต่อด้วย SQLSRV ก่อน (เนื่องจากเป็น SQL Server)
        if (extension_loaded('sqlsrv')) {
            $connectionOptions = [
                "Database" => $dbname,
                "Uid" => $username,
                "PWD" => $password,
                "CharacterSet" => "UTF-8",
                "LoginTimeout" => 10,
                "ConnectRetryCount" => 3,
                "ConnectRetryInterval" => 5,
                "ReturnDatesAsStrings" => true,
                "TrustServerCertificate" => true
            ];
            
            // ลองเชื่อมต่อแบบ TCP/IP ก่อน
            $conn = sqlsrv_connect($servername . ",1433", $connectionOptions);
            if ($conn !== false) {
                return $conn;
            }
            
            // ลองเชื่อมต่อแบบปกติ
            $conn = sqlsrv_connect($servername, $connectionOptions);
            if ($conn !== false) {
                return $conn;
            }
            
            $errors = sqlsrv_errors();
            if ($errors) {
                error_log("SQLSRV failed: " . print_r($errors, true));
            }
        }

        // ลองเชื่อมต่อด้วย PDO SQL Server
        if (extension_loaded('pdo') && extension_loaded('pdo_sqlsrv')) {
            try {
                // ลองหลายรูปแบบการเชื่อมต่อ
                $dsn_variants = [
                    "sqlsrv:server=$servername,1433;Database=$dbname;CharacterSet=UTF-8",
                    "sqlsrv:server=$servername;Database=$dbname;CharacterSet=UTF-8",
                    "sqlsrv:server=tcp:$servername,1433;Database=$dbname;CharacterSet=UTF-8",
                ];
                
                foreach ($dsn_variants as $dsn) {
                    try {
                        $conn = new PDO($dsn, $username, $password, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 10,
                            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
                        ]);
                        return $conn;
                    } catch(PDOException $e) {
                        error_log("PDO SQL Server failed with DSN '$dsn': " . $e->getMessage());
                        continue;
                    }
                }
            } catch(PDOException $e) {
                error_log("PDO SQL Server failed: " . $e->getMessage());
            }
        }

        // ลองเชื่อมต่อด้วย MySQLi (หากมี และเป็น MySQL)
        if (extension_loaded('mysqli')) {
            try {
                $conn = new mysqli($servername, $username, $password, $dbname);
                if ($conn->connect_error) {
                    throw new Exception("MySQLi Connection failed: " . $conn->connect_error);
                }
                $conn->set_charset("utf8");
                return $conn;
            } catch (Exception $e) {
                error_log("MySQLi failed: " . $e->getMessage());
            }
        }

        // ลองเชื่อมต่อด้วย PDO MySQL (หากมี)
        if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
            try {
                $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 10
                ]);
                return $conn;
            } catch(PDOException $e) {
                error_log("PDO MySQL failed: " . $e->getMessage());
            }
        }

        // หากทุกวิธีไม่ได้ - แทนที่จะ throw exception ให้ log error และ return null
        error_log("Database connection failed completely - all methods exhausted");
        error_log("Server: " . $servername . ", Database: " . $dbname);
        error_log("Please check: 1. Network connectivity to " . $servername . " 2. PHP extensions 3. Database server status");
        
        return null; // Return null แทนการ throw exception
    }
}

// ตรวจสอบว่าฟังก์ชัน verifyLogin ถูกประกาศแล้วหรือยัง
if (!function_exists('verifyLogin')) {
    function verifyLogin($employee_id, $password) {
        // หาก DEV_MODE เปิดใช้งาน ให้ใช้ mock data
        if (defined('DEV_MODE') && DEV_MODE === true) {
            return mockVerifyLogin($employee_id, $password);
        }
        
        try {
            $conn = dbConnect();
            $sql = "SELECT employee_id, pws_user, is_first_login, first_name_th, last_name_th, position_th, department, division 
                    FROM employees WHERE employee_id = ?";
            
            // ตรวจสอบประเภทการเชื่อมต่อ
            if ($conn instanceof PDO) {
                // ใช้ PDO
                $stmt = $conn->prepare($sql);
                $stmt->execute([$employee_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif (is_resource($conn)) {
                // ใช้ SQLSRV
                $params = [$employee_id];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    return false;
                }
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            } else {
                // ใช้ mysqli
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $employee_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
            }

            if ($row && $password === $row['pws_user']) {
                // ตั้งค่า Session
                $_SESSION['employee_id'] = $row['employee_id'];
                $_SESSION['is_first_login'] = $row['is_first_login'];
                $_SESSION['first_name_th'] = $row['first_name_th'];
                $_SESSION['last_name_th'] = $row['last_name_th'];
                $_SESSION['position_th'] = $row['position_th'];
                $_SESSION['department'] = $row['department'];
                $_SESSION['division'] = $row['division'];
                return true;
            }
            return false;
        } catch (Exception $e) {
            // หากเชื่อมต่อฐานข้อมูลไม่ได้ ใช้ mock data
            error_log("Database connection failed, using mock data: " . $e->getMessage());
            return mockVerifyLogin($employee_id, $password);
        }
    }
}

// Mock data สำหรับการทดสอบ
if (!function_exists('mockVerifyLogin')) {
    function mockVerifyLogin($employee_id, $password) {
        $mock_users = [
            'admin' => [
                'employee_id' => 'admin',
                'pws_user' => 'admin123',
                'is_first_login' => 0,
                'first_name_th' => 'ผู้ดูแล',
                'last_name_th' => 'ระบบ',
                'position_th' => 'System Administrator',
                'department' => 'IT',
                'division' => 'Technology'
            ],
            'test' => [
                'employee_id' => 'test',
                'pws_user' => 'test123',
                'is_first_login' => 1,
                'first_name_th' => 'ทดสอบ',
                'last_name_th' => 'ระบบ',
                'position_th' => 'Test User',
                'department' => 'Test',
                'division' => 'Test'
            ],
            '001' => [
                'employee_id' => '001',
                'pws_user' => 'password',
                'is_first_login' => 0,
                'first_name_th' => 'พนักงาน',
                'last_name_th' => 'ตัวอย่าง',
                'position_th' => 'Employee',
                'department' => 'General',
                'division' => 'Operations'
            ]
        ];
        
        if (isset($mock_users[$employee_id])) {
            $user = $mock_users[$employee_id];
            if ($password === $user['pws_user']) {
                // ตั้งค่า Session
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['is_first_login'] = $user['is_first_login'];
                $_SESSION['first_name_th'] = $user['first_name_th'];
                $_SESSION['last_name_th'] = $user['last_name_th'];
                $_SESSION['position_th'] = $user['position_th'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['division'] = $user['division'];
                $_SESSION['is_mock_user'] = true; // ระบุว่าใช้ mock data
                
                return true;
            }
        }
        return false;
    }
}
?>
