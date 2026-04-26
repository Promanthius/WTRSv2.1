<?php
require_once __DIR__ . '/../includes/session.php';

// Search query
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'latest';

$sortOptions = [
  'latest' => [
    'label' => 'LATEST FIRST',
    'order_sql' => "COALESCE(t.hardbound_received_at, t.updated_at, t.created_at) DESC"
  ],
  'oldest' => [
    'label' => 'OLDEST FIRST',
    'order_sql' => "COALESCE(t.hardbound_received_at, t.updated_at, t.created_at) ASC"
  ],
  'title_az' => [
    'label' => 'TITLE (A-Z)',
    'order_sql' => "t.title ASC"
  ],
  'most_viewed' => [
    'label' => 'MOST VIEWED',
    'order_sql' => "t.views DESC, COALESCE(t.hardbound_received_at, t.updated_at, t.created_at) DESC"
  ]
];
if (!isset($sortOptions[$sort])) {
  $sort = 'latest';
}

// Build query - only show published theses publicly
$sql = "SELECT t.*, u.first_name, u.last_name, u.college 
        FROM theses t
        JOIN users u ON t.author_id = u.id
        WHERE t.status = 'archived'";
$params = [];

if (!empty($search)) {
  $sql .= " AND (t.title LIKE :search OR t.abstract LIKE :search2 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search3)";
  $params['search'] = "%$search%";
  $params['search2'] = "%$search%";
  $params['search3'] = "%$search%";
}

$sql .= " ORDER BY " . $sortOptions[$sort]['order_sql'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$theses = $stmt->fetchAll();

// Total published count
$countStmt = $pdo->query("SELECT COUNT(*) FROM theses WHERE status = 'archived'");
$totalApproved = $countStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Published Archives - WMSU Repository</title>

  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/cormorant-garamond.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/css/phosphor-all.css">

  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/global.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/public.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/archive.css">
</head>

<body style="background: var(--off-white); font-family: var(--font-base);">

  <?php require_once __DIR__ . '/../includes/layout_public_nav.php'; ?>

  <main>
    <!-- Search Hero Section -->
    <section class="archive-hero">
      <div class="container">
        <h1>A Crimsonian's <span>Wisdom</span></h1>
        <p>A prestigious digital repository of published scholarly work from the Western Mindanao State University community.</p>

        <form action="archive.php" method="GET" class="search-container">
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <div class="search-bar-giant">
            <input type="text" name="q" placeholder="Search research title, keywords, or author..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            <button type="submit" class="btn btn-primary"><i class="ph ph-magnifying-glass"></i> Explore Archives</button>
          </div>
        </form>
      </div>
    </section>

    <!-- Archive Results -->
    <section class="container pub-results-section">

      <div class="results-header">
        <h2 class="results-title">
          Found <span><?= count($theses) ?></span> scholarly artifact<?= count($theses) !== 1 ? 's' : '' ?>
        </h2>
        <div class="pub-results-sort">
          <form method="GET" action="archive.php" class="pub-results-sort-form">
            <?php if ($search !== ''): ?>
              <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>
            <span>Sort by:</span>
            <select name="sort" onchange="this.form.submit()" class="pub-sort-select">
              <?php foreach ($sortOptions as $key => $opt): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= htmlspecialchars($opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>

      <div class="archive-grid">
        <?php if (count($theses) > 0): ?>
          <?php foreach ($theses as $thesis): ?>
            <a href="thesis.php?code=<?= htmlspecialchars($thesis['thesis_code']) ?>" style="text-decoration: none; display: block;">
              <article class="thesis-card">
                <div class="thesis-thumb">
                  <i class="ph-fill ph-scroll"></i>
                  <span class="thesis-thumb-text">THESIS</span>
                </div>
                <div class="thesis-content">
                  <div class="thesis-meta-top">
                    <span class="thesis-date"><?= date('F j, Y', strtotime($thesis['created_at'])) ?></span>
                    <span class="badge badge-approved" style="font-size: 0.6rem; letter-spacing: 0.05em; padding: 0.2rem 0.5rem;"><?= htmlspecialchars($thesis['college'] ?? 'CCS') ?></span>
                  </div>
                  <h3><?= htmlspecialchars($thesis['title']) ?></h3>
                  <div class="thesis-authors">
                    <span><i class="ph-bold ph-user"></i> <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></span>
                  </div>
                  <?php $abs = (string)($thesis['abstract'] ?? ''); ?>
                  <p class="thesis-abstract"><?= htmlspecialchars(mb_substr($abs, 0, 250)) ?><?= mb_strlen($abs) > 250 ? '...' : '' ?></p>
                  <div class="btn-view">
                    READ MANUSCRIPT <i class="ph-bold ph-arrow-right"></i>
                  </div>
                </div>
              </article>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="pub-empty-state card-academic">
            <i class="ph-fill ph-file-search" style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.2; color: var(--crimson);"></i>
            <h3>No Research Found</h3>
            <p>
              <?php if (!empty($search)): ?>
                We couldn't find any published manuscripts matching "<strong><?= htmlspecialchars($search) ?></strong>". Try adjusting your keywords.
              <?php else: ?>
                No published theses are available yet. Check back soon.
              <?php endif; ?>
            </p>
            <?php if (!empty($search)): ?>
              <a href="archive.php" class="btn btn-primary">
                <i class="ph-bold ph-arrow-counter-clockwise"></i> CLEAR SEARCH
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

    </section>
  </main>

  <footer class="pub-footer">
    <div style="margin-bottom: 2rem;">
      <img
        src="<?= BASE_URL ?>assets/images/wmsu-logo.png"
        alt="WMSU Logo"
        style="width: 72px; height: 72px; object-fit: contain; opacity: 0.12;"
      />
    </div>
    <p style="font-size: 0.85rem; color: var(--text-muted);">&copy; <?= date('Y') ?> Western Mindanao State University. Scholarly Repository System.</p>
    <div style="display: flex; justify-content: center; gap: 2rem; margin-top: 2rem;">
      <a href="guidelines.php" style="color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 700;">GUIDELINES</a>
      <a href="about.php" style="color: var(--text-muted); font-size: 0.75rem; text-decoration: none; font-weight: 700;">ABOUT</a>
      <a href="<?= BASE_URL ?>auth/login.php" style="color: var(--crimson); font-size: 0.75rem; text-decoration: none; font-weight: 800; letter-spacing: 0.05em;">PORTAL LOGIN</a>
    </div>
  </footer>

  <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>