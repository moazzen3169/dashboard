// Initialize Persian datepicker on all inputs with class 'jalali-date'
$(document).ready(function() {
    $('.jalali-date').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        initialValue: false,
        calendarType: 'persian',
        observer: true,
        toolbox: {
            calendarSwitch: {
                enabled: false
            },
            todayButton: {
                enabled: true
            },
            submitButton: {
                enabled: true
            },
            text: {
                btnToday: "امروز",
                btnSubmit: "تایید",
                btnCancel: "لغو"
            }
        },
        navigator: {
            enabled: true,
            scroll: {
                enabled: true
            },
            text: {
                btnNextText: "بعدی",
                btnPrevText: "قبلی"
            }
        },
        timePicker: {
            enabled: false
        },
        altField: '.alt-date',
        altFormat: 'YYYY-MM-DD',
        autoClose: true,
        onSelect: function(unix) {
            // Custom validation or actions on date select can be added here
        }
    });

    // Disable manual input to enforce datepicker usage
    $('.jalali-date').on('keydown paste', function(e) {
        e.preventDefault();
    });
});
