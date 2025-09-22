<?php
include 'db.php';
include 'jalali_calendar.php';

// دریافت ورودی‌های فیلتر
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$buyer_id = $_GET['buyer_id'] ?? '';
$product  = $_GET['product'] ?? '';

// شرط تاریخ و فیلترها
$where = [];
$params = [];
if ($from && $to) {
    if (strpos($from, '/') !== false) {
        list($jy,$jm,$jd) = explode('/', $from);
        list($gy,$gm,$gd) = jalali_to_gregorian($jy,$jm,$jd);
        $from = sprintf('%04d-%02d-%02d', $gy,$gm,$gd);
    }
    if (strpos($to, '/') !== false) {
        list($jy,$jm,$jd) = explode('/', $to);
        list($gy,$gm,$gd) = jalali_to_gregorian($jy,$jm,$jd);
        $to = sprintf('%04d-%02d-%02d', $gy,$gm,$gd);
    }
    $where[] = "purchase_date BETWEEN ? AND ?";
    $params[] = $from; $params[] = $to;
}
if ($buyer_id) {
    $where[] = "buyer_id=?";
    $params[] = $buyer_id;
}
if ($product) {
    $where[] = "product_name=?";
    $params[] = $product;
}
$whereSQL = $where ? "WHERE ".implode(" AND ", $where) : "";

