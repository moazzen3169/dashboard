<style>
    body {
  margin: 0;
  font-family: "Vazirmatn", sans-serif;
  display: flex;
}

.sidebar {
  width: 250px;
  min-height: 100vh;
  background: #222;
  color: #fff;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 1rem;
}

.sidebar .date-box {
  text-align: center;
  font-weight: bold;
  margin-bottom: 1rem;
}

.sidebar nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sidebar nav a,
.sidebar .support a {
  color: #fff;
  text-decoration: none;
  padding: 10px;
  border-radius: 8px;
  transition: background 0.3s;
}

.sidebar nav a:hover,
.sidebar .support a:hover {
  background: #444;
}

</style>






<aside class="sidebar">
  <div class="date-box">
    <span id="persian-date"></span>
  </div>
  <nav>
    <a href="dashboard.php">داشبورد</a>
    <a href="products.php">محصولات</a>
    <a href="payments.php">پرداختی‌ها</a>
    <a href="invoices.php">فاکتورها</a>
  </nav>
  <div class="support">
    <a href="support.php">پشتیبانی</a>
  </div>
</aside>

<script src="https://cdn.jsdelivr.net/npm/jalaali-js/dist/jalaali.min.js"></script>
<script>
  function toJalali(date) {
    let gDate = new Date(date);
    let j = jalaali.toJalaali(gDate.getFullYear(), gDate.getMonth() + 1, gDate.getDate());
    return `${j.jy}/${j.jm}/${j.jd}`;
  }
  document.getElementById("persian-date").innerText = toJalali(new Date());
</script>
