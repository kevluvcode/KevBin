<?php
require_once __DIR__ . '/functions.php';

start_session();
$cfg = $GLOBALS['CFG'];
$me = current_user();
$pdo = db();

const WIKI_SCOPES = ['site' => 'Site Docs', 'community' => 'Community Wiki', 'personal' => 'My Wiki'];
const WIKI_MARKS = ['site' => '📘', 'community' => '🌐', 'personal' => '👤'];

function wiki_escape(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function wiki_slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    $s = mb_substr($s, 0, 100);
    return $s !== '' ? $s : 'page';
}

function wiki_inline(string $line, string $scope = 'community'): string
{
    $line = wiki_escape($line);
    $line = preg_replace_callback('/`([^`]+)`/', function ($m) {
        return '<code>' . $m[1] . '</code>';
    }, $line);
    $line = preg_replace_callback('/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/', function ($m) use ($scope) {
        $slug = wiki_slugify($m[1]);
        $label = $m[2] ?? $m[1];
        return '<a href="' . e('wiki.php?scope=' . $scope . '&slug=' . $slug) . '">' . $label . '</a>';
    }, $line);
    $line = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', function ($m) {
        return '<a href="' . $m[2] . '" rel="noopener nofollow" target="_blank">' . $m[1] . '</a>';
    }, $line);
    $line = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $line);
    $line = preg_replace('/(?<![\w*])\*([A-Za-z0-9][^*\n]*[A-Za-z0-9])\*(?![\w*])/', '<em>$1</em>', $line);
    return $line;
}

function wiki_render(string $src, string $scope = 'community'): string
{
    $src = str_replace(["\r\n", "\r"], "\n", $src);
    $lines = explode("\n", $src);
    $out = '';
    $inCode = false;
    $codeBuf = [];
    $blockquote = [];
    $listUl = [];
    $listOl = [];
    $table = [];
    $plain = [];

    $flush = function () use (&$out, &$blockquote, &$listUl, &$listOl, &$table, &$plain, $scope): void {
        if ($plain !== []) {
            $out .= '<p>' . implode("<br>\n", $plain) . "</p>\n";
            $plain = [];
        }
        if ($blockquote !== []) {
            $out .= '<blockquote class="border-start border-3 ps-3 text-secondary">' . implode("<br>\n", $blockquote) . "</blockquote>\n";
            $blockquote = [];
        }
        if ($listUl !== []) {
            $out .= "<ul>\n" . implode("\n", $listUl) . "\n</ul>\n";
            $listUl = [];
        }
        if ($listOl !== []) {
            $out .= "<ol>\n" . implode("\n", $listOl) . "\n</ol>\n";
            $listOl = [];
        }
        if ($table !== []) {
            $out .= '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-3">';
            foreach ($table as $row) {
                $header = $row['h'];
                $out .= '<tr>';
                foreach ($row['c'] as $cell) {
                    $out .= $header ? '<th>' . wiki_inline($cell, $scope) . '</th>' : '<td>' . wiki_inline($cell, $scope) . '</td>';
                }
                $out .= '</tr>';
            }
            $out .= '</table></div>' . "\n";
            $table = [];
        }
    };

    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];

        if (preg_match('/^```/', $line)) {
            $flush();
            if ($inCode) {
                $out .= '<pre class="wiki-code p-3 rounded mb-3 overflow-auto"><code>' . wiki_escape(implode("\n", $codeBuf)) . "</code></pre>\n";
                $codeBuf = [];
                $inCode = false;
            } else {
                $inCode = true;
            }
            continue;
        }
        if ($inCode) {
            $codeBuf[] = $line;
            continue;
        }

        $trimmed = trim($line);
        if ($trimmed === '') {
            $flush();
            continue;
        }

        // Table rows: consecutive lines starting with "|" (first row = header).
        if ($line[0] === '|') {
            $flush();
            $first = true;
            while ($i < $n && $lines[$i] !== '' && $lines[$i][0] === '|') {
                $raw = trim($lines[$i]);
                $cells = explode('|', trim($raw, '|'));
                if (preg_match('/^-+$/', trim(implode('', $cells)))) {
                    $i++;
                    continue;
                }
                $table[] = ['h' => $first, 'c' => $cells];
                $first = false;
                $i++;
            }
            $i--;
            continue;
        }

        if (preg_match('/^(={1,6})\s+(.*)$/', $trimmed, $m)) {
            $flush();
            $level = strlen($m[1]);
            $out .= '<h' . $level . ' class="wiki-h' . $level . ' mt-4 mb-2">' . wiki_inline(trim($m[2]), $scope) . '</h' . $level . ">\n";
            continue;
        }
        if ($trimmed === '---') {
            $flush();
            $out .= "<hr class=\"my-4\">\n";
            continue;
        }
        if (strpos($trimmed, '> ') === 0 || $trimmed === '>') {
            $flush();
            while ($i < $n && (strpos(trim($lines[$i]), '> ') === 0 || trim($lines[$i]) === '>')) {
                $blockquote[] = wiki_inline(trim(substr(trim($lines[$i]), 1)), $scope);
                $i++;
            }
            $i--;
            continue;
        }
        if (strpos($trimmed, '* ') === 0 || strpos($trimmed, '- ') === 0) {
            $flush();
            while ($i < $n && (strpos(trim($lines[$i]), '* ') === 0 || strpos(trim($lines[$i]), '- ') === 0)) {
                $listUl[] = '<li>' . wiki_inline(substr(trim($lines[$i]), 2), $scope) . '</li>';
                $i++;
            }
            $i--;
            continue;
        }
        if (preg_match('/^(#|\d+\.)\s+(.*)$/', $trimmed, $m)) {
            $flush();
            while ($i < $n && preg_match('/^(#|\d+\.)\s+(.*)$/', trim($lines[$i]), $mm)) {
                $listOl[] = '<li>' . wiki_inline($mm[2], $scope) . '</li>';
                $i++;
            }
            $i--;
            continue;
        }

        $plain[] = wiki_inline($line, $scope);
    }
    $flush();
    if ($inCode) {
        $out .= '<pre class="wiki-code p-3 rounded mb-3 overflow-auto"><code>' . wiki_escape(implode("\n", $codeBuf)) . "</code></pre>\n";
    }
    return $out;
}