// ---------------- Daily Report ----------------
$stmt = $conn->prepare("
    SELECT purchase_date, SUM(quantity) as total_qty, SUM(total_price) as total_price
    FROM purchases
    $whereSQL
    GROUP BY purchase_date
    ORDER BY purchase_date DESC
    LIMIT 30
");
$stmt->execute($params);
$daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------- Monthly Report ----------------
$stmt = $conn->prepare("SELECT * FROM purchases $whereSQL");
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthly = [];
foreach ($purchases as $p) {
    list($gy,$gm,$gd) = explode('-', $p['purchase_date']);
    list($jy,$jm,$jd) = gregorian_to_jalali($gy,$gm,$gd);
    $key = sprintf("%04d/%02d", $jy, $jm);

    if (!isset($monthly[$key])) {
        $monthly[$key] = ['qty'=>0,'price'=>0,'payments'=>0,'balance'=>0,'percentPaid'=>0];
    }
    $monthly[$key]['qty']   += $p['quantity'];
    $monthly[$key]['price'] += $p['total_price'];
}

// پرداختی و مانده حساب برای هر ماه
foreach ($monthly as $month => &$info) {
    list($jy,$jm) = explode('/',$month);
    $start = jalali_to_gregorian($jy,$jm,1);
    $end   = jalali_to_gregorian($jy,$jm,31);
    $startDate = sprintf("%04d-%02d-%02d",$start[0],$start[1],$start[2]);
    $endDate   = sprintf("%04d-%02d-%02d",$end[0],$end[1],$end[2]);

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE payment_date BETWEEN ? AND ?");
    $stmt->execute([$startDate,$endDate]);
    $info['payments'] = $stmt->fetchColumn() ?? 0;

    $stmt = $conn->prepare("SELECT SUM(total_price) FROM purchases WHERE purchase_date <= ?");
    $stmt->execute([$endDate]);
    $sumPurch = $stmt->fetchColumn() ?? 0;

    $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE payment_date <= ?");
    $stmt->execute([$endDate]);
    $sumPay = $stmt->fetchColumn() ?? 0;

    $info['balance'] = $sumPurch - $sumPay;
    $info['percentPaid'] = $sumPurch>0 ? round(($sumPay/$sumPurch)*100,1) : 0;
}

// ---------------- Overall Stats ----------------
$totalPurchases = array_sum(array_column($purchases, 'quantity'));
$totalRevenue   = array_sum(array_column($purchases, 'total_price'));
$uniqueDays     = count($daily);
$averageDaily   = $uniqueDays>0 ? round($totalPurchases/$uniqueDays,1) : 0;

// ---------------- Bests ----------------
$bestProduct = $conn->query("SELECT product_name,SUM(quantity) q FROM purchases GROUP BY product_name ORDER BY q DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$bestBuyer   = $conn->query("SELECT b.name,SUM(p.total_price) s FROM purchases p JOIN buyers b ON p.buyer_id=b.id GROUP BY b.id ORDER BY s DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<title>📊 گزارش فروش</title>
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/design-system.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="css/reports-update.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dashboard-container">
  <aside class="sidebar"><?php include 'sidebar.php'; ?></aside>
  <div class="main-content">
    <header class="top-bar"><h1 class="h1">📊 گزارش فروش</h1></header>
    <div class="content-area">

      <!-- آمار کلی -->
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= number_format($totalPurchases) ?></div><div class="stat-label">کل تعداد</div></div>
        <div class="stat-card"><div class="stat-value"><?= number_format($totalRevenue) ?></div><div class="stat-label">کل مبلغ</div></div>
        <div class="stat-card"><div class="stat-value"><?= $uniqueDays ?></div><div class="stat-label">روز فعال</div></div>
        <div class="stat-card"><div class="stat-value"><?= $averageDaily ?></div><div class="stat-label">میانگین روزانه</div></div>
      </div>

      <!-- بهترین‌ها -->
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= htmlspecialchars($bestProduct['product_name']??'-') ?></div><div class="stat-label">پرفروش‌ترین محصول</div></div>
        <div class="stat-card"><div class="stat-value"><?= htmlspecialchars($bestBuyer['name']??'-') ?></div><div class="stat-label">بهترین مشتری</div></div>
      </div>

      <!-- فرم فیلتر -->
      <form method="get" class="filter-form">
        <input type="text" name="from" placeholder="از تاریخ (1404/01/01)" value="<?= htmlspecialchars($_GET['from']??'') ?>">
        <input type="text" name="to" placeholder="تا تاریخ (1404/12/29)" value="<?= htmlspecialchars($_GET['to']??'') ?>">

        <select name="buyer_id">
          <option value="">همه مشتری‌ها</option>
          <?php foreach($conn->query("SELECT * FROM buyers") as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $buyer_id==$b['id']?"selected":"" ?>><?= $b['name'] ?></option>
          <?php endforeach; ?>
        </select>

        <select name="product">
          <option value="">همه محصولات</option>
          <?php foreach($conn->query("SELECT DISTINCT product_name FROM purchases") as $pr): ?>
            <option <?= $product==$pr['product_name']?"selected":"" ?>><?= $pr['product_name'] ?></option>
          <?php endforeach; ?>
        </select>

        <button type="submit">اعمال فیلتر</button>
      </form>

      <!-- گزارش روزانه -->
      <h3>📅 گزارش روزانه</h3>
      <table class="report-table">
        <tr><th>تاریخ</th><th>تعداد</th><th>مبلغ</th></tr>
        <?php foreach($daily as $d): ?>
          <tr>
            <td><?php list($gy,$gm,$gd)=explode('-',$d['purchase_date']); list($jy,$jm,$jd)=gregorian_to_jalali($gy,$gm,$gd); echo "$jy/$jm/$jd"; ?></td>
            <td><?= number_format($d['total_qty']) ?></td>
            <td><?= number_format($d['total_price']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>

      <!-- گزارش ماهانه -->
      <h3>🗓 گزارش ماهانه</h3>
      <table class="report-table">
        <tr><th>ماه</th><th>تعداد</th><th>مبلغ</th><th>پرداختی</th><th>مانده</th><th>% تسویه</th></tr>
        <?php foreach($monthly as $m=>$info): ?>
          <tr <?= $info['qty']<20 ? "style='background:#ffe0e0'" : "" ?>>
            <td><?= $m ?></td>
            <td><?= number_format($info['qty']) ?></td>
            <td><?= number_format($info['price']) ?></td>
            <td><?= number_format($info['payments']) ?></td>
            <td><?= number_format($info['balance']) ?></td>
            <td><?= $info['percentPaid'] ?>%</td>
          </tr>
        <?php endforeach; ?>
      </table>

      <!-- نمودار -->
      <canvas id="chart1" style="height:300px"></canvas>

      <!-- خروجی -->
      <button onclick="downloadCSV()">دانلود CSV</button>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('chart1'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_keys($monthly),JSON_UNESCAPED_UNICODE) ?>,
    datasets: [
      {label:'تعداد', data: <?= json_encode(array_column($monthly,'qty')) ?>, borderColor:'blue'},
      {label:'مبلغ', data: <?= json_encode(array_column($monthly,'price')) ?>, borderColor:'green'}
    ]
  }
});
function downloadCSV(){
  let rows=[["ماه","تعداد","مبلغ","پرداختی","مانده","%تسویه"]];
  <?php foreach($monthly as $m=>$i): ?>
    rows.push(["<?= $m ?>","<?= $i['qty'] ?>","<?= $i['price'] ?>","<?= $i['payments'] ?>","<?= $i['balance'] ?>","<?= $i['percentPaid'] ?>"]);
  <?php endforeach; ?>
  let csv=rows.map(r=>r.join(",")).join("\n");
  let link=document.createElement("a");
  link.href="data:text/csv;charset=utf-8,"+encodeURIComponent(csv);
  link.download="report.csv";
  link.click();
}
</script>
</body>
</html>
