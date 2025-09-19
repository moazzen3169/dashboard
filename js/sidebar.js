// Sidebar JavaScript
function toJalali(date) {
    let gDate = new Date(date);
    let j = jalaali.toJalaali(gDate.getFullYear(), gDate.getMonth() + 1, gDate.getDate());
    return `${j.jy}/${j.jm}/${j.jd}`;
}

// Set Persian date
document.getElementById("persian-date").innerText = toJalali(new Date());

// Toggle sidebar
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('toggle-btn');
toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
});
