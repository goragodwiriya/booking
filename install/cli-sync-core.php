<?php
/**
 * install/cli-sync-core.php — กระจายแกน (Now.js, Kotchasan, Gcms, modules/index, install/ …)
 * จากโปรเจ็คนี้ (adminframework) ไปยังโปรเจ็คลูก ให้เหมือนกัน 100% ตามนโยบายใน core-manifest.php
 *
 * ใช้:  php install/cli-sync-core.php [ที่อยู่โปรเจ็ค ...] [ตัวเลือก]
 *
 *   ไม่ระบุที่อยู่   = ค้นหาโปรเจ็คพี่น้องในโฟลเดอร์แม่ (รวมที่ซ้อน 1 ชั้น เช่น edms/adminframework)
 *   --apply         เขียนจริง (ปกติเป็นการซ้อม แสดงว่าจะทำอะไร)
 *   --base=<dir>    สำเนาแกนรุ่นก่อน (เช่น backup/adminframework 7.0.2) ใช้ตัดสินว่าไฟล์ในโปรเจ็คลูก
 *                   "แค่เก่า" (ตรงกับรุ่นก่อน → ทับได้) หรือ "ถูกแก้เฉพาะโปรเจ็ค" (ต้อง merge/ตรวจ)
 *                   ระบุได้หลายครั้ง · ทุกรุ่นใน git history ของโปรเจ็คนี้ถูกใช้เป็นฐานเสมอถ้ามี
 *   --surface=core|nowjs   core (ค่าปริยาย) = ตาม manifest · nowjs = เฉพาะ Kotchasan + Now
 *                   (โปรเจ็คตระกูล GCMS ที่ Gcms/modules/index เป็นของตัวเอง ถูกตรวจจับและใช้ nowjs ให้เอง)
 *   --include-backup       รวมโฟลเดอร์ backup/ ด้วย (ปกติข้าม — สำเนาสำรองไม่ควรถูกแก้)
 *   --list          แสดงรายการไฟล์ทุกสถานะ (ปกติแสดงเฉพาะที่ต้องตัดสินใจ)
 *
 * วิธีตัดสินต่อไฟล์ (เทียบกับ "ของเรา" = โปรเจ็คนี้)
 *   same      เหมือนกันแล้ว                              → ไม่ทำอะไร
 *   add       โปรเจ็คลูกไม่มีไฟล์นี้                        → คัดลอก
 *   stale     ตรงกับรุ่นก่อนรุ่นใดรุ่นหนึ่ง (git history / --base) → ทับ
 *   upstreamed ต่างจากทุกฐาน แต่ 3-way merge ได้ผลเท่าของเรา  → ทับ (สิ่งที่ลูกแก้ถูก backport แล้ว)
 *   merged    ต่างจากทุกฐาน merge ได้แต่ยังต่างจากของเรา    → เขียนผล merge + รายงานว่าต้อง backport
 *   kept      ผล merge เท่ากับที่โปรเจ็คมีอยู่ (merge ไปแล้วรอบก่อน)  → ไม่เขียน แต่ยังรายงาน
 *   conflict  merge ไม่ได้                                → ไม่แตะ + รายงาน
 *   skip      ของโปรเจ็คนั้นเอง (manifest skip)              → ไม่แตะ
 *   extra     ไฟล์ในโฟลเดอร์แกนที่ไม่มีในของเรา              → เก็บไว้ + รายงาน
 *   obsolete  ไฟล์ที่รุ่นก่อนมีแต่ของเราลบแล้ว และลูกยังเท่ารุ่นก่อน → ลบ
 *
 * exit code : 0 = ไม่มี conflict, 1 = มี conflict/ข้อผิดพลาด
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('ROOT_PATH', str_replace(['\\', 'install/cli-sync-core.php'], ['/', ''], __FILE__));
$MANIFEST = include ROOT_PATH.'install/core-manifest.php';

$options = ['apply' => false, 'bases' => [], 'surface' => 'core', 'backup' => false, 'list' => false];
$paths = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $options['apply'] = true;
    } elseif (preg_match('/^--base=(.+)$/', $arg, $m)) {
        $options['bases'][] = rtrim($m[1], '/').'/';
    } elseif (preg_match('/^--surface=(core|nowjs)$/', $arg, $m)) {
        $options['surface'] = $m[1];
    } elseif ($arg === '--include-backup') {
        $options['backup'] = true;
    } elseif ($arg === '--list') {
        $options['list'] = true;
    } elseif (substr($arg, 0, 2) === '--') {
        fwrite(STDERR, "ไม่รู้จักตัวเลือก $arg\n");
        exit(1);
    } else {
        // ที่อยู่จริงแบบเต็ม — ดัชนีข้ามโปรเจ็คใช้ที่อยู่จาก discoverSiblings() (เต็มเสมอ)
        // ถ้าเก็บ ../profito ไว้ตามที่พิมพ์ โปรเจ็คเดียวกันจะนับเป็น "อีกโปรเจ็ค" ที่มีเนื้อหาเดียวกัน
        // แล้วการแก้เฉพาะตัวของมันถูกตัดสินผิดเป็น stale และถูกทับ
        $paths[] = rtrim(realpath($arg) ?: $arg, '/').'/';
    }
}

if (empty($paths)) {
    $paths = discoverSiblings(ROOT_PATH, $options['backup']);
    if (empty($paths)) {
        fwrite(STDERR, "ไม่พบโปรเจ็คพี่น้อง — ระบุที่อยู่โปรเจ็คเอง\n");
        exit(1);
    }
}
foreach ($options['bases'] as $base) {
    if (!is_dir($base)) {
        fwrite(STDERR, "ไม่พบโฟลเดอร์ฐาน $base\n");
        exit(1);
    }
}

$hasGit = is_dir(ROOT_PATH.'.git') && trim((string) shell_exec('cd '.escapeshellarg(ROOT_PATH).' && git rev-parse --verify HEAD 2>/dev/null')) !== '';
echo 'ต้นทาง: '.ROOT_PATH.' (รุ่น '.currentVersion(ROOT_PATH).')'.($hasGit ? ' · ฐาน git history' : '')
    .($options['bases'] ? ' · ฐานเพิ่มเติม '.count($options['bases']) : '')
    .' · '.($options['apply'] ? 'เขียนจริง' : 'ซ้อม (ใส่ --apply เพื่อเขียน)')."\n";

// ดัชนี "ไฟล์นี้ hash นี้ พบในโปรเจ็คไหนบ้าง" จากทุกโปรเจ็คที่รู้จัก — ไฟล์แกนที่มีเนื้อหาเดียวกัน
// ในโปรเจ็คอื่นด้วยคือสำเนาของต้นทางรุ่นใดรุ่นหนึ่ง (แม้ไม่มี snapshot ของรุ่นนั้นแล้ว) ไม่ใช่การแก้เฉพาะตัว
$allProjects = array_unique(array_merge($paths, discoverSiblings(ROOT_PATH, true)));
$SIBLINGS = siblingIndex($allProjects, $MANIFEST);

$exit = 0;
$grand = [];
foreach ($paths as $path) {
    if (!is_dir($path)) {
        fwrite(STDERR, "ไม่พบโฟลเดอร์ $path\n");
        $exit = 1;
        continue;
    }
    if (realpath($path) === realpath(ROOT_PATH)) {
        continue;
    }
    $surface = $options['surface'];
    if ($surface === 'core' && isGcmsLineage($path)) {
        $surface = 'nowjs';
    }
    $core = $surface === 'nowjs' ? $MANIFEST['nowjs'] : $MANIFEST['core'];
    $result = syncProject($path, $core, $MANIFEST, $options, $hasGit, $SIBLINGS);
    $counts = $result['counts'];
    $grand[$path] = $counts;
    if ($counts['conflict'] > 0) {
        $exit = 1;
    }
    echo "\n== $path (".currentVersion($path).' · แกนแบบ '.$surface.")\n";
    $summary = [];
    foreach (['same', 'add', 'stale', 'upstreamed', 'merged', 'kept', 'conflict', 'extra', 'obsolete', 'skip'] as $k) {
        if ($counts[$k] > 0) {
            $summary[] = "$k {$counts[$k]}";
        }
    }
    echo '   '.implode(' · ', $summary)."\n";
    foreach ($result['lines'] as $line) {
        echo "   $line\n";
    }
}

if (count($grand) > 1) {
    echo "\n== สรุป ".count($grand)." โปรเจ็ค ==\n";
    foreach ($grand as $path => $c) {
        printf("   %-60s เปลี่ยน %3d · merged %2d · kept %2d · conflict %2d · extra %3d\n", str_replace(dirname(rtrim(ROOT_PATH, '/')).'/', '', $path),
            $c['add'] + $c['stale'] + $c['upstreamed'] + $c['merged'] + $c['obsolete'], $c['merged'], $c['kept'], $c['conflict'], $c['extra']);
    }
}
exit($exit);

// =============================================================================

/**
 * โปรเจ็คพี่น้อง: โฟลเดอร์ (และซ้อน 1 ชั้น) ที่มี Kotchasan/ + Now/js/
 *
 * @param string $root
 * @param bool   $includeBackup
 *
 * @return string[]
 */
