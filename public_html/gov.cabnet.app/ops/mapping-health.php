<?php
/**
 * gov.cabnet.app — Mapping Health v1.0
 *
 * Read-only health dashboard for Bolt → EDXEIX mappings.
 * Safety: no external calls, no writes, no production submit behavior.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$shellFile = __DIR__ . '/_shell.php';
if (is_file($shellFile)) { require_once $shellFile; }

function mh_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mh_badge(string $text, string $type='neutral'): string
{
    if (function_exists('opsui_badge')) { return opsui_badge($text, $type); }
    $type = in_array($type, ['good','warn','bad','neutral'], true) ? $type : 'neutral';
    return '<span class="badge badge-' . mh_h($type) . '">' . mh_h($text) . '</span>';
}
function mh_root(): string { return dirname(__DIR__, 3); }
function mh_ctx(?string &$error=null): ?array
{
    static $ctx=null,$loaded=false,$loadError=null;
    if ($loaded) { $error=$loadError; return is_array($ctx)?$ctx:null; }
    $loaded=true;
    $bootstrap=mh_root().'/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) { $loadError='Private app bootstrap was not found.'; $error=$loadError; return null; }
    try { $ctx=require $bootstrap; if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'],'connection')) throw new RuntimeException('Invalid DB context.'); $error=null; return $ctx; }
    catch(Throwable $e){ $loadError=$e->getMessage(); $error=$loadError; return null; }
}
function mh_db(?string &$error=null): ?mysqli
{
    $ctx=mh_ctx($error); if (!$ctx) return null;
    try { $db=$ctx['db']->connection(); return $db instanceof mysqli ? $db : null; } catch(Throwable $e){ $error=$e->getMessage(); return null; }
}
function mh_table_exists(mysqli $db,string $table): bool
{
    try { $stmt=$db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'); $stmt->bind_param('s',$table); $stmt->execute(); return (bool)$stmt->get_result()->fetch_assoc(); } catch(Throwable){ return false; }
}
function mh_rows(mysqli $db,string $sql): array
{
    try { $res=$db->query($sql); if (!$res) return []; $out=[]; while($row=$res->fetch_assoc()) $out[]=$row; return $out; } catch(Throwable){ return []; }
}
function mh_one(mysqli $db,string $sql): int
{
    $rows=mh_rows($db,$sql); return (int)($rows[0]['c'] ?? 0);
}
function mh_col(array $row,string $key,string $default=''): string { return trim((string)($row[$key] ?? $default)); }
function mh_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title'=>'Mapping Health',
            'page_title'=>'Mapping Health',
            'active_section'=>'Mappings',
            'breadcrumbs'=>'Αρχική / Mappings / Health',
            'safe_notice'=>'Read-only mapping health dashboard. This page does not call Bolt, EDXEIX, or AADE and does not write data.',
            'force_safe_notice'=>true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Mapping Health</title></head><body>';
}
function mh_shell_end(): void { if (function_exists('opsui_shell_end')) opsui_shell_end(); else echo '</body></html>'; }

$dbError=null; $db=mh_db($dbError);
$exists=[]; $needed=['mapping_drivers','mapping_vehicles','mapping_lessor_starting_points','edxeix_export_lessors','edxeix_export_starting_points'];
foreach($needed as $t){ $exists[$t]=$db?mh_table_exists($db,$t):false; }

$lessors=[]; $globalIssues=[];
if ($db) {
    if ($exists['edxeix_export_lessors']) {
        foreach (mh_rows($db, 'SELECT * FROM edxeix_export_lessors ORDER BY id ASC LIMIT 1000') as $row) {
            $id=mh_col($row,'id'); if($id==='') continue;
            $lessors[$id]=['id'=>$id,'name'=>mh_col($row,'name','Lessor '.$id),'drivers_total'=>0,'drivers_mapped'=>0,'vehicles_total'=>0,'vehicles_mapped'=>0,'override_id'=>'','override_label'=>'','issues'=>[],'sources'=>['edxeix_export_lessors']];
        }
    }
    if ($exists['mapping_drivers']) {
        foreach (mh_rows($db, "SELECT edxeix_lessor_id, COUNT(*) AS total, SUM(CASE WHEN edxeix_driver_id IS NOT NULL AND edxeix_driver_id <> 0 THEN 1 ELSE 0 END) AS mapped FROM mapping_drivers WHERE COALESCE(is_active,1)=1 AND edxeix_lessor_id IS NOT NULL AND edxeix_lessor_id <> 0 GROUP BY edxeix_lessor_id") as $row) {
            $id=mh_col($row,'edxeix_lessor_id'); if($id==='') continue;
            if(!isset($lessors[$id])) $lessors[$id]=['id'=>$id,'name'=>'Lessor '.$id,'drivers_total'=>0,'drivers_mapped'=>0,'vehicles_total'=>0,'vehicles_mapped'=>0,'override_id'=>'','override_label'=>'','issues'=>[],'sources'=>[]];
            $lessors[$id]['drivers_total']=(int)($row['total']??0); $lessors[$id]['drivers_mapped']=(int)($row['mapped']??0); $lessors[$id]['sources'][]='mapping_drivers';
        }
        foreach (mh_rows($db, "SELECT edxeix_lessor_id, external_driver_name FROM mapping_drivers WHERE COALESCE(is_active,1)=1 AND (edxeix_driver_id IS NULL OR edxeix_driver_id = 0) LIMIT 200") as $row) {
            $globalIssues[]='Active driver without EDXEIX driver ID: '.mh_col($row,'external_driver_name').' / lessor '.mh_col($row,'edxeix_lessor_id');
        }
    }
    if ($exists['mapping_vehicles']) {
        foreach (mh_rows($db, "SELECT edxeix_lessor_id, COUNT(*) AS total, SUM(CASE WHEN edxeix_vehicle_id IS NOT NULL AND edxeix_vehicle_id <> 0 THEN 1 ELSE 0 END) AS mapped FROM mapping_vehicles WHERE COALESCE(is_active,1)=1 AND edxeix_lessor_id IS NOT NULL AND edxeix_lessor_id <> 0 GROUP BY edxeix_lessor_id") as $row) {
            $id=mh_col($row,'edxeix_lessor_id'); if($id==='') continue;
            if(!isset($lessors[$id])) $lessors[$id]=['id'=>$id,'name'=>'Lessor '.$id,'drivers_total'=>0,'drivers_mapped'=>0,'vehicles_total'=>0,'vehicles_mapped'=>0,'override_id'=>'','override_label'=>'','issues'=>[],'sources'=>[]];
            $lessors[$id]['vehicles_total']=(int)($row['total']??0); $lessors[$id]['vehicles_mapped']=(int)($row['mapped']??0); $lessors[$id]['sources'][]='mapping_vehicles';
        }
        foreach (mh_rows($db, "SELECT edxeix_lessor_id, plate FROM mapping_vehicles WHERE COALESCE(is_active,1)=1 AND (edxeix_vehicle_id IS NULL OR edxeix_vehicle_id = 0) LIMIT 200") as $row) {
            $globalIssues[]='Active vehicle without EDXEIX vehicle ID: '.mh_col($row,'plate').' / lessor '.mh_col($row,'edxeix_lessor_id');
        }
    }
    if ($exists['mapping_lessor_starting_points']) {
        foreach (mh_rows($db, "SELECT edxeix_lessor_id, label, edxeix_starting_point_id FROM mapping_lessor_starting_points WHERE COALESCE(is_active,1)=1 ORDER BY edxeix_lessor_id ASC, id ASC") as $row) {
            $id=mh_col($row,'edxeix_lessor_id'); if($id==='') continue;
            if(!isset($lessors[$id])) $lessors[$id]=['id'=>$id,'name'=>'Lessor '.$id,'drivers_total'=>0,'drivers_mapped'=>0,'vehicles_total'=>0,'vehicles_mapped'=>0,'override_id'=>'','override_label'=>'','issues'=>[],'sources'=>[]];
            if ($lessors[$id]['override_id']==='') { $lessors[$id]['override_id']=mh_col($row,'edxeix_starting_point_id'); $lessors[$id]['override_label']=mh_col($row,'label'); }
            $lessors[$id]['sources'][]='mapping_lessor_starting_points';
        }
    }
    $expected = ['1756'=>'612164'];
    foreach ($lessors as $id=>&$lessor) {
        $hasOperationalMapping = ((int)$lessor['drivers_total'] + (int)$lessor['vehicles_total']) > 0;
        if ($hasOperationalMapping && $lessor['override_id']==='') $lessor['issues'][]='missing_lessor_specific_starting_point_override';
        if ((int)$lessor['drivers_total'] > (int)$lessor['drivers_mapped']) $lessor['issues'][]='active_unmapped_driver';
        if ((int)$lessor['vehicles_total'] > (int)$lessor['vehicles_mapped']) $lessor['issues'][]='active_unmapped_vehicle';
        if (isset($expected[$id]) && $lessor['override_id'] !== '' && $lessor['override_id'] !== $expected[$id]) $lessor['issues'][]='verified_starting_point_mismatch_expected_'.$expected[$id];
        if (isset($expected[$id]) && $lessor['override_id'] === $expected[$id]) $lessor['sources'][]='verified_expectation_ok';
    }
    unset($lessor);
}
ksort($lessors, SORT_NATURAL);
$warnCount=0; $badCount=0; $goodCount=0;
foreach($lessors as $lessor){ if(!empty($lessor['issues'])) $warnCount++; else $goodCount++; }

mh_shell_begin();
?>
<style>
.mh-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.mh-metric{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:16px}.mh-metric strong{display:block;font-size:30px;color:#132a5e}.mh-table{width:100%;border-collapse:collapse}.mh-table th,.mh-table td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left;vertical-align:top}.mh-table th{background:#f8fafc}.mh-row-warn{background:#fffaf0}.mh-row-good{background:#f7fff8}.mh-nav{display:flex;gap:10px;flex-wrap:wrap}.mh-issue{display:block;color:#b45309;font-size:13px;margin:3px 0}.mh-source{font-size:12px;color:#667085}@media(max-width:900px){.mh-summary{grid-template-columns:1fr 1fr}.mh-table{font-size:13px}}@media(max-width:640px){.mh-summary{grid-template-columns:1fr}}
</style>
<section class="card hero">
    <h1>Mapping Health</h1>
    <p>Read-only health dashboard for company, driver, vehicle, and starting-point mappings.</p>
    <div><?= mh_badge('READ ONLY','good') ?> <?= mh_badge('NO DB WRITES','good') ?> <?= mh_badge('NO EDXEIX CALL','good') ?></div>
    <div class="mh-nav" style="margin-top:14px;">
        <a class="btn" href="/ops/mapping-center.php">Mapping Center</a>
        <a class="btn dark" href="/ops/company-mapping-control.php">Company Mapping Control</a>
        <a class="btn warn" href="/ops/starting-point-control.php">Starting Point Control</a>
        <a class="btn dark" href="/ops/mapping-control.php">Driver/Vehicle Review</a>
    </div>
</section>
<?php if($dbError): ?><section class="card"><p class="badline"><strong>DB error:</strong> <?= mh_h($dbError) ?></p></section><?php endif; ?>
<section class="mh-summary">
    <div class="mh-metric"><strong><?= mh_h((string)count($lessors)) ?></strong><span>Lessors tracked</span></div>
    <div class="mh-metric"><strong><?= mh_h((string)$goodCount) ?></strong><span>Lessors without page-detected issues</span></div>
    <div class="mh-metric"><strong><?= mh_h((string)$warnCount) ?></strong><span>Lessors needing review</span></div>
    <div class="mh-metric"><strong><?= mh_h((string)count($globalIssues)) ?></strong><span>Global mapping issues</span></div>
</section>
<?php if(!empty($globalIssues)): ?>
<section class="card">
    <h2>Global mapping issues</h2>
    <?php foreach($globalIssues as $issue): ?><div class="mh-issue">⚠ <?= mh_h($issue) ?></div><?php endforeach; ?>
</section>
<?php endif; ?>
<section class="card">
    <h2>Lessor health table</h2>
    <div class="table-wrap"><table class="mh-table">
        <thead><tr><th>Lessor</th><th>Drivers</th><th>Vehicles</th><th>Starting point override</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($lessors as $lessor): $issues=(array)$lessor['issues']; ?>
            <tr class="<?= empty($issues) ? 'mh-row-good' : 'mh-row-warn' ?>">
                <td><strong><?= mh_h($lessor['name']) ?></strong><br><code><?= mh_h($lessor['id']) ?></code><div class="mh-source">Sources: <?= mh_h(implode(', ', array_values(array_unique((array)$lessor['sources'])))) ?></div></td>
                <td><?= mh_h((string)$lessor['drivers_mapped']) ?>/<?= mh_h((string)$lessor['drivers_total']) ?> mapped</td>
                <td><?= mh_h((string)$lessor['vehicles_mapped']) ?>/<?= mh_h((string)$lessor['vehicles_total']) ?> mapped</td>
                <td><?php if($lessor['override_id']!==''): ?><?= mh_badge($lessor['override_id'],'good') ?><br><?= mh_h($lessor['override_label']) ?><?php else: ?><?= mh_badge('missing override','warn') ?><br><span class="mh-source">Global fallback would be used.</span><?php endif; ?></td>
                <td><?php if(empty($issues)): ?><?= mh_badge('GOOD','good') ?><?php else: ?><?= mh_badge('REVIEW','warn') ?><?php foreach($issues as $issue): ?><span class="mh-issue"><?= mh_h($issue) ?></span><?php endforeach; ?><?php endif; ?></td>
                <td><a class="btn" href="/ops/company-mapping-detail.php?lessor=<?= urlencode((string)$lessor['id']) ?>">Details</a><br><br><a class="btn dark" href="/ops/starting-point-control.php?lessor=<?= urlencode((string)$lessor['id']) ?>">Starting point</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if(empty($lessors)): ?><tr><td colspan="6">No lessor data available.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<section class="card">
    <h2>Safety rule enforced by governance</h2>
    <p>Any lessor used by active mapped drivers or vehicles should have an explicit row in <code>mapping_lessor_starting_points</code>. This prevents wrong global fallback values such as an Athens starting point being used for a Mykonos company.</p>
</section>
<?php mh_shell_end();
