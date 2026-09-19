//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("gatelandAddGateway", ()=>({
        pageLoaderIsActive: false,

        //table data
        tableData: [],
        tableLoaderIsActive: false,
        tableFilters: {
          type: null,
        },

        //page data
        currentStep: 'add', //setting
        gatewaysTypes: [
            {
                label: 'درگاه اعتباری',
                value: 'BNPLFeature',
            },
            {
                label: 'استعلام تراکنش',
                value: 'InquiryFeature',
            },
            {
                label: 'استرداد تراکنش',
                value: 'RefundFeature',
            },
            {
                label: 'درگاه بانکی',
                value: 'ShaparakFeature',
            },
            {
                label: 'تطبیق کارت و موبایل',
                value: 'MatchCardWithMobile',
            }
        ],

        //select step
        isProActive: false,
        selectedGatewayClass: null,

        selectedGateway: {},

        //setting step
        gatewayOptions: [],

        init(){
            this.getPageData();
        },

        //request functions
        async getPageData(){
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/gateway/list', {
                    method: 'POST',
                })

                if(result.success){
                    const data = result.data;
                    this.tableData = data.gateways;
                    this.isProActive = data.is_pro_active;
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

        async getGatewayOptions(className){
            this.pageLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/gateway/get-options', {
                    method: 'POST',
                    data: {
                        class: className
                    }
                })

                this.pageLoaderIsActive = false;

                if(result.success){
                    result.data.options.forEach(item=>{
                        if(item.type === 'select'){
                            const arrayOptions = [];
                            for(const key in item.options){
                                arrayOptions.push(item.options[key])
                            }
                            item.options = arrayOptions;
                        }
                    })
                   return  result.data.options;
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.pageLoaderIsActive = false;
                }

            }catch (error){
                console.error('Error fetching posts:', error);
                this.pageLoaderIsActive = false;
            }

            return false;

        },

        async addGateway(data){
            this.pageLoaderIsActive = true;

            try {

                const result = await gatelandApiRequest('gateland/gateway/add', {
                    method: 'POST',
                    data
                })

                if(result.success){
                    gatelandNotyf.success('درگاه با موفقیت اضافه شد. در حال انتقال به لیست درگاه‌ها ...');
                    setTimeout(()=>{
                        window.location.replace(window.origin + window.location.pathname + '?page=gateland-gateways');
                    }, 3000)
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.pageLoaderIsActive = false;
                }

            }catch (error){
                console.error('Error fetching posts:', error);
                this.pageLoaderIsActive = false;
            }

        },

        getFilteredData(){
            if(this.tableFilters.type){
                return this.tableData.filter(item => item.features.includes(this.tableFilters.type));
            }else {
                return this.tableData;
            }
        },

        getGatewayTypeLabel(value){
            const type = this.gatewaysTypes.find((item)=> item.value === value);
            return  type ?  type.label : '-';
        },

        async nextStep(){
            switch (this.currentStep) {
                case 'add': {

                    if(!this.selectedGateway.class){
                        gatelandNotyf.open({
                            type: 'warning',
                            message: 'لطفا ابتدا یک درگاه انتخاب کنید!'
                        })
                        return
                    }

                    const options = await this.getGatewayOptions(this.selectedGateway.class)
                    if(options){
                        this.gatewayOptions = options.map(item=>{
                            item.model = item.default ? item.default : null;
                            item.errorMsg = ''
                            return item;
                        });

                        this.currentStep = 'setting';
                    }

                    break
                }
            }
        },

        prevStep(){
            switch (this.currentStep) {
                case 'add': {
                    this.currentStep = 'setting';
                    break
                }
                case 'setting': {
                    this.currentStep = 'add';
                    break
                }
            }
        },

        submit(){

            const data = {};
            this.gatewayOptions.forEach(item=>{
                data[item.key] = item.model;
            })

            this.addGateway({
                class: this.selectedGateway.class,
                data
            });
        }

    }))

})