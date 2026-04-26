<?php
require_once __DIR__ . '/../includes/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About WTRS - WMSU Repository</title>
  
  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/nunito.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/playfair-display.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/fonts/google/css/cormorant-garamond.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/phosphor/css/phosphor-all.css">

  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/global.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/public.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/about.css">
</head>
<body style="background: var(--off-white);">

  <?php require_once __DIR__ . '/../includes/layout_public_nav.php'; ?>

  <!-- Hero Section -->
  <section class="about-hero">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; gap: 4rem;">
      <div class="ah-left">
        <span class="ah-label" style="color: var(--crimson); font-weight: 800; letter-spacing: 0.1em; font-size: 0.75rem;">INSTITUTIONAL EXCELLENCE</span>
        <h1 class="ah-title" style="font-family: var(--font-serif); font-size: 3.5rem; color: var(--text-dark); margin-top: 1rem;">Preserving the Academic Legacy.</h1>
      </div>
      <div class="ah-quote" style="max-width: 400px; padding: 2rem; border-left: 4px solid var(--gold); background: var(--surface); box-shadow: var(--shadow-sm); border-radius: 0 var(--radius) var(--radius) 0;">
        <p style="font-family: 'Georgia', serif; font-style: italic; font-size: 1.1rem; color: var(--text-dark); margin-bottom: 1rem;">"Research is formalized curiosity. It is poking and prying with a purpose."</p>
        <cite style="font-weight: 800; font-size: 0.8rem; color: var(--crimson);">&mdash; Zora Neale Hurston</cite>
      </div>
    </div>
  </section>

  <!-- Explanation Section -->
  <section class="container" style="padding: 6rem 1.5rem;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6rem; align-items: center;">
      <div class="ai-text-box">
        <h2 style="font-family: var(--font-serif); font-size: 2.5rem; margin-bottom: 2rem;">About the WMSU Repository</h2>
        <p style="font-size: 1.1rem; line-height: 1.8; color: var(--text-muted); margin-bottom: 2rem;">The Western Mindanao State University Thesis Repository System (WTRS) serves as a digital sanctuary for academic rigor. Our platform is designed to treat every thesis, dissertation, and research paper not as a static file, but as a living artifact of scholarly contribution.</p>
        <p style="font-size: 1.1rem; line-height: 1.8; color: var(--text-muted);">By centralizing the University's intellectual output, WTRS provides researchers with a sophisticated interface to explore, cite, and build upon the foundations laid by their predecessors. It is more than a database; it is a bridge between generations of innovators.</p>
      </div>
      
      <div style="position: relative;">
        <img src="../assets/images/library.png" alt="Library" style="border-radius: var(--radius); box-shadow: var(--shadow-lg); width: 100%; height: 500px; object-fit: cover;">
        <div style="position: absolute; bottom: -2rem; left: -2rem; background: var(--crimson); color: white; padding: 2rem; border-radius: var(--radius-sm); box-shadow: var(--shadow-md);">
          <h3 style="font-size: 3rem; font-weight: 800; margin: 0; color: white;">50+</h3>
          <p style="font-size: 0.7rem; font-weight: 800; letter-spacing: 0.1em; color: rgba(255,255,255,0.7);">YEARS OF RESEARCH</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Journey Grid -->
  <section class="about-journey" style="background: var(--surface); padding: 8rem 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
    <div class="container">
      <div style="text-align: center; margin-bottom: 5rem;">
        <h2 style="font-family: var(--font-serif); font-size: 2.5rem;">The Scholarly Journey</h2>
        <p style="color: var(--text-muted); font-size: 1.1rem;">A streamlined process from initial registration to global academic visibility.</p>
      </div>
      
      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 2.5rem;">
        <!-- Step 1 -->
        <article class="card-academic" style="padding: 2.5rem; text-align: center;">
          <div style="width: 60px; height: 60px; background: var(--crimson-faint); color: var(--crimson); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; font-size: 1.5rem;">
            <i class="ph-fill ph-user-plus"></i>
          </div>
          <span style="font-size: 0.65rem; font-weight: 800; color: var(--gold); letter-spacing: 0.15em; display: block; margin-bottom: 1rem;">STEP 01</span>
          <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Register</h3>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">Secure your institutional credentials to gain access to the secure submission portal.</p>
        </article>

        <!-- Step 2 -->
        <article class="card-academic" style="padding: 2.5rem; text-align: center;">
          <div style="width: 60px; height: 60px; background: var(--crimson-faint); color: var(--crimson); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; font-size: 1.5rem;">
            <i class="ph-fill ph-file-text"></i>
          </div>
          <span style="font-size: 0.65rem; font-weight: 800; color: var(--gold); letter-spacing: 0.15em; display: block; margin-bottom: 1rem;">STEP 02</span>
          <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Submit</h3>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">Upload your final manuscript with comprehensive metadata and categorical tags.</p>
        </article>

        <!-- Step 3 -->
        <article class="card-academic" style="padding: 2.5rem; text-align: center;">
          <div style="width: 60px; height: 60px; background: var(--crimson-faint); color: var(--crimson); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; font-size: 1.5rem;">
            <i class="ph-fill ph-check-square-offset"></i>
          </div>
          <span style="font-size: 0.65rem; font-weight: 800; color: var(--gold); letter-spacing: 0.15em; display: block; margin-bottom: 1rem;">STEP 03</span>
          <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Review</h3>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">Our curation team verifies the submission for formatting and metadata integrity.</p>
        </article>

        <!-- Step 4 -->
        <article class="card-academic" style="padding: 2.5rem; text-align: center;">
          <div style="width: 60px; height: 60px; background: var(--crimson-faint); color: var(--crimson); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; font-size: 1.5rem;">
            <i class="ph-fill ph-books"></i>
          </div>
          <span style="font-size: 0.65rem; font-weight: 800; color: var(--gold); letter-spacing: 0.15em; display: block; margin-bottom: 1rem;">STEP 04</span>
          <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Access</h3>
          <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">Your research becomes a permanent part of the WMSU digital academic archive.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- Support Section -->
  <section class="container" style="padding: 8rem 1.5rem;">
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; display: flex; box-shadow: var(--shadow-lg);">
      <div style="flex: 1; padding: 5rem; background: var(--off-white); border-right: 1px solid var(--border);">
        <h2 style="font-family: var(--font-serif); font-size: 2.5rem; margin-bottom: 1.5rem;">Need Academic Support?</h2>
        <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.7; margin-bottom: 3rem;">Our librarians and technical team are available to assist you with submission guidelines or platform troubleshooting.</p>
        
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
          <div style="display: flex; align-items: center; gap: 1rem; font-weight: 700;">
            <i class="ph-fill ph-envelope" style="color: var(--crimson); font-size: 1.5rem;"></i> support.wtrs@wmsu.edu.ph
          </div>
          <div style="display: flex; align-items: center; gap: 1rem; font-weight: 700;">
            <i class="ph-fill ph-phone-call" style="color: var(--crimson); font-size: 1.5rem;"></i> +63 62 991 1771
          </div>
        </div>
      </div>
      <div style="flex: 1; padding: 5rem;">
        <h3 style="font-weight: 800; font-size: 0.8rem; color: var(--crimson); letter-spacing: 0.1em; margin-bottom: 2rem; text-transform: uppercase;">Quick Assistance</h3>
        <form>
          <div style="margin-bottom: 1.5rem;">
            <input type="text" placeholder="Your Subject" style="width: 100%; padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-sm); font-family: inherit;">
          </div>
          <div style="margin-bottom: 2rem;">
            <textarea placeholder="How can we help?" style="width: 100%; height: 120px; padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-sm); font-family: inherit; resize: none;"></textarea>
          </div>
          <button type="button" class="btn btn-primary" style="width: 100%; padding: 1.25rem;">SEND REQUEST</button>
        </form>
      </div>
    </div>
  </section>

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
