// Sidebar JavaScript - Persian Date Display

/**
 * Converts a Gregorian date to Jalali (Persian) date string.
 * @param {Date} date - The Gregorian date to convert.
 * @returns {string} Jalali date in format "YYYY/MM/DD".
 */
function toJalali(date) {
    const gDate = new Date(date);
    const j = jalaali.toJalaali(gDate.getFullYear(), gDate.getMonth() + 1, gDate.getDate());
    return `${j.jy}/${j.jm}/${j.jd}`;
}

/**
 * Gets the Persian day name for a given date.
 * @param {Date} date - The date to get the day name for.
 * @returns {string} Persian day name.
 */
function getPersianDayName(date) {
    const days = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
    return days[date.getDay()];
}

// Initialize Persian date display
document.addEventListener('DOMContentLoaded', function() {
    const persianDateElem = document.getElementById('persian-date');
    if (persianDateElem) {
        const now = new Date();
        const persianDate = toJalali(now);
        const dayName = getPersianDayName(now);
        persianDateElem.textContent = `${dayName}، ${persianDate}`;
    }
});