function discoverSiblings($root, $includeBackup)
{
    $parent = dirname(rtrim($root, '/')).'/';
    $found = [];
    foreach (glob($parent.'*', GLOB_ONLYDIR) ?: [] as $dir) {
        $dir = rtrim($dir, '/').'/';
        if ($dir === $root || (!$includeBackup && basename(rtrim($dir, '/')) === 'backup')) {
            continue;
        }
        if (is_dir($dir.'Kotchasan') && is_dir($dir.'Now/js')) {
            $found[] = $dir;
            continue;
        }
        foreach (glob($dir.'*', GLOB_ONLYDIR) ?: [] as $sub) {
            $sub = rtrim($sub, '/').'/';
            if (is_dir($sub.'Kotchasan') && is_dir($sub.'Now/js')) {
                $found[] = $sub;
            }
        }
    }
    sort($found);

    return $found;
}

/**
 * โปรเจ็คตระกูล GCMS (CMS): มี admin/ และ modules/index ที่ไม่ใช่ของ adminframework
 *
 * @param string $path
 *
 * @return bool
 */
function isGcmsLineage($path)
{
    // GCMS มีโฟลเดอร์ admin/ (หน้าผู้ดูแลของ CMS) — โปรเจ็คลูกของ adminframework ไม่มี
    return is_dir($path.'admin');
}