function wiki_get_page(PDO $pdo, string $scope, ?int $ownerId, string $slug): ?array
{
    if ($scope === 'personal') {
        $stmt = $pdo->prepare('SELECT * FROM wiki_pages WHERE scope = ? AND owner_id = ? AND slug = ?');
        $stmt->execute([$scope, $ownerId, $slug]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM wiki_pages WHERE scope = ? AND owner_id IS NULL AND slug = ?');
        $stmt->execute([$scope, $slug]);
    }
    $page = $stmt->fetch();
    return $page !== false ? $page : null;
}

function wiki_author(PDO $pdo, ?array $page): string
{
    if ($page === null || empty($page['owner_id'])) {
        return '<span class="text-secondary">Staff</span>';
    }
    $stmt = $pdo->prepare('SELECT username, profile_color FROM users WHERE id = ?');
    $stmt->execute([(int)$page['owner_id']]);
    $u = $stmt->fetch();
    if ($u === false) {
        return '<span class="text-secondary">former user</span>';
    }
    $color = $u['profile_color'] ?: 'var(--accent)';
    return '<a class="text-decoration-none fw-semibold" style="color:' . e($color) . '" href="' . e(url('profile.php?id=' . (int)$page['owner_id'])) . '">' . e($u['username']) . '</a>';
}

function wiki_can_edit(?array $me, ?array $page): bool
{
    if ($page === null) {
        return $me !== null;
    }
    $scope = $page['scope'];
    if ($scope === 'site') {
        return is_admin();
    }
    if ($me === null) {
        return false;
    }
    if ($scope === 'personal') {
        return (int)$page['owner_id'] === (int)$me['id'] || is_admin();
    }
    if ((int)$page['owner_id'] === (int)$me['id'] || is_staff()) {
        return true;
    }
    return (int)$page['locked'] === 0;
}

function wiki_can_delete(?array $me, ?array $page): bool
{
    if ($page === null) {
        return false;
    }
    $scope = $page['scope'];
    if ($scope === 'site') {
        return is_admin();
    }
    if ($me === null) {
        return false;
    }
    if ($scope === 'personal') {
        return (int)$page['owner_id'] === (int)$me['id'] || is_admin();
    }
    return (int)$page['owner_id'] === (int)$me['id'] || is_staff();
}

function wiki_can_create(?array $me, string $scope): bool
{
    if ($scope === 'site') {
        return is_admin();
    }
    return $me !== null;
}

function wiki_can_view(?array $me, ?array $page): bool
{
    if ($page === null) {
        return true;
    }
    if ($page['scope'] !== 'personal') {
        return true;
    }
    return $me !== null && ((int)$page['owner_id'] === (int)$me['id'] || is_admin());
}

function wiki_scopes(?array $me): array
{
    $list = [];
    $list['community'] = WIKI_SCOPES['community'];
    if ($me !== null) {
        $list['personal'] = WIKI_SCOPES['personal'];
    }
    if (is_admin()) {
        $list['site'] = WIKI_SCOPES['site'];
    }
    return $list;
}

// ——— request handling ———
$scope = $_GET['scope'] ?? 'community';
if (!isset(WIKI_SCOPES[$scope])) {
    $scope = 'community';
}
if ($scope === 'personal' && $me === null) {
    flash_set('error', 'You need to be logged in to use your personal wiki.');
    redirect('login.php');
}
$ownerId = $scope === 'personal' ? (int)$me['id'] : null;
$slug = isset($_GET['slug']) ? wiki_slugify((string)$_GET['slug']) : '';
$action = $_GET['action'] ?? 'view';

$page = $slug !== '' ? wiki_get_page($pdo, $scope, $ownerId, $slug) : null;
if ($page !== null && !wiki_can_view($me, $page)) {
    friendly_error('You do not have access to this page.', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    if ($postAction === 'preview') {
        $previewTitle = trim((string)($_POST['title'] ?? ''));
        $previewContent = (string)($_POST['content'] ?? '');
        $previewHtml = wiki_render($previewContent, $scope);
    } elseif ($postAction === 'save') {
        csrf_verify_or_fail();
        $newTitle = trim((string)($_POST['title'] ?? ''));
        $newContent = (string)($_POST['content'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));
        $newSlug = wiki_slugify($newTitle);
        if ($newTitle === '' || $newContent === '') {
            flash_set('error', 'Title and content are required.');
        } elseif (!wiki_can_create($me, $scope)) {
            friendly_error('You are not allowed to create pages here.', 403);
        } elseif (!wiki_can_edit($me, $page)) {
            friendly_error('You are not allowed to edit this page.', 403);
        } elseif ($scope === 'community' && !rate_limit_check('wiki_edit', 30, 600)) {
            flash_set('error', 'Slow down — wiki edit limit reached. Try again in a few minutes.');
        } else {
            $existing = wiki_get_page($pdo, $scope, $ownerId, $newSlug);
            if ($existing !== null && $page !== null && (int)$existing['id'] !== (int)$page['id']) {
                flash_set('error', 'Another page already uses that title.');
            } elseif (!is_staff()) {
                // Non-staff edits (regular users): route through the approval queue.
                $mq = $pdo->prepare(
                    'INSERT INTO moderation_queue
                        (action_type, target_type, ref_id, scope, slug, title, old_content, new_content, note, requested_by)
                     VALUES (\'edit\', \'wiki\', ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $mq->execute([
                    $page !== null ? (string)$page['id'] : null,
                    $scope,
                    $newSlug,
                    $newTitle,
                    $page !== null ? $page['content'] : null,
                    $newContent,
                    mb_substr($note, 0, 1000),
                    $me !== null ? (int)$me['id'] : null,
                ]);
                log_activity('moderation_queued', 'wiki edit ' . $scope . '/' . $newSlug);
                flash_set('success', 'Your edit has been submitted for staff approval.');
                redirect('wiki.php?scope=' . urlencode($scope) . ($page !== null ? '&slug=' . urlencode($page['slug']) : ''));
            } else {
                if ($page !== null) {
                    if ($page['slug'] !== $newSlug || $page['title'] !== $newTitle) {
                        $upd = $pdo->prepare('UPDATE wiki_pages SET slug = ?, title = ? WHERE id = ?');
                        $upd->execute([$newSlug, $newTitle, (int)$page['id']]);
                    }
                    $pdo->prepare('UPDATE wiki_pages SET content = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                        ->execute([$newContent, (int)$page['id']]);
                    $pageId = (int)$page['id'];
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO wiki_pages (scope, owner_id, slug, title, content, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                    );
                    $ins->execute([$scope, $ownerId, $newSlug, $newTitle, $newContent]);
                    $pageId = (int)$pdo->lastInsertId();
                }
                $pdo->prepare(
                    'INSERT INTO wiki_revisions (page_id, user_id, content, note, created_at)
                     VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
                )->execute([$pageId, $me !== null ? (int)$me['id'] : null, $newContent, mb_substr($note, 0, 255)]);
                log_activity('wiki_save', $scope . '/' . $newSlug);
                flash_set('success', 'Page saved.');
                redirect('wiki.php?scope=' . urlencode($scope) . '&slug=' . urlencode($newSlug));
            }
        }
    } elseif ($postAction === 'delete') {
        csrf_verify_or_fail();
        if ($page === null || !wiki_can_delete($me, $page)) {
            friendly_error('You are not allowed to delete this page.', 403);
        }
        if (!is_staff()) {
            $mq = $pdo->prepare(
                'INSERT INTO moderation_queue
                    (action_type, target_type, ref_id, scope, slug, title, old_content, note, requested_by)
                 VALUES (\'delete\', \'wiki\', ?, ?, ?, ?, ?, ?, ?)'
            );
            $mq->execute([
                (string)$page['id'],
                $scope,
                $page['slug'],
                $page['title'],
                $page['content'],
                'Requested deletion of this page',
                $me !== null ? (int)$me['id'] : null,
            ]);
            log_activity('moderation_queued', 'wiki delete ' . $scope . '/' . $slug);
            flash_set('success', 'Your deletion request has been submitted for staff approval.');
            redirect('wiki.php?scope=' . urlencode($scope) . '&slug=' . urlencode($slug));
        }
        $pdo->prepare('DELETE FROM wiki_revisions WHERE page_id = ?')->execute([(int)$page['id']]);
        $pdo->prepare('DELETE FROM wiki_pages WHERE id = ?')->execute([(int)$page['id']]);
        log_activity('wiki_delete', $scope . '/' . $slug);
        flash_set('success', 'Page deleted.');
        redirect('wiki.php?scope=' . urlencode($scope));
    }
}

// ——— view rendering ———
$title = WIKI_SCOPES[$scope] . ($slug !== '' ? ' — ' . ($page['title'] ?? $slug) : '');
page_header($title);
$tabs = wiki_scopes($me);
$editAllowed = wiki_can_edit($me, $page);
?>

<div class="container" style="max-width: 980px;">
    <div class="d-flex align-items-center gap-2 mb-1">
        <h1 class="h4 mb-0"><?= WIKI_MARKS[$scope] ?> <?= e(WIKI_SCOPES[$scope]) ?></h1>
        <?php foreach ($tabs as $s => $label): ?>
            <a class="btn btn-sm <?= $s === $scope ? 'btn-primary' : 'btn-outline-light' ?>"
               href="wiki.php?scope=<?= e($s) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <p class="text-secondary small mb-4">Wiki markup: <code>= Heading</code> <code>**bold**</code> <code>`code`</code> <code>[[Page]]</code> <code>[label](url)</code>, lists, quotes, tables and ``` code blocks.</p>

<?php if ($action === 'edit' || $action === 'new' || isset($previewHtml)): ?>
    <?php if (!is_staff()): ?>
        <div class="alert alert-info py-2 small">Your changes will be submitted for staff approval before they appear on the page.</div>
    <?php endif; ?>
    <form method="post" class="mb-4">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if (isset($previewHtml)): ?>
            <h2 class="h6 mt-3 mb-2">Preview<?= $previewTitle !== '' ? ' — ' . e($previewTitle) : '' ?></h2>
            <div class="card mb-3"><div class="card-body wiki-preview">
                <?= $previewHtml ?>
            </div></div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="wiki-title">Title</label>
            <input class="form-control" id="wiki-title" name="title" required maxlength="255"
                   value="<?= e($_POST['title'] ?? ($page['title'] ?? ($_GET['slug'] ?? ''))) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label" for="wiki-content">Content</label>
            <textarea class="form-control font-monospace" id="wiki-content" name="content" rows="18"
                      placeholder="= Heading&#10;Write with **bold**, `code`, [[links]]..."><?= e($_POST['content'] ?? ($page['content'] ?? '')) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="wiki-note">Edit note <span class="text-secondary small">(optional)</span></label>
            <input class="form-control" id="wiki-note" name="note" maxlength="255"
                   value="<?= e($_POST['note'] ?? '') ?>" placeholder="What did you change?">
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" name="action" value="save">💾 Save page</button>
            <button class="btn btn-outline-light" name="action" value="preview">👁 Preview</button>
            <a class="btn btn-outline-secondary" href="wiki.php?scope=<?= e($scope) ?>&slug=<?= e($slug) ?>">Cancel</a>
        </div>
    </form>

<?php elseif ($action === 'history' && $page !== null): ?>
    <div class="d-flex align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0">History — <?= e($page['title']) ?></h2>
        <a class="btn btn-sm btn-outline-light" href="wiki.php?scope=<?= e($scope) ?>&slug=<?= e($slug) ?>">← Back</a>
    </div>
    <div class="list-group mb-4">
        <?php
        $stmt = $pdo->prepare(
            'SELECT r.*, u.username, u.profile_color
             FROM wiki_revisions r LEFT JOIN users u ON u.id = r.user_id
             WHERE r.page_id = ? ORDER BY r.id DESC LIMIT 100'
        );
        $stmt->execute([(int)$page['id']]);
        $revs = $stmt->fetchAll();
        foreach ($revs as $r):
            $color = $r['profile_color'] ?: 'var(--accent)';
        ?>
            <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <a class="text-decoration-none" href="wiki.php?scope=<?= e($scope) ?>&slug=<?= e($slug) ?>&action=revision&rev=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a>
                <span class="text-secondary small"><?= e($r['created_at']) ?></span>
                <?php if ($r['username'] !== null): ?>
                    <a class="text-decoration-none fw-semibold small" style="color:<?= e($color) ?>" href="profile.php?id=<?= (int)$r['user_id'] ?>"><?= e($r['username']) ?></a>
                <?php else: ?>
                    <span class="text-secondary small">Staff</span>
                <?php endif; ?>
                <?php if ($r['note'] !== '' && $r['note'] !== null): ?>
                    <em class="small text-secondary ms-auto"><?= e($r['note']) ?></em>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($action === 'revision' && $page !== null): ?>
    <?php
    $stmt = $pdo->prepare('SELECT * FROM wiki_revisions WHERE id = ? AND page_id = ?');
    $stmt->execute([(int)($_GET['rev'] ?? 0), (int)$page['id']]);
    $rev = $stmt->fetch();
    if ($rev === false) {
        friendly_error('Revision not found.', 404);
    }
    ?>
    <div class="d-flex align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0">Revision #<?= (int)$rev['id'] ?> — <?= e($page['title']) ?></h2>
        <a class="btn btn-sm btn-outline-light" href="wiki.php?scope=<?= e($scope) ?>&slug=<?= e($slug) ?>&action=history">← History</a>
        <a class="btn btn-sm btn-primary" href="wiki.php?scope=<?= e($scope) ?>&slug=<?= e($slug) ?>&action=edit">Edit</a>
    </div>
    <p class="text-secondary small mb-3">Saved <?= e($rev['created_at']) ?><?= $rev['note'] !== '' && $rev['note'] !== null ? ' — ' . e($rev['note']) : '' ?></p>
    <div class="card mb-4"><div class="card-body"><?= wiki_render($rev['content'], $page['scope']) ?></div></div>

<?php elseif ($page === null && $slug !== ''): ?>
    <div class="alert alert-warning">This page does not exist.
        <?php if (wiki_can_create($me, $scope)): ?>
            <a class="alert-link" href="wiki.php?scope=<?= e($scope) ?>&action=new&slug=<?= e($slug) ?>">Create it</a>
        <?php endif; ?>
    </div>
    <?php include_wiki_list($pdo, $scope, $me, $slug); ?>

<?php elseif ($page !== null): ?>
    <?php
    $pdo->prepare('UPDATE wiki_pages SET views = views + 1 WHERE id = ?')->execute([(int)$page['id']]);
    ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h2 class="h4 mb-0"><?= e($page['title']) ?></h2>
        <div class="ms-auto d-flex gap-2">
            <?php if (wiki_can_edit($me, $page)): ?>
                <a class="btn btn-sm btn-primary" href="wiki.php?scope=<?= e($scope) ?>&slug=<?= e($slug) ?>&action=edit">✏ Edit</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-light" href="wiki.php?scope=<?= e($scope) ?>&slug=<?= e($slug) ?>&action=history">🕘 History</a>
            <?php if (wiki_can_delete($me, $page)): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this page and all its history?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-sm btn-outline-danger">🗑 Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <p class="text-secondary small mb-3">
        by <?= wiki_author($pdo, $page) ?> · updated <?= e($page['updated_at']) ?> · <?= (int)$page['views'] ?> views
        <?php if ((int)$page['locked'] === 1): ?> · <span class="text-warning">🔒 locked</span><?php endif; ?>
    </p>
    <div class="card mb-4"><div class="card-body wiki-content"><?= wiki_render($page['content'], $page['scope']) ?></div></div>

<?php else: ?>
    <?php include_wiki_list($pdo, $scope, $me, $slug); ?>
<?php endif; ?>
</div>

<?php
function include_wiki_list(PDO $pdo, string $scope, ?array $me, string $search): void
{
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '' && $search !== '') {
        $q = $search;
    }
    $ownerId = $scope === 'personal' ? (int)$me['id'] : null;
    ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <form method="get" class="d-flex gap-2 flex-grow-1" style="max-width: 420px;">
            <input type="hidden" name="scope" value="<?= e($scope) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search pages…">
            <button class="btn btn-outline-light">Search</button>
        </form>
        <?php if (wiki_can_create($me, $scope)): ?>
            <a class="btn btn-primary" href="wiki.php?scope=<?= e($scope) ?>&action=new">+ New page</a>
        <?php endif; ?>
    </div>
    <?php
    $sql = 'SELECT * FROM wiki_pages WHERE scope = ? AND owner_id ' . ($ownerId === null ? 'IS NULL' : '= ?');
    $params = [$scope];
    if ($ownerId !== null) {
        $params[] = $ownerId;
    }
    if ($q !== '') {
        $sql .= ' AND (title LIKE ? OR content LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY updated_at DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pages = $stmt->fetchAll();
    if ($pages === []) {
        echo '<div class="alert alert-secondary">No pages' . ($q !== '' ? ' matching “' . e($q) . '”' : ' yet') . '. Create the first one!</div>';
        return;
    }
    ?>
    <div class="list-group mb-4">
        <?php foreach ($pages as $p): ?>
            <a class="list-group-item list-group-item-action d-flex flex-wrap align-items-center gap-2"
               href="wiki.php?scope=<?= e($scope) ?>&slug=<?= e($p['slug']) ?>">
                <span class="fw-semibold"><?= e($p['title']) ?></span>
                <?php if ((int)$p['locked'] === 1): ?><span class="text-warning small">🔒</span><?php endif; ?>
                <span class="text-secondary small ms-auto">updated <?= e($p['updated_at']) ?> · <?= (int)$p['views'] ?> views</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($pages !== [] && count($pages) >= 200): ?>
        <p class="text-secondary small">Showing the 200 most recently updated pages.</p>
    <?php endif;
}

page_footer();
