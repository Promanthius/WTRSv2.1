  </main>
</div>

<!-- ═══════════════════════════════════════════
     GLOBAL SCRIPTS
═══════════════════════════════════════════ -->
  <!-- Global Confirmation Modal -->
  <div id="confirmModal" class="confirm-modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="confirm-modal-content" style="background: white; border-radius: var(--radius); width: 90%; max-width: 400px; padding: 2rem; box-shadow: var(--shadow-lg); text-align: center;">
      <div id="confirmIcon" style="font-size: 3rem; color: var(--crimson); margin-bottom: 1.5rem;"><i class="ph-fill ph-warning-circle"></i></div>
      <h3 id="confirmTitle" style="font-family: var(--font-serif); margin-bottom: 0.75rem;">Are you sure?</h3>
      <p id="confirmMessage" style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">This action cannot be undone.</p>
      <div style="display: flex; gap: 1rem;">
        <button id="confirmCancel" class="btn btn-secondary" style="flex: 1;">Cancel</button>
        <button id="confirmProceed" class="btn btn-primary" style="flex: 1; background: var(--crimson);">Confirm</button>
      </div>
    </div>
  </div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
