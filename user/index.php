<?php
require_once __DIR__."/../inc/db.php";
require_once __DIR__."/../inc/helpers.php";

$seats = $pdo->query("SELECT id, seat_name FROM seats ORDER BY seat_name")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <link rel="stylesheet" href="../assets/style.css">
  <title>Election 2026 Results</title>
</head>
<body class="container">
  <div class="card">
    <h2>ত্রয়োদশ জাতীয় সংসদ নির্বাচনের ফলাফল</h2>

    <form method="get" action="results.php" class="row" style="grid-template-columns: 1.6fr .6fr;">
      <div class="col">
        <label>আসন সিলেক্ট করুন</label>
        <select name="seat_id" required>
          <option value="">-- Select Seat --</option>
          <?php foreach($seats as $s): ?>
            <option value="<?=$s["id"]?>"><?=h($s["seat_name"])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col">
        <button class="btn" type="submit">Search</button>
      </div>
    </form>

    <p class="muted">আসন সিলেক্ট করে সার্চ করলে উপরে প্রতীক অনুযায়ী মোট ফলাফল, নিচে কেন্দ্রভিত্তিক ফলাফল টেবিল দেখাবে।</p>
  </div> <br>

  <!-- ===== Official Final Result (Seat) ===== -->
<div class="card" id="finalSeatCard" style="margin-top:14px; display:none;">
  <h3 style="margin:0 0 8px;">Official Final Result</h3>
  <div class="muted" id="finalSeatMeta"></div>

  <div class="matrix-wrap" style="margin-top:10px;">
    <table class="table" id="finalSeatTable">
      <thead>
        <tr>
          <th>প্রতীক</th>
          <th style="text-align:right;">ভোট</th>
        </tr>
      </thead>
      <tbody id="finalSeatBody"></tbody>
    </table>
  </div>
</div>

<!-- ===== Top 10 Leading Symbols Grid (Final Official) ===== -->
<div class="card" style="margin-top:14px;">
  <h3 style="margin:0 0 10px;">Top 10 Symbols (Leading Seats) — Official Final</h3>
  <div class="muted" id="finalTopMeta"></div>

  <div class="final-grid" id="finalTopGrid"></div>
</div>


  <hr>
  <h3>300 Seat Overview</h3>

  <div class="overview-wrapper">

      <!-- LEFT 50% : Pie charts -->
    <div class="overview-left">
        <div class="chart-card">
          <canvas id="seatLeadChart"></canvas>
        </div>

        <div class="chart-card">
          <canvas id="votePercentChart"></canvas>
        </div>
    </div>

      <!-- RIGHT 50% : Table -->
    <div class="overview-right">
        <table class="table">
          <thead>
            <tr>
              <th>প্রতীক</th>
              <th style="text-align:right;">এগিয়ে থাকা আসন সংখ্যা</th>
              <th style="text-align:right;">মোট প্রাপ্ত ভোট (%)</th>
            </tr>
          </thead>
          <tbody id="overviewTable"></tbody>
        </table>
    </div>
  </div>

  <hr>
  <h3>Seat-wise Casting Votes (Top 10 Symbols)</h3>

  <div class="matrix-wrap">
    <table class="table" id="matrixTable">
      <thead id="matrixHead"></thead>
      <tbody id="matrixBody"></tbody>
    </table>
  </div>



    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
