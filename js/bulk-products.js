const bulkProductOptions = window.bulkProductOptions || { colors: [], sizes: [] };

document.addEventListener('DOMContentLoaded', () => {
  const colorContainer = document.querySelector('#colorContainer .color-groups');
  const addColorBtn = document.getElementById('addColorBtn');
  const form = document.getElementById('bulkProductForm');
  const previewCard = document.getElementById('previewCard');
  const hidePreview = document.getElementById('hidePreview');
  const previewContent = document.getElementById('previewContent');
  const productSelect = document.getElementById('productSelect');
  const colorSection = document.getElementById('colorContainer');
  const stepElements = Array.from(document.querySelectorAll('.steps .step'));
  const stepOrder = ['product', 'colors', 'sizes', 'preview'];

  let colorIndex = 0;
  let lastSelectedProduct = productSelect?.value || '';

  const escapeSelector = (value) => {
    if (window.CSS?.escape) {
      return window.CSS.escape(value);
    }
    return value.replace(/([\s#.;?+<>~*^$|()\[\]{}])/g, '\\$1');
  };

  const updateStepProgress = () => {
    const productChosen = Boolean(productSelect?.value);
    const groups = Array.from(colorContainer.querySelectorAll('.color-group'));
    const colorChosen = groups.some((group) => group.querySelector('.color-select-input')?.value);
    const sizesChosen = groups.some((group) => {
      const select = group.querySelector('.color-select-input');
      if (!select?.value) return false;
      return group.querySelectorAll('.multi-select input[type="checkbox"]:checked').length > 0;
    });
    const previewDone = !previewCard.hidden;

    const status = {
      product: productChosen,
      colors: productChosen && colorChosen,
      sizes: sizesChosen,
      preview: previewDone,
    };

    const activeStep = stepOrder.find((key) => !status[key]) || stepOrder[stepOrder.length - 1];

    stepElements.forEach((step) => {
      const key = step.dataset.step;
      if (!key) return;
      step.classList.toggle('completed', Boolean(status[key]));
      step.classList.toggle('active', key === activeStep);
    });
  };

  const refreshColorAvailability = () => {
    const selects = Array.from(colorContainer.querySelectorAll('.color-select-input'));
    const selectedValues = selects.map((select) => select.value).filter(Boolean);
    const uniqueSelected = new Set(selectedValues);
    const totalColors = bulkProductOptions.colors.length;

    selects.forEach((select) => {
      Array.from(select.options).forEach((option) => {
        if (!option.value) return;
        option.disabled = selectedValues.includes(option.value) && select.value !== option.value;
      });
    });

    if (addColorBtn) {
      const productChosen = Boolean(productSelect?.value);
      const allUsed = productChosen && totalColors > 0 && uniqueSelected.size >= totalColors;
      const shouldDisable = !productChosen || allUsed;

      addColorBtn.disabled = shouldDisable;
      addColorBtn.classList.toggle('is-disabled', shouldDisable);
      addColorBtn.setAttribute('aria-disabled', shouldDisable ? 'true' : 'false');
      addColorBtn.title = !productChosen
        ? 'ابتدا محصول را انتخاب کنید'
        : allUsed
          ? 'تمام رنگ‌های موجود استفاده شده است'
          : 'افزودن رنگ جدید';
    }

    colorSection?.classList.toggle('awaiting-product', !productSelect?.value);
    updateStepProgress();
  };

  const closeAllDropdowns = () => {
    document.querySelectorAll('.multi-select.open').forEach((select) => {
      select.classList.remove('open');
    });
  };

  const updateSizeSelection = (multiSelect) => {
    const display = multiSelect.querySelector('.multi-select-display');
    const chips = display.querySelector('.chips');
    const hiddenContainer = multiSelect.querySelector('.multi-select-hidden');
    const placeholder = display.dataset.placeholder || '';

    chips.innerHTML = '';
    hiddenContainer.innerHTML = '';

    const checkedBoxes = Array.from(multiSelect.querySelectorAll('input[type="checkbox"]:checked'));

    if (!checkedBoxes.length) {
      const placeholderElement = document.createElement('span');
      placeholderElement.className = 'placeholder';
      placeholderElement.textContent = placeholder;
      chips.appendChild(placeholderElement);
      return;
    }

    checkedBoxes.forEach((checkbox) => {
      const value = checkbox.value;
      const label = checkbox.dataset.label || value;

      const chip = document.createElement('span');
      chip.className = 'chip';
      chip.dataset.value = value;
      chip.innerHTML = `
        ${label}
        <button type="button" class="chip-remove" aria-label="حذف ${label}">
          <i class="fas fa-times"></i>
        </button>
      `;
      chips.appendChild(chip);

      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = multiSelect.dataset.inputName;
      hiddenInput.value = value;
      hiddenContainer.appendChild(hiddenInput);
    });

    updateStepProgress();
  };

  const createSizeMultiSelect = (colorIdx) => {
    const multiSelect = document.createElement('div');
    multiSelect.className = 'multi-select';
    multiSelect.dataset.inputName = `colors[${colorIdx}][sizes][]`;

    multiSelect.innerHTML = `
      <div class="multi-select-display" data-placeholder="سایز انتخاب کنید">
        <div class="chips"></div>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div class="multi-select-dropdown"></div>
      <div class="multi-select-hidden"></div>
    `;

    const dropdown = multiSelect.querySelector('.multi-select-dropdown');

    bulkProductOptions.sizes.forEach((size) => {
      const option = document.createElement('label');
      option.className = 'multi-select-option';
      option.innerHTML = `
        <input type="checkbox" value="${size.value}" data-label="${size.label}">
        <span>${size.label}</span>
      `;
      dropdown.appendChild(option);
    });

    updateSizeSelection(multiSelect);
    return multiSelect;
  };

  const createColorGroup = () => {
    const currentIndex = colorIndex++;
    const wrapper = document.createElement('div');
    wrapper.className = 'color-group';
    wrapper.dataset.index = currentIndex.toString();

    wrapper.innerHTML = `
      <div class="color-group-header">
        <div class="field">
          <label>انتخاب رنگ</label>
          <div class="color-select">
            <span class="color-swatch" aria-hidden="true"></span>
            <select name="colors[${currentIndex}][color]" class="color-select-input" required>
              <option value="" disabled selected>یک رنگ انتخاب کنید...</option>
            </select>
          </div>
        </div>
        <button type="button" class="btn-small danger remove-color" aria-label="حذف رنگ">
          <i class="fas fa-trash"></i>
        </button>
      </div>
      <div class="field size-field">
        <label>انتخاب سایز برای این رنگ</label>
      </div>
      <div class="field note-field">
        <label for="color-note-${currentIndex}">یادداشت (اختیاری)</label>
        <textarea id="color-note-${currentIndex}" name="colors[${currentIndex}][note]" rows="2" placeholder="مثلاً تعداد موجودی یا توضیحی کوتاه"></textarea>
      </div>
    `;

    const colorSelect = wrapper.querySelector('.color-select-input');
    const colorSwatch = wrapper.querySelector('.color-swatch');

    bulkProductOptions.colors.forEach((color) => {
      const option = document.createElement('option');
      option.value = color.value;
      option.textContent = color.label;
      option.dataset.hex = color.hex;
      colorSelect.appendChild(option);
    });

    colorSelect.addEventListener('change', () => {
      const selectedOption = colorSelect.options[colorSelect.selectedIndex];
      const hex = selectedOption?.dataset.hex;
      if (hex) {
        colorSwatch.style.setProperty('--swatch-color', hex);
        colorSwatch.classList.add('visible');
      } else {
        colorSwatch.classList.remove('visible');
      }
      refreshColorAvailability();
    });

    const sizeField = wrapper.querySelector('.size-field');
    sizeField.appendChild(createSizeMultiSelect(currentIndex));

    colorContainer.appendChild(wrapper);
    refreshColorAvailability();
  };

  addColorBtn.addEventListener('click', () => {
    createColorGroup();
  });

  productSelect?.addEventListener('change', (event) => {
    const previousValue = lastSelectedProduct;
    const newValue = event.target.value;
    const hasExistingData = Array.from(colorContainer.querySelectorAll('.color-group')).some((group) => {
      const hasColor = Boolean(group.querySelector('.color-select-input')?.value);
      const hasSizes = group.querySelectorAll('.multi-select input[type="checkbox"]:checked').length > 0;
      const hasNote = Boolean(group.querySelector('textarea')?.value.trim());
      return hasColor || hasSizes || hasNote;
    });

    if (hasExistingData && previousValue && previousValue !== newValue) {
      const proceed = window.confirm('با تغییر محصول، رنگ‌ها و سایزهای انتخاب‌شده پاک می‌شوند. آیا ادامه می‌دهید؟');
      if (!proceed) {
        productSelect.value = previousValue;
        refreshColorAvailability();
        return;
      }
      colorContainer.innerHTML = '';
      colorIndex = 0;
      createColorGroup();
      previewContent.innerHTML = '';
      previewCard.hidden = true;
    }

    if (previousValue !== newValue) {
      previewContent.innerHTML = '';
      previewCard.hidden = true;
    }

    lastSelectedProduct = newValue;
    refreshColorAvailability();
  });

  colorContainer.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const colorGroup = target.closest('.color-group');

    if (target.closest('.remove-color')) {
      colorGroup?.remove();
      refreshColorAvailability();
      if (!colorContainer.querySelector('.color-group')) {
        createColorGroup();
      }
      return;
    }

    if (target.closest('.multi-select-display')) {
      const multiSelect = target.closest('.multi-select');
      if (!multiSelect) return;
      if (multiSelect.classList.contains('open')) {
        multiSelect.classList.remove('open');
      } else {
        closeAllDropdowns();
        multiSelect.classList.add('open');
      }
      return;
    }

    if (target.classList.contains('chip-remove')) {
      const chip = target.closest('.chip');
      const multiSelect = target.closest('.multi-select');
      if (!chip || !multiSelect) return;
      const value = chip.dataset.value;
      const checkbox = multiSelect.querySelector(`input[type="checkbox"][value="${escapeSelector(value)}"]`);
      if (checkbox) {
        checkbox.checked = false;
        updateSizeSelection(multiSelect);
      }
    }
  });

  colorContainer.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.type === 'checkbox') {
      const multiSelect = target.closest('.multi-select');
      if (multiSelect) {
        updateSizeSelection(multiSelect);
      }
    }
  });

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (!target.closest('.multi-select')) {
      closeAllDropdowns();
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

    const productHeader = document.createElement('div');
    productHeader.className = 'preview-product-header';
    productHeader.innerHTML = `
      <div class="product-icon"><i class="fas fa-box"></i></div>
      <div>
        <h4>${productName}</h4>
        <span class="muted">${colors.length} رنگ انتخاب شده</span>
      </div>
    `;
    productBlock.appendChild(productHeader);

    const colorList = document.createElement('div');
    colorList.className = 'preview-color-list';

    colors.forEach((color) => {
      const colorCard = document.createElement('article');
      colorCard.className = 'preview-color';
      colorCard.innerHTML = `
        <header>
          <span class="swatch" style="--swatch-color: ${color.hex}"></span>
          <strong>${color.name}</strong>
        </header>
        <div class="sizes">${color.sizes.map((size) => `<span class="size-chip">${size}</span>`).join('')}</div>
        ${color.note ? `<p class="note"><i class="fas fa-note-sticky"></i> ${color.note}</p>` : ''}
      `;
      colorList.appendChild(colorCard);
    });

    productBlock.appendChild(colorList);
    previewContent.appendChild(productBlock);
    previewCard.hidden = false;
    previewCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    updateStepProgress();
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    const productName = productSelect?.options[productSelect.selectedIndex]?.text || '';

    if (!productSelect?.value) {
      alert('لطفاً ابتدا محصول را انتخاب کنید.');
      productSelect?.focus();
      return;
    }

    const colorGroups = Array.from(colorContainer.querySelectorAll('.color-group'));
    const errors = [];
    const colors = [];

    colorGroups.forEach((group) => {
      const select = group.querySelector('.color-select-input');
      const note = group.querySelector('textarea')?.value.trim();
      const selectedOption = select?.options[select.selectedIndex];
      const multiSelect = group.querySelector('.multi-select');
      const sizes = Array.from(multiSelect?.querySelectorAll('input[type="checkbox"]:checked') || []).map((input) => input.dataset.label || input.value);
      if (select?.value && !sizes.length) {
        errors.push(`برای رنگ ${selectedOption?.textContent || ''} حداقل یک سایز انتخاب کنید.`);
        return;
      }
      if (!select?.value && sizes.length) {
        errors.push('برای گروهی که سایز انتخاب شده، رنگی مشخص نشده است.');
        return;
      }
      if (!select?.value) {
        return;
      }
      colors.push({
        value: select.value,
        name: selectedOption?.textContent || select.value,
        hex: selectedOption?.dataset.hex || '#d1d5db',
        sizes,
        note,
      });
    });

    if (errors.length) {
      alert(errors[0]);
      return;
    }

    if (!colors.length) {
      alert('حداقل یک رنگ به همراه سایزهای آن را انتخاب کنید.');
      return;
    }

    buildPreview({ productName, colors });
  });

  form.addEventListener('reset', () => {
    window.setTimeout(() => {
      colorContainer.innerHTML = '';
      previewCard.hidden = true;
      colorIndex = 0;
      createColorGroup();
      lastSelectedProduct = productSelect?.value || '';
      refreshColorAvailability();
    }, 0);
  });

  hidePreview.addEventListener('click', () => {
    previewCard.hidden = true;
    updateStepProgress();
  });

  // ایجاد اولین گروه رنگ به صورت پیشفرض
  createColorGroup();
});
