<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Search Hotels';

$locId    = (int)($_GET['location_id'] ?? 0);
$checkIn  = $_GET['check_in']   ?? '';
$checkOut = $_GET['check_out']  ?? '';
$guests   = max(1,(int)($_GET['guests']    ?? 1));
$stars    = (int)($_GET['stars']           ?? 0);
$minP     = (float)($_GET['min_price']     ?? 0);
$maxP     = (float)($_GET['max_price']     ?? 0);
$sort     = in_array($_GET['sort']??'',['rating','price_asc','price_desc','name']) ? $_GET['sort'] : 'rating';
$propName = trim($_GET['name'] ?? '');
$propType = in_array($_GET['type']??'',['hotels','homes']) ? $_GET['type'] : 'all';

// Amenity filter — comma-separated list e.g. "Pool,WiFi"
$amenFilter = array_filter(array_map('trim', explode(',', $_GET['amenities'] ?? '')));
$allAmenities = ['Free WiFi','Pool','Gym','Spa','Restaurant','Parking','Bar','Beach Access','Airport Shuttle','Pet Friendly'];

// Homes are hotels with "Resort","Villa","Cottage","Hideaway","Homestay" in the name
$homeKeywords = ['Resort','Villa','Cottage','Hideaway','Homestay','Retreat','Lodge'];

$where  = ['h.is_active=1'];
$params = [];
if ($locId)    { $where[] = 'h.location_id=?'; $params[] = $locId; }
if ($stars)    { $where[] = 'h.stars=?';        $params[] = $stars; }
if ($propName) { $where[] = 'h.name LIKE ?';    $params[] = '%'.$propName.'%'; }
if ($guests > 1) { $where[] = 'EXISTS(SELECT 1 FROM rooms r WHERE r.hotel_id=h.id AND r.max_guests>=? AND r.is_available=1)'; $params[] = $guests; }
if ($minP > 0)   { $where[] = 'EXISTS(SELECT 1 FROM rooms r WHERE r.hotel_id=h.id AND r.price_per_night>=?)'; $params[] = $minP; }
if ($maxP > 0)   { $where[] = 'EXISTS(SELECT 1 FROM rooms r WHERE r.hotel_id=h.id AND r.price_per_night<=?)'; $params[] = $maxP; }
foreach ($amenFilter as $am) {
    $where[] = 'h.amenities LIKE ?';
    $params[] = '%' . $am . '%';
}
if ($propType === 'homes') {
    $kw = array_map(fn($k) => 'h.name LIKE ?', $homeKeywords);
    $where[] = '(' . implode(' OR ', $kw) . ')';
    foreach ($homeKeywords as $k) $params[] = '%'.$k.'%';
} elseif ($propType === 'hotels') {
    $kw = array_map(fn($k) => 'h.name LIKE ?', $homeKeywords);
    $where[] = 'NOT (' . implode(' OR ', $kw) . ')';
    foreach ($homeKeywords as $k) $params[] = '%'.$k.'%';
}

$orderMap = ['rating'=>'h.rating DESC','price_asc'=>'minp ASC','price_desc'=>'minp DESC','name'=>'h.name ASC'];
$sql = "SELECT h.*,l.city,l.country,(SELECT MIN(r.price_per_night) FROM rooms r WHERE r.hotel_id=h.id AND r.is_available=1) AS minp
        FROM hotels h JOIN locations l ON l.id=h.location_id
        WHERE ".implode(' AND ',$where)." ORDER BY ".$orderMap[$sort];
$stmt = db()->prepare($sql); $stmt->execute($params);
$hotels    = $stmt->fetchAll();
$locations = db()->query('SELECT * FROM locations ORDER BY city')->fetchAll();
$currentLoc = null;
foreach ($locations as $l) { if ($l['id']==$locId) { $currentLoc=$l; break; } }

$hasFilters = $locId||$stars||$minP||$maxP||$propName||$propType!=='all'||!empty($amenFilter);

include __DIR__ . '/../includes/header.php';
?>
<div>
<div class="page-header">
  <div class="container">
    <h1><?= $currentLoc ? 'Hotels in '.e($currentLoc['city']) : 'All Hotels in the Philippines' ?></h1>
    <p><?= count($hotels) ?> propert<?= count($hotels)===1?'y':'ies' ?> found<?= $checkIn&&$checkOut?' · '.e($checkIn).' → '.e($checkOut):'' ?></p>
  </div>
</div>