document.addEventListener("DOMContentLoaded", () => {

  // ------------- (A) Top 10 Grid -------------
  fetch('api_final_overview_300.php')
    .then(r => r.json())
    .then(d => {
      const meta = document.getElementById("finalTopMeta");
      meta.textContent = `Submitted Seats: ${d.computed_from_submitted_seats} | Total Votes: ${d.grand_total_votes.toLocaleString()}`;

      const grid = document.getElementById("finalTopGrid");
      grid.innerHTML = "";

      (d.top10 || []).forEach(x => {
        const card = document.createElement("div");
        card.className = "final-grid-card";
        card.innerHTML = `
          <div class="final-seatcount">${x.seats}</div>
          <div class="final-symbol">${x.symbol}</div>
          <div class="final-pct">${x.percentage}% ভোট</div>
        `;
        grid.appendChild(card);
      });
    })
    .catch(console.log);

  // ------------- (B) Seat-wise Final Result (on Search / or seat dropdown change) -------------
  const seatSelect = document.querySelector('select[name="seat_id"]') || document.getElementById("seatSelect");
  const searchBtn  = document.querySelector('button[type="submit"]');

  function loadFinalSeat(seatId){
    if(!seatId) return;

    fetch('api_final_result_seat.php?seat_id=' + encodeURIComponent(seatId))
      .then(r => r.json())
      .then(d => {
        const card = document.getElementById("finalSeatCard");
        const meta = document.getElementById("finalSeatMeta");
        const tbody = document.getElementById("finalSeatBody");

        if(!d.ok) {
          card.style.display = "none";
          return;
        }

        // যদি কোনো final data না থাকে
        if(!d.rows || d.rows.length === 0){
          card.style.display = "block";
          meta.textContent = `Seat: ${d.seat_name || ''} — এখনও Official Final Result এন্ট্রি হয়নি।`;
          tbody.innerHTML = "";
          return;
        }

        card.style.display = "block";
        meta.textContent = `Seat: ${d.seat_name} | Total Casting Votes: ${d.total_votes.toLocaleString()}`;

        tbody.innerHTML = "";
        d.rows.forEach(rw => {
          const tr = document.createElement("tr");
          tr.innerHTML = `<td>${rw.symbol_name}</td><td style="text-align:right;">${Number(rw.vote_count).toLocaleString()}</td>`;
          tbody.appendChild(tr);
        });
      })
      .catch(console.log);
  }

  // seat dropdown change করলে instant load
  if(seatSelect){
    seatSelect.addEventListener("change", ()=> loadFinalSeat(seatSelect.value));
  }

  // যদি আপনার সার্চ বাটন form submit করে page reload করে—তাহলে এই লাইন না লাগলেও চলবে।
  // কিন্তু যদি AJAX থাকে, তাহলে নিচেরটা কাজে আসবে:
  // if(searchBtn) searchBtn.addEventListener("click", ()=> loadFinalSeat(seatSelect.value));

  // page load এ যদি GET এ seat_id থাকে (server side selected), load it:
  const url = new URL(window.location.href);
  const seatIdFromUrl = url.searchParams.get("seat_id");
  if (seatIdFromUrl) loadFinalSeat(seatIdFromUrl);

});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {

  // ===== Fixed Symbol Color Map =====
  const SYMBOL_COLORS = {
    "ধানের শীষ": "#2e7d32",
    "দাঁড়িপাল্লা": "#c62828",
    "লাঙ্গল": "#1565c0",
    "হাতপাখা": "#f9a825",
    "শাপলা কলি": "#6a1b9a"
  };

  const AUTO_COLORS = [
    "#00897b", "#5e35b1", "#f4511e", "#3949ab",
    "#00acc1", "#7cb342", "#fb8c00", "#8d6e63",
    "#546e7a", "#d81b60", "#43a047", "#ff7043"
  ];

  function buildColors(labels) {
    let idx = 0;
    return labels.map(l => SYMBOL_COLORS[l] || AUTO_COLORS[idx++ % AUTO_COLORS.length]);
  }

  fetch('api_overview_300.php')
    .then(r => r.json())
    .then(data => {

      const labels   = data.map(d => d.symbol);
      const seatData = data.map(d => d.seats);
      const voteData = data.map(d => d.percentage);

      // ✅ colors MUST be outside Chart config object
      const colors = buildColors(labels);

      // Table
      const tbody = document.getElementById('overviewTable');
      tbody.innerHTML = '';
      data.forEach(d => {
        tbody.innerHTML += `
          <tr>
            <td>${d.symbol}</td>
            <td style="text-align:right">${d.seats}</td>
            <td style="text-align:right">${d.percentage}%</td>
          </tr>`;
      });

      const pieOptions = {
        responsive: false,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            align: 'center',
            labels: { boxWidth: 12, font: { size: 11 } }
          }
        }
      };

      if (window.seatLeadChartObj) window.seatLeadChartObj.destroy();
      if (window.votePercentChartObj) window.votePercentChartObj.destroy();

      // Pie 1: Seat lead
      window.seatLeadChartObj = new Chart(document.getElementById('seatLeadChart'), {
        type: 'pie',
        data: {
          labels,
          datasets: [{
            data: seatData,
            backgroundColor: colors,
            borderColor: "#ffffff",
            borderWidth: 1
          }]
        },
        options: pieOptions
      });

      // Pie 2: Vote %
      window.votePercentChartObj = new Chart(document.getElementById('votePercentChart'), {
        type: 'pie',
        data: {
          labels,
          datasets: [{
            data: voteData,               // ✅ এখানে voteData হবে
            backgroundColor: colors,
            borderColor: "#ffffff",
            borderWidth: 1
          }]
        },
        options: pieOptions
      });

    })
    .catch(err => console.log(err));
});
</script>

  <!--
    <script>
      document.addEventListener('DOMContentLoaded', () => {

      fetch('api_seat_symbol_matrix.php')
        .then(r => r.json())
        .then(payload => {
          const symbols = payload.symbols || [];
          const rows = payload.rows || [];

      // Build THEAD
      const thead = document.getElementById('matrixHead');
      let headHtml = "<tr><th>আসনের নাম</th>";
      symbols.forEach(s => headHtml += `<th style="text-align:right;">${s}</th>`);
      headHtml += `<th style="text-align:right;">মোট কাস্টিং ভোট</th></tr>`;
      thead.innerHTML = headHtml;

      // Build TBODY
      const tbody = document.getElementById('matrixBody');
      tbody.innerHTML = "";

        rows.forEach(row => {
          let tr = `<tr><td>${row.seat}</td>`;
          let casting = row.casting_total || 0;
          const totals = row.totals || {};

          symbols.forEach(sym => {
            const v = totals[sym] ?? 0;
            tr += `<td style="text-align:right;">${Number(v).toLocaleString()}</td>`;
          });

          tr += `<td style="text-align:right;"><b>${Number(casting).toLocaleString()}</b></td></tr>`;
          tbody.innerHTML += tr;
        });
      })
    .catch(err => console.log(err));
  });
  </script>
  -->

  <script>
