document.addEventListener('DOMContentLoaded', () => {
  const colorContainer = document.querySelector('#colorContainer .color-groups');
  const addColorBtn = document.getElementById('addColorBtn');
  const form = document.getElementById('bulkProductForm');
  const previewCard = document.getElementById('previewCard');
  const previewContent = document.getElementById('previewContent');

  let colorIndex = 0;

  const createSizeItem = (colorIdx, sizeValue = '') => {
    const sizeWrapper = document.createElement('div');
    sizeWrapper.className = 'size-item';
    sizeWrapper.innerHTML = `
      <input type="text" name="colors[${colorIdx}][sizes][]" value="${sizeValue}" placeholder="مثلاً L" required>
      <button type="button" class="btn-small danger remove-size"><i class="fas fa-times"></i></button>
    `;
    return sizeWrapper;
  };

  const createColorGroup = () => {
    const currentIndex = colorIndex++;
    const wrapper = document.createElement('div');
    wrapper.className = 'color-group';
    wrapper.dataset.index = currentIndex.toString();

    wrapper.innerHTML = `
      <div class="color-group-header">
        <div class="field">
          <label>نام رنگ</label>
          <input type="text" name="colors[${currentIndex}][name]" placeholder="مثلاً قرمز" required>
        </div>
        <button type="button" class="btn-small danger remove-color"><i class="fas fa-trash"></i></button>
      </div>
      <div class="field">
        <label>سایزها</label>
        <div class="size-list"></div>
        <button type="button" class="btn-small add-size"><i class="fas fa-plus"></i> افزودن سایز</button>
      </div>
    `;

    const sizeList = wrapper.querySelector('.size-list');
    sizeList.appendChild(createSizeItem(currentIndex));

    colorContainer.appendChild(wrapper);
  };

  addColorBtn.addEventListener('click', () => {
    createColorGroup();
  });

  colorContainer.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const button = target.closest('button');
    if (!button) return;

    if (button.classList.contains('add-size')) {
      const group = button.closest('.color-group');
      if (!group) return;
      const sizeList = group.querySelector('.size-list');
      const idx = group.dataset.index;
      sizeList.appendChild(createSizeItem(idx));
    }

    if (button.classList.contains('remove-size')) {
      const sizeItem = button.closest('.size-item');
      if (sizeItem) {
        const sizeList = sizeItem.parentElement;
        sizeItem.remove();
        if (sizeList && sizeList.children.length === 0) {
          const group = sizeList.closest('.color-group');
          if (group) {
            const idx = group.dataset.index;
            sizeList.appendChild(createSizeItem(idx));
          }
        }
      }
    }

    if (button.classList.contains('remove-color')) {
      const group = button.closest('.color-group');
      if (group) {
        group.remove();
      }
    }
  });

  const buildPreview = (data) => {
    previewContent.innerHTML = '';
    const { productName, colors } = data;

    if (!colors.length) {
      previewCard.hidden = true;
      return;
    }

    const productBlock = document.createElement('div');
    productBlock.className = 'preview-product';
    productBlock.innerHTML = `<h4>${productName}</h4>`;

    const colorList = document.createElement('ul');
    colors.forEach((color) => {
      const li = document.createElement('li');
      li.innerHTML = `<strong>${color.name}</strong>: ${color.sizes.join('، ')}`;
      colorList.appendChild(li);
    });

    productBlock.appendChild(colorList);
    previewContent.appendChild(productBlock);
    previewCard.hidden = false;
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    const productSelect = document.getElementById('productSelect');
    const productName = productSelect.options[productSelect.selectedIndex]?.text || '';

    const colorGroups = Array.from(colorContainer.querySelectorAll('.color-group'));
    const colors = colorGroups.map((group) => {
      const nameInput = group.querySelector('input[name^="colors"][name$="[name]"]');
      const sizeInputs = Array.from(group.querySelectorAll('.size-list input'));
      const name = nameInput?.value.trim();
      const sizes = sizeInputs.map((input) => input.value.trim()).filter(Boolean);
      return name && sizes.length ? { name, sizes } : null;
    }).filter(Boolean);

    if (!productSelect.value) {
      alert('لطفا ابتدا محصول را انتخاب کنید.');
      productSelect.focus();
      return;
    }

    if (!colors.length) {
      alert('حداقل یک رنگ و سایز باید وارد شود.');
      return;
    }

    buildPreview({ productName, colors });
  });

  // ایجاد اولین گروه رنگ به صورت پیشفرض
  createColorGroup();
});
