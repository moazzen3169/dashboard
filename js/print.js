// Print all monthly invoices
function printTable() {
  var contentArea = document.querySelector(".content-area");
  var newWin = window.open("");
  newWin.document.write("<html><head><title>فاکتورها</title><link rel='stylesheet' href='css/invoices.css'></head><body>");
  newWin.document.write(contentArea.innerHTML);
  newWin.document.write("</body></html>");
  newWin.print();
  newWin.close();
}

// Print a single monthly invoice
function printInvoiceMonth(btn) {
  var invoiceSection = btn.closest(".invoice-month");
  var newWin = window.open("");
  newWin.document.write("<html><head><title>فاکتور ماهانه</title><link rel='stylesheet' href='css/invoices.css'></head><body>");
  newWin.document.write(invoiceSection.outerHTML);
  newWin.document.write("</body></html>");
  newWin.print();
  newWin.close();
}

// Print product sales receipt
function printProductReceipt(productData) {
  var receiptNumber = 'RCP-' + productData.id + '-' + Date.now();
  var currentDate = new Date();
  var jalaliDate = productData.sale_date_jalali;
  var currentTime = currentDate.toLocaleTimeString('fa-IR');

  var receiptHTML = `
    <html>
    <head>
      <title>فیش فروش - ${productData.name}</title>
      <style>
        @media print {
          body { font-family: 'Peyda', Arial, sans-serif; direction: rtl; margin: 0; padding: 10px; }
          .receipt { border: 1px solid #000; padding: 15px; max-width: 300px; margin: 0 auto; }
          .header { text-align: center; border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 10px; }
          .store-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
          .store-info { font-size: 12px; color: #666; }
          .receipt-info { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 12px; }
          .product-details { border: 1px solid #ddd; padding: 10px; margin: 10px 0; }
          .product-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
          .total { font-weight: bold; font-size: 14px; border-top: 1px solid #000; padding-top: 10px; margin-top: 10px; }
          .footer { text-align: center; font-size: 10px; margin-top: 15px; color: #666; }
        }
      </style>
    </head>
    <body>
      <div class="receipt">
        <div class="header">
          <div class="store-name">فروشگاه پوشاک</div>
          <div class="store-info">آدرس: تهران، خیابان ولیعصر<br>تلفن: ۰۲۱-۱۲۳۴۵۶۷۸</div>
        </div>

        <div class="receipt-info">
          <span>شماره فیش: ${receiptNumber}</span>
          <span>تاریخ: ${jalaliDate}</span>
        </div>

        <div class="receipt-info">
          <span>ساعت: ${currentTime}</span>
        </div>

        <div class="product-details">
          <div class="product-row">
            <span>کد محصول:</span>
            <span>${productData.id}</span>
          </div>
          <div class="product-row">
            <span>نام محصول:</span>
            <span>${productData.name}</span>
          </div>
          <div class="product-row">
            <span>رنگ:</span>
            <span>${productData.color}</span>
          </div>
          <div class="product-row">
            <span>سایز:</span>
            <span>${productData.size}</span>
          </div>
        </div>

        <div class="total">
          <div class="product-row">
            <span>مجموع:</span>
            <span>${productData.price.toLocaleString('fa-IR')} تومان</span>
          </div>
        </div>

        <div class="footer">
          از خرید شما سپاسگزاریم<br>
          این فیش توسط سیستم مدیریت فروش تولید شده است
        </div>
      </div>
    </body>
    </html>
  `;

  var newWin = window.open("", "_blank");
  newWin.document.write(receiptHTML);
  newWin.document.close();

  // Wait for content to load then print
  newWin.onload = function() {
    newWin.print();
    newWin.close();
  };
}

// Print payment receipt
function printPaymentReceipt(paymentData) {
  var receiptNumber = 'PAY-' + paymentData.id + '-' + Date.now();
  var currentDate = new Date();
  var jalaliDate = paymentData.jalali_date;
  var currentTime = currentDate.toLocaleTimeString('fa-IR');

  var receiptHTML = `
    <html>
    <head>
      <title>فیش پرداختی - ${paymentData.target}</title>
      <style>
        @media print {
          body { font-family: 'Peyda', Arial, sans-serif; direction: rtl; margin: 0; padding: 10px; }
          .receipt { border: 1px solid #000; padding: 15px; max-width: 300px; margin: 0 auto; }
          .header { text-align: center; border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 10px; }
          .store-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
          .store-info { font-size: 12px; color: #666; }
          .receipt-info { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 12px; }
          .payment-details { border: 1px solid #ddd; padding: 10px; margin: 10px 0; }
          .payment-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
          .total { font-weight: bold; font-size: 14px; border-top: 1px solid #000; padding-top: 10px; margin-top: 10px; }
          .footer { text-align: center; font-size: 10px; margin-top: 15px; color: #666; }
        }
      </style>
    </head>
    <body>
      <div class="receipt">
        <div class="header">
          <div class="store-name">فروشگاه پوشاک</div>
          <div class="store-info">آدرس: تهران، خیابان ولیعصر<br>تلفن: ۰۲۱-۱۲۳۴۵۶۷۸</div>
        </div>

        <div class="receipt-info">
          <span>شماره فیش: ${receiptNumber}</span>
          <span>تاریخ: ${jalaliDate}</span>
        </div>

        <div class="receipt-info">
          <span>ساعت: ${currentTime}</span>
        </div>

        <div class="payment-details">
          <div class="payment-row">
            <span>کد پرداختی:</span>
            <span>${paymentData.id}</span>
          </div>
          <div class="payment-row">
            <span>مقصد:</span>
            <span>${paymentData.target}</span>
          </div>
        </div>

        <div class="total">
          <div class="payment-row">
            <span>مبلغ پرداختی:</span>
            <span>${paymentData.amount.toLocaleString('fa-IR')} تومان</span>
          </div>
        </div>

        <div class="footer">
          فیش پرداختی<br>
          این فیش توسط سیستم مدیریت فروش تولید شده است
        </div>
      </div>
    </body>
    </html>
  `;

  var newWin = window.open("", "_blank");
  newWin.document.write(receiptHTML);
  newWin.document.close();

  // Wait for content to load then print
  newWin.onload = function() {
    newWin.print();
    newWin.close();
  };
}
