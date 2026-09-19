//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("gatelandEditGateway", ()=>({
        pageLoaderIsActive: false,

        //pagedata
        fromLoaderIsActive: false,
        gateway: null,

        async init(){
            const gateway = await this.getGateway(gatelandGetQueryParam('gateway_id'));

            if(gateway){
                this.gateway = gateway;
            }

        },

        //request functions
        async getGateway(gatewayId){
            this.fromLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/gateway/overview', {
                    method: 'POST',
                    form: true,
                    data: {
                        gateway_id: gatewayId,
                        from_date: 1749326676,
                        to_date: 1749326676
                    }
                })

                if(result.success){
                    const data = result.data;
                    data.gateway.options = data.gateway.options.map(item=>{
                        item.model = data.gateway.data[item.key];
                        item.errorMsg = ''
                        return item;
                    });
                    this.fromLoaderIsActive = false;
                    return data.gateway;
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.fromLoaderIsActive = false;
                }


            }catch (error){
                console.error('Error fetching posts:', error);
                this.fromLoaderIsActive = false;
            }

            return false;

        },

        async editGateway(data){
            this.pageLoaderIsActive = true;

            try {

                const result = await gatelandApiRequest('gateland/gateway/update', {
                    method: 'POST',
                    data
                })

                if(result.success){
                    gatelandNotyf.success('درگاه با موفقیت بروزرسانی شد. در حال انتقال به لیست درگاه‌ها ...');
                    setTimeout(()=>{
                        window.location.replace(window.origin + window.location.pathname + '?page=gateland-gateways');
                        this.pageLoaderIsActive = false;
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

        submit(){

            const data = {};
            this.gateway.options.forEach(item=>{
                data[item.key] = item.model;
            })

            console.log(data)

            this.editGateway({
                gateway_id: gatelandGetQueryParam('gateway_id'),
                data
            });
        }

    }))

})




















