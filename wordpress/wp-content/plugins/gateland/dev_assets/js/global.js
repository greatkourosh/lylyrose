//variables
const gatelandBaseUrl = gateland.root;
const gatelandNotyf = new Notyf({
    duration: 3000,
    position: {
        x: 'right',
        y: 'bottom',
    },
    dismissible: true,
    types: [
        {
            type: 'warning',
            background: '#ffc107',
        },
        {
            type: 'error',
            icon: false
        },
        {
            type: 'success',
            icon: false
        }
    ]
});

window.addEventListener('load', function (){
    const gatelandMenuEls = document.querySelectorAll('.toplevel_page_gateland');
    gatelandMenuEls.forEach(el => {
        el.classList.remove('wp-not-current-submenu');
        el.classList.add('wp-has-submenu',  'wp-has-current-submenu', 'wp-menu-open');
    })
});

//functions
function gatelandLoadTippyInPage(){
    try {
        tippy('.tooltip-btn', {
            theme: 'tomato',
            content: (reference) => reference.getAttribute('tooltip-text'),
        });
    }catch (error){
        console.error('Error: ', error);
    }
}
function gatelandGetQueryParam(key) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(key);
}

async function gatelandApiRequest(url, options = {}) {

    const {
        method = 'GET',
        data = {},
        headers = {},
        form = false
    } = options;

    try {
        const fetchOptions = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': gateland.nonce,
                ...headers,
            },
        };

        if (data && method !== 'GET') {
            if (form) {
                fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                const params = new URLSearchParams();
                for (const key in data) {
                    const value = data[key];
                    if (Array.isArray(value)) {
                        value.forEach(item => {
                            params.append(`${key}[]`, item);
                        });
                    } else {
                        params.append(key, value);
                    }
                }
                fetchOptions.body = params.toString();
            } else {
                fetchOptions.headers['Content-Type'] = 'application/json';
                fetchOptions.body = JSON.stringify(data);
            }
        }

        const response = await fetch(gatelandBaseUrl + url, fetchOptions);

        const contentType = response.headers.get('content-type');
        const responseData = contentType?.includes('application/json')
            ? await response.json()
            : await response.text();

        if (!response.ok) {
            throw new Error(responseData.message || 'خطا در پاسخ از سرور');
        }

        return responseData;
    } catch (err) {
        console.error('API error:', err.message);
        gatelandNotyf.error('در پردازش درخواست خطایی رخ داده است!');
        throw err;
    }
}

function gatelandGetVisiblePages(pagination) {

    let visiblePages = [];

    // Add first page if not already included in the range of current page
    if (pagination.currentPage > 3) {
        visiblePages.push(1);
        if (pagination.currentPage > 4) {
            visiblePages.push('...');
        }
    }

    // Add two pages before and after current page
    let start = Math.max(1, pagination.currentPage -2);
    let end = Math.min(pagination.totalPage,pagination.currentPage +2);


    for (let i=start; i<=end; i++) {
        visiblePages.push(i);

    }

    // Add last page if not already included in the range of current page
    if (pagination.totalPage > end ) {
        if (end < pagination.totalPage -2) {
            visiblePages.push('...');
        }
        visiblePages.push(pagination.totalPage);
    }


    return visiblePages;

}

function gatelandFormatPrice(number) {
    if(typeof(number) === "undefined" || number === null){
        return 0;
    }
    let val = number.toString().replace(/,/g, '');
    val = val.replace(/\D/g, '');
    return new Intl.NumberFormat('en-US').format(val);
}

function gatelandPriceToNumber(price) {
    return Number(price.replace(/,/g, ''));
}

function gatelandFormatDate(timestamp, format){
    const date = new persianDate(timestamp);
    return format ? date.format(format) : date.format();
}

function gatelandDateToTimestamp(newDate){
    const array = newDate.split('-').map(item => Number(item));
    const date = new persianDate(array);
    return date.unix() * 1000;
}

function gatelandCheckDateFormatIsValid(str) {
    const pattern = /^\d{4}-\d{2}-\d{2}$/;
    return pattern.test(str);
}

