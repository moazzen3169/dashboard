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
