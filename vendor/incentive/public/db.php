<?php
$serverName = "journal.ada-soft.com";
$connectionOptions = array(
    "Database" => "AdaAccPos5.0Journal_Repl",
    "Uid" => "adminjournal",
    "PWD" => "Journal@25",
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

// if ($conn) {
//     echo "เชื่อมต่อ SQL Server สำเร็จ!";
// } else {
//     echo "เชื่อมต่อไม่สำเร็จ:<br>";
//     die(print_r(sqlsrv_errors(), true));
// }
?>