/**
 * @param string $path
 *
 * @return string
 */
function currentVersion($path)
{
    foreach (['settings/config.php', 'install/settings/config.php'] as $file) {
        if (is_file($path.$file) && preg_match("/'version'\s*=>\s*'([^']+)'/", (string) file_get_contents($path.$file), $m)) {
            return $m[1];
        }
    }

    return '?';
}

/**
 * รายชื่อไฟล์แกนของโปรเจ็คหนึ่ง (ตัด skip แล้ว)
 *
 * @return array [ที่อยู่ในโปรเจ็ค => md5]
 */
function coreFiles($root, array $core, array $skip)
{
    $files = [];
    foreach ($core as $entry) {
        $full = $root.$entry;
        if (is_file($full)) {
            $files[$entry] = contentHash($full);
        } elseif (is_dir($full)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
                foreach ($skip as $ignore) {
                    if ($relative === $ignore || strpos($relative, $ignore.'/') === 0) {
                        continue 2;
                    }
                }
                $files[$relative] = contentHash($file->getPathname());
            }
        }
    }
    ksort($files);

    return $files;
}

/**
 * md5 ของไฟล์โดยไม่สนใจชนิดขึ้นบรรทัด (สำเนาในบางโปรเจ็คถูกบันทึกเป็น CRLF
 * ทั้งที่เนื้อหาเหมือนกัน — ไม่ควรนับเป็นความต่าง)
 *
 * @param string $file
 *
 * @return string
 */
function contentHash($file)
{
    return md5(normalizeEol((string) file_get_contents($file)));
}

/**
 * @param string $text
 *
 * @return string
 */
function normalizeEol($text)
{
    return str_replace(["\r\n", "\r"], "\n", $text);
}

/**
 * เนื้อหาของไฟล์ในรุ่นฐานทั้งหมดที่รู้จัก (ทุกรุ่นใน git history + --base)
 *
 * ทุกรุ่นที่เคย commit ไม่ใช่แค่ HEAD — ไม่งั้นพอ commit งานของต้นทางก่อนกระจาย
 * สำเนารุ่นก่อนหน้าในโปรเจ็คลูกก็ไม่ตรงฐานใดอีก แล้วถูกนับเป็นการแก้เฉพาะตัว
 *
 * @return string[] เนื้อหา (ไม่ซ้ำ ใหม่สุดก่อน)
 */
