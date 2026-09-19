$(document).ready(function () {
    $('.my-select2').select2({
        style: 'w-100 pb-0'
    });

    var from, to;

    from = $("[data-date-from]").pDatepicker({
        format: 'YYYY-MM-DD',
        initialValue: false,
        initialValueType: 'persian',
        altField: '#from_date',
        toolbox: {
            enabled: true,
            submitButton: {
                enabled: true,
                text: {
                    fa: 'تایید',
                    en: 'Submit'
                }
            },
            calendarSwitch: {
                enabled: false,
            }
        },
        observer: true,
        altFieldFormatter: function (unixDate) {

            var self = this;
            var thisAltFormat = self.altFormat.toLowerCase();
            if (thisAltFormat === 'gregorian' || thisAltFormat === 'g') {
                return new Date(unixDate);
            }
            if (thisAltFormat === 'unix' || thisAltFormat === 'u') {
                return unixDate / 1000;
            } else {
                var pd = new persianDate(unixDate);
                pd.formatPersian = this.persianDigit;
                return pd.format(self.altFormat);
            }
        },
        onSelect: function (unix) {
            from.touched = true;
            if (to && to.options && to.options.minDate != unix) {
                var cachedValue = to.getState().selected.unixDate;
                to.options = {minDate: unix};
                if (to.touched) {
                    to.setDate(cachedValue);
                }
            }
        },
    });

    to = $("[data-date-to]").pDatepicker({
        format: 'YYYY-MM-DD',
        initialValue: false,
        altField: '#to_date',
        initialValueType: 'persian',
        observer: true,
        toolbox: {
            enabled: true,
            submitButton: {
                enabled: true,
                text: {
                    fa: 'تایید',
                    en: 'Submit'
                }
            },
            calendarSwitch: {
                enabled: false,
            }
        },
        altFieldFormatter: function (unixDate) {
            var self = this;
            var thisAltFormat = self.altFormat.toLowerCase();
            if (thisAltFormat === 'gregorian' || thisAltFormat === 'g') {
                return new Date(unixDate);
            }
            if (thisAltFormat === 'unix' || thisAltFormat === 'u') {
                return unixDate / 1000;
            } else {
                var pd = new persianDate(unixDate);
                pd.formatPersian = this.persianDigit;
                return pd.format(self.altFormat);
            }
        },
        onSelect: function (unix) {
            to.touched = true;
            if (from && from.options && from.options.maxDate != unix) {
                var cachedValue = from.getState().selected.unixDate;
                from.options = {maxDate: unix};
                if (from.touched) {
                    from.setDate(cachedValue);
                }
            }
        }
    });

    to = $("[data-created-at]").pDatepicker({
        format: 'YYYY-MM-DD',
        initialValue: false,
        altField: '#created_at_value',
        initialValueType: 'persian',
        observer: true,
        toolbox: {
            enabled: true,
            submitButton: {
                enabled: true,
                text: {
                    fa: 'تایید',
                    en: 'Submit'
                }
            },
            calendarSwitch: {
                enabled: false,
            }
        },
        altFieldFormatter: function (unixDate) {
            console.log(3123123);
            var self = this;
            var thisAltFormat = self.altFormat.toLowerCase();
            if (thisAltFormat === 'gregorian' || thisAltFormat === 'g') {
                return new Date(unixDate);
            }
            if (thisAltFormat === 'unix' || thisAltFormat === 'u') {
                return unixDate / 1000;
            } else {
                var pd = new persianDate(unixDate);
                pd.formatPersian = this.persianDigit;
                return pd.format(self.altFormat);
            }
        }
    });


    $('#from_date_picker').change(function () {
        if (this.value === "") {
            $("#from_date").removeAttr('value')
        }
    })

    $('#to_date_picker').change(function () {
        if (this.value === "") {
            $("#to_date").removeAttr('value')
        }
    })

    $('#created_at_picker').change(function () {
        if (this.value === "") {
            $("#created_at_value").removeAttr('value')
        }
    })
})
