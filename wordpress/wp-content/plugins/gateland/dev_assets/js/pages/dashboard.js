//change default font chart.js
Chart.defaults.font.family = "'YekanBakhFaNum', 'Vazirmatn', serif";

//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("gatelandDashboard", ()=>({
        pageLoaderIsActive: false,

        //page data
        todayDate: null,
        dashboard: {},
        chartTransactionsData: [],
        chartTransactionsStatusData: [],
        chartTransactionsStatusInYearData: [],
        rangeDateFrom: null,
        rangeDateTo: null,

        //table data
        tableData: [],
        tableLoaderIsActive: false,
        tableFilters: {
            page: 1,
            per_page: 20,
            from_date: null,
            to_date: null,
        },
        pagination:{
            items: [],
            currentPage: 1,
            totalPage: 0,
        },

        //modals
        modals: {
            rangeDate:{
                active: false
            }
        },

        async init(){
            this.todayDate = new persianDate();

            await this.getPageData();

            //initial date picker
            const tempFromDate = this.tableFilters.from_date ? this.tableFilters.from_date  : this.todayDate;
            const tempToDate = this.tableFilters.to_date ? this.tableFilters.to_date : this.todayDate;
            const [rangeDateFrom, rangeDateTo] = gatelandCreateRangeDateFilter(document.getElementById("rangeDateFilter"), tempFromDate, tempToDate)
            this.rangeDateFrom = rangeDateFrom;
            this.rangeDateTo = rangeDateTo;

        },

        async getPageData(){
            this.pageLoaderIsActive = true;
            this.tableLoaderIsActive = true;

            const queryString = gatelandParseQueryString(window.location.href);
            delete queryString.page;
            delete queryString.per_page;

            for (const objKey in queryString) {
                if(objKey === 'from_date' || objKey === 'to_date'){
                    if(gatelandCheckDateFormatIsValid(queryString[objKey])){
                        this.tableFilters[objKey] = gatelandDateToTimestamp(queryString[objKey]);
                    }else{
                        delete queryString[objKey];
                    }
                }else{
                    this.tableFilters[objKey] = queryString[objKey];
                }
            }

            if(!this.tableFilters?.from_date || !this.tableFilters?.to_date || (this.tableFilters?.from_date > this.tableFilters?.to_date)){
                this.tableFilters.from_date = this.todayDate.add('days', -30).unix() * 1000;
                this.tableFilters.to_date = this.todayDate.unix() * 1000;

                const filtersObj = gatelandGenerateFiltersObject(this.tableFilters);
                gatelandSetUrlQueryParams('gateland', filtersObj);
            }

            await this.getDashboard();
            await this.getTransactions();

            this.chartTransactionsStatusData = [];
            if(this.dashboard.donut_chart.length > 0){

                this.dashboard.donut_chart.forEach((item)=>{
                    item.value = Number(item.value);
                    switch (item.status){
                        case 'paid': {
                            this.chartTransactionsStatusData.push(
                                {
                                    label: 'موفق',
                                    value: item.value,
                                    color: '#039855',
                                }
                            )
                            break;
                        }
                        case 'failed': {
                            this.chartTransactionsStatusData.push(
                                {
                                    label: 'در انتظار پرداخت',
                                    value: item.value,
                                    color: '#FEC84B',
                                }
                            )
                            break;
                        }
                        case 'pending': {
                            this.chartTransactionsStatusData.push(
                                {
                                    label: 'ناموفق',
                                    value: item.value,
                                    color: '#DF2040',
                                }
                            )
                            break;
                        }
                        case 'refund': {
                            this.chartTransactionsStatusData.push(
                                {
                                    label: 'استرداد شده',
                                    value: item.value,
                                    color: '#667085',
                                }
                            )
                            break;
                        }
                    }
                })
                gatelandCreateChartTransactionsStatus(document.getElementById("chartTransactionsStatus"),  this.chartTransactionsStatusData);
            }

            if(this.dashboard.bar_chart){
                this.chartTransactionsData = this.dashboard.bar_chart;

                gatelandCreateChartTransactions(document.getElementById("chartTransactions"),  this.chartTransactionsData);
            }

            this.pageLoaderIsActive = false;

        },

        //request functions
        async getDashboard(){
            try{
                const result = await gatelandApiRequest('gateland/dashboard/overview', {
                    method: 'POST',
                    form: true,
                    data: gatelandGenerateFiltersObject(this.tableFilters)
                })

                if(result.success){
                    this.dashboard = {};
                    this.dashboard =  result.data;
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }


            }catch (error){
                console.error('Error fetching posts:', error);
                this.fromLoaderIsActive = false;
            }
        },

        async getTransactions(){
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/transaction/index', {
                    method: 'POST',
                    form: true,
                    data: gatelandGenerateFiltersObject(this.tableFilters)
                })

                if(result.success){
                    this.tableData = [];

                    const data = result.data;
                    this.tableData = data.transactions;
                    this.pagination = {
                        currentPage: data.current_page,
                        totalPage: parseInt((data.total_items / this. tableFilters.per_page)) + 1,
                        items: gatelandGetVisiblePages({
                            currentPage: 1,
                            totalPage: parseInt((data.total_items / this. tableFilters.per_page)) + 1
                        })
                    }
                    data.statuses.push( {
                        status: "all",
                        count: data.total_items,
                    })
                    this.statuses = data.statuses;
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.pageLoaderIsActive = false;
                }

                this.tableLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }

        },

        //other function
        setDateFilter(){

            this.tableFilters.from_date = this.rangeDateFrom.getState().selected.unixDate;
            this.tableFilters.to_date = this.rangeDateTo.getState().selected.unixDate;

            const filtersObj = gatelandGenerateFiltersObject(this.tableFilters);
            gatelandSetUrlQueryParams('gateland', filtersObj);

            this.getPageData();

        },

        clearDateFilter(){
            this.tableFilters.from_date = this.todayDate.add('days', -30).unix() * 1000;
            this.tableFilters.to_date = this.todayDate.unix() * 1000;

            const filtersObj = gatelandGenerateFiltersObject(this.tableFilters);
            gatelandSetUrlQueryParams('gateland', filtersObj);

            this.getPageData();
        }
    }))

    //remove later
    Alpine.data("charts", ()=>({

        init(){
            this.chartTransactionsStatusInYearData = [
                {
                    label: "فروردین 1403",
                    successful: 850,
                    unsuccessful: 450,
                },
                {
                    label: "اردیبهشت 1403",
                    successful: 900,
                    unsuccessful: 50,
                },
                {
                    label: "خرداد 1403",
                    successful: 1000,
                    unsuccessful: 85,
                },
                {
                    label: "تیر 1403",
                    successful: 700,
                    unsuccessful: 29,
                },
                {
                    label: "مرداد 1403",
                    successful: 1025,
                    unsuccessful: 350,
                },
                {
                    label: "شهریور 1403",
                    successful: 850,
                    unsuccessful: 450,
                },
                {
                    label: "مهر 1403",
                    successful: 100,
                    unsuccessful: 900,
                },
                {
                    label: "آبان 1403",
                    successful: 700,
                    unsuccessful: 29,
                },
                {
                    label: "آذر 1403",
                    successful: 990,
                    unsuccessful: 100,
                },
                {
                    label: "دی 1403",
                    successful: 850,
                    unsuccessful: 150,
                },
                {
                    label: "بهمن 1403",
                    successful: 500,
                    unsuccessful: 40,
                },
                {
                    label: "اسفند 1403",
                    successful: 746,
                    unsuccessful: 201,
                }
            ]
            gatelandCreateChartTransactionsStatusInYear(document.getElementById("chartTransactionsStatusInYear"),  this.chartTransactionsStatusInYearData)
        }

    }))

})