function baseVersions($relative, array $bases, $hasGit)
{
    $versions = [];
    if ($hasGit) {
        $root = escapeshellarg(ROOT_PATH);
        $commits = preg_split('/\s+/', trim((string) shell_exec('cd '.$root.' && git log --format=%H -- '.escapeshellarg($relative).' 2>/dev/null')));
        foreach (array_filter($commits) as $commit) {
            $blob = shell_exec('cd '.$root.' && git show '.escapeshellarg($commit.':'.$relative).' 2>/dev/null');
            if ($blob !== null && $blob !== '') {
                $blob = normalizeEol($blob);
                $versions[md5($blob)] = $blob;
            }
        }
    }
    foreach ($bases as $base) {
        if (is_file($base.$relative)) {
            $content = normalizeEol((string) file_get_contents($base.$relative));
            $versions[md5($content)] = $content;
        }
    }

    return array_values($versions);
}

/**
 * 3-way merge ด้วย git merge-file
 *
 * @return array|null ['clean' => bool, 'content' => string] หรือ null เมื่อไม่มี git
 */
function threeWayMerge($theirs, $base, $ours)
{
    $dir = sys_get_temp_dir().'/sync-'.getmypid().'-'.mt_rand().'/';
    @mkdir($dir, 0700, true);
    file_put_contents($dir.'theirs', $theirs);
    file_put_contents($dir.'base', $base);
    file_put_contents($dir.'ours', $ours);
    $out = shell_exec('cd '.escapeshellarg($dir).' && git merge-file -p theirs base ours 2>/dev/null; echo "__EXIT__$?"');
    @unlink($dir.'theirs');
    @unlink($dir.'base');
    @unlink($dir.'ours');
    @rmdir($dir);
    if ($out === null || !preg_match('/__EXIT__(\d+)\s*$/', $out, $m)) {
        return null;
    }
    $content = preg_replace('/__EXIT__\d+\s*$/', '', $out);

    return ['clean' => (int) $m[1] === 0, 'content' => $content];
}

/**
 * เทียบและ (ถ้า --apply) เขียนไฟล์แกนของโปรเจ็คหนึ่ง
 *
 * @return array ['counts' => [...], 'lines' => [...]]
 */