document.addEventListener('DOMContentLoaded', () => {

  fetch('api_seat_top10_matrix.php')
    .then(r => r.json())
    .then(payload => {
      const topn = payload.topn || 10;
      const rows = payload.rows || [];

      // THEAD
      const thead = document.getElementById('matrixHead');
      let head = `<tr><th>আসনের নাম</th>`;
      for (let i = 1; i <= topn; i++) head += `<th style="text-align:center;">Top ${i}</th>`;
      head += `<th style="text-align:right;">মোট কাস্টিং ভোট</th></tr>`;
      thead.innerHTML = head;

      // TBODY
      const tbody = document.getElementById('matrixBody');
      tbody.innerHTML = "";

      rows.forEach(row => {
        let tr = `<tr><td>${row.seat}</td>`;

        (row.top || []).forEach(item => {
          if (item.symbol === 'N/A') {
            tr += `<td style="text-align:center;">N/A</td>`;
          } else {
            tr += `
              <td class="matrix-cell">
                <div class="sym">${item.symbol}</div>
                <div class="v">${Number(item.votes || 0).toLocaleString()}</div>
              </td>`;
          }
        });

        tr += `<td style="text-align:right;"><b>${Number(row.casting_total || 0).toLocaleString()}</b></td></tr>`;
        tbody.innerHTML += tr;
      });
    })
    .catch(err => console.log(err));

});
</script>

</body>
</html>
