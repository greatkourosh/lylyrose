//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("gatelandTransactions", ()=>({
        pageLoaderIsActive: false,

        //table data
        tableData: [],
        tableLoaderIsActive: false,
        tableFilters: {
            page: 1,
            per_page: 20,
            gateway_ids: [],
            clients: [],
            status: null,
            from_date: null,
            to_date: null,
            order_id: null,
            transaction_id: null,
            mobile: null,
            card_number: null,
            gateway_au: null,
            ip: null,
            description: null,
            amount: null,
            min_amount: null,
            max_amount: null,
        },
        pagination:{
            items: [],
            currentPage: 1,
            totalPage: 0,
        },

        //page data
        statuses: [],
        filters: {
            gateways: [],
            clients: [],
            statuses: []
        },
        filtersLoaderIsActive: false,
        rangeDateFrom: null,
        rangeDateTo: null,

        //modals
        modals: {
            advanceSearch: {
                active: false
            },
            rangeDate:{
                active: false
            }
        },

        async init(){
            let todayDate = new persianDate(); //set today

            //this.tableFilters.from_date = todayDate.add('days', -30).unix() * 1000;
            //this.tableFilters.to_date = todayDate.unix() * 1000;

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

            if(!this.tableFilters.from_date || !this.tableFilters.to_date || (this.tableFilters?.from_date > this.tableFilters?.to_date)){
                delete this.tableFilters.from_date;
                delete this.tableFilters.to_date;
            }

            this.getPageData();
            this.getFilters();

            //initial date picker
            const tempFromDate = this.tableFilters.from_date ? this.tableFilters.from_date  : todayDate;
            const tempToDate = this.tableFilters.to_date ? this.tableFilters.to_date : todayDate;
            const [rangeDateFrom, rangeDateTo] = gatelandCreateRangeDateFilter(document.getElementById("rangeDateFilter"), tempFromDate, tempToDate)
            this.rangeDateFrom = rangeDateFrom;
            this.rangeDateTo = rangeDateTo;

        },

        //request functions
        async getPageData(){
            this.tableLoaderIsActive = true;

            const filtersObj = gatelandGenerateFiltersObject(this.tableFilters);
            gatelandSetUrlQueryParams('gateland-transactions', filtersObj);

            try{

                const result = await gatelandApiRequest('gateland/transaction/index', {
                    method: 'POST',
                    form: true,
                    data: gatelandGenerateFiltersObject(this.tableFilters)
                })

                if(result.success){
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

        async getFilters(){
            this.filtersLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/transaction/filters', {
                    method: 'POST'
                })

                if(result.success){
                    result.data?.gateways.forEach(item=>{
                        item.key = item.key.toString();
                    })

                    this.filters = result.data;
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.filtersLoaderIsActive = false;
                }

                this.filtersLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }

        },

        //other function
        getNumberOfAdvancedFilters(){
            let count = 0 ;
            const advancedFiltersKey = ['clients', 'card_number', 'gateway_au', 'ip', 'description', 'amount', 'min_amount', 'max_amount']

            advancedFiltersKey.forEach((item)=>{
                if(this.tableFilters[item]){
                    if(Array.isArray(this.tableFilters[item])){
                        if(this.tableFilters[item].length > 0){
                            count+=1;
                        }
                    }else{
                        count+=1;
                    }
                }
            })

            return count;
        },

        changePage(newPage){
            this.pagination.currentPage = newPage;
            this. tableFilters.page = newPage;
            this.getPageData();
        },

        setDateFilter(){

            this.tableFilters.from_date = this.rangeDateFrom.getState().selected.unixDate;
            this.tableFilters.to_date = this.rangeDateTo.getState().selected.unixDate;

        },

        download(nonce){
            const filtersObj = gatelandGenerateFiltersObject(this.tableFilters);
            filtersObj.export = true;
            filtersObj._wpnonce = nonce;
            window.location = gatelandBaseUrl + 'gateland/transaction/index?' + gatelandBuildQueryString(filtersObj);
        },

        clearDateFilter(){
            this.tableFilters.from_date = null;
            this.tableFilters.to_date = null;
        },

        clearFilter(){
            this.tableFilters = {
                page: 1,
                per_page: 20,
                gateway_ids: [],
                clients: [],
                status: null,
                from_date: null,
                to_date: null,
                order_id: null,
                transaction_id: null,
                mobile: null,
                card_number: null,
                gateway_au: null,
                ip: null,
                description: null,
                amount: null,
                min_amount: null,
                max_amount: null,
            }

            this.getPageData();
        }

    }))

})