function syncProject($path, array $core, array $manifest, array $options, $hasGit, array $siblings)
{
    $backupDir = $path.'datas/core-sync-backup/'.date('Ymd-His').'/';
    $skip = $manifest['skip'];
    // ส่วนที่โปรเจ็คนี้แยกทางจากแกนโดยตั้งใจ — ไม่เพิ่ม ไม่ทับ ไม่ลบ (install/core-own.txt ของโปรเจ็คนั้น)
    $owned = ownedPaths($path);
    $mine = withoutOwned(coreFiles(ROOT_PATH, $core, $skip), $owned);
    $theirs = withoutOwned(coreFiles($path, $core, $skip), $owned);

    // โมดูลตัวเลือก: ถ้าฝั่งใดฝั่งหนึ่งไม่ได้ติดตั้งไว้เลย ให้ข้ามทั้งโมดูล (ไม่ยัดให้ และไม่นับเป็น extra)
    foreach ($manifest['optional'] as $optional) {
        if (!in_array($optional, $core, true) || (is_dir($path.$optional) && is_dir(ROOT_PATH.$optional))) {
            continue;
        }
        foreach (array_keys($mine) as $file) {
            if (strpos($file, $optional.'/') === 0) {
                unset($mine[$file]);
            }
        }
        foreach (array_keys($theirs) as $file) {
            if (strpos($file, $optional.'/') === 0) {
                unset($theirs[$file]);
            }
        }
    }

    $counts = array_fill_keys(['same', 'add', 'stale', 'upstreamed', 'merged', 'kept', 'conflict', 'extra', 'obsolete', 'skip'], 0);
    $lines = [];
    $changed = [];
    $note = function ($status, $file, $detail = '') use (&$lines, $options) {
        $important = in_array($status, ['merged', 'kept', 'conflict', 'extra', 'obsolete'], true);
        if ($important || $options['list']) {
            $lines[] = sprintf('%-10s %s%s', $status, $file, $detail === '' ? '' : ' — '.$detail);
        }
    };
    $write = function ($file, $content) use ($path, $options, &$changed, $backupDir) {
        $changed[] = $file;
        if (!$options['apply']) {
            return;
        }
        $target = $path.$file;
        if (is_file($target)) {
            // เก็บของเดิมไว้ก่อนทับเสมอ (ย้อนกลับได้ ไม่มีอะไรหายเงียบ ๆ)
            if (!is_dir(dirname($backupDir.$file))) {
                mkdir(dirname($backupDir.$file), 0777, true);
            }
            copy($target, $backupDir.$file);
        }
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException('เขียนไม่ได้: '.$target);
        }
    };

    foreach ($mine as $file => $hash) {
        // เทียบด้วยเนื้อหาที่ normalize แล้ว แต่เขียนด้วยไบต์จริงของต้นทาง (บางไฟล์ของต้นทางเองเป็น CRLF)
        // ไม่งั้นปลายทางได้ LF ส่วนต้นทางเป็น CRLF แล้ว cli-check-core ยังเห็นว่าต่างตลอดกาล
        $oursRaw = (string) file_get_contents(ROOT_PATH.$file);
        $ours = normalizeEol($oursRaw);
        if (!isset($theirs[$file])) {
            $counts['add']++;
            $note('add', $file);
            $write($file, $oursRaw);
            continue;
        }
        if ($theirs[$file] === $hash) {
            if (md5_file($path.$file) !== md5_file(ROOT_PATH.$file)) {
                // เนื้อหาเดียวกันแต่ขึ้นบรรทัดคนละแบบ (CRLF) — เขียนทับให้เหมือนกันทุกไบต์ ตามที่ cli-check-core ตรวจ
                $counts['stale']++;
                $note('stale', $file, 'ต่างเฉพาะชนิดขึ้นบรรทัด');
                $write($file, $oursRaw);
                continue;
            }
            $counts['same']++;
            $note('same', $file);
            continue;
        }
        $their = normalizeEol((string) file_get_contents($path.$file));
        if (rtrim($their) === rtrim($ours)) {
            // ต่างแค่บรรทัดว่าง/ช่องว่างท้ายไฟล์ — ไม่ใช่การแก้เฉพาะตัว แต่ cli-check-core ยังเห็นว่าต่าง
            $counts['stale']++;
            $note('stale', $file, 'ต่างเฉพาะช่องว่างท้ายไฟล์');
            $write($file, $oursRaw);
            continue;
        }
        $bases = baseVersions($file, $options['bases'], $hasGit);
        $isStale = false;
        foreach ($bases as $base) {
            if (md5($base) === $theirs[$file]) {
                $isStale = true;
                break;
            }
        }
        if ($isStale || preg_match('/\.(map|min\.js|min\.css)$/', $file)) {
            // แค่เก่า (หรือเป็นผลลัพธ์ build ที่ต้องตรงกับซอร์สของเรา) → ทับ
            $counts['stale']++;
            $note('stale', $file);
            $write($file, $oursRaw);
            continue;
        }
        $elsewhere = array_values(array_diff($siblings[$file][$theirs[$file]] ?? [], [$path]));
        if ($elsewhere) {
            // เนื้อหาเดียวกันมีในโปรเจ็คอื่นด้วย = สำเนาของต้นทางรุ่นหนึ่ง → ทับ (ของเดิมอยู่ใน backup)
            $counts['stale']++;
            $note('stale', $file, 'รุ่นเดียวกับ '.implode(', ', array_map(function ($p) {
                return basename(dirname($p)) === basename(dirname(rtrim(ROOT_PATH, '/'))) ? basename($p) : basename(dirname($p)).'/'.basename($p);
            }, array_slice($elsewhere, 0, 3))).(count($elsewhere) > 3 ? ' …' : ''));
            $write($file, $oursRaw);
            continue;
        }
        // ไม่ตรงกับฐานใด: เลือกฐานที่ใกล้ที่สุด (รุ่นที่ไฟล์ของลูกน่าจะแตกออกมา) แล้วลอง 3-way merge
        // วัดด้วยจำนวนบรรทัดที่ต่างเท่านั้น — ความยาวไฟล์หลอกได้: คอมเมนต์ที่ต้นทางเพิ่มทำให้รุ่นใหม่
        // "ใกล้" ไฟล์ที่ลูกแก้จนยาวขึ้น แล้วถูกเลือกเป็นฐานแทนรุ่นที่ลูกแตกออกมาจริง
        // รุ่นของเราเอง (อยู่ใน git history ด้วย) ใช้เป็นฐานไม่ได้ถ้ายังมีรุ่นอื่น: merge กับฐานที่เป็นของเรา
        // ได้ไฟล์ของลูกกลับมาเสมอ (kept) ไม่ว่าไฟล์นั้นเป็น "ของเรา + การแก้เฉพาะตัว" หรือ "รุ่นเก่า
        // ที่ขาด fix" — eleave_multi_step เคยถูกรายงานว่า kept ทั้งที่ Telegram.php/Line.php ไม่มีการตรวจ
        // ลายเซ็น webhook · กับฐานรุ่นเก่ากว่า กรณีแรกยังได้ kept (ของเราเปลี่ยนตรงไหน ลูกก็มีแล้ว)
        // ส่วนกรณีหลังได้ merged/conflict ตามจริง · ใช้ของเราเป็นฐานเฉพาะเมื่อไม่มีรุ่นอื่นเลย
        $candidates = [];
        foreach ($bases as $base) {
            if (md5($base) !== $hash) {
                $candidates[] = $base;
            }
        }
        $unverified = empty($candidates) && !empty($bases);
        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach ($unverified ? $bases : $candidates as $base) {
            $score = levenshteinApprox($base, $their);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $base;
            }
        }
        if ($best !== null && stripCommentsAndSpace($best) === stripCommentsAndSpace($their)) {
            // ต่างจากรุ่นก่อนแค่คอมเมนต์/ช่องว่าง → ถือว่าแค่เก่า
            $counts['stale']++;
            $note('stale', $file, 'ต่างจากรุ่นก่อนเฉพาะคอมเมนต์/ช่องว่าง');
            $write($file, $oursRaw);
            continue;
        }
        $merge = $best === null ? null : threeWayMerge($their, $best, $ours);
        if ($merge === null) {
            $counts['conflict']++;
            $note('conflict', $file, $best === null ? 'ไม่มีรุ่นฐานให้ merge — ตรวจเอง' : 'git ไม่พร้อม');
            continue;
        }
        if (!$merge['clean']) {
            $counts['conflict']++;
            $note('conflict', $file, 'merge ไม่ได้ — โปรเจ็คแก้ไฟล์แกนตรงจุดที่ต้นทางก็แก้');
            continue;
        }
        $duplicated = duplicatedLines(normalizeEol($merge['content']), $their, $ours);
        if ($duplicated) {
            // สองฝั่งเพิ่มโค้ดชุดเดียวกันคนละตำแหน่ง git merge-file ใส่ให้ทั้งสองชุดโดยไม่ถือว่าชน
            // (เช่น $config['selfUrl'] ใน filebrowser.php ของ oms.in.th) ผ่าน php -l แต่ผิด
            $counts['conflict']++;
            $note('conflict', $file, 'merge ได้แต่มีโค้ดซ้ำ — '.mb_substr($duplicated[0], 0, 60));
            continue;
        }
        if (md5($merge['content']) === $hash) {
            $counts['upstreamed']++;
            $note('upstreamed', $file);
            $write($file, $oursRaw);
            continue;
        }
        if (normalizeEol($merge['content']) === $their) {
            // ผล merge เท่ากับที่โปรเจ็คมีอยู่แล้ว (รอบก่อน merge ไปแล้ว) — ไม่เขียนซ้ำ แต่ยังรายงานหนี้ backport
            $counts['kept']++;
            $note('kept', $file, $unverified
                ? 'ต่างจากของเรา และไม่มีรุ่นก่อนให้เทียบ — ตรวจเองว่าเป็นการแก้เฉพาะตัวหรือรุ่นเก่าที่ขาด fix'
                : 'มีการแก้เฉพาะตัวที่รวมของต้นทางแล้ว (ยังต้อง backport หรือย้ายออกจากแกน)');
            continue;
        }
        $counts['merged']++;
        $note('merged', $file, 'โปรเจ็คมีการแก้เฉพาะตัว — เก็บไว้ในผล merge (ควร backport เข้าต้นทางหรือย้ายออกจากแกน)');
        $write($file, $merge['content']);
    }

    // ไฟล์ที่โปรเจ็คลูกมีแต่ของเราไม่มี: รุ่นก่อนเคยมี (ถูกลบที่ต้นทาง) หรือของโปรเจ็คเอง
    foreach ($theirs as $file => $hash) {
        if (isset($mine[$file])) {
            continue;
        }
        $bases = baseVersions($file, $options['bases'], $hasGit);
        $matchesBase = false;
        foreach ($bases as $base) {
            if (md5($base) === $hash) {
                $matchesBase = true;
                break;
            }
        }
        if ($matchesBase) {
            // ลบเฉพาะที่พิสูจน์ได้ว่าเป็นไฟล์ของต้นทางรุ่นก่อน (ตรง snapshot) ที่ต้นทางเลิกใช้แล้ว
            // ไฟล์อื่นในโฟลเดอร์แกนที่ไม่รู้ที่มา = เก็บไว้และรายงาน (extra) — การลบย้อนกลับไม่ได้
            $counts['obsolete']++;
            $note('obsolete', $file, 'ต้นทางลบแล้ว');
            if ($options['apply']) {
                if (!is_dir(dirname($backupDir.$file))) {
                    mkdir(dirname($backupDir.$file), 0777, true);
                }
                copy($path.$file, $backupDir.$file);
                @unlink($path.$file);
            }
        } else {
            $counts['extra']++;
            $note('extra', $file, 'ไม่มีในต้นทาง (เก็บไว้)');
        }
    }
    foreach ($skip as $s) {
        if (is_file($path.$s) || is_dir($path.$s)) {
            $counts['skip']++;
        }
    }
    foreach ($owned as $s) {
        $counts['skip']++;
        $lines[] = sprintf('%-10s %s — %s', 'own', $s, 'โปรเจ็คแยกทางเอง (install/core-own.txt) ไม่แตะ');
    }

    if ($options['apply'] && $changed) {
        // ตรวจ syntax ไฟล์ PHP ที่เพิ่งเขียน กันไฟล์พังหลุดไปถึง production
        foreach ($changed as $file) {
            if (substr($file, -4) === '.php' && is_file($path.$file)) {
                $lint = (string) shell_exec('php -l '.escapeshellarg($path.$file).' 2>&1');
                if (strpos($lint, 'No syntax errors') === false) {
                    $counts['conflict']++;
                    $lines[] = 'LINT       '.$file.' — '.trim($lint);
                }
            }
        }
        // ล้าง cache ของโปรเจ็คลูก (คำแปล/ตั้งค่าอาจถูก cache ไว้)
        foreach (glob($path.'datas/cache/*') ?: [] as $cached) {
            if (is_file($cached)) {
                @unlink($cached);
            }
        }
    }

    return ['counts' => $counts, 'lines' => $lines];
}

