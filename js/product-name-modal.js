document.addEventListener('DOMContentLoaded', function () {
  const productNameSelect = document.getElementById('product_name_select');
  const showAddProductNameModalBtn = document.getElementById('showAddProductNameModalBtn');
  const addProductNameModal = document.getElementById('addProductNameModal');
  const addProductNameForm = document.getElementById('addProductNameForm');
  const cancelBtn = document.getElementById('cancelAddProductName');

  // Show modal function
  function showModal() {
    addProductNameModal.classList.add('active');
  }

  // Hide modal function
  function hideModal() {
    addProductNameModal.classList.remove('active');
  }

  // Listen for button click to show modal
  showAddProductNameModalBtn.addEventListener('click', function () {
    showModal();
  });

  // Cancel button hides modal
  cancelBtn.addEventListener('click', function () {
    hideModal();
  });

  // Handle form submission
  addProductNameForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const newName = document.getElementById('new_product_name').value.trim();
    const newPrice = document.getElementById('new_product_price').value.trim();

    if (!newName || !newPrice || isNaN(newPrice) || Number(newPrice) < 0) {
      alert('لطفا نام محصول و قیمت معتبر وارد کنید.');
      return;
    }

    // Send AJAX request to save new product name and price
    fetch('add_product_name.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: newName, price: newPrice })
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Add new option to select, add data-price, and select it
          const option = document.createElement('option');
          option.value = newName;
          option.textContent = newName;
          option.dataset.price = newPrice; // Add data-price attribute
          productNameSelect.appendChild(option);
          productNameSelect.value = newName;

          // Update the main form's price input and trigger formatting
          const mainPriceInput = document.querySelector('.form input[name="price"]');
          if (mainPriceInput) {
            mainPriceInput.value = newPrice;
            mainPriceInput.dispatchEvent(new Event('input', { bubbles: true }));
          }

          hideModal();
          addProductNameForm.reset(); // Clear form inputs
        } else {
          alert('خطا در افزودن نام محصول: ' + (data.message || 'خطای نامشخص'));
        }
      })
      .catch(() => {
        alert('خطا در ارتباط با سرور.');
      });
  });
});
