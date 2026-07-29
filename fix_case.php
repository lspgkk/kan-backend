<?php
$pdo = new PDO('mysql:host=116.206.196.211;port=3306;dbname=lspgatensi_kan;charset=utf8mb4', 'lspgatensi_kan_admin', 'K4nGkk@Pr0j3cT!*123432');

function fixWordCase(string $text): string {
    // Split by word boundaries, preserve spaces/punctuation
    return preg_replace_callback('/\p{L}+/u', function($m) {
        $word = $m[0];
        // If word is ALL CAPS (acronym like K3, SOP, KUK), leave it
        if (mb_strtoupper($word) === $word) return $word;
        // Otherwise, lowercase the entire word
        // then uppercase first letter
        $word = mb_strtolower($word);
        return mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
    }, $text);
}

foreach (['kan_ia4b_answers', 'kan_ia6_answers'] as $table) {
    echo $table . "...\n";
    $stmt = $pdo->prepare("UPDATE {$table} SET jawaban = ? WHERE id = ?");
    $q = $pdo->query("SELECT id, jawaban FROM {$table} WHERE jawaban IS NOT NULL AND jawaban != ''");

    $fixed = 0;
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $newText = fixWordCase($row['jawaban']);
        if ($newText !== $row['jawaban']) {
            $stmt->execute([$newText, $row['id']]);
            $fixed++;
        }
    }
    echo "  Fixed {$fixed} rows\n";
}

// Duplicate check
echo "\n=== DUPLICATE CHECK ===\n";
foreach (['kan_ia4b_answers', 'kan_ia6_answers'] as $table) {
    $q = $pdo->query("SELECT COALESCE(COUNT(*),0) FROM (SELECT jawaban FROM {$table} WHERE jawaban IS NOT NULL AND jawaban != '' GROUP BY jawaban HAVING COUNT(*) > 1) d");
    echo "{$table}: {$q->fetchColumn()} duplicate groups\n";
}
