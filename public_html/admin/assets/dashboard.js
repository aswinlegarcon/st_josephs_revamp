/* N7: live dashboard vitals — polls ?r=stats every 20 s, measures the round
   trip itself as the "speed" sample, draws a 20-point sparkline, animates
   numbers. Vanilla JS, no libraries, CSP-safe (external file). */
(function () {
  'use strict';
  if (!document.getElementById('sj-vitals')) return;

  var samples = [];
  var $ = function (id) { return document.getElementById(id); };

  function setNum(el, html) { if (el) el.innerHTML = html; }

  function spark() {
    var c = $('v-spark');
    if (!c || !c.getContext || samples.length < 2) return;
    var ctx = c.getContext('2d');
    var w = c.width, h = c.height, max = Math.max.apply(null, samples) * 1.15 || 1;
    ctx.clearRect(0, 0, w, h);
    ctx.beginPath();
    samples.forEach(function (v, i) {
      var x = i / (samples.length - 1) * (w - 4) + 2;
      var y = h - 3 - (v / max) * (h - 8);
      i ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
    });
    ctx.strokeStyle = '#2b4b8a';
    ctx.lineWidth = 2;
    ctx.lineJoin = ctx.lineCap = 'round';
    ctx.stroke();
    ctx.lineTo(w - 2, h - 1);
    ctx.lineTo(2, h - 1);
    ctx.closePath();
    ctx.fillStyle = 'rgba(43,75,138,.10)';
    ctx.fill();
  }

  function poll() {
    var t0 = performance.now();
    fetch('/admin/api/index.php?r=stats', { headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        var ms = Math.round(performance.now() - t0);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        samples.push(ms);
        if (samples.length > 20) samples.shift();
        return r.json().then(function (j) { return { j: j, ms: ms }; });
      })
      .then(function (o) {
        var j = o.j;
        setNum($('v-avail'), '<span class="sj-dot ok"></span>Online');
        $('v-avail-sub').textContent = 'admin API + database responding';
        setNum($('v-speed'), o.ms + '<small> ms</small>');
        spark();

        if (j.mem) {
          var used = j.mem.total_mb - j.mem.available_mb;
          var pct = Math.round(used / j.mem.total_mb * 100);
          setNum($('v-mem'), (used / 1024).toFixed(1) + '<small> / ' + (j.mem.total_mb / 1024).toFixed(1) + ' GB</small>');
          var bar = $('v-mem-bar');
          bar.style.width = pct + '%';
          bar.className = pct > 90 ? 'bad' : (pct > 75 ? 'warn' : '');
        } else {
          setNum($('v-mem'), '—');
        }
        $('v-mem-sub').textContent = 'PHP peak this request: ' + j.php_peak_mb + ' MB · PHP ' + j.php;

        if (j.disk && j.disk.free_gb !== null) {
          setNum($('v-disk'), j.disk.free_gb + '<small> GB free</small>');
          $('v-disk-sub').textContent = j.disk.used_pct + '% used of ' + j.disk.total_gb + ' GB';
          $('v-disk-donut').style.setProperty('--p', j.disk.used_pct);
        }
        setNum($('v-db'), j.db_mb + '<small> MB</small>');
        $('v-db-sub').textContent = 'database · media ' + j.media_mb + ' MB · photos ' + j.photos_mb + ' MB';

        if (j.backup) {
          setNum($('v-backup'), j.backup.age_h + '<small> h ago</small>');
          $('v-backup-sub').textContent = 'nightly dump · ' + j.backup.size_kb + ' KB';
        } else {
          setNum($('v-backup'), 'none yet');
        }
        if (j.opcache) {
          setNum($('v-opcache'), j.opcache.hit_rate + '<small> %</small>');
          $('v-opcache-sub').textContent = 'OPcache · ' + j.opcache.used_mb + ' MB used, ' + j.opcache.free_mb + ' MB free';
        } else {
          setNum($('v-opcache'), '—');
          $('v-opcache-sub').textContent = 'OPcache not available';
        }
      })
      .catch(function () {
        setNum($('v-avail'), '<span class="sj-dot bad"></span>Problem');
        $('v-avail-sub').textContent = 'the admin API did not respond — check the site';
      });
  }

  poll();
  setInterval(poll, 20000);
})();