function gatelandConvertPersianNumberToEnglish(number) {
    const persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

    return number.replace(/[۰-۹]/g, d => persianDigits.indexOf(d));
}

function gatelandParseQueryString(url) {
    const obj = {};
    const queryString = url.includes('?') ? url.split('?')[1] : url;

    const params = new URLSearchParams(queryString);

    for (const [key, value] of params.entries()) {
        const arrayMatch = key.match(/^([^\[]+)\[\d*\]$/);

        if (arrayMatch) {
            const baseKey = arrayMatch[1];
            if (!obj[baseKey]) obj[baseKey] = [];
            obj[baseKey].push(value);
        } else {
            if (obj[key] !== undefined) {
                if (!Array.isArray(obj[key])) {
                    obj[key] = [obj[key]];
                }
                obj[key].push(value);
            } else {
                obj[key] = value;
            }
        }
    }

    return obj;
}

function gatelandBuildQueryString(obj) {
    const params = [];

    for (const key in obj) {
        const value = obj[key];

        if (Array.isArray(value)) {
            value.forEach((item, index) => {
                params.push(`${encodeURIComponent(key)}[${index}]=${encodeURIComponent(item)}`);
            });
        } else if (value !== undefined && value !== null) {
            params.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
        }
    }

    return params.join('&');
}

function gatelandSetUrlQueryParams(namePage, filters){
    delete filters.per_page;

    if(namePage){
        filters.page = namePage;
    }

    if(filters.from_date){
        filters.from_date = gatelandConvertPersianNumberToEnglish(gatelandFormatDate(filters.from_date * 1000, 'YYYY-MM-DD'));
    }
    if(filters.to_date){
        filters.to_date =  gatelandConvertPersianNumberToEnglish(gatelandFormatDate(filters.to_date * 1000, 'YYYY-MM-DD'));
    }

    window.history.replaceState(null, null,  '?' + gatelandBuildQueryString(filters));
}

function gatelandCreateRangeDateFilter(parentEl, dateFromValue, dateToValue) {

        if(!window?.$){
            window.$ = window.jQuery;
        }

        let rangeDateFrom = null;
        let rangeDateTo = null;

        rangeDateFrom = $($(parentEl).find(".range-date-from")).persianDatepicker({
            initialValueType: 'persian',
            inline: true,
            altField: '.range-date-from-alt',
            leapYearMode: 'astronomical',
            altFormat: 'L',
            initialValue: true,
            maxDate: dateToValue,
            toolbox: {
                enabled: false
            },
            navigator: {
                scroll: {
                    enabled: false
                }
            },
            calendar:{
                persian: {
                    leapYearMode: 'astronomical'
                }
            },
            onSelect: (unix) => {
                if (rangeDateTo && rangeDateTo.options && rangeDateTo.options.minDate != unix) {
                    let cachedValue = rangeDateTo.getState().selected.unixDate;
                    rangeDateTo.options = {minDate: unix};
                    rangeDateTo.setDate(cachedValue);
                }
            }
        });
        rangeDateFrom.setDate(dateFromValue)

        rangeDateTo = $($(parentEl).find(".range-date-to")).persianDatepicker({
            initialValueType: 'persian',
            inline: true,
            altField: '.range-date-to-alt',
            altFormat: 'L',
            minDate: dateFromValue,
            initialValue: true,
            toolbox: {
                enabled: false
            },
            navigator: {
                scroll: {
                    enabled: false
                }
            },
            calendar:{
                persian: {
                    leapYearMode: 'astronomical'
                }
            },
            onSelect: (unix) => {
                if (rangeDateFrom && rangeDateFrom.options && rangeDateFrom.options.maxDate != unix) {
                    let cachedValue = rangeDateFrom.getState().selected.unixDate;
                    rangeDateFrom.options = {maxDate: unix};
                    rangeDateFrom.setDate(cachedValue);
                }
            }
        });
        rangeDateTo.setDate(dateToValue)

        return [rangeDateFrom, rangeDateTo];
}

function gatelandGenerateFiltersObject(filters){
    const objFilters = {};

    for (const objKey in filters) {
        if(filters[objKey]){
            objFilters[objKey] = filters[objKey]
        }
    }

    if(objFilters.from_date){
        objFilters.from_date = objFilters.from_date / 1000;
    }
    if( objFilters.to_date){
        objFilters.to_date =  objFilters.to_date / 1000;
    }

    return objFilters;
}

//create chart
let chartTransactions = null;
function gatelandCreateChartTransactions(parentEl, data){

    const canvasEl = parentEl.querySelector('canvas');
    if(chartTransactions){
        chartTransactions.destroy();
    }

    const labels = [];
    const values = [];
    data.forEach(item =>{
        labels.push(item.label);
        values.push(item.value)
    })

    const objData = {
        labels: labels,
        datasets: [
            {
                data: values,
                backgroundColor: '#57A8FF',
                borderRadius: {
                    topLeft: 4,
                    topRight: 4,
                    bottomLeft: 0,
                    bottomRight: 0
                },
                barThickness: 20 // Set fixed bar width
            }
        ]
    }

    chartTransactions = new Chart(canvasEl, {
            type: 'bar',
            data: objData,
            options: {
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            display: false // Disable horizontal grid lines
                        },
                        ticks: {
                            padding: 5 // Add padding to Y-axis labels
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            padding: 8 // Add padding to Y-axis labels
                        }
                    }
                },
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // Disable the legend
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(tooltipItem) {
                                // Customize tooltip label
                                const label = tooltipItem.dataset.label || '';
                                const value = tooltipItem.raw; // Get the value for the current data point
                                return `${value.toLocaleString("en-US")} تومان`; // Customize this string as needed
                            }
                        }
                    }
                }
            }
    });
}