/**
 * ดัชนีข้ามโปรเจ็ค: [ไฟล์ => [hash => [โปรเจ็ค...]]]
 *
 * นับเฉพาะไฟล์ที่อยู่ในแกนที่โปรเจ็คนั้นรับจากเรา — โปรเจ็คตระกูล GCMS รับแค่ Kotchasan + Now
 * ส่วน Gcms/, modules/, js/components/editor ของมันเป็นของตัวเอง (รุ่น tenant) ถ้านับด้วย
 * โปรเจ็คที่คัดลอกของ gcms.in.th มาใช้ (oms.in.th) จะดูเหมือนถือ "สำเนาของต้นทาง" แล้วถูกทับ
 *
 * @param array $projects
 * @param array $manifest
 *
 * @return array
 */
function siblingIndex(array $projects, array $manifest)
{
    $index = [];
    foreach ($projects as $project) {
        if (!is_dir($project)) {
            continue;
        }
        $core = isGcmsLineage($project) ? $manifest['nowjs'] : $manifest['core'];
        foreach (withoutOwned(coreFiles($project, $core, $manifest['skip']), ownedPaths($project)) as $file => $hash) {
            $index[$file][$hash][] = $project;
        }
    }

    return $index;
}

/**
 * ส่วนของแกนที่โปรเจ็คแยกทางโดยตั้งใจ — install/core-own.txt บรรทัดละหนึ่ง path (โฟลเดอร์หรือไฟล์)
 * บรรทัดว่างและบรรทัดที่ขึ้นต้นด้วย # ไม่นับ
 *
 * เป็นไฟล์ข้อความ ไม่ใช่ PHP — เครื่องมือนี้อ่านจากทุกโปรเจ็คพี่น้อง จึงไม่รันโค้ดของโปรเจ็คอื่น
 * ประกาศเฉพาะส่วนที่ตั้งใจให้ต่างถาวร (เช่น oms.in.th เขียนตัวติดตั้งใหม่ทั้งชุด) ไม่ใช่ที่พักของงานที่ยังไม่ได้ backport
 *
 * @param string $root
 *
 * @return array
 */
