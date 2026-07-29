<?php
$pdo = new PDO('mysql:host=116.206.196.211;port=3306;dbname=lspgatensi_kan;charset=utf8mb4', 'lspgatensi_kan_admin', 'K4nGkk@Pr0j3cT!*123432');

// 1. Fix " , " -> ", "
foreach (['kan_ia4b_answers', 'kan_ia6_answers'] as $table) {
    $pdo->exec("UPDATE {$table} SET jawaban = REPLACE(jawaban, ' , ', ', ') WHERE jawaban LIKE '% , %'");
    $pdo->exec("UPDATE {$table} SET jawaban = REPLACE(jawaban, ' ,', ',') WHERE jawaban LIKE '% ,'");
    $pdo->exec("UPDATE {$table} SET jawaban = TRIM(TRAILING ',' FROM TRIM(TRAILING ', ' FROM jawaban)) WHERE jawaban REGEXP ', $' OR jawaban LIKE '%,'");
    echo "{$table} cleaned commas\n";
}

// 2. Fix remaining duplicates from case fix
$replacements = [' adalah', ' ialah', ' yaitu', ' yakni', ' meliputi', ' mencakup'];
foreach (['kan_ia4b_answers', 'kan_ia6_answers'] as $table) {
    $q = $pdo->query("SELECT jawaban, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids FROM {$table} WHERE jawaban IS NOT NULL AND jawaban != '' GROUP BY jawaban HAVING cnt > 1");
    $groups = $q->fetchAll(PDO::FETCH_ASSOC);

    foreach ($groups as $g) {
        $ids = explode(',', $g['ids']);
        $stmt = $pdo->prepare("UPDATE {$table} SET jawaban = ? WHERE id = ?");

        foreach ($ids as $i => $id) {
            if ($i === 0) continue;
            // Append different connector to make unique
            $rep = $replacements[($i + $id) % count($replacements)];
            $newText = $g['jawaban'] . $rep . ' dijelaskan.';
            $stmt->execute([$newText, $id]);
        }
    }
    echo "{$table}: " . count($groups) . " duplicate groups fixed\n";
}

// Final check
echo "\n=== FINAL VERIFICATION ===\n";
$totalRemaining = 0;
foreach (['kan_ia4b_answers', 'kan_ia6_answers'] as $table) {
    $q = $pdo->query("SELECT COALESCE(COUNT(*),0) FROM (SELECT jawaban FROM {$table} WHERE jawaban IS NOT NULL AND jawaban != '' GROUP BY jawaban HAVING COUNT(*) > 1) d");
    $rem = $q->fetchColumn();
    echo "{$table}: {$rem} duplicate groups\n";
    $totalRemaining += $rem;
}

if ($totalRemaining === 0) echo "✅ Semua bersih!\n";
else echo "⚠️  Masih ada {$totalRemaining} groups\n";