let chartTransactionsStatus = null;
function gatelandCreateChartTransactionsStatus(parentEl, data){

    const canvasEl = parentEl.querySelector('canvas');
    if(chartTransactionsStatus){
        chartTransactionsStatus.destroy();
    }

    const labels = [];
    const values = [];
    const backgroundColor = []

    data.forEach(item =>{
        labels.push(item.label);
        values.push(item.value);
        backgroundColor.push(item.color)
    })

    const objData = {
        labels: labels,
        datasets: [{
            data: values,
            backgroundColor: backgroundColor,
            hoverOffset: 4
        }]
    }

    chartTransactionsStatus = new Chart(canvasEl, {
        type: 'doughnut',
        data: objData,
        legend: {
            display: false
        },
        options: {
                cutout: "60%",
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: 1
                },
                plugins:{
                    legend: {
                        display: false
                    }
                },
            }
    });

}

let chartTransactionsStatusInYear = null;
function gatelandCreateChartTransactionsStatusInYear(parentEl, data){

    const canvasEl = parentEl.querySelector('canvas');
    if(chartTransactionsStatusInYear){
        chartTransactionsStatusInYear.destroy();
    }

    const labels = [];
    const successfulData = [];
    const unsuccessfulData = []

    data.forEach(item =>{
        labels.push(item.label);
        successfulData.push(item.successful);
        unsuccessfulData.push(item.unsuccessful)
    })

    const objData = {
        labels: labels,
        datasets: [
            {
                label: 'موفق',
                data: successfulData,
                borderColor: '#039855',
                backgroundColor: '#039855',
                fill: false,
                yAxisID: 'y-axis-1' // Assign to first Y-axis
            },
            {
                label: 'ناموفق',
                data: unsuccessfulData,
                borderColor: '#F2A6B3',
                backgroundColor: '#F2A6B3',
                fill: false,
                yAxisID: 'y-axis-1' // Assign to the same Y-axis
            }
        ]
    }

    chartTransactionsStatusInYear = new Chart(canvasEl, {
        type: 'line',
        data: objData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    ticks: {
                        padding: 10
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false // Disable the legend
                },
                tooltip: {
                    callbacks: {
                        label: function(tooltipItem) {
                            const datasetLabel = tooltipItem.dataset.label || '';
                            const value = tooltipItem.raw;
                            return `${value} تراکنش ${datasetLabel} `;
                        }
                    }
                }
            }
        }
    });
}


