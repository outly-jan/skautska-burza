<?php
// Token musí odpovídat hodnotě GitHub secret DEPLOY_SECRET v repozitáři
// outly-jan/skautska-burza. Po nahrání na server změň CHANGE_ME na náhodný řetězec.
define('DEPLOY_SECRET', 'CHANGE_ME');

if (($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '') !== DEPLOY_SECRET) {
    http_response_code(403);
    exit('Unauthorized');
}

$repo = 'outly-jan/skautska-burza';

// Stahuje se podle konkrétního commitu, ne podle větve — raw.githubusercontent.com
// drží soubory z větve několik minut v cache, takže deploy hned po mergi
// by jinak mohl nahrát starou verzi. SHA posílá GitHub Actions v hlavičce.
$ref = $_SERVER['HTTP_X_DEPLOY_SHA'] ?? '';
if (!preg_match('/^[0-9a-f]{40}$/', $ref)) {
    $ref = 'main';
}
$base_url = 'https://raw.githubusercontent.com/' . $repo . '/' . $ref . '/';
$target   = __DIR__;
$log      = [];
$errors   = 0;

// Soubory, které na server nepatří. Webhook sám sebe nikdy nestahuje —
// na serveru má ručně nastavený token, který by se přepsal na CHANGE_ME.
$skip_files    = ['deploy-webhook.php', 'CLAUDE.md', '.gitignore', '.gitattributes'];
$skip_prefixes = ['.github/'];

// Seznam souborů podle aktuálního obsahu repa (žádný natvrdo psaný seznam),
// ať se s přibývajícími částmi pluginu nemusí tenhle skript ručně rozšiřovat.
$tree_context = stream_context_create([
    'http' => ['header' => "User-Agent: skautska-burza-deploy\r\n"],
]);
$tree_json = @file_get_contents(
    'https://api.github.com/repos/' . $repo . '/git/trees/' . $ref . '?recursive=1',
    false,
    $tree_context
);
$tree = $tree_json ? json_decode($tree_json, true) : null;

if (!$tree || empty($tree['tree'])) {
    http_response_code(500);
    exit('CHYBA: nepodařilo se načíst seznam souborů z GitHubu');
}

foreach ($tree['tree'] as $item) {
    if ($item['type'] !== 'blob') continue;

    $path = $item['path'];
    if (in_array($path, $skip_files, true)) continue;
    foreach ($skip_prefixes as $prefix) {
        if (strpos($path, $prefix) === 0) continue 2;
    }

    $data = @file_get_contents($base_url . $path);
    if ($data === false) {
        $log[] = 'CHYBA: ' . $path;
        $errors++;
        continue;
    }

    $dest = $target . '/' . $path;
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }
    if (file_put_contents($dest, $data) === false) {
        $log[] = 'CHYBA zápisu: ' . $path;
        $errors++;
        continue;
    }
    if (function_exists('opcache_invalidate') && str_ends_with($dest, '.php')) {
        opcache_invalidate($dest, true);
    }
    $log[] = $path . ' — ' . strlen($data) . ' bytes';
}

if ($errors > 0) {
    http_response_code(500);
}
$status = $errors === 0 ? 'OK' : "CHYBY: $errors";
echo $status . ' — ' . date('Y-m-d H:i:s') . ' — ' . $ref . "\n" . implode("\n", $log);
