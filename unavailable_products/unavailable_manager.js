/**
 * Unavailable Products Manager
 * Optimized and modular JavaScript for managing unavailable products
 */
class UnavailableProductsManager {
    constructor() {
        this.editColorIndex = 0;
        this.availableSizes = [36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60];
        this.colors = [
            'مشکی', 'سفید', 'قرمز', 'سبز', 'زرد', 'خردلی', 'کرمی',
            'قهوه ای', 'صورتی', 'زرشکی', 'توسی', 'گلبهی', 'بنفش', 'آبی', 'تعویضی'
        ];

        this.init();
    }

    /**
     * Initialize the manager
     */
    init() {
        this.bindEvents();
        this.setupAccessibility();
    }

    /**
     * Bind all event listeners
     */
    bindEvents() {
        // Modal close events
        document.addEventListener('click', (e) => this.handleModalClose(e));

        // Edit form submission
        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.addEventListener('submit', (e) => this.handleEditSubmit(e));
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => this.handleKeyboardNavigation(e));
    }

    /**
     * Setup accessibility features
     */
    setupAccessibility() {
        // Add ARIA labels to modals
        const editModal = document.getElementById('editModal');
        const printModal = document.getElementById('printModal');

        if (editModal) {
            editModal.setAttribute('role', 'dialog');
            editModal.setAttribute('aria-labelledby', 'editModalTitle');
            editModal.setAttribute('aria-hidden', 'true');
        }

        if (printModal) {
            printModal.setAttribute('role', 'dialog');
            printModal.setAttribute('aria-labelledby', 'printModalTitle');
            printModal.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Handle modal close events
     */
    handleModalClose(e) {
        if (e.target.classList.contains('modal-close') || e.target.closest('.modal-close')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                this.closeModal(modal.id);
            }
        }
    }

    /**
     * Handle keyboard navigation
     */
    handleKeyboardNavigation(e) {
        if (e.key === 'Escape') {
            const editModal = document.getElementById('editModal');
            const printModal = document.getElementById('printModal');

            if (editModal && editModal.style.display === 'block') {
                this.closeEditModal();
            } else if (printModal && printModal.style.display === 'block') {
                this.closePrintModal();
            }
        }
    }

    /**
     * Delete item with improved UX
     */
    async deleteItem(id, button) {
        const productName = button.closest('tr').querySelector('td:first-child').textContent.trim();

        // Create custom confirmation dialog
        const confirmed = await this.showCustomConfirm(
            `حذف محصول`,
            `آیا مطمئن هستید که می‌خواهید محصول "${productName}" را حذف کنید؟`,
            'حذف',
            'انصراف'
        );

        if (!confirmed) return;

        try {
            this.showLoading(button, 'حذف...');

            const response = await fetch('list_unavailable.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `delete_id=${id}`
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification('محصول با موفقیت حذف شد', 'success');
                button.closest('tr').remove();

                // Check if table is empty
                this.checkEmptyTable();
            } else {
                this.showNotification(data.error || 'خطا در حذف محصول', 'error');
            }
        } catch (error) {
            this.showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
            this.hideLoading(button);
        }
    }

    /**
     * Edit item with improved UX
     */
    editItem(id, productName, colorsJson) {
        const editModal = document.getElementById('editModal');
        if (!editModal) return;

        // Set form values
        document.getElementById('editId').value = id;
        document.getElementById('editProductName').value = productName;

        // Parse and populate colors
        try {
            const colors = JSON.parse(colorsJson);
            this.populateEditColors(colors);
        } catch (error) {
            console.error('Error parsing colors JSON:', error);
            this.populateEditColors([]);
        }

        // Show modal
        this.openModal('editModal');
    }

    /**
     * Populate edit colors
     */
    populateEditColors(colors) {
        const editColorsDiv = document.getElementById('editColorsDiv');
        if (!editColorsDiv) return;

        editColorsDiv.innerHTML = '';
        this.editColorIndex = 0;

        if (colors && colors.length > 0) {
            colors.forEach(colorData => {
                this.addEditColorField(colorData);
            });
        } else {
            // Add at least one empty color field
            this.addEditColorField();
        }
    }

    /**
     * Add edit color field
     */
    addEditColorField(colorData = null) {
        const editColorsDiv = document.getElementById('editColorsDiv');
        if (!editColorsDiv) return;

        const div = document.createElement('div');
        div.className = 'color-group';
        div.setAttribute('data-index', this.editColorIndex);

        const colorValue = colorData ? colorData.color : '';
        const sizes = colorData ? colorData.sizes || [] : [];

        div.innerHTML = `
            <div class="color-group-header">
                <div class="field">
                    <label>رنگ:</label>
                    <select name="edit_colors[${this.editColorIndex}][color]" class="form-input" required>
                        <option value="">انتخاب رنگ...</option>
                        ${this.colors.map(color =>
                            `<option value="${color}" ${colorValue === color ? 'selected' : ''}>${color}</option>`
                        ).join('')}
                    </select>
                </div>
                <button type="button" class="btn-small danger remove-color-btn" onclick="unavailableManager.removeEditColorField(this)" title="حذف رنگ">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="size-list">
                ${this.availableSizes.map(size => `
                    <label class="size-item">
                        <input type="checkbox" name="edit_colors[${this.editColorIndex}][sizes][]" value="${size}" ${sizes.includes(size) ? 'checked' : ''}>
                        <span>${size}</span>
                    </label>
                `).join('')}
            </div>
        `;

        editColorsDiv.appendChild(div);
        this.editColorIndex++;
    }

    /**
     * Remove edit color field
     */
    removeEditColorField(button) {
        const colorGroup = button.closest('.color-group');
        const editColorsDiv = document.getElementById('editColorsDiv');

        if (colorGroup && editColorsDiv) {
            // Don't remove if it's the only color field
            if (editColorsDiv.children.length > 1) {
                colorGroup.remove();
            } else {
                this.showNotification('حداقل یک رنگ باید وجود داشته باشد', 'warning');
            }
        }
    }

    /**
     * Handle edit form submission
     */
    async handleEditSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const productName = form.product_name.value.trim();

        if (!productName) {
            this.showNotification('لطفاً نام محصول را وارد کنید', 'error');
            return;
        }

        // Validate colors and sizes
        const colorsData = this.validateAndGetColorsData();
        if (!colorsData) return;

        try {
            this.showLoading(submitBtn, 'در حال ذخیره...');

            const formData = new FormData();
            formData.append('update_id', form.id.value);
            formData.append('product_name', productName);
            formData.append('colors', JSON.stringify(colorsData));

            const response = await fetch('list_unavailable.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message || 'محصول با موفقیت بروزرسانی شد', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                this.showNotification(data.error || 'خطا در بروزرسانی', 'error');
            }
        } catch (error) {
            this.showNotification('خطا در ارتباط با سرور', 'error');
        } finally {
            this.hideLoading(submitBtn);
        }
    }

    /**
     * Validate and get colors data
     */
    validateAndGetColorsData() {
        const colorGroups = document.querySelectorAll('#editColorsDiv .color-group');
        const colorsData = [];

        colorGroups.forEach((group, index) => {
            const colorSelect = group.querySelector(`select[name="edit_colors[${index}][color]"]`);
            const sizeCheckboxes = group.querySelectorAll(`input[name="edit_colors[${index}][sizes][]"]:checked`);

            if (colorSelect && colorSelect.value) {
                const sizes = Array.from(sizeCheckboxes).map(cb => parseInt(cb.value));
                colorsData.push({
                    color: colorSelect.value,
                    sizes: sizes
                });
            }
        });

        if (colorsData.length === 0) {
            this.showNotification('لطفاً حداقل یک رنگ با سایز انتخاب کنید', 'error');
            return null;
        }

        return colorsData;
    }

    /**
     * Print item
     */
    printItem(id, productName, colorsJson) {
        const printModal = document.getElementById('printModal');
        const printContent = document.getElementById('printContent');

        if (!printModal || !printContent) return;

        try {
            const colors = JSON.parse(colorsJson);

            let html = `
                <div class="print-header">
                    <h2>محصول ناموجود</h2>
                    <h3>${this.escapeHtml(productName)}</h3>
                </div>
                <div class="print-details">
            `;

            colors.forEach(colorData => {
                html += `
                    <div class="print-color">
                        <strong>رنگ ${this.escapeHtml(colorData.color)}:</strong>
                        <span>سایزهای ${colorData.sizes.join(', ')}</span>
                    </div>
                `;
            });

            html += `</div>`;
            printContent.innerHTML = html;

            this.openModal('printModal');
        } catch (error) {
            this.showNotification('خطا در نمایش اطلاعات پرینت', 'error');
        }
    }

    /**
     * Open modal
     */
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            modal.setAttribute('aria-hidden', 'false');

            // Focus management
            const firstFocusable = modal.querySelector('input, button, select, textarea');
            if (firstFocusable) {
                firstFocusable.focus();
            }
        }
    }

    /**
     * Close modal
     */
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Close edit modal
     */
    closeEditModal() {
        this.closeModal('editModal');
    }

    /**
     * Close print modal
     */
    closePrintModal() {
        this.closeModal('printModal');
    }

    /**
     * Show loading state
     */
    showLoading(element, text = 'در حال بارگذاری...') {
        if (element) {
            element.disabled = true;
            element.dataset.originalText = element.innerHTML;
            element.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
        }
    }

    /**
     * Hide loading state
     */
    hideLoading(element) {
        if (element && element.dataset.originalText) {
            element.disabled = false;
            element.innerHTML = element.dataset.originalText;
            delete element.dataset.originalText;
        }
    }

    /**
     * Show custom confirmation dialog
     */
    showCustomConfirm(title, message, confirmText = 'تایید', cancelText = 'انصراف') {
        return new Promise((resolve) => {
            // Create modal HTML
            const modalHtml = `
                <div id="confirmModal" class="modal" style="display: block;">
                    <div class="modal-content" style="max-width: 400px;">
                        <div class="modal-header">
                            <h3 class="h3">${this.escapeHtml(title)}</h3>
                        </div>
                        <div style="padding: 20px; text-align: center;">
                            <p>${this.escapeHtml(message)}</p>
                            <div style="margin-top: 20px;">
                                <button id="confirmBtn" class="btn">${this.escapeHtml(confirmText)}</button>
                                <button id="cancelBtn" class="btn-secondary" style="margin-right: 10px;">${this.escapeHtml(cancelText)}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add to DOM
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Bind events
            const confirmBtn = document.getElementById('confirmBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const modal = document.getElementById('confirmModal');

            const cleanup = () => {
                if (modal) modal.remove();
            };

            confirmBtn.addEventListener('click', () => {
                cleanup();
                resolve(true);
            });

            cancelBtn.addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            // Close on outside click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    cleanup();
                    resolve(false);
                }
            });
        });
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelector('.notification');
        if (existing) existing.remove();

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    /**
     * Get notification icon
     */
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    /**
     * Check if table is empty
     */
    checkEmptyTable() {
        const tbody = document.querySelector('.table tbody');
        if (tbody && tbody.children.length <= 1) { // Only empty message row
            location.reload(); // Refresh to show empty state
        }
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.unavailableManager = new UnavailableProductsManager();
});
