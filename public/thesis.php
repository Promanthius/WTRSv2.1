<?php
require_once __DIR__ . '/../includes/session.php';

$thesisCode = $_GET['code'] ?? null;

if (!$thesisCode) {
    header('Location: archive.php');
    exit;
}

// Fetch thesis
$stmt = $pdo->prepare("SELECT t.*, 
                        u.first_name, u.last_name, u.college,
                        a.first_name as adviser_first, a.last_name as adviser_last
                       FROM theses t
                       JOIN users u ON t.author_id = u.id
                       LEFT JOIN users a ON t.adviser_id = a.id
                       WHERE t.thesis_code = :code AND t.status = 'archived'");
$stmt->execute(['code' => $thesisCode]);
$thesis = $stmt->fetch();

if (!$thesis) {
    header('Location: archive.php');
    exit;
}

// Increment views
$pdo->prepare("UPDATE theses SET views = views + 1 WHERE id = ?")->execute([$thesis['id']]);

// Fetch versions
$vStmt = $pdo->prepare("SELECT * FROM thesis_versions WHERE thesis_id = :id AND status = 'approved' ORDER BY submitted_at DESC");
$vStmt->execute(['id' => $thesis['id']]);
$versions = $vStmt->fetchAll();

// Fetch recommended (other published theses)
$recStmt = $pdo->prepare("SELECT t.thesis_code, t.title, u.college 
                          FROM theses t 
                          JOIN users u ON t.author_id = u.id
                          WHERE t.status = 'archived' AND t.id != :id 
                          ORDER BY t.views DESC LIMIT 3");
$recStmt->execute(['id' => $thesis['id']]);
$recommended = $recStmt->fetchAll();

// Custom CSS for Thesis View - Unified with Crimsonian System
ob_start();
?>
<style>
  .thesis-page-container { max-width: 1000px; margin: 0 auto; padding: 4rem 2rem; }
  
  .digital-sheet { background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-md); padding: 5rem 6rem; position: relative; overflow: hidden; }
  .digital-sheet::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px; background: var(--crimson); }
  
  .sheet-badge { position: absolute; top: 3.5rem; right: 6rem; }

  .document-header { text-align: center; margin-bottom: 4rem; border-bottom: 1px solid var(--border); padding-bottom: 3.5rem; }
  .document-code { font-size: 0.75rem; font-weight: 800; letter-spacing: 0.15em; color: var(--crimson); margin-bottom: 1.5rem; display: block; text-transform: uppercase; }
  .document-title { font-family: var(--font-serif); font-size: 2.75rem; font-weight: 800; color: var(--text-dark); line-height: 1.15; margin-bottom: 2rem; }
  
  .institutional-banner { display: flex; justify-content: center; gap: 4rem; margin-top: 1.5rem; }
  .banner-item { text-align: center; }
  .banner-label { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem; display: block; }
  .banner-value { font-family: var(--font-base); font-weight: 750; font-size: 0.95rem; color: var(--text-dark); }

  .abstract-title { font-family: var(--font-serif); font-size: 1.5rem; font-weight: 800; margin-bottom: 1.5rem; text-align: center; color: var(--text-dark); }
  .abstract-body { font-family: 'Georgia', serif; font-size: 1.2rem; line-height: 1.85; color: var(--text-dark); text-align: justify; margin-bottom: 4rem; position: relative; }
  .abstract-body::first-letter { font-size: 3rem; font-weight: 800; float: left; margin-right: 0.5rem; line-height: 1; color: var(--crimson); font-family: var(--font-serif); }

  .manuscript-link-card { background: var(--off-white); border-radius: 8px; border: 1px solid var(--border); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
  .manuscript-link-card i { font-size: 2.5rem; color: var(--crimson); }
  
  .recommended-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem; }
  .rec-card { background: white; border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; transition: transform 0.2s; }
  .rec-card:hover { transform: translateY(-5px); border-color: var(--crimson); box-shadow: var(--shadow-sm); }
  .rec-card h4 { font-family: var(--font-serif); font-size: 1rem; color: var(--text-dark); margin-bottom: 0.75rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>
<?php
$extraCss = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($thesis['title']) ?> - WMSU Repository</title>
  
  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/cormorant-garamond.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/css/phosphor-all.css">

  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/global.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/public.css">

  <?= $extraCss ?>
</head>
<body style="background: var(--off-white);">

  <?php require_once __DIR__ . '/../includes/layout_public_nav.php'; ?>

  <main class="thesis-page-container">
    
    <!-- Breadcrumb -->
    <nav style="margin-bottom: 2rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em;">
      <a href="archive.php" style="color: var(--text-muted); text-decoration: none;">ARCHIVE</a> 
      <i class="ph ph-caret-right" style="margin: 0 0.5rem; vertical-align: middle;"></i>
      <span style="color: var(--crimson);"><?= htmlspecialchars($thesis['thesis_code']) ?></span>
    </nav>

    <article class="digital-sheet">
       <div class="sheet-badge">
         <span class="badge badge-approved"><i class="ph-fill ph-seal-check"></i> PEER REVIEWED</span>
       </div>
       
       <header class="document-header">
         <span class="document-code"><?= htmlspecialchars($thesis['thesis_code']) ?> Repository Artifact</span>
         <h1 class="document-title"><?= htmlspecialchars($thesis['title']) ?></h1>
         
         <div class="institutional-banner">
            <div class="banner-item">
              <span class="banner-label">CONTRIBUTOR / AUTHOR</span>
              <span class="banner-value"><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></span>
            </div>
            <div class="banner-item">
              <span class="banner-label">COLLEGE / FACULTY</span>
              <span class="banner-value"><?= htmlspecialchars($thesis['college'] ?? 'CCS') ?></span>
            </div>
            <div class="banner-item">
              <span class="banner-label">ARCHIVAL DATE</span>
              <span class="banner-value"><?= date('F Y', strtotime($thesis['created_at'])) ?></span>
            </div>
         </div>
       </header>

       <h2 class="abstract-title">Academic Abstract</h2>
       <div class="abstract-body">
         <?= nl2br(htmlspecialchars($thesis['abstract'])) ?>
       </div>

       <!-- Manuscript Actions -->
       <div class="manuscript-link-card">
          <div style="display: flex; align-items: center; gap: 1.5rem;">
            <i class="ph-fill ph-file-pdf"></i>
            <div>
              <h4 style="font-family: var(--font-serif); font-size: 1.1rem; color: var(--text-dark); margin-bottom: 0.25rem;">Final Manuscript</h4>
              <p style="font-size: 0.75rem; color: var(--text-muted);">Version <?= htmlspecialchars($versions[0]['version_number'] ?? '1.0') ?> • PDF Format • Verified Metadata</p>
            </div>
          </div>
          <a href="download.php?id=<?= $thesis['id'] ?>" target="_blank" class="btn btn-primary">
            <i class="ph-bold ph-eye"></i> VIEW FULL MANUSCRIPT
          </a>
       </div>

       <footer class="sheet-stats-footer">
          <div>
            <span style="font-weight:700;">Views:</span> <?= number_format($thesis['views']) ?> &bull; 
            <span style="font-weight:700;">Downloads:</span> <?= number_format($thesis['downloads']) ?>
          </div>
          <div style="font-size: 0.7rem; letter-spacing: 0.05em; font-weight: 800; text-transform: uppercase; color: var(--text-muted);">
            © <?= date('Y') ?> WESTERN MINDANAO STATE UNIVERSITY
          </div>
       </footer>
    </article>

    <!-- Recommended Readings -->
    <?php if (count($recommended) > 0): ?>
    <section style="margin-top: 5rem;">
      <h3 style="font-family: var(--font-serif); font-size: 1.5rem; font-weight: 800; border-bottom: 2px solid var(--border); padding-bottom: 1rem; margin-bottom: 2rem; color: var(--text-dark);">Recommended Scholarly Readings</h3>
      
      <div class="recommended-grid">
        <?php foreach ($recommended as $rec): ?>
          <article class="rec-card">
            <span style="font-size: 0.6rem; font-weight: 800; color: var(--crimson); text-transform: uppercase; margin-bottom: 0.5rem; display: block;"><?= htmlspecialchars($rec['college'] ?? 'CCS') ?></span>
            <h4><?= htmlspecialchars($rec['title']) ?></h4>
            <a href="thesis.php?code=<?= htmlspecialchars($rec['thesis_code']) ?>" style="font-size: 0.75rem; font-weight: 800; color: var(--crimson); text-decoration: none; display: flex; align-items: center; gap: 0.4rem;">
              EXPLORE METADATA <i class="ph-bold ph-arrow-right"></i>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </main>

  <footer class="pub-footer">
    <div style="margin-bottom: 2rem;">
      <i class="ph-fill ph-seal" style="font-size: 3rem; color: var(--crimson); opacity: 0.1;"></i>
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