<!-- Sticky search bar -->
<div style="background:#fff;border-bottom:1px solid var(--border);padding:10px 0;box-shadow:var(--shadow-sm)">
  <div class="container">
    <form class="search-bar" style="max-width:100%;padding:5px" action="" method="GET">
      <div class="sf">
        <label>Destination</label>
        <select name="location_id">
          <option value="">Anywhere</option>
          <?php foreach ($locations as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $locId==$l['id']?'selected':'' ?>><?= e($l['city']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sdiv"></div>
      <div class="sf"><label>Check-in</label><input type="date" id="check_in" name="check_in" value="<?= e($checkIn) ?>"></div>
      <div class="sdiv"></div>
      <div class="sf"><label>Check-out</label><input type="date" id="check_out" name="check_out" value="<?= e($checkOut) ?>"></div>
      <div class="sdiv"></div>
      <div class="sf" style="max-width:85px">
        <label>Guests</label>
        <select name="guests">
          <?php for ($i=1;$i<=8;$i++): ?><option value="<?= $i ?>" <?= $guests==$i?'selected':'' ?>><?= $i ?></option><?php endfor; ?>
        </select>
      </div>
      <input type="hidden" name="stars"     id="star_filter" value="<?= $stars ?>">
      <input type="hidden" name="sort"      value="<?= e($sort) ?>">
      <input type="hidden" name="min_price" value="<?= $minP ?>">
      <input type="hidden" name="max_price" value="<?= $maxP ?>">
      <input type="hidden" name="type"      value="<?= e($propType) ?>">
      <input type="hidden" name="name"      value="<?= e($propName) ?>">
      <input type="hidden" name="amenities" value="<?= e(implode(',',$amenFilter)) ?>">
      <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <!-- Property type tabs — centred -->
    <div style="display:flex;justify-content:center;gap:6px;padding:10px 0 4px">
      <?php foreach(['all'=>'🏠 All Stays','hotels'=>'🏨 Hotels','homes'=>'🌴 Homes & Resorts'] as $val=>$lbl): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['type'=>$val])) ?>"
           style="padding:6px 20px;border-radius:20px;font-size:.82rem;font-weight:500;border:1.5px solid;text-decoration:none;
                  <?= $propType===$val ? 'background:var(--navy);color:#fff;border-color:var(--navy)' : 'background:#fff;color:var(--text2);border-color:var(--border)' ?>">
          <?= $lbl ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="container" style="padding-top:26px;padding-bottom:60px">
  <div style="display:grid;grid-template-columns:240px 1fr;gap:26px;align-items:start">

    <!-- SIDEBAR FILTERS -->
    <aside class="filters">
      <h3>Filters</h3>
      <div class="filter-block">
        <h4>Search by name</h4>
        <div style="display:flex;gap:6px">
          <input type="text" id="prop_name" placeholder="Property name…" value="<?= e($propName) ?>"
                 style="flex:1;padding:8px 10px;font-size:.84rem">
          <button type="button" onclick="applyName()" class="btn btn-navy btn-sm">Go</button>
        </div>
      </div>
      <div class="filter-block">
        <h4>Star rating</h4>
        <div class="star-btns">
          <?php foreach ([5,4,3,2] as $s): ?>
            <button type="button" class="star-btn <?= $stars===$s?'on':'' ?>" data-s="<?= $s ?>" onclick="applyStar(<?= $s ?>)"><?= str_repeat('★',$s) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="filter-block">
        <h4>Price per night (₱)</h4>
        <div class="range-row">
          <input type="number" id="min_p" placeholder="Min" value="<?= $minP ?: '' ?>" min="0">
          <span style="color:var(--text3)">–</span>
          <input type="number" id="max_p" placeholder="Max" value="<?= $maxP ?: '' ?>" min="0">
        </div>
        <button type="button" class="btn btn-outline btn-sm mt-2" style="width:100%;margin-top:8px" onclick="applyPrice()">Apply</button>
      </div>
      <div class="filter-block">
        <h4>Sort by</h4>
        <select onchange="applySort(this.value)" style="width:100%">
          <option value="rating"     <?= $sort==='rating'    ?'selected':'' ?>>Top rated</option>
          <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low → High</option>
          <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High → Low</option>
          <option value="name"       <?= $sort==='name'      ?'selected':'' ?>>Name A–Z</option>
        </select>
      </div>
      <div class="filter-block">
        <h4>Amenities</h4>
        <div style="display:flex;flex-direction:column;gap:7px">
          <?php foreach ($allAmenities as $am):
            $checked = in_array($am, $amenFilter);
          ?>
          <label style="display:flex;align-items:center;gap:8px;font-size:.84rem;cursor:pointer;color:var(--text)">
            <input type="checkbox" class="amen-cb" value="<?= e($am) ?>" <?= $checked?'checked':'' ?>
                   style="width:15px;height:15px;accent-color:var(--navy);cursor:pointer">
            <?= e($am) ?>
          </label>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline btn-sm" style="width:100%;margin-top:10px" onclick="applyAmen()">Apply</button>
      </div>
      <?php if ($hasFilters): ?>
        <a href="search.php" style="font-size:.82rem;color:var(--coral)">✕ Clear all filters</a>
      <?php endif; ?>
    </aside>

    <!-- RESULTS -->
    <div>
      <?php if (empty($hotels)): ?>
        <div class="empty"><div class="icon">🏨</div><h3>No hotels found</h3><p>Try adjusting your filters.</p><a href="search.php" class="btn btn-navy">View all hotels</a></div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px">
          <?php foreach ($hotels as $h):
            $ams = json_decode($h['amenities']??'[]',true)?:[];
          ?>
          <a href="hotel.php?id=<?= $h['id'] ?>&check_in=<?= urlencode($checkIn) ?>&check_out=<?= urlencode($checkOut) ?>&guests=<?= $guests ?>"
             style="text-decoration:none" class="fade-up">
            <div class="card" style="display:grid;grid-template-columns:250px 1fr">
              <div style="height:195px;overflow:hidden;border-radius:var(--r) 0 0 var(--r)">
                <img src="<?= preg_match('#^https?://#',$h['thumbnail']??'') ? e($h['thumbnail']) : SITE_URL.'/'.e($h['thumbnail']??'') ?>" alt="<?= e($h['name']) ?>" style="height:100%;width:100%;object-fit:cover;transition:transform .5s"
                     onerror="this.src='https://placehold.co/250x195/003580/ffffff?text=<?= urlencode($h['name']) ?>'">
              </div>
              <div style="padding:18px;display:flex;flex-direction:column;justify-content:space-between">
                <div>
                  <div class="flex jb ac" style="gap:8px;margin-bottom:5px">
                    <h3 style="font-size:1.05rem;font-weight:700;color:var(--text)"><?= e($h['name']) ?></h3>
                    <span class="rating-pill" style="flex-shrink:0">★ <?= number_format($h['rating'],2) ?></span>
                  </div>
                  <p class="text-sm text-muted" style="margin-bottom:4px">📍 <?= e($h['city']) ?> · <?= str_repeat('★',$h['stars']) ?></p>
                  <p class="text-sm text-muted" style="line-height:1.6;margin-bottom:8px"><?= e(mb_substr($h['description'],0,120)) ?>…</p>
                  <div class="pills"><?php foreach (array_slice($ams,0,5) as $a): ?><span class="pill"><?= e($a) ?></span><?php endforeach; ?><?php if (count($ams)>5): ?><span class="pill">+<?= count($ams)-5 ?> more</span><?php endif; ?></div>
                </div>
                <div class="flex jb ac" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
                  <span class="text-sm text-muted"><?= number_format($h['review_count']) ?> reviews</span>
                  <div style="text-align:right">
                    <div class="text-sm text-muted">From</div>
                    <strong style="font-size:1.25rem;color:var(--navy)"><?= money((float)$h['minp']) ?></strong>
                    <div class="text-sm text-muted">per night</div>
                  </div>
                </div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
<script>
function applyStar(s){const u=new URL(location.href);const c=u.searchParams.get('stars');if(c==s)u.searchParams.delete('stars');else u.searchParams.set('stars',s);location.href=u;}
function applyPrice(){const u=new URL(location.href);const mn=document.getElementById('min_p').value;const mx=document.getElementById('max_p').value;mn?u.searchParams.set('min_price',mn):u.searchParams.delete('min_price');mx?u.searchParams.set('max_price',mx):u.searchParams.delete('max_price');location.href=u;}
function applySort(v){const u=new URL(location.href);u.searchParams.set('sort',v);location.href=u;}
function applyName(){const u=new URL(location.href);const v=document.getElementById('prop_name').value.trim();v?u.searchParams.set('name',v):u.searchParams.delete('name');location.href=u;}
document.getElementById('prop_name')?.addEventListener('keydown',e=>{if(e.key==='Enter')applyName();});
function applyAmen(){
  const checked=[...document.querySelectorAll('.amen-cb:checked')].map(cb=>cb.value);
  const u=new URL(location.href);
  checked.length?u.searchParams.set('amenities',checked.join(',')):u.searchParams.delete('amenities');
  location.href=u;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
