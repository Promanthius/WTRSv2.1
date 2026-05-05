<?php
require_once __DIR__ . '/../includes/session.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archival Guidelines - WMSU Repository</title>

    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/nunito.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/playfair-display.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/cormorant-garamond.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/css/phosphor-all.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/public.css">

    <style>
        .guidelines-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
            margin-bottom: 5rem;
        }

        .guide-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 3rem;
            transition: all var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .guide-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--crimson);
        }

        .guide-icon {
            font-size: 3rem;
            color: var(--crimson);
            margin-bottom: 2rem;
            opacity: 0.9;
            background: var(--crimson-faint);
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .guide-card h3 {
            font-family: var(--font-serif);
            font-size: 1.6rem;
            color: var(--text-dark);
            margin-bottom: 1.25rem;
            border-bottom: 2px solid var(--off-white);
            padding-bottom: 1rem;
        }

        .guide-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .guide-list li {
            position: relative;
            padding-left: 1.75rem;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .guide-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--crimson);
            font-weight: 900;
        }

        .cta-box {
            background: var(--crimson);
            color: #fff;
            padding: 5rem 3rem;
            border-radius: var(--radius-lg);
            text-align: center;
            margin-bottom: 6rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .cta-box::after {
            content: 'WMSU';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 15rem;
            font-weight: 900;
            color: rgba(255,255,255,0.03);
            pointer-events: none;
        }

        .cta-box h2 {
            font-family: var(--font-serif);
            font-size: 2.75rem;
            margin-bottom: 1.5rem;
            color: #fff;
        }

        .cta-box p {
            font-size: 1.15rem;
            opacity: 0.9;
            margin-bottom: 3rem;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
        }
    </style>
</head>

<body style="background: var(--off-white);">

    <?php require_once __DIR__ . '/../includes/layout_public_nav.php'; ?>

    <main>
        <section class="archive-hero">
            <div class="container">
                <h1>Repository <span>Standards</span></h1>
                <p>Establishing scholarly excellence through rigorous archival guidelines and institutional standards of Western Mindanao State University.</p>
            </div>
        </section>

        <section class="container pub-results-section">
            <div class="guidelines-grid">

                <!-- Submission Guide -->
                <article class="guide-card">
                    <div class="guide-icon"><i class="ph-bold ph-file-pdf"></i></div>
                    <h3>Manuscript Standards</h3>
                    <ul class="guide-list">
                        <li>Submissions must be in standard PDF format (Maximum file size: 20MB).</li>
                        <li>Original manuscripts must include a clear and concise abstract.</li>
                        <li>Follow your department's specific formatting and citation guidelines.</li>
                        <li>Ensure all group members' names are listed for collaborative projects.</li>
                    </ul>
                </article>

                <!-- Review Cycle -->
                <article class="guide-card">
                    <div class="guide-icon"><i class="ph-bold ph-shield-check"></i></div>
                    <h3>The Review Cycle</h3>
                    <ul class="guide-list">
                        <li>All submissions undergo a dual verification process.</li>
                        <li>First Review: Assigned faculty adviser validation.</li>
                        <li>Second Review: Institutional repository system audit.</li>
                        <li>Artifacts are only archived publicly upon final approval.</li>
                    </ul>
                </article>

                <!-- Rights & Ethics -->
                <article class="guide-card">
                    <div class="guide-icon"><i class="ph-bold ph-gavel"></i></div>
                    <h3>Archival Rights</h3>
                    <ul class="guide-list">
                        <li>Authors retain intellectual ownership of their work.</li>
                        <li>WMSU maintains a non-exclusive license for institutional hosting.</li>
                        <li>The repository follows academic integrity and anti-plagiarism policies.</li>
                        <li>Public access is provided to support scholarly advancement.</li>
                    </ul>
                </article>

            </div>

            <div class="cta-box">
                <h2>Ready to Contribute?</h2>
                <p>Join the collection of excellence and ensure your research is preserved for future generations of Crimsonian scholars.</p>
                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-secondary" style="padding: 1.25rem 3rem; font-size: 1rem;">
                    <i class="ph-bold ph-arrow-square-out"></i> Proceed to Student Portal
                </a>
            </div>
        </section>
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