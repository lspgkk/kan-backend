<?php
$pdo = new PDO('mysql:host=116.206.196.211;port=3306;dbname=lspgatensi_kan;charset=utf8mb4', 'lspgatensi_kan_admin', 'K4nGkk@Pr0j3cT!*123432');

$replacements = [' adalah', ' ialah', ' yaitu', ' yakni', ' meliputi', ' mencakup', ' antara lain', ' seperti'];

foreach (['kan_ia4b_answers', 'kan_ia6_answers'] as $table) {
    $q = $pdo->query("SELECT id, jawaban FROM {$table} WHERE jawaban LIKE '%:%'");
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    echo $table . ': ' . count($rows) . " rows with :\n";

    $stmt = $pdo->prepare("UPDATE {$table} SET jawaban = ? WHERE id = ?");
    $i = 0;
    foreach ($rows as $row) {
        $rep = $replacements[$i % count($replacements)];
        $newJawaban = str_replace(':', $rep, $row['jawaban']);
        $stmt->execute([$newJawaban, $row['id']]);
        $i++;
    }
    echo "  Updated {$i} rows\n";
}
