<?php // includes/footer.php ?>
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <div class="footer-logo"><em>E</em>xpedia PH</div>
      <p>Find your perfect hotel stay across the beautiful Philippines.</p>
    </div>
    <div class="footer-col">
      <h4>Destinations</h4>
      <?php foreach([[3,'Boracay'],[4,'El Nido'],[7,'Siargao'],[9,'Tagaytay'],[2,'Cebu']] as [$id,$city]): ?>
        <a href="<?= SITE_URL ?>/pages/search.php?location_id=<?= $id ?>"><?= $city ?></a>
      <?php endforeach; ?>
    </div>
    <div class="footer-col">
      <h4>Company</h4>
      <a href="#">About Us</a><a href="#">Careers</a><a href="#">Press</a><a href="#">Blog</a>
      <a href="<?= SITE_URL ?>/pages/partner-apply.php" style="color:var(--coral);font-weight:500">List your property</a>
    </div>
    <div class="footer-col">
      <h4>Support</h4>
      <a href="#">Help Center</a><a href="#">Cancellations</a><a href="#">Safety</a><a href="#">Contact</a>
    </div>
  </div>
  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> Expedia PH. All rights reserved.</span>
    <span><a href="#">Privacy</a> · <a href="#">Terms</a> · <a href="#">Cookies</a></span>
  </div>
</footer>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