function ownedPaths($root)
{
    $file = $root.'install/core-own.txt';
    if (!is_file($file)) {
        return [];
    }
    $paths = [];
    foreach (preg_split('/\R/', (string) file_get_contents($file)) as $line) {
        $line = trim($line, " \t/");
        if ($line !== '' && $line[0] !== '#' && strpos($line, '..') === false) {
            $paths[] = $line;
        }
    }

    return $paths;
}

/**
 * ตัดไฟล์ที่อยู่ใต้ path ที่โปรเจ็คแยกทางออกจากรายการ
 *
 * @param array $files [ที่อยู่ => hash]
 * @param array $owned
 *
 * @return array
 */
function withoutOwned(array $files, array $owned)
{
    foreach (array_keys($files) as $file) {
        foreach ($owned as $path) {
            if ($file === $path || strpos($file, $path.'/') === 0) {
                unset($files[$file]);
                break;
            }
        }
    }

    return $files;
}

/**
 * เนื้อหาที่ตัดคอมเมนต์ (บรรทัด //, #, บล็อก slash-star และ HTML comment) และช่องว่างทั้งหมดออก — ใช้ตัดสินว่า
 * สองไฟล์ต่างกันเฉพาะสิ่งที่ไม่มีผลต่อการทำงานหรือไม่
 *
 * @param string $text
 *
 * @return string
 */
