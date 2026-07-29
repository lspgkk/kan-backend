<?php
$pdo = new PDO('mysql:host=116.206.196.211;port=3306;dbname=lspgatensi_kan;charset=utf8mb4', 'lspgatensi_kan_admin', 'K4nGkk@Pr0j3cT!*123432');

foreach (['kan_ia4b_answers', 'kan_ia6_answers'] as $table) {
    // Replace "1)", "2)", "3)", "4)" etc with comma
    $q = $pdo->prepare("UPDATE {$table} SET jawaban = REGEXP_REPLACE(jawaban, '[0-9]+[\)]', ',') WHERE jawaban REGEXP '[0-9]+[\)]'");
    $q->execute();
    echo $table . ': updated ' . $q->rowCount() . " rows\n";
}

// Clean up double commas
foreach (['kan_ia4b_answers', 'kan_ia6_answers'] as $table) {
    $pdo->exec("UPDATE {$table} SET jawaban = REGEXP_REPLACE(jawaban, ', ,', ',') WHERE jawaban LIKE '%, ,%'");
    $pdo->exec("UPDATE {$table} SET jawaban = REGEXP_REPLACE(jawaban, ',,', ',') WHERE jawaban LIKE '%,,%'");
    // Clean leading/trailing comma+space
    $pdo->exec("UPDATE {$table} SET jawaban = TRIM(LEADING ', ' FROM TRIM(LEADING ',' FROM jawaban)) WHERE jawaban LIKE ',%'");
    $pdo->exec("UPDATE {$table} SET jawaban = TRIM(TRAILING ', ' FROM TRIM(TRAILING ',' FROM jawaban)) WHERE jawaban LIKE '%,'");
    $pdo->exec("UPDATE {$table} SET jawaban = REGEXP_REPLACE(jawaban, '^, ', '') WHERE jawaban REGEXP '^, '");
    echo $table . ': cleaned up commas' . "\n";
}