function stripCommentsAndSpace($text)
{
    $text = preg_replace('#/\*.*?\*/#s', '', $text);
    $text = preg_replace('#<!--.*?-->#s', '', $text);
    $text = preg_replace('#^\s*(//|\#)[^\n]*$#m', '', $text);
    $text = preg_replace('#(?<=[;{}\s])//[^\n]*$#m', '', $text);

    return preg_replace('/\s+/', '', (string) $text);
}

/**
 * บรรทัดที่ผล merge มีมากกว่าทั้งสองฝั่ง — สัญญาณว่าโค้ดชุดเดียวกันถูกใส่ซ้ำ
 * นับเฉพาะบรรทัดที่ยาวพอจะเป็นโค้ดจริง (วงเล็บปิด / return; ซ้ำกันเป็นปกติ)
 *
 * @param string $merged
 * @param string $theirs
 * @param string $ours
 *
 * @return string[]
 */
function duplicatedLines($merged, $theirs, $ours)
{
    $count = function ($text) {
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if (strlen($line) >= 20) {
                $lines[$line] = ($lines[$line] ?? 0) + 1;
            }
        }

        return $lines;
    };
    $a = $count($theirs);
    $b = $count($ours);
    $found = [];
    foreach ($count($merged) as $line => $n) {
        if ($n > max($a[$line] ?? 0, $b[$line] ?? 0)) {
            $found[] = $line;
        }
    }

    return $found;
}

/**
 * ระยะห่างโดยประมาณ (นับบรรทัดที่ต่างกัน) — levenshtein ของ PHP จำกัดความยาว
 *
 * @return int
 */
function levenshteinApprox($a, $b)
{
    $la = explode("\n", $a);
    $lb = explode("\n", $b);

    return count(array_diff($la, $lb)) + count(array_diff($lb, $la));
